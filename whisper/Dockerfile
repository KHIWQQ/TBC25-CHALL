FROM ubuntu:20.04
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    socat && \
    pip3 install --no-cache-dir pycryptodome requests && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY whisper/server.py .
COPY whisper/aes_key.bin .
COPY whisper/auth.py  .
COPY whisper/curve_op.py .
COPY whisper/nonce.bin .
COPY whisper/start.sh .
RUN chmod +x start.sh
RUN python3 auth.py

EXPOSE 1337
ENTRYPOINT ["./start.sh"]

