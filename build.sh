#!/bin/sh

VER="1.1.17"

# Create 1.6 version
PRESTAV="1.6"

cp $PRESTAV/mobbex.php mobbex/mobbex.php
zip mobbex.$VER.ps-$PRESTAV.zip -r mobbex
rm mobbex/mobbex.php

# Create 1.7 version
PRESTAV="1.7"

cp $PRESTAV/mobbex.php mobbex/mobbex.php
zip mobbex.$VER.ps-$PRESTAV.zip -r mobbex
rm mobbex/mobbex.php