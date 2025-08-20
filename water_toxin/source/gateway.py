#!/usr/bin/env python3
import os
import time
import logging
import random
import struct
import hashlib
import base64
import sqlite3
import threading
from pathlib import Path
from typing import List

from pyModbusTCP.client import ModbusClient

from pymodbus.datastore.store import BaseModbusDataBlock
from pymodbus.server import StartTcpServer
from pymodbus.datastore import (
    ModbusServerContext,
    ModbusSlaveContext,
    ModbusSparseDataBlock,
)
from pymodbus.device import ModbusDeviceIdentification

from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PublicKey
from cryptography.exceptions import InvalidSignature

LOG_LEVEL = os.getenv("LOG_LEVEL", "WARNING").upper()
logging.basicConfig(
    level=getattr(logging, LOG_LEVEL, logging.WARNING),
    format="%(asctime)s %(levelname)s %(message)s",
)
log = logging.getLogger("gate")

BACKEND_HOST = os.getenv("BACKEND_HOST", "127.0.0.1")
BACKEND_PORT = int(os.getenv("BACKEND_PORT", "1502"))
GATE_PORT = int(os.getenv("GATE_PORT", "502"))

RO_REGION_START, RO_REGION_END = 200, 679
SLOTS, SLOT_SIZE = 15, 32
PTR_TABLE_BASE = 600
PTR_TABLE_END = PTR_TABLE_BASE + SLOTS - 1

REG_ENG_UNLOCK = 50
COIL_ENG_MODE = 8
REG_PTR_LOCK_BASE = 710
REG_PTR_OPEN = 905
RO_READ_TRIG = 906

PTR_LOCK_SECS = 10
PTR_WINDOW_SECS = 10
RO_WRITABLE_WINDOWS_SECS = 10

# Modbus transport cap for a single response (hardware/register layout constraint)
MAX_FLAG_BYTES = (SLOT_SIZE - 1) * 2

CHK_SEED_L, CHK_SEED_H = 920, 921
CHK_SLOT = 926
CHK_LEN = 927
CHK_CMD = 928

CHK_DATA_BASE = 929
CHK_DATA_WORDS = SLOT_SIZE - 1
CHK_DATA_END = CHK_DATA_BASE + CHK_DATA_WORDS - 1

CHK_SIG_BASE = 960
CHK_SIG_WORDS = 32
CHK_SIG_END = CHK_SIG_BASE + CHK_SIG_WORDS - 1

AUTH_PUT, AUTH_GET = 1, 2
RESP_TTL_SECS = float(os.getenv("CHK_RESP_TTL_SECS", "0.8"))
CHECKER_PUBKEY_B64 = os.getenv("CHECKER_PUBKEY_B64", "")

FLAGS_DB_PATH = os.getenv("FLAGS_DB_PATH", "/data/flags.db")


def in_range(addr: int, count: int, lo: int, hi: int) -> bool:
    return (addr <= hi) and (addr + count - 1 >= lo)


class FlagDB:
    """Simple SQLite wrapper for persisted flags."""

    def __init__(self, db_path: str):
        self.db_path = db_path
        Path(db_path).parent.mkdir(parents=True, exist_ok=True)
        self.conn = sqlite3.connect(db_path, check_same_thread=False)
        self.lock = threading.Lock()
        with self.lock, self.conn:
            self.conn.execute("PRAGMA journal_mode=WAL;")
            self.conn.execute(
                """
                CREATE TABLE IF NOT EXISTS flags(
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    slot INTEGER NOT NULL,
                    len  INTEGER NOT NULL,
                    sha256 TEXT NOT NULL,
                    payload BLOB NOT NULL,
                    created_at INTEGER NOT NULL
                );
            """
            )
            self.conn.execute(
                """
                CREATE INDEX IF NOT EXISTS idx_flags_slot_created
                ON flags(slot, created_at);
            """
            )

    def insert_flag(self, slot: int, payload: bytes) -> None:
        h = hashlib.sha256(payload).hexdigest()
        ts = int(time.time())
        with self.lock, self.conn:
            self.conn.execute(
                "INSERT INTO flags(slot, len, sha256, payload, created_at) VALUES(?,?,?,?,?)",
                (slot, len(payload), h, sqlite3.Binary(payload), ts),
            )

    def latest_for_slot(self, slot: int) -> bytes | None:
        with self.lock:
            cur = self.conn.execute(
                "SELECT payload FROM flags WHERE slot=? ORDER BY created_at DESC, id DESC LIMIT 1",
                (slot,),
            )
            row = cur.fetchone()
        return row[0] if row else None


