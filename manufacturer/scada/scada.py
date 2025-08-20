import os
from flask import Flask, render_template_string
import psycopg
from pyModbusTCP.client import ModbusClient

app = Flask(__name__)
PLC_HOST = os.getenv("PLC_HOST", "plc1")
PLC_PORT = int(os.getenv("PLC_PORT", "502"))
DB_HOST = os.getenv("DB_HOST", "historiandb")
DB_NAME = os.getenv("DB_NAME", "histdb")
DB_USER = os.getenv("DB_USER", "hist")
DB_PASS = os.getenv("DB_PASS", "histpass")

TPL = """
<!doctype html><html><head><meta charset=utf-8><title>SCADA</title></head><body>
<h2>SCADA Overview</h2>
<p>Conveyor: <b>{{ 'RUN' if conveyor else 'STOP' }}</b> | E‑Stop OK: <b>{{ estop }}</b> | Quality: <b>{{ quality }}</b></p>
<h3>Recent Metrics</h3>
<table border=1 cellpadding=4>
<tr><th>ts</th><th>conveyor</th><th>estop</th><th>quality</th></tr>
{% for r in rows %}
<tr><td>{{ r[0] }}</td><td>{{ r[1] }}</td><td>{{ r[2] }}</td><td>{{ r[3] }}</td></tr>
{% endfor %}
</table>
</body></html>
"""


@app.route("/")
def index():
    # live read
    mc = ModbusClient(host=PLC_HOST, port=PLC_PORT, auto_open=True, auto_close=True)
    regs = mc.read_holding_registers(300, 4) or [0, 0, 0, 0]
    conveyor = bool(regs[0])
    estop = bool(regs[1])
    quality = (regs[2] << 16) | regs[3]
    # recent historian
    with psycopg.connect(
        host=DB_HOST, dbname=DB_NAME, user=DB_USER, password=DB_PASS
    ) as db:
        with db.cursor() as cur:
            cur.execute(
                "SELECT ts, conveyor_run, emergency_ok, quality_score FROM process_metrics ORDER BY ts DESC LIMIT 15"
            )
            rows = cur.fetchall()
    return render_template_string(
        TPL, conveyor=conveyor, estop=estop, quality=quality, rows=rows
    )


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8090)
