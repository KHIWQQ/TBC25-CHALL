#!/usr/bin/env bash
set -euo pipefail
cd /app
echo "[build] gcc: $(gcc --version | head -1)"
echo "[build] compiling libplc.so (real overflow; guards disabled)…"
gcc -O2 \
  -fno-stack-protector \
  -U_FORTIFY_SOURCE -D_FORTIFY_SOURCE=0 \
  -fno-builtin -fno-builtin-strcpy -fno-builtin-memcpy \
  -fno-pie -no-pie \
  -Wl,-z,noexecstack \
  -shared -fPIC -o /app/libplc.so /app/plc_shared.c
echo "[build] done."
