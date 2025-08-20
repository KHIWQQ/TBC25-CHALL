#!/usr/bin/env python3
import ctypes
import os
import sys
import time
import json
import base64
import threading
import socketserver
import sqlite3
from pathlib import Path
from collections import deque
from typing import Tuple, List

from pyModbusTCP.server import ModbusServer
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ec
from cryptography.exceptions import InvalidSignature


try:
    LIB = ctypes.CDLL("/app/libplc.so")
except OSError as e:
    print("[plc] FATAL: cannot load /app/libplc.so:", e, flush=True)
    sys.exit(1)

LIB.process_production_order.argtypes = [ctypes.c_char_p]
LIB.plc_compromised.restype = ctypes.c_int
LIB.get_conveyor_run.restype = ctypes.c_uint8
LIB.get_emergency_ok.restype = ctypes.c_uint8
LIB.get_quality_score.restype = ctypes.c_uint32
LIB.plc_reset_state()

DEBUG = os.getenv("DEBUG", "0") == "1"

MGMT_ALLOWLIST = {
    ip.strip() for ip in os.getenv("MGMT_ALLOWLIST", "").split(",") if ip.strip()
}

pub_key = None
pem_txt = os.getenv("CHECKER_PUB_PEM", "").strip()
try:
    if pem_txt:
        pub_key = serialization.load_pem_public_key(pem_txt.encode())
    else:
        with open("/app/checker_pub.pem", "rb") as f:
            pub_key = serialization.load_pem_public_key(f.read())
except Exception as e:
    print("[plc] failed to load checker pubkey:", e, flush=True)


class SQLiteFlagStore:
    """
    Stores every flag write (history) and can return the latest flag per flag_id,
    or all latest flags across all flag_ids.
    """

    def __init__(self, db_path: str = "/data/flags.db"):
        self.db_path = db_path
        Path(self.db_path).parent.mkdir(parents=True, exist_ok=True)
        self._conn = sqlite3.connect(
            self.db_path, check_same_thread=False, isolation_level=None
        )
        self._conn.execute("PRAGMA journal_mode=WAL;")
        self._conn.execute("PRAGMA synchronous=NORMAL;")
        self._conn.execute(
            """
            CREATE TABLE IF NOT EXISTS flags (
              id         INTEGER PRIMARY KEY AUTOINCREMENT,
              fid        TEXT NOT NULL,
              flag       TEXT NOT NULL,
              created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
            );
        """
        )
        self._conn.execute(
            "CREATE INDEX IF NOT EXISTS idx_flags_fid_created ON flags(fid, created_at DESC);"
        )

    def put(self, fid: str, flag: str) -> None:
        self._conn.execute("INSERT INTO flags(fid, flag) VALUES (?, ?);", (fid, flag))

    def get_latest(self, fid: str) -> str:
        cur = self._conn.execute(
            "SELECT flag FROM flags WHERE fid = ? ORDER BY id DESC LIMIT 1;", (fid,)
        )
        row = cur.fetchone()
        return row[0] if row else ""

    def get_all_latest(self) -> List[str]:
        """
        Return the latest flag for each fid.
        """
        cur = self._conn.execute(
            """
            SELECT f.flag
            FROM flags f
            JOIN (
              SELECT fid, MAX(id) AS max_id
              FROM flags
              GROUP BY fid
            ) latest ON latest.max_id = f.id;
        """
        )
        return [r[0] for r in cur.fetchall()]

    def concat_all_latest(self, sep: str = "\n", max_bytes: int | None = None) -> bytes:
        """
        Join all latest flags with `sep`. If max_bytes is set, truncate safely.
        """
        data = sep.join(self.get_all_latest()).encode()
        return data[:max_bytes] if max_bytes is not None else data


FLAGS_DB_PATH = os.getenv("FLAGS_DB", "/data/flags.db")
FLAG_STORE = SQLiteFlagStore(FLAGS_DB_PATH)

# ------------------------- Auth / anti-replay ---------------------------------

NONCES: deque[str] = deque(maxlen=4096)


def canon_v1(op: str, flag_id: str, flag: str | None, ts: int, nonce: str) -> bytes:
    flag_s = "" if flag is None else flag
    return f"{op}|{flag_id}|{flag_s}|{ts}|{nonce}".encode()


def verify(msg: dict) -> bool:
    if pub_key is None:
        return False
    try:
        op = msg.get("op")
        fid = msg.get("flag_id")
        ts = int(msg.get("ts", 0))
        nonce = msg.get("nonce", "")
        sig_b64 = msg.get("sig", "")
        fl = msg.get("flag") if op == "put" else None

        if not (op in ("put", "get") and fid and nonce and sig_b64):
            return False

        now = int(time.time())
        if abs(now - ts) > 120:
            return False
        if nonce in NONCES:
            return False

        sig = base64.b64decode(sig_b64)
        pub_key.verify(sig, canon_v1(op, fid, fl, ts, nonce), ec.ECDSA(hashes.SHA256()))
        NONCES.append(nonce)
        return True
    except InvalidSignature:
        return False
    except Exception:
        return False


