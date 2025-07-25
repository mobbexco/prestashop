#!/bin/sh

VER="4.4.1"

# Unified Version
PRESTAV="1.6-8.2"

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