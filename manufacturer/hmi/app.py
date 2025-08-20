import os
from flask import Flask, request, render_template, redirect, url_for, session, flash
from pyModbusTCP.client import ModbusClient

app = Flask(__name__)
app.secret_key = os.getenv("SECRET_KEY", "devkey")
PLC_HOST = os.getenv("PLC_HOST", "plc1")
PLC_PORT = int(os.getenv("PLC_PORT", "502"))
DEFAULT_USER = os.getenv("DEFAULT_USER", "admin")
DEFAULT_PASS = os.getenv("DEFAULT_PASS", "admin")

ORDERS = []


def plc_send_order(raw: bytes):
    c = ModbusClient(host=PLC_HOST, port=PLC_PORT, auto_open=True, auto_close=True)
    regs = [b for b in raw[:100]]
    regs += [0] * (100 - len(regs))
    c.write_multiple_registers(100, regs)
    c.write_single_register(99, len(raw))
    c.write_single_register(200, 1)


@app.route("/")
def index():
    return render_template("index.html")


@app.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "POST":
        u = request.form.get("username", "")
        p = request.form.get("password", "")
        if u == DEFAULT_USER and p == DEFAULT_PASS:
            session["user"] = u
            flash(f"Welcome {u}")
            return redirect(url_for("dashboard"))
        flash("Invalid credentials")
    return render_template("login.html")


@app.route("/dashboard")
def dashboard():
    if "user" not in session:
        return redirect(url_for("login"))
    return render_template("index.html", orders=ORDERS)


@app.route("/upload", methods=["GET", "POST"])
def upload():
    if "user" not in session:
        return redirect(url_for("login"))

    if request.method == "GET":
        return render_template("upload.html", orders=ORDERS)

    # POST method handling
    if "user" not in session:
        return redirect(url_for("login"))
    f = request.files.get("file")
    if not f:
        flash("No file")
        return redirect(url_for("dashboard"))
    data = f.read()
    name = request.form.get("order_name", f.filename)
    ORDERS.append({"name": name, "size": len(data)})
    plc_send_order(data)
    flash("Order sent to PLC")
    return redirect(url_for("orders"))


@app.route("/orders")
def orders():
    return render_template("orders.html", orders=ORDERS)


@app.route("/manual")
def manual():
    return render_template("manual.html")


@app.get("/kiosk")
def kiosk():
    nonce = os.urandom(8).hex()
    session["kiosk_nonce"] = nonce
    return render_template("kiosk.html", nonce=nonce)


@app.post("/kiosk/upload")
def kiosk_upload():
    if request.form.get("nonce") != session.get("kiosk_nonce"):
        flash("Kiosk session refreshed")
    f = request.files.get("file")
    if not f:
        return redirect(url_for("kiosk"))
    data = f.read()
    name = request.form.get("order_name", f.filename or "order.wcp")
    ORDERS.append({"name": name, "size": len(data)})
    plc_send_order(data)
    return redirect(url_for("orders"))
