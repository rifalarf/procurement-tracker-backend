#!/bin/bash

# Backend Deployment Script for cPanel
# This script helps prepare the backend for deployment

echo "🚀 Preparing Backend for cPanel Deployment..."
echo ""

# Navigate to backend directory
cd "$(dirname "$0")"

# Check if .env.production exists
if [ ! -f ".env.production" ]; then
    echo "❌ Error: .env.production file not found!"
    echo "Please create .env.production with your production settings."
    exit 1
fi

echo "✅ Found .env.production"

# Install dependencies
echo ""
echo "📦 Installing Composer dependencies..."
composer install --optimize-autoloader --no-dev

if [ $? -ne 0 ]; then
    echo "❌ Composer install failed!"
    exit 1
fi

echo "✅ Composer dependencies installed"

# Clear and cache config
echo ""
echo "🔧 Optimizing Laravel..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "✅ Cache cleared"

# Run optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

echo "✅ Application optimized"

# Create deployment package
echo ""
echo "📦 Creating deployment package..."

# Create a temporary directory
TEMP_DIR="deployment_$(date +%Y%m%d_%H%M%S)"
mkdir -p "../$TEMP_DIR"

# Copy necessary files
echo "Copying files..."
rsync -av --progress \
    --exclude='node_modules' \
    --exclude='.git' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='.env' \
    --exclude='tests' \
    --exclude='.phpunit.result.cache' \
    ./ "../$TEMP_DIR/"

# Copy .env.production as .env
cp .env.production "../$TEMP_DIR/.env"

# Create necessary directories
mkdir -p "../$TEMP_DIR/storage/framework/cache"
mkdir -p "../$TEMP_DIR/storage/framework/sessions"
mkdir -p "../$TEMP_DIR/storage/framework/views"
mkdir -p "../$TEMP_DIR/storage/logs"

# Create .gitkeep files
touch "../$TEMP_DIR/storage/framework/cache/.gitkeep"
touch "../$TEMP_DIR/storage/framework/sessions/.gitkeep"
touch "../$TEMP_DIR/storage/framework/views/.gitkeep"
touch "../$TEMP_DIR/storage/logs/.gitkeep"

# Create zip file
echo ""
echo "Creating zip archive..."
cd ..
zip -r "${TEMP_DIR}.zip" "$TEMP_DIR" -q

echo ""
echo "✅ Deployment package created: ${TEMP_DIR}.zip"
echo ""
echo "📋 Next steps:"
echo "1. Upload ${TEMP_DIR}.zip to your cPanel File Manager"
echo "2. Extract the zip file"
echo "3. Move contents to your domain directory"
echo "4. Update .env with your database credentials"
echo "5. Run: php artisan migrate --force"
echo "6. Set proper permissions: chmod -R 755 storage bootstrap/cache"
echo ""
echo "For detailed instructions, see DEPLOYMENT.md"
echo ""

# Clean up temp directory
rm -rf "$TEMP_DIR"

echo "✅ Done!"
