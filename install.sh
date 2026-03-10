#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "This installer must be run as root." >&2
  exit 1
fi

# 0. Install curl (Debian/Ubuntu)
if ! command -v curl >/dev/null 2>&1; then
  apt-get update -y
  apt-get install -y curl
fi

# 1. Install nvm
export NVM_DIR="${NVM_DIR:-/root/.nvm}"
mkdir -p "$NVM_DIR"
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.4/install.sh | bash

# Load nvm
if [[ -s "$NVM_DIR/nvm.sh" ]]; then
  # shellcheck disable=SC1090
  . "$NVM_DIR/nvm.sh"
else
  echo "nvm.sh not found at $NVM_DIR/nvm.sh" >&2
  exit 1
fi

# 2. Install/use Node 22
nvm install 22
nvm use 22

# 3. Install yarn
npm install -g yarn

# 4. Go to panel directory
cd /var/www/pterodactyl

# 5. Download release tarball
rm -f panel.tar.gz
curl -L -o panel.tar.gz "https://github.com/FarizGD/FarizDev-Theme/releases/download/v1.12.2/panel.tar.gz"

# 6. Extract
 tar -xvf panel.tar.gz

# 7. Composer install
composer install

# 8. Migrate
php artisan migrate

# 9. Build assets
bash build.sh

echo "Done."
