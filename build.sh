#!/bin/sh

VER="2.1.4"

# Unified Version
PRESTAV="1.6-1.7"

if type 7z > /dev/null; then
    7z a -tzip "mobbex.$VER.ps-$PRESTAV.zip" mobbex
elif type zip > /dev/null; then
    zip mobbex.$VER.ps-$PRESTAV.zip -r mobbex
elif type powershell > /dev/null; then
    powershell Compress-Archive mobbex mobbex.$VER.ps-$PRESTAV.zip
fi