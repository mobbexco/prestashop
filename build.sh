#!/bin/sh

# Version is read from Config::MODULE_VERSION, never hardcoded here.
VER=$(sed -n "s/.*const MODULE_VERSION *= *'\([^']*\)'.*/\1/p" mobbex/Models/Config.php)

if [ -z "$VER" ]; then
    echo "error: could not read MODULE_VERSION from mobbex/Models/Config.php" >&2
    exit 1
fi

echo "Building mobbex $VER"

# Unified Version
PRESTAV="1.6-8.2"

# Install dependencies
cd mobbex
composer install --no-dev
cd ..

# Prevent building using local repositories as dependencies
if find mobbex/vendor -maxdepth 3 -name '.git' | grep -q .; then
    echo "error: vendor/ contains a .git directory, so composer installed from source." >&2
    echo "       Point the repository at the GitHub URL, not a local path, and rebuild." >&2
    find mobbex/vendor -maxdepth 3 -name '.git' >&2
    exit 1
fi

if type 7z > /dev/null; then
    7z a -tzip "mobbex.$VER.ps-$PRESTAV.zip" mobbex
elif type zip > /dev/null; then
    zip mobbex.$VER.ps-$PRESTAV.zip -r mobbex
fi

# Remove dep files
rm -r mobbex/vendor mobbex/composer.lock

echo
echo "Built mobbex.$VER.ps-$PRESTAV.zip"
echo "Publish it as the asset of tag $VER."