class GatewayCtrl:
    def __init__(self, backend: ModbusClient, db: FlagDB):
        self.backend = backend
        self.db = db

        self.ptr_key = random.randint(1, 0xFFFE)
        self.ptr_bug_armed_until = 0.0
        self.ptr_window_until = 0.0
        self.ro_writable_until = 0.0
        self.slot_bases: List[int] = []

        self.chk_len_full = 0
        self.chk_len = 0

        self.chk_seed = random.getrandbits(32)
        self.chk_slot = 0
        self.chk_cmd_last = 0
        self.chk_sig = bytearray(64)

        self.chk_resp_buf = b""
        self.chk_resp_until = 0.0

        self.stage_words = {}

        # Ed25519 pubkey
        self.pubkey = None
        if CHECKER_PUBKEY_B64:
            try:
                pk_bytes = base64.b64decode(CHECKER_PUBKEY_B64)
                if len(pk_bytes) != 32:
                    raise ValueError("invalid public key size")
                self.pubkey = Ed25519PublicKey.from_public_bytes(pk_bytes)
            except Exception as e:
                log.warning(f"bad public key: {e}")

    def now(self) -> float:
        return time.time()

    def is_unlocked(self) -> bool:
        return self.now() < self.ptr_bug_armed_until

    def ptr_open(self) -> bool:
        return self.now() < self.ptr_window_until

    def ro_open_writable(self) -> bool:
        return self.now() < self.ro_writable_until

    def refresh_seed(self):
        self.chk_seed = random.getrandbits(32)

    def verify_sig(self, op: int, slot: int, length: int) -> bool:
        if not self.pubkey:
            return False
        msg = struct.pack(">I B B H", self.chk_seed, op, slot, length)
        try:
            self.pubkey.verify(bytes(self.chk_sig), msg)
            return True
        except InvalidSignature:
            return False

    def verify_sig_with_payload(
        self, op: int, slot: int, length: int, payload_bytes: bytes
    ) -> bool:
        if not self.pubkey:
            return False
        ph = hashlib.sha256(payload_bytes[:MAX_FLAG_BYTES]).digest()[:16]
        msg = struct.pack(">I B B H", self.chk_seed, op, slot, length) + ph
        try:
            self.pubkey.verify(bytes(self.chk_sig), msg)
            return True
        except InvalidSignature:
            return False

    def poll_plc_state(self):
        try:
            eng_unlock = self.backend.read_holding_registers(REG_ENG_UNLOCK, 1)
            eng_mode = self.backend.read_coils(COIL_ENG_MODE, 1)
            if (
                eng_unlock
                and eng_unlock[0] == 0xC0DE
                and eng_mode
                and bool(eng_mode[0])
            ):
                self.ptr_bug_armed_until = self.now() + PTR_LOCK_SECS
        except Exception as e:
            log.warning(f"backend poll error: {e}")


