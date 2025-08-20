FROM python:3.10-slim

ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1
WORKDIR /app
RUN useradd -m appuser

COPY ./source/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY ./source/gateway.py .
USER appuser

ENV BACKEND_HOST=plc BACKEND_PORT=1502 GATE_PORT=502 AUTH_KEY=dev-secret WINDOW_SECS=5
EXPOSE 502/tcp

CMD ["python","/app/gateway.py"]
