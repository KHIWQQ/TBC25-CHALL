#!/usr/bin/env bash
set -euo pipefail

JSON_FLAG=""
if [ "${CHECK_JSON,,}" = "true" ]; then
  JSON_FLAG="--json"
fi

run_once() {
  /usr/local/bin/python /app/checker.py \
    --host "${SERVICE_HOST}" \
    --port "${SERVICE_PORT}" \
    ${JSON_FLAG}
}

# If CHECK_INTERVAL > 0, loop. Otherwise run once (CI/judge mode).
if [ "${CHECK_INTERVAL}" != "0" ]; then
  echo "[checker] Running every ${CHECK_INTERVAL}s against ${SERVICE_HOST}:${SERVICE_PORT}"
  while true; do
    run_once || true
    sleep "${CHECK_INTERVAL}"
  done
else
  run_once
fi
