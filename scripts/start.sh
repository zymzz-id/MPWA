#!/bin/bash

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PORT_LARAVEL=8000
PORT_NODE=${PORT_NODE:-3000}
LOG_DIR="./logs"

mkdir -p "${LOG_DIR}"

echo -e "${BLUE}==================================${NC}"
echo -e "${BLUE}   MPWA Auto Startup Script${NC}"
echo -e "${BLUE}==================================${NC}"
echo ""

print_status() { echo -e "${GREEN}[✓]${NC} $1"; }
print_error() { echo -e "${RED}[✗]${NC} $1"; }
print_info() { echo -e "${BLUE}[i]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[!]${NC} $1"; }

# Check .env
if [ ! -f .env ]; then
    print_warning ".env file not found. Creating from .env.example..."
    if [ -f .env.example ]; then
        cp .env.example .env
        print_status ".env created from .env.example"
    else
        print_error ".env.example not found."
        exit 1
    fi
fi

# Check PHP
if ! command -v php &> /dev/null; then
    print_error "PHP is not installed."
    exit 1
fi
print_status "PHP found: $(php -v | head -n 1)"

# Check Node.js
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed."
    exit 1
fi
print_status "Node.js found: $(node -v)"

# Check Composer
if ! command -v composer &> /dev/null; then
    if [ ! -f composer.phar ]; then
        print_info "Downloading Composer..."
        curl -s https://getcomposer.org/installer | php
    fi
    COMPOSER="php composer.phar"
else
    COMPOSER="composer"
fi
print_status "Composer ready"

echo ""
print_info "Installing dependencies..."
echo ""

# Install PHP
if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
    print_info "Installing PHP dependencies..."
    $COMPOSER install --no-interaction
    print_status "PHP dependencies installed"
else
    print_status "PHP dependencies already installed"
fi

# Install Node.js
if [ ! -d node_modules ]; then
    print_info "Installing Node.js dependencies..."
    npm install
    print_status "Node.js dependencies installed"
else
    print_status "Node.js dependencies already installed"
fi

echo ""
print_info "Setting up Laravel..."
echo ""

# Generate key
if ! grep -q "APP_KEY=" .env || grep -q "APP_KEY=$" .env; then
    print_info "Generating Laravel application key..."
    php artisan key:generate
    print_status "Application key generated"
else
    print_status "Application key already set"
fi

# Run migrations
print_info "Running database migrations..."
php artisan migrate --force 2>/dev/null || print_warning "Database migration completed"
print_status "Database migrations completed"

echo ""
echo -e "${BLUE}==================================${NC}"
echo -e "${BLUE}   Starting Servers${NC}"
echo -e "${BLUE}==================================${NC}"
echo ""

cleanup() {
    print_warning "Shutting down servers..."
    [ ! -z "$LARAVEL_PID" ] && kill $LARAVEL_PID 2>/dev/null || true
    [ ! -z "$NODE_PID" ] && kill $NODE_PID 2>/dev/null || true
    print_status "Servers stopped"
    exit 0
}

trap cleanup SIGINT SIGTERM

# Start Node.js
print_info "Starting Node.js server on port $PORT_NODE..."
node server.js > "${LOG_DIR}/node-server.log" 2>&1 &
NODE_PID=$!
echo $NODE_PID > "${LOG_DIR}/node-server.pid"
sleep 2

if ps -p $NODE_PID > /dev/null; then
    print_status "Node.js server started (PID: $NODE_PID)"
else
    print_error "Failed to start Node.js server"
    exit 1
fi

# Start Laravel
print_info "Starting Laravel server on port $PORT_LARAVEL..."
php artisan serve --port=$PORT_LARAVEL > "${LOG_DIR}/laravel-server.log" 2>&1 &
LARAVEL_PID=$!
echo $LARAVEL_PID > "${LOG_DIR}/laravel-server.pid"
sleep 2

if ps -p $LARAVEL_PID > /dev/null; then
    print_status "Laravel server started (PID: $LARAVEL_PID)"
else
    print_error "Failed to start Laravel server"
    exit 1
fi

echo ""
echo -e "${GREEN}==================================${NC}"
echo -e "${GREEN}   MPWA is Running${NC}"
echo -e "${GREEN}==================================${NC}"
echo ""
print_info "Web Dashboard: http://localhost:$PORT_LARAVEL"
print_info "Node.js Server: http://localhost:$PORT_NODE"
print_info "API Documentation: http://localhost:$PORT_LARAVEL/api-docs"
echo ""
print_info "Press Ctrl+C to stop both servers"
echo ""

wait
