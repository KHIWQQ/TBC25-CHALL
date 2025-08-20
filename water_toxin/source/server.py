#!/usr/bin/env python3
"""
PLC simulator (deterministic addressing)

Addressing contract:
- pyModbusTCP ModbusServer is used with **0-based** addresses.
- Gateway and checker must use the same 0-based client-space addresses.
"""

import argparse
import logging
import signal
import sys
import time
import threading
import os
from pyModbusTCP.server import ModbusServer

logging.basicConfig(
    level=logging.WARNING, format="%(asctime)s %(levelname)s %(message)s"
)

COIL_ENG_MODE = 8
REG_HEARTBEAT = 10  # increments each tick
REG_ENG_UNLOCK = 50

RO_START, RO_END = 200, 679  # 15 slots x 32 regs = 480 regs, inclusive
SLOTS, SLOT_SIZE = 15, 32
PTR_TABLE_BASE = 600


class WaterTreatmentPLC:
    def __init__(self, host="0.0.0.0", port=1502, tick=1.0):
        self.sim_thread = None
        self.server = ModbusServer(host=host, port=port, no_block=True)
        self.tick = tick
        self._stop = threading.Event()

    def start(self):
        self._setup()
        self.server.start()
        logging.info(f"[+] PLC started on {self.server.host}:{self.server.port}")
        self.sim_thread = threading.Thread(target=self._simulate, daemon=True)
        self.sim_thread.start()

    def stop(self):
        logging.info("[*] Stopping PLC...")
        self._stop.set()
        try:
            self.server.stop()
        except Exception as e:
            logging.warning(f"stop() exception: {e}")

    def _setup(self):
        db = self.server.data_bank
        for a in range(0, 9):
            db.set_coils(a, [False])
        # Process registers 0..4 + heartbeat
        db.set_holding_registers(0, [500])  # tank L
        db.set_holding_registers(1, [72])  # pH*10
        db.set_holding_registers(2, [20])  # Cl*10
        db.set_holding_registers(3, [100])  # flow L/min
        db.set_holding_registers(4, [55])  # temperature C
        db.set_holding_registers(REG_HEARTBEAT, [1])
        db.set_holding_registers(REG_ENG_UNLOCK, [0])

        for a in range(RO_START, RO_END + 1):
            db.set_holding_registers(a, [0])

        for i in range(SLOTS):
            db.set_holding_registers(PTR_TABLE_BASE + i, [0])

        logging.info("[PLC] setup complete (0-based)")

    def _simulate(self):
        db = self.server.data_bank
        while not self._stop.is_set():
            try:
                coils = db.get_coils(0, 9) or [False] * 9
                regs = db.get_holding_registers(0, 5) or [500, 72, 20, 100, 55]
                tank = regs[0]
                main_pump = coils[0]
                intake = coils[2]
                discharge = coils[3]

                if main_pump and intake:
                    tank = min(1000, tank + 5)
                if discharge:
                    tank = max(0, tank - 5)
                db.set_holding_registers(0, [tank])

                # heartbeat++
                hb = db.get_holding_registers(REG_HEARTBEAT, 1)[0]
                db.set_holding_registers(REG_HEARTBEAT, [(hb + 1) & 0xFFFF])

            except Exception as e:
                logging.error(f"[PLC] simulate error: {e}")
            time.sleep(self.tick)


def main():
    p = argparse.ArgumentParser()
    p.add_argument("--host", default="0.0.0.0")
    p.add_argument(
        "--port", type=int, default=int(os.environ.get("BACKEND_PORT", "1502"))
    )
    p.add_argument("--tick", type=float, default=1.0)
    args = p.parse_args()

    plc = WaterTreatmentPLC(host=args.host, port=args.port, tick=args.tick)

    def _stop(*_):
        plc.stop()
        sys.exit(0)

    signal.signal(signal.SIGINT, _stop)
    signal.signal(signal.SIGTERM, _stop)

    plc.start()
    while True:
        time.sleep(1)


if __name__ == "__main__":
    main()