class ProxyHolding(BaseModbusDataBlock):
    def __init__(self, ctrl: GatewayCtrl):
        self.ctrl = ctrl
        self.b = ctrl.backend

        all_bases = list(range(RO_REGION_START, RO_REGION_END + 1, SLOT_SIZE))
        if len(all_bases) < SLOTS:
            raise RuntimeError("region too small")
        random.shuffle(all_bases)
        self.ctrl.slot_bases = sorted(all_bases[:SLOTS])

        for i, base in enumerate(self.ctrl.slot_bases):
            _ = self.b.write_single_register(PTR_TABLE_BASE + i, base)

        self.ctrl.stage_words = {}

    def validate(self, address, count=1):
        return True

    def getValues(self, address, count=1):
        self.ctrl.poll_plc_state()

        if in_range(address, count, CHK_LEN, CHK_DATA_END):
            if self.ctrl.now() < self.ctrl.chk_resp_until and self.ctrl.chk_resp_buf:
                stored = self.ctrl.chk_resp_buf

                if address == CHK_LEN:
                    out = [min(len(stored), MAX_FLAG_BYTES)]
                    if count > 1:
                        data = stored
                        if len(data) % 2:
                            data += b"\x00"
                        words = [
                            (data[i] << 8) | data[i + 1] for i in range(0, len(data), 2)
                        ]
                        remaining_count = count - 1
                        data_words_needed = min(
                            remaining_count, len(words), CHK_DATA_WORDS
                        )
                        out.extend(words[:data_words_needed])
                        if len(out) < count:
                            out.extend([0] * (count - len(out)))
                    return out

                elif address >= CHK_DATA_BASE:
                    data = stored
                    if len(data) % 2:
                        data += b"\x00"
                    words = [
                        (data[i] << 8) | data[i + 1] for i in range(0, len(data), 2)
                    ]
                    start_idx = address - CHK_DATA_BASE
                    slice_ = words[start_idx : start_idx + count]
                    if len(slice_) < count:
                        slice_.extend([0] * (count - len(slice_)))
                    return slice_

        if in_range(address, count, CHK_DATA_BASE, CHK_DATA_END):
            out = []
            for i in range(count):
                a = address + i
                out.append(self.ctrl.stage_words.get(a, 0))
            return out

        if in_range(address, count, CHK_SLOT, CHK_CMD):
            out = []
            for i in range(count):
                a = address + i
                if a == CHK_SLOT:
                    out.append(self.ctrl.chk_slot & 0xFFFF)
                elif a == CHK_LEN:
                    out.append(self.ctrl.chk_len & 0xFFFF)
                elif a == CHK_CMD:
                    out.append(self.ctrl.chk_cmd_last & 0xFFFF)
                else:
                    out.append(0)
            return out

        if in_range(address, count, CHK_SEED_L, CHK_SEED_H):
            out = []
            for i in range(count):
                a = address + i
                if a == CHK_SEED_L:
                    out.append(self.ctrl.chk_seed & 0xFFFF)
                elif a == CHK_SEED_H:
                    out.append((self.ctrl.chk_seed >> 16) & 0xFFFF)
                else:
                    out.append(0)
            return out

        if in_range(address, count, CHK_SIG_BASE, CHK_SIG_END):
            return [0] * count

        if self.ctrl.is_unlocked() and in_range(
            address, count, REG_PTR_LOCK_BASE, REG_PTR_LOCK_BASE + 1
        ):
            low = self.ctrl.ptr_key & 0xFF
            high = (self.ctrl.ptr_key >> 8) & 0xFF
            out = []
            for i in range(count):
                a = address + i
                out.append(
                    low
                    if a == REG_PTR_LOCK_BASE
                    else high if a == REG_PTR_LOCK_BASE + 1 else 0
                )
            return out

        if in_range(address, count, PTR_TABLE_BASE, PTR_TABLE_END):
            if not self.ctrl.ptr_open():
                return [0] * count
            start = address - PTR_TABLE_BASE
            out = []
            for i in range(count):
                idx = start + i
                out.append(self.ctrl.slot_bases[idx] if 0 <= idx < SLOTS else 0)
            return out

        if (
            in_range(address, count, RO_REGION_START, RO_REGION_END)
            and not self.ctrl.ro_open_writable()
        ):
            return [0] * count

        vals = self.b.read_holding_registers(address, count)
        if vals is None:
            log.warning(f"backend HR[{address}:{count}] -> None")
            return [0] * count
        if len(vals) != count:
            out = (vals or []) + [0] * (count - len(vals or []))
            log.warning(
                f"backend HR short-read {address}:{count} -> {len(vals) if vals else 0}"
            )
            return out
        return vals

    def setValues(self, address, values: List[int]):
        self.ctrl.poll_plc_state()

        if in_range(address, len(values), CHK_SIG_BASE, CHK_SIG_END):
            for i, v in enumerate(values):
                idx = (address - CHK_SIG_BASE) + i
                if 0 <= idx < CHK_SIG_WORDS:
                    v = int(v) & 0xFFFF
                    self.ctrl.chk_sig[idx * 2 : (idx * 2) + 2] = bytes(
                        [(v >> 8) & 0xFF, v & 0xFF]
                    )
            return

        if address == CHK_SLOT and len(values) == 1:
            self.ctrl.chk_slot = int(values[0]) % SLOTS
            return

        if address == CHK_LEN and len(values) == 1:
            L = int(values[0]) & 0xFFFF
            self.ctrl.chk_len_full = L
            self.ctrl.chk_len = min(L, MAX_FLAG_BYTES)
            return

        if in_range(address, len(values), CHK_DATA_BASE, CHK_DATA_END):
            for i, v in enumerate(values):
                self.ctrl.stage_words[address + i] = int(v) & 0xFFFF
            return

        if address == CHK_CMD and len(values) == 1:
            op = AUTH_PUT if int(values[0]) == AUTH_PUT else AUTH_GET
            self.ctrl.chk_cmd_last = op

            if not self.ctrl.pubkey:
                return

            if op == AUTH_PUT:
                words = [
                    self.ctrl.stage_words.get(CHK_DATA_BASE + i, 0)
                    for i in range(CHK_DATA_WORDS)
                ]
                payload = bytearray()
                for w in words:
                    payload.extend([(w >> 8) & 0xFF, w & 0xFF])

                payload = bytes(
                    payload[: min(self.ctrl.chk_len_full, CHK_DATA_WORDS * 2)]
                )

                if self.ctrl.verify_sig_with_payload(
                    AUTH_PUT, self.ctrl.chk_slot, self.ctrl.chk_len_full, payload
                ):
                    self.ctrl.db.insert_flag(self.ctrl.chk_slot, payload)

                    self.ctrl.stage_words.clear()
                    self.ctrl.chk_resp_buf = b""
                    self.ctrl.chk_resp_until = 0.0

                    self.ctrl.refresh_seed()
                return

            else:
                if self.ctrl.verify_sig(AUTH_GET, self.ctrl.chk_slot, 0):
                    stored = self.ctrl.db.latest_for_slot(self.ctrl.chk_slot) or b""
                    self.ctrl.chk_resp_buf = stored[:MAX_FLAG_BYTES]
                    self.ctrl.chk_resp_until = self.ctrl.now() + RESP_TTL_SECS
                    self.ctrl.chk_len = len(self.ctrl.chk_resp_buf)

                    try:
                        base = self.ctrl.slot_bases[self.ctrl.chk_slot]
                        _ = self.b.write_single_register(
                            base, self.ctrl.chk_len & 0xFFFF
                        )
                        data = self.ctrl.chk_resp_buf
                        if len(data) % 2:
                            data += b"\x00"
                        words = [
                            (data[i] << 8) | data[i + 1] for i in range(0, len(data), 2)
                        ]
                        if words:
                            _ = self.b.write_multiple_registers(
                                base + 1, words[: (self.ctrl.chk_len + 1) // 2]
                            )
                    except Exception as e:
                        log.warning(f"mirror failed: {e}")

                    self.ctrl.refresh_seed()
                else:
                    self.ctrl.chk_resp_buf = b""
                    self.ctrl.chk_resp_until = 0.0
                return

        if address == REG_PTR_OPEN and len(values) == 1:
            if int(values[0]) == self.ctrl.ptr_key:
                self.ctrl.ptr_window_until = self.ctrl.now() + PTR_WINDOW_SECS
            return

        if address == RO_READ_TRIG and len(values) == 1 and int(values[0]) == 0xDEAD:
            self.ctrl.ro_writable_until = self.ctrl.now() + RO_WRITABLE_WINDOWS_SECS
            return

        if (
            in_range(address, len(values), RO_REGION_START, RO_REGION_END)
            and not self.ctrl.ro_open_writable()
        ):
            return

        if len(values) == 1:
            _ = self.b.write_single_register(address, int(values[0]))
        else:
            payload = [int(v) for v in values]
            _ = self.b.write_multiple_registers(address, payload)


class ProxyCoils(ModbusSparseDataBlock):
    def __init__(self, ctrl: GatewayCtrl):
        super().__init__()
        self.ctrl = ctrl
        self.b = ctrl.backend

    def validate(self, address, count=1):
        return True

    def getValues(self, address, count=1):
        self.ctrl.poll_plc_state()
        vals = self.b.read_coils(address, count)
        if vals is None:
            log.warning(f"backend COIL[{address}:{count}] -> None")
            return [0] * count
        if len(vals) != count:
            out = (vals or []) + [0] * (count - len(vals or []))
            log.warning(
                f"backend COIL short-read {address}:{count} -> {len(vals) if vals else 0}"
            )
            return out
        return vals

    def setValues(self, address, values: List[int]):
        self.ctrl.poll_plc_state()
        bools = [bool(v) for v in values]
        if len(bools) == 1:
            _ = self.b.write_single_coil(address, bools[0])
        else:
            _ = self.b.write_multiple_coils(address, bools)


def main():
    backend = ModbusClient(BACKEND_HOST, BACKEND_PORT, auto_open=True, timeout=3.0)
    try:
        backend.open()
        _ = backend.read_holding_registers(10, 1)
    except Exception as e:
        log.error(f"backend init failed: {e}")

    db = FlagDB(FLAGS_DB_PATH)
    ctrl = GatewayCtrl(backend, db)

    slave = ModbusSlaveContext(
        di=None, co=ProxyCoils(ctrl), hr=ProxyHolding(ctrl), ir=None, zero_mode=True
    )
    ctx = ModbusServerContext(slaves=slave, single=True)

    ident = ModbusDeviceIdentification()
    ident.VendorName = "AcmeCorp"
    ident.ProductCode = "WT-Proxy"
    ident.ProductName = "Modbus Gateway"
    ident.ModelName = "WT-502-H"
    ident.MajorMinorRevision = "3.1"

    log.info(f"listening on :{GATE_PORT}")
    StartTcpServer(context=ctx, identity=ident, address=("0.0.0.0", GATE_PORT))


if __name__ == "__main__":
    main()
