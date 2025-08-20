FROM python:3.11-slim

WORKDIR /app

COPY app.py .
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt


# Expose the port the server runs on
EXPOSE 5555

# Run the server
CMD ["python", "-u", "app.py"]
