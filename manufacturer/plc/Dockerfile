FROM python:3.11-slim
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential gcc && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r requirements.txt
COPY plc_shared.c plc_shared.h build.sh run.sh gateway.py /app/
RUN chmod +x build.sh run.sh && ./build.sh
EXPOSE 502
CMD ["/app/run.sh"]
