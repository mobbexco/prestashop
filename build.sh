#!/bin/sh

VER="4.5.0"

# Unified Version
PRESTAV="1.6-1.7"

# Install dependencies
cd mobbex
composer install --no-dev
cd ..

if type 7z > /dev/null; then
    7z a -tzip "mobbex.$VER.ps-$PRESTAV.zip" mobbex
elif type zip > /dev/null; then
    zip mobbex.$VER.ps-$PRESTAV.zip -r mobbex
fi

# Remove dep files
rm -r mobbex/vendor mobbex/composer.lock