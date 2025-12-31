#!/bin/bash

# ============================================================
# Laravel cPanel Deployment Preparation Script
# ============================================================
# This script prepares the Laravel backend for cPanel deployment
# 
# Usage: ./prepare-deploy.sh
# ============================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  Laravel cPanel Deployment Preparation${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="${SCRIPT_DIR}/deploy-package"
APP_DIR="${DEPLOY_DIR}/laravel_app"
PUBLIC_DIR="${DEPLOY_DIR}/public_html"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Clean previous deploy package
echo -e "${YELLOW}[1/7] Cleaning previous deploy package...${NC}"
rm -rf "${DEPLOY_DIR}"
mkdir -p "${APP_DIR}"
mkdir -p "${PUBLIC_DIR}"

# Copy application files (excluding unnecessary files)
echo -e "${YELLOW}[2/7] Copying application files...${NC}"
rsync -av --progress \
    --exclude='deploy-package' \
    --exclude='.git' \
    --exclude='.env' \
    --exclude='.env.local' \
    --exclude='node_modules' \
    --exclude='storage/logs/*.log' \
    --exclude='storage/framework/cache/data/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='tests' \
    --exclude='phpunit.xml' \
    --exclude='.phpunit.cache' \
    --exclude='prepare-deploy.sh' \
    "${SCRIPT_DIR}/" "${APP_DIR}/"

# Create production .env file
echo -e "${YELLOW}[3/7] Creating production .env file...${NC}"
cat > "${APP_DIR}/.env" << 'ENVFILE'
APP_NAME="Procurement Tracker"
APP_ENV=production
APP_KEY=base64:tLhVfQp/ZqL2VhVUYDNdz6Moh7K9d/Q1nv0qLu4XBSY=
APP_DEBUG=false
APP_URL=https://pengadaan.matrifix.site

FRONTEND_URL=https://proctrack.vercel.app

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=matf2269_pengadaan
DB_USERNAME=matf2269_pengadaan
DB_PASSWORD="aiG$a&g3-Y4(_aa3"

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.matrifix.site

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=file
ENVFILE

# Copy public files to separate folder
echo -e "${YELLOW}[4/7] Preparing public folder...${NC}"
cp -r "${APP_DIR}/public/"* "${PUBLIC_DIR}/"

# Create modified index.php for cPanel
echo -e "${YELLOW}[5/7] Creating cPanel-compatible index.php...${NC}"
cat > "${PUBLIC_DIR}/index.php" << 'INDEXPHP'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
// Path: public_html/pengadaan -> public_html -> home -> pengadaan_app
if (file_exists($maintenance = __DIR__.'/../../pengadaan_app/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
// IMPORTANT: Path to Laravel app folder (2 levels up from public_html/pengadaan)
require __DIR__.'/../../pengadaan_app/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../../pengadaan_app/bootstrap/app.php')
    ->handleRequest(Request::capture());
INDEXPHP

# Create .htaccess if not exists
echo -e "${YELLOW}[6/7] Ensuring .htaccess exists...${NC}"
if [ ! -f "${PUBLIC_DIR}/.htaccess" ]; then
cat > "${PUBLIC_DIR}/.htaccess" << 'HTACCESS'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTACCESS
fi

# Create deployment info
echo -e "${YELLOW}[7/7] Creating deployment info...${NC}"
cat > "${DEPLOY_DIR}/DEPLOY_INFO.txt" << DEPLOYINFO
============================================
 Deployment Package Created: ${TIMESTAMP}
============================================

STRUKTUR FOLDER DI CPANEL:
--------------------------
/home/matf2269/
├── public_html/
│   └── pengadaan/       ← Upload isi folder 'public_html/'
│       ├── index.php    ← (sudah dimodifikasi untuk cPanel)
│       ├── .htaccess
│       └── ...
│
└── pengadaan_app/       ← Upload isi folder 'laravel_app/'
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── vendor/
    ├── .env             ← (sudah dikonfigurasi untuk production)
    └── ...

LANGKAH UPLOAD:
---------------
1. Upload folder 'laravel_app/' ke /home/matf2269/pengadaan_app/
2. Upload isi folder 'public_html/' ke /home/matf2269/public_html/pengadaan/
3. Set permissions:
   - chmod 755 pengadaan_app/storage -R
   - chmod 755 pengadaan_app/bootstrap/cache -R
4. Run migrations (via terminal atau script PHP)
5. Clear & cache config

ATAU GUNAKAN ZIP:
-----------------
cd deploy-package/laravel_app && zip -r ../laravel_app.zip .
cd deploy-package/public_html && zip -r ../public_html.zip .

Upload kedua ZIP file ke cPanel dan extract di lokasi yang tepat.

URLS:
-----
Backend API: https://pengadaan.matrifix.site/api
Frontend: https://proctrack.vercel.app

DEPLOYINFO

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  Deployment package ready!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo -e "Package location: ${YELLOW}${DEPLOY_DIR}${NC}"
echo ""
echo "Contents:"
echo "  - laravel_app/  : Laravel application (upload to ~/pengadaan_app/)"
echo "  - public_html/  : Public files (upload to ~/public_html/pengadaan/)"
echo "  - DEPLOY_INFO.txt : Deployment instructions"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "  1. Create ZIP files for upload:"
echo "     cd ${DEPLOY_DIR}/laravel_app && zip -r ../laravel_app.zip ."
echo "     cd ${DEPLOY_DIR}/public_html && zip -r ../public_html.zip ."
echo ""
echo "  2. Upload to cPanel File Manager"
echo "  3. Extract and set permissions"
echo "  4. Run migrations"
echo ""
