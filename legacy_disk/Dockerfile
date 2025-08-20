FROM ubuntu:22.04

RUN apt update && apt install socat python3 python3-pip -y && pip3 install --no-cache-dir flask && rm -rf /var/lib/apt/lists/*
RUN useradd -m legacydisk
COPY challenge/verifier /home/legacydisk/
COPY challenge/LegacyDiskApp /home/legacydisk/LegacyDiskApp
COPY challenge/start.sh /home/legacydisk/
RUN chown -R legacydisk:legacydisk /home/legacydisk
RUN chmod -R 555 /home/legacydisk/LegacyDiskApp/chall.py
RUN chmod -R 555 /home/legacydisk/verifier
RUN chmod -R 555 /home/legacydisk/start.sh

EXPOSE 4444
EXPOSE 5000
USER legacydisk
WORKDIR /home/legacydisk
CMD ./start.sh


