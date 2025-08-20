#!/bin/bash

while true; do
    socat -d -d TCP-LISTEN:1337,reuseaddr,fork SYSTEM:'python3 server.py'
done

