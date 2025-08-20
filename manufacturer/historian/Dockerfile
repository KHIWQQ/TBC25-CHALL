FROM python:3.11-slim
WORKDIR /app
RUN pip install --no-cache-dir psycopg[binary]==3.2.1 pyModbusTCP==0.2.0
COPY init.sql /docker-entrypoint-initdb.d/init.sql
COPY logger.py /app/logger.py
CMD ["python", "/app/logger.py"]
