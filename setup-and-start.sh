#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR" || exit 1

echo -e "${GREEN}=== Vue URA Backend Setup & Start Script ===${NC}\n"

# Function to find PHP executable
find_php() {
    # Check common PHP locations
    if command -v php &> /dev/null; then
        echo "php"
        return 0
    fi
    
    # Check Laravel Herd location (Windows)
    if [ -f "/c/Users/$USER/.config/herd/bin/php.bat" ]; then
        echo "/c/Users/$USER/.config/herd/bin/php.bat"
        return 0
    fi
    
    # Check Laravel Herd location (alternative)
    if [ -f "$HOME/.config/herd/bin/php" ]; then
        echo "$HOME/.config/herd/bin/php"
        return 0
    fi
    
    # Check Windows Program Files
    if [ -f "/c/Program Files/Laravel Herd/bin/php.exe" ]; then
        echo "/c/Program Files/Laravel Herd/bin/php.exe"
        return 0
    fi
    
    echo ""
    return 1
}

# Find PHP executable
PHP_CMD=$(find_php)
if [ -z "$PHP_CMD" ]; then
    echo -e "${RED}Error: PHP not found. Please install PHP or Laravel Herd.${NC}"
    exit 1
fi

echo -e "${YELLOW}Using PHP: $PHP_CMD${NC}\n"

# Step 1: Check Docker
echo -e "${GREEN}[1/6] Checking Docker...${NC}"
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Error: Docker is not installed or not in PATH${NC}"
    exit 1
fi

if ! docker info &> /dev/null; then
    echo -e "${RED}Error: Docker is not running. Please start Docker Desktop.${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Docker is running${NC}\n"

# Step 2: Start Docker containers
echo -e "${GREEN}[2/6] Starting Docker containers...${NC}"
docker-compose up -d

if [ $? -ne 0 ]; then
    echo -e "${RED}Error: Failed to start Docker containers${NC}"
    exit 1
fi

# Wait for MariaDB to be healthy
echo -e "${YELLOW}Waiting for MariaDB to be ready...${NC}"
MAX_ATTEMPTS=30
ATTEMPT=0
while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    if docker-compose ps mariadb | grep -q "healthy"; then
        echo -e "${GREEN}✓ MariaDB is healthy${NC}\n"
        break
    fi
    ATTEMPT=$((ATTEMPT + 1))
    echo -n "."
    sleep 2
done

if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
    echo -e "\n${RED}Error: MariaDB did not become healthy in time${NC}"
    exit 1
fi

# Step 3: Run migrations
echo -e "${GREEN}[3/6] Running database migrations...${NC}"
$PHP_CMD artisan migrate --force

if [ $? -ne 0 ]; then
    echo -e "${RED}Error: Migrations failed${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Migrations completed${NC}\n"

# Step 4: Seed SuperAdmin
echo -e "${GREEN}[4/6] Seeding SuperAdmin user...${NC}"
$PHP_CMD artisan db:seed --class=SuperAdminSeeder
echo -e "${GREEN}✓ SuperAdmin seeded${NC}\n"

# Step 5: Seed Branches
echo -e "${GREEN}[5/6] Seeding Branches...${NC}"
$PHP_CMD artisan db:seed --class=BranchSeeder
echo -e "${GREEN}✓ Branches seeded${NC}\n"

# Step 6: Seed Page Permissions
echo -e "${GREEN}[6/6] Seeding Page Permissions...${NC}"
$PHP_CMD artisan db:seed --class=PagePermissionSeeder
echo -e "${GREEN}✓ Page Permissions seeded${NC}\n"

# Step 7: Start Laravel server
echo -e "${GREEN}=== Starting Laravel Development Server ===${NC}"
echo -e "${YELLOW}Server will be available at: http://127.0.0.1:8000${NC}"
echo -e "${YELLOW}Press Ctrl+C to stop the server${NC}\n"

$PHP_CMD artisan serve
