from flask import Flask, request, session, redirect,jsonify
from werkzeug.security import generate_password_hash, check_password_hash
import sqlite3
import secrets
import json
import os

app = Flask(__name__)
app.secret_key = secrets.token_hex(16)


DATA_FILE = 'employee_data.json'

def load_employee_data():
    if os.path.exists(DATA_FILE):
        with open(DATA_FILE, 'r') as f:
            return json.load(f)
    else:
        # Initial default data
        return [
            {"name": "Alice", "role": "Manager"},
            {"name": "Bob", "role": "Engineer"},
            {"name": "Charlie", "role": "HR"},
            {"name": "Dana", "role": "Intern"},
        ]

def save_employee_data(data):
    with open(DATA_FILE, 'w') as f:
        json.dump(data, f, indent=2)

employee_data = load_employee_data()


def init_db():
    conn = sqlite3.connect("users.db")
    c = conn.cursor()
    c.execute("CREATE TABLE IF NOT EXISTS users (idx INTEGER,username TEXT UNIQUE, password TEXT, role TEXT)")
    c.execute("CREATE INDEX IF NOT EXISTS idx_username ON users(username)")
    c.execute("INSERT OR IGNORE INTO users (idx, username, password, role) VALUES (?, ?, ?, ?)",
              (0,"admin", "pbkdf2:sha256:260000$A3CyzDUhLt1P7PLu$24a44b45679e803644c83b3282115a82c354c96285f8919fd1ae936a5a5c7bce", "admin"))
    conn.commit()
    conn.close()

@app.route("/")
def home():
    if "user" in session:
        return f"Hello, {session['user']} ({session['role']}) | <a href='/logout'>Logout</a> | <a href='/employee'>Employee Lookup</a>"
    return "<a href='/login'>Login</a> | <a href='/register'>Register</a>"

@app.route("/register", methods=["GET", "POST"])
def register():
    html = '''
    <h2>Register (employee only)</h2>
    <form method="POST">
        Username: <input name="username"><br>
        Password: <input name="password" type="password"><br>
        Role: <input name="role"><br>
        <input type="submit" value="Register">
    </form>
    '''
    if request.method == "POST":
        username = request.form["username"]
        password = generate_password_hash(request.form["password"])
        requested_role = request.form.get("role", "").strip().lower()
        if not requested_role or requested_role=="admin":
            role = "employee"
        else:
            role = requested_role
        try:
            conn = sqlite3.connect("users.db")
            c = conn.cursor()
            employee_data.append({"name": username, "role": role})
            save_employee_data(employee_data)
            c.execute("INSERT INTO users (idx, username, password, role) VALUES (?, ?, ?, ?)", (len(employee_data)-1, username, password, role))
            conn.commit()
            return jsonify({"status": "Success","Response":"Registered successfully.","index": len(employee_data)-1}), 200 
        except sqlite3.IntegrityError:
            return html + "<p>Username already exists.</p>"
    return html

@app.route("/login", methods=["GET", "POST"])
def login():
    html = '''
    <h2>Login</h2>
    <form method="POST">
        Username: <input name="username"><br>
        Password: <input name="password" type="password"><br>
        <input type="submit" value="Login">
    </form>
    '''
    if request.method == "POST":
        username = request.form["username"]
        password = request.form["password"]
        conn = sqlite3.connect("users.db")
        c = conn.cursor()
        c.execute("SELECT idx,password, role FROM users WHERE username = ?", (username,))
        row = c.fetchone()
        if row and check_password_hash(row[1], password):
            session["user"] = username
            session["role"] = row[2]
            session["id"] = row[0]
            return redirect("/")
        else:
            return html + "<p>Invalid credentials.</p>"
    return html

@app.route("/logout")
def logout():
    session.clear()
    return redirect("/")

@app.route("/employee")
def employee_lookup():
    if "user" not in session:
        return redirect("/login")

    role = session["role"]
    session_id = session["id"]
    if role == "admin":
        base = abs(int(request.args.get("id", "0")))
        idx = (base) & 0xFFFFFFFF
        if idx >= 0x80000000:
            idx -= 0x100000000
        try:
            emp = employee_data[idx]
            return f"<b>Admin Access:</b> {emp['name']} - {emp['role']}"
        except Exception as e:
            return jsonify({"status": "Error"}), 200

    else:
        try:
            idx = abs(int(request.args.get("id", "0")))
            idx = (idx) & 0xFFFFFFFF
            if idx >= 0x80000000:
                idx -= 0x100000000
            if idx < 4 or session_id == idx:
                emp = employee_data[idx]
                return f"<b>Employee Access:</b> {emp['name']} - {emp['role']}"
            else:
                return "<p>Access denied: index out of range</p>"
        except Exception as e:
            return jsonify({"status": "Error"}), 200

    
if __name__ == "__main__":
    init_db()
    app.run(host="0.0.0.0", port=5000,debug=True)
