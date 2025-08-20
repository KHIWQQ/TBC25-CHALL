#!/usr/bin/env bash
set -euo pipefail
export LD_LIBRARY_PATH=/app:${LD_LIBRARY_PATH:-}
cd /app

need_build=0
if [ ! -f /app/libplc.so ]; then
  echo "[run] libplc.so missing → will build"
  need_build=1
fi

if [ "${need_build}" -eq 0 ]; then
  # try to load; if it fails, rebuild
  python - <<'PY' || need_build=1
import ctypes, sys
try:
    ctypes.CDLL("/app/libplc.so")
    print("[run] libplc.so loads OK")
except OSError as e:
    print("[run] libplc.so load failed:", e)
    sys.exit(1)
PY
fi

if [ "${need_build}" -eq 1 ]; then
  echo "[run] building libplc.so…"
  /app/build.sh
  python - <<'PY'
import ctypes, sys
try:
    ctypes.CDLL("/app/libplc.so")
    print("[run] libplc.so loads OK after rebuild")
except OSError as e:
    print("[run] FATAL: cannot load libplc.so even after rebuild:", e)
    sys.exit(1)
PY
fi

exec python /app/gateway.py
