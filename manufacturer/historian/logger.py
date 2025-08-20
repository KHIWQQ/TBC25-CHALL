import os, time
import psycopg
from pyModbusTCP.client import ModbusClient

DB = psycopg.connect(
    host=os.getenv("DB_HOST", "historiandb"),
    dbname=os.getenv("DB_NAME", "histdb"),
    user=os.getenv("DB_USER", "hist"),
    password=os.getenv("DB_PASS", "histpass"),
)
PLC_HOST = os.getenv("PLC_HOST", "plc1")
PLC_PORT = int(os.getenv("PLC_PORT", "502"))

c = ModbusClient(host=PLC_HOST, port=PLC_PORT, auto_open=True, auto_close=True)

print("[hist] logger started")
while True:
    regs = c.read_holding_registers(300, 4) or [0, 0, 0, 0]
    conveyor = bool(regs[0])
    estop = bool(regs[1])
    quality = (regs[2] << 16) | regs[3]
    with DB.cursor() as cur:
        cur.execute(
            "INSERT INTO process_metrics(conveyor_run, emergency_ok, quality_score) VALUES (%s,%s,%s)",
            (conveyor, estop, quality),
        )
        DB.commit()
    time.sleep(2)
