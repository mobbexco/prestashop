#!/bin/sh

# Version is read from Config::MODULE_VERSION, never hardcoded here.
#
# The plugin reports that string to Mobbex on every checkout, and the published
# release with the matching tag is what the integrity check is verified against.
# A zip named after a version the code does not report — or a tag that does not
# exist — leaves every merchant on it degraded, with no local symptom.
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

# Composer installs from dist (a zipball) for tagged releases resolved through
# Packagist or a GitHub VCS repository, and from source (a git clone) when it
# cannot get a dist — a local path repository, for instance. A source install
# leaves a .git directory inside vendor/, which would ship the dependency's full
# history inside a module directory the web server serves, and would also make
# vendor/ differ from what merchants running a normal install get.
#
# Fail loudly rather than excluding it: its presence means the build resolved the
# dependency the wrong way, and that is worth knowing before publishing.
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