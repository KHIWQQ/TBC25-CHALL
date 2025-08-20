#!/usr/bin/env bash
set -euo pipefail

# ---------- Basic config ----------
RPC_URL="${RPC_URL:-http://127.0.0.1:8545}"
export ETH_RPC_URL="$RPC_URL"

OUT_DIR="${OUT_DIR:-/app/data}"
FOUNDRY_PROJECT_DIR="${FOUNDRY_PROJECT_DIR:-/app/foundry}"
ANVIL_LOG="${OUT_DIR}/anvil.log"
MNEMONIC_FILE="${OUT_DIR}/mnemonic.txt"

ANVIL_ACCOUNTS="${ANVIL_ACCOUNTS:-1}"
ANVIL_BALANCE="${ANVIL_BALANCE:-10000000000000000000}" # 10 ETH
DEPLOYER_INDEX="${DEPLOYER_INDEX:-0}"

mkdir -p "$OUT_DIR"

# ---------- Sanity: tools ----------
command -v anvil >/dev/null 2>&1 || { echo "[entrypoint] ERROR: anvil not found"; exit 2; }
command -v cast  >/dev/null 2>&1 || { echo "[entrypoint] ERROR: cast not found";  exit 2; }
command -v forge >/dev/null 2>&1 || { echo "[entrypoint] ERROR: forge not found"; exit 2; }
[ -d "$FOUNDRY_PROJECT_DIR" ] || { echo "[entrypoint] ERROR: Not a directory: $FOUNDRY_PROJECT_DIR"; exit 2; }

# ---------- Fresh mnemonic every start ----------
echo "[entrypoint] Generating fresh mnemonic via 'cast wallet new-mnemonic'..."
MNEMONIC="$(cast wallet new-mnemonic | awk '/^Phrase:/{getline; print; exit}')"
if [[ -z "$MNEMONIC" ]]; then
  echo "[entrypoint] ERROR: failed to generate mnemonic with cast." >&2
  exit 2
fi
echo "$MNEMONIC" > "$MNEMONIC_FILE"
export ANVIL_MNEMONIC="$MNEMONIC"
export MNEMONIC="$MNEMONIC"

FIRST_ADDR="$(cast wallet derive --mnemonic "$MNEMONIC" --index "$DEPLOYER_INDEX" --address 2>/dev/null || true)"
[[ -n "$FIRST_ADDR" ]] && echo "[entrypoint] Deployer (index $DEPLOYER_INDEX) = $FIRST_ADDR"

# ---------- Start Anvil (detached) ----------
echo "[entrypoint] Starting Anvil… (logs: $ANVIL_LOG)"
args=( --host 0.0.0.0 --block-time 1 --mnemonic "$ANVIL_MNEMONIC" --accounts "$ANVIL_ACCOUNTS" --balance "$ANVIL_BALANCE" )
if [[ -n "${ANVIL_ARGS:-}" ]]; then
  extra_args=(${ANVIL_ARGS})
  args+=("${extra_args[@]}")
fi
anvil "${args[@]}" >"$ANVIL_LOG" 2>&1 &
ANVIL_PID=$!

# ---------- Wait for RPC ----------
echo "[entrypoint] Waiting for Anvil at $RPC_URL ..."
for i in {1..120}; do
  if cast rpc eth_chainId >/dev/null 2>&1; then
    echo "[entrypoint] Anvil is up."
    break
  fi
  sleep 0.5
  if [[ $i -eq 120 ]]; then
    echo "[entrypoint] ERROR: Anvil did not become ready in time."
    tail -n 120 "$ANVIL_LOG" || true
    kill "$ANVIL_PID" >/dev/null 2>&1 || true
    exit 2
  fi
done

DEPLOYER_PK="$(cast wallet private-key --  "$MNEMONIC" "$DEPLOYER_INDEX" || true)"


export DEPLOYER_PK
echo "[entrypoint] Derived deployer PK."

# ---------- Run forge script to deploy (writes $OUT_DIR/instance.json) ----------
echo "[entrypoint] Running forge script…"
pushd "$FOUNDRY_PROJECT_DIR" >/dev/null
if ! forge script script/Deploy.s.sol:Deploy \
      --broadcast \
      --rpc-url "$RPC_URL" \
      --private-key "$DEPLOYER_PK" \
      >/tmp/forge.out 2>/tmp/forge.err; then
  echo "[entrypoint] ERROR: forge failed"
  exit 2
fi
popd >/dev/null

if [[ ! -f "$OUT_DIR/instance.json" ]]; then
  echo "[entrypoint] ERROR: instance.json not found at $OUT_DIR/instance.json after deploy" >&2
  echo "Check your Deploy.s.sol writes file to \$OUT_DIR (currently '$OUT_DIR')."
  exit 2
fi
echo "[entrypoint] Deployment done. instance.json ready."

# ---------- Start Bun API ----------
echo "[entrypoint] Starting Bun API ..."
bun run service/src/index.ts