class Handler(socketserver.StreamRequestHandler):
    def handle(self):
        try:
            if MGMT_ALLOWLIST and (self.client_address[0] not in MGMT_ALLOWLIST):
                self._send_error("ip_denied")
                return

            line = self.rfile.readline(65536)
            if not line:
                self._send_error("empty_request")
                return

            try:
                msg = json.loads(line.decode("utf-8"))
            except (json.JSONDecodeError, UnicodeDecodeError):
                self._send_error("bad_json")
                return

            if not verify(msg):
                self._send_error("auth")
                return

            op = msg.get("op")
            fid = msg.get("flag_id")
            if not op or not fid:
                self._send_error("missing_fields")
                return

            if op == "put":
                self._handle_put(msg, fid)
            elif op == "get":
                self._handle_get(fid)
            else:
                self._send_error("bad_op")

        except Exception as e:
            if DEBUG:
                print(f"[plc] Handler error: {e}", flush=True)
            self._send_error("internal_error")

    def _handle_put(self, msg: dict, fid: str) -> None:
        flag = msg.get("flag", "")
        FLAG_STORE.put(fid, flag)
        self._send_success()

    def _handle_get(self, fid: str) -> None:
        latest_for_id = FLAG_STORE.get_latest(fid)
        self._send_json({"ok": True, "flag": latest_for_id})

    def _send_error(self, error: str) -> None:
        self._send_json({"ok": False, "err": error})

    def _send_success(self) -> None:
        self._send_json({"ok": True})

    def _send_json(self, data: dict) -> None:
        try:
            response = json.dumps(data).encode("utf-8") + b"\n"
            self.wfile.write(response)
        except Exception as e:
            if DEBUG:
                print(f"[plc] Failed to send response: {e}", flush=True)


class ThreadedTCPServer(socketserver.ThreadingMixIn, socketserver.TCPServer):
    daemon_threads = True
    allow_reuse_address = True


mgmt_server = ThreadedTCPServer(("0.0.0.0", 9000), Handler)
threading.Thread(target=mgmt_server.serve_forever, daemon=True).start()
print("[plc] mgmt API on :9000 (ECDSA-verified)")

server = ModbusServer(host="0.0.0.0", port=502, no_block=True)
print("[plc] starting Modbus gateway on :502")
server.start()
db = server.data_bank

try:
    db.set_holding_registers(99, [0])
    db.set_holding_registers(200, [0])
    db.set_holding_registers(210, [0])
    db.set_holding_registers(120, [0] * 60)
    db.set_holding_registers(400, [0] * 64)
except Exception as e:
    print("[plc] init registers failed:", e, flush=True)


try:
    while True:
        ctrl_regs = db.get_holding_registers(200, 1) or [0]
        if ctrl_regs[0] == 1:
            words = db.get_holding_registers(100, 100) or [0] * 100
            length = (db.get_holding_registers(99, 1) or [0])[0]
            if length == 0:
                buf = bytearray()
                for w in words:
                    if w == 0:
                        break
                    buf.append(w & 0xFF)
                payload = bytes(buf)
            else:
                payload = bytes([(w & 0xFF) for w in words[:length]])
            try:
                LIB.process_production_order(ctypes.c_char_p(payload + b"\x00"))
            except Exception as e:
                if DEBUG:
                    print("[plc] error calling PLC lib:", e, flush=True)
            finally:
                db.set_holding_registers(200, [0])

        fetch = (db.get_holding_registers(210, 1) or [0])[0]
        compromised = int(LIB.plc_compromised())
        if fetch == 1:
            if compromised:
                blob = FLAG_STORE.concat_all_latest(sep="\n", max_bytes=64)
            else:
                blob = b""
            out = blob.ljust(64, b"\x00")
            db.set_holding_registers(400, list(out))
            db.set_holding_registers(210, [0])

        conveyor = int(LIB.get_conveyor_run())
        estop_ok = int(LIB.get_emergency_ok())
        quality = int(LIB.get_quality_score())

        try:
            server.data_bank.set_coils(0, [conveyor == 1, estop_ok == 1])
        except Exception:
            pass

        db.set_holding_registers(300, [conveyor])
        db.set_holding_registers(301, [estop_ok])
        db.set_holding_registers(302, [(quality >> 16) & 0xFFFF])
        db.set_holding_registers(303, [quality & 0xFFFF])
        db.set_holding_registers(310, [compromised])

        time.sleep(0.2)
except KeyboardInterrupt:
    pass
finally:
    try:
        server.stop()
    except Exception:
        pass
