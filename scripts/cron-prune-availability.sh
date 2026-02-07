#!/bin/bash
# Cron script to prune old availability data (room slots + staff date-specific availability)
# Run daily via cron.
#
# OPTION A - Direct run at 2 AM:
#   0 2 * * * /home/mediva/veccura-mediva/vue-ura-backend/scripts/cron-prune-availability.sh >> /var/log/prune-availability.log 2>&1
#
# OPTION B - Laravel scheduler (runs schedule:run every minute, our command runs daily):
#   * * * * * cd /home/mediva/veccura-mediva/vue-ura-backend && docker exec vue-ura-app php artisan schedule:run >> /dev/null 2>&1

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$(dirname "$SCRIPT_DIR")"
cd "$BACKEND_DIR"

run_artisan() {
    if docker ps --format '{{.Names}}' 2>/dev/null | grep -q vue-ura-app; then
        docker exec vue-ura-app php artisan availability:prune-old "$@"
    elif docker compose ps -q app 2>/dev/null; then
        docker compose exec -T app php artisan availability:prune-old "$@"
    else
        php artisan availability:prune-old "$@"
    fi
}

run_artisan "$@"
