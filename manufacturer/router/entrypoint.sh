#!/usr/bin/env bash
set -euo pipefail

# Default‑permit inside each zone; restrict cross‑zone per OT best‑practice.
# it_net ↔ ot_net filtering. Container has both NICs.

# Flush
iptables -F
iptables -t nat -F
iptables -t mangle -F
iptables -X

# Default policies
iptables -P INPUT ACCEPT
iptables -P FORWARD DROP
iptables -P OUTPUT ACCEPT

iptables -A FORWARD -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT

# Whitelist flows (defenders can harden further at runtime):
# SCADA ↔ HistorianDB (Postgres 5432)
iptables -A FORWARD -p tcp --dport 5432 -j ACCEPT
iptables -A FORWARD -p tcp --sport 5432 -j ACCEPT
# HMI -> PLC (Modbus 502)
iptables -A FORWARD -p tcp --dport 502 -j ACCEPT
# SCADA -> PLC (read‑only polling; allow for now)
iptables -A FORWARD -p tcp --dport 502 -j ACCEPT

# Optional: block direct IT → PLC later by comment‑toggling these rules.

echo "[router] iptables configured."
tail -f /dev/null
