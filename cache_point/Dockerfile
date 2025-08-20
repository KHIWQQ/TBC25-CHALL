FROM python:3.11-slim

WORKDIR /app

COPY server.py .
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Expose the port the server runs on
EXPOSE 6000

# Run the server
CMD ["python", "-u", "server.py"]
