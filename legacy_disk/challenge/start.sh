#!/bin/bash

python3 /home/legacydisk/LegacyDiskApp/chall.py &
socat -s TCP-LISTEN:4444,reuseaddr,fork EXEC:/home/legacydisk/verifier,pty,stderr
