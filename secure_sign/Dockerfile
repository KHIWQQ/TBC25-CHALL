FROM python:3.11-slim

# Install socat
RUN apt-get update && \
    apt-get install -y socat && \
    rm -rf /var/lib/apt/lists/* \

# Set the working directory
WORKDIR /app
COPY service.py .
RUN touch storage.db

# Setup CMD to run the service
CMD ["socat", "-d", "-d", "TCP-LISTEN:5555,fork,reuseaddr", "EXEC:python3 service.py"]