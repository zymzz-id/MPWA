#!/bin/bash

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}==================================${NC}"
echo -e "${BLUE}   MPWA Docker Startup Script${NC}"
echo -e "${BLUE}==================================${NC}"
echo ""

print_status() { echo -e "${GREEN}[✓]${NC} $1"; }
print_error() { echo -e "${RED}[✗]${NC} $1"; }
print_info() { echo -e "${BLUE}[i]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[!]${NC} $1"; }

# Check Docker
if ! command -v docker &> /dev/null; then
    print_error "Docker is not installed."
    exit 1
fi
print_status "Docker found"

# Check Docker Compose
if ! command -v docker-compose &> /dev/null; then
    print_error "Docker Compose is not installed."
    exit 1
fi
print_status "Docker Compose found"

# Check Docker daemon
if ! docker info &> /dev/null; then
    print_error "Docker daemon is not running."
    exit 1
fi
print_status "Docker daemon is running"

# Check docker-compose.yml
if [ ! -f docker-compose.yml ]; then
    print_error "docker-compose.yml not found."
    exit 1
fi
print_status "docker-compose.yml found"

# Check .env
if [ ! -f .env ]; then
    print_warning ".env file not found. Creating..."
    if [ -f .env.example ]; then
        cp .env.example .env
        print_status ".env created"
    else
        print_error ".env.example not found."
        exit 1
    fi
fi
print_status ".env file exists"

echo ""
echo -e "${BLUE}==================================${NC}"
echo -e "${BLUE}   Docker Options${NC}"
echo -e "${BLUE}==================================${NC}"
echo ""
echo "1) Start containers"
echo "2) Stop containers"
echo "3) View logs"
echo "4) Restart containers"
echo "5) Run migrations"
echo "6) Open shell"
echo "7) Full reset"
echo "0) Exit"
echo ""

read -p "Select option (0-7): " OPTION

case $OPTION in
    1)
        print_info "Starting containers..."
        docker-compose up -d
        print_status "Containers started"
        sleep 5
        print_info "Web Dashboard: http://localhost:8000"
        ;;
    2)
        print_info "Stopping containers..."
        docker-compose down
        print_status "Containers stopped"
        ;;
    3)
        print_info "Showing logs..."
        docker-compose logs -f
        ;;
    4)
        print_info "Restarting containers..."
        docker-compose restart
        print_status "Containers restarted"
        ;;
    5)
        print_info "Running migrations..."
        docker-compose exec -T mpwa php artisan migrate --force
        print_status "Migrations completed"
        ;;
    6)
        print_info "Opening shell..."
        docker-compose exec mpwa bash
        ;;
    7)
        print_warning "This will remove all volumes!"
        read -p "Are you sure? (y/N): " CONFIRM
        if [ "$CONFIRM" = "y" ]; then
            docker-compose down -v
            docker-compose up -d
            print_status "Fresh containers started"
        fi
        ;;
    0)
        print_info "Exiting..."
        exit 0
        ;;
    *)
        print_error "Invalid option"
        exit 1
        ;;
esac

echo ""
print_info "Done!"
echo ""
