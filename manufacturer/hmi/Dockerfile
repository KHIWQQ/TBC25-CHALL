FROM python:3.11-slim
WORKDIR /app
COPY requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r requirements.txt
COPY app.py /app/app.py
COPY templates /app/templates
COPY static /app/static
EXPOSE 80
CMD ["python", "-m", "gunicorn", "-w", "2", "-b", "0.0.0.0:80", "app:app"]
