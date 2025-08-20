FROM python:3.12-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

EXPOSE 8000

# Initialize the SQLite database
RUN touch notes.db

CMD ["gunicorn", "--bind", "0.0.0.0:8000", "--access-logfile", "-", "app:app"]
