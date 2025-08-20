FROM python:3.11-slim

# Set environment variables for production
ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    FLASK_ENV=production \
    FLASK_DEBUG=0

# Create non-root user for security
RUN groupadd -r scada && useradd -r -g scada scada

# Set working directory
WORKDIR /app

# Install system dependencies and clean up
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        gcc \
        && rm -rf /var/lib/apt/lists/*

# Install Python dependencies with security considerations
RUN pip install --no-cache-dir --upgrade pip==24.0 && \
    pip install --no-cache-dir \
        Flask==3.0.3 \
        psycopg[binary]==3.2.1 \
        pyModbusTCP==0.2.0 \
        gunicorn==21.2.0 \
        gevent==23.9.1

# Copy application files
COPY --chown=scada:scada scada.py /app/scada.py

# Create logs directory
RUN mkdir -p /app/logs && chown -R scada:scada /app/logs

# Switch to non-root user
USER scada

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD python -c "import requests; requests.get('http://localhost:8090/health', timeout=5)" || exit 1

# Expose port
EXPOSE 8090

# Use gunicorn for production with proper configuration
CMD ["gunicorn", "--bind", "0.0.0.0:8090", \
     "--workers", "4", \
     "--worker-class", "gevent", \
     "--worker-connections", "1000", \
     "--max-requests", "1000", \
     "--max-requests-jitter", "100", \
     "--timeout", "30", \
     "--keep-alive", "5", \
     "--log-level", "info", \
     "--access-logfile", "/app/logs/access.log", \
     "--error-logfile", "/app/logs/error.log", \
     "--capture-output", \
     "scada:app"]