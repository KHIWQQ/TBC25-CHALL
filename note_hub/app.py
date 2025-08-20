import uuid
from functools import wraps

from flask import (Flask, flash, redirect, render_template, request, session, url_for)
from sqlalchemy import text
from sqlalchemy.exc import IntegrityError, SQLAlchemyError

from models import Note, User, init_db, session_scope

# Initialize Flask app
app = Flask(__name__)

# Load the config file
app.config.from_object("config")

# Initialize database
init_db()


# Authentication decorator
def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if "username" not in session:
            flash("Please log in to access this page", "error")
            return redirect(url_for("login"))
        return f(*args, **kwargs)

    return decorated_function


# Routes
@app.route("/")
def index():
    return render_template("index.html")


@app.route("/register", methods=["GET", "POST"])
def register():
    if request.method == "POST":
        username = request.form["username"]
        password = request.form["password"]

        if not username or not password:
            flash("Username and password are required", "error")
            return redirect(url_for("register"))

        try:
            with session_scope() as session:
                new_user = User(username=username, password=password)
                session.add(new_user)
                # session.commit() is handled by context manager

            flash("Registration successful! Please login.", "success")
            return redirect(url_for("login"))
        except IntegrityError:
            flash("Username already exists", "error")

    return render_template("register.html")


@app.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "POST":
        username = request.form["username"]
        password = request.form["password"]

        with session_scope() as db_session:
            user = (
                db_session.query(User)
                .filter_by(username=username, password=password)
                .with_for_update()
                .first()
            )

            if user:
                session["username"] = user.username
                flash("Login successful!", "success")
                return redirect(url_for("dashboard"))
            else:
                flash("Invalid username or password", "error")

    return render_template("login.html")


@app.route("/logout")
def logout():
    session.clear()
    flash("You have been logged out", "info")
    return redirect(url_for("login"))


@app.route("/dashboard")
@login_required
def dashboard():
    return render_template("dashboard.html")


@app.route("/create_note", methods=["GET", "POST"])
@login_required
def create_note():
    if request.method == "POST":
        title = request.form["title"]
        content = request.form["content"]
        is_public = "is_public" in request.form
        note_uuid = str(uuid.uuid4())

        with session_scope() as db_session:
            user = (
                db_session.query(User)
                .filter_by(username=session["username"])
                .with_for_update()
                .first()
            )
            if not user:
                flash("User not found", "error")
                return redirect(url_for("login"))

        with session_scope() as db_session:
            new_note = Note(
                uuid=note_uuid,
                user_id=user.id,
                title=title,
                content=content,
                is_public=is_public,
            )
            db_session.add(new_note)
            # session.commit() is handled by context manager

        flash("Note created successfully!", "success")
        return redirect(url_for("my_notes"))

    return render_template("create_note.html")


@app.route("/my_notes")
@login_required
def my_notes():
    with session_scope() as db_session:
        user = (
            db_session.query(User)
            .filter_by(username=session["username"])
            .with_for_update()
            .first()
        )
        if not user:
            flash("User not found", "error")
            return redirect(url_for("login"))

    with session_scope() as db_session:
        notes = (
            db_session.query(Note)
            .filter_by(user_id=user.id)
            .order_by(Note.id.desc())
            .all()
        )

        return render_template("my_notes.html", notes=notes)


@app.route("/search", methods=["GET", "POST"])
def search():
    results = []
    if request.method == "POST" and "query" in request.form:
        search_query = request.form["query"]

        if search_query.strip():
            with session_scope() as db_session:
                try:
                    raw_query = text(
                        "SELECT notes.id, notes.uuid, notes.user_id, notes.title, notes.content, notes.is_public, users.username "
                        "FROM notes JOIN users ON notes.user_id = users.id "
                        f"WHERE title LIKE '%{search_query}%' AND is_public is True"
                    )

                    # Execute with parameters to prevent SQL injection
                    results = db_session.execute(raw_query).all()

                    if not results:
                        flash(
                            f"No notes found containing '{search_query}' in the title.",
                            "info",
                        )

                    return render_template("search.html", results=results)
                except SQLAlchemyError as e:
                    flash(f"Error in search query: {str(e)}", "error")

        else:
            flash("Please enter a search term", "error")

    return render_template("search.html", results=[])


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8000, debug=False)
