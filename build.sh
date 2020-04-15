#!/bin/sh

VER="1.2.2"
CUR_TIME=$(date "+%Y.%m.%d-%H.%M.%S")

# Create 1.6 version
# PRESTAV="1.6"

# cp $PRESTAV/mobbex.php mobbex/mobbex.php
# zip mobbex.$VER.ps-$PRESTAV.zip -r mobbex
# rm mobbex/mobbex.php

# Create 1.7 version
PRESTAV="1.7"

cp $PRESTAV/mobbex.php mobbex/mobbex.php
zip mobbex.$VER.ps-$PRESTAV.$CUR_TIME.zip -r mobbex
zip mobbex.$VER.ps-$PRESTAV.zip -r mobbex
rm mobbex/mobbex.php