#!/bin/sh

VER="4.3.0"
UIV="1.3.1"

# Unified Version
PRESTAV="1.6-1.7"

# Install UI Library
npm pack @mobbex/ecommerce-ui@$UIV
tar -xzf mobbex-ecommerce-ui-$UIV.tgz
rm -rf ./mobbex/views/library
mkdir -p ./mobbex/views/library
cp ./package/dist/ecommerce-ui.js ./mobbex/views/library #TODO: Change in base to final ecommerce-ui version. 

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