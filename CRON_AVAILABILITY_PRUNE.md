# Cron: Prune Old Availability Data

Removes past availability data that is no longer needed:
- **room_availability_slots**: Deletes rows where `date < today`
- **staff.availability**: Removes date-specific keys (YYYY-MM-DD) that are in the past; keeps day-based keys (Monday, Tuesday, etc.) for recurring schedules

## Manual Run

```bash
# Inside Docker container
docker compose exec app php artisan availability:prune-old

# Or directly
php artisan availability:prune-old
```

## Cron Setup (Server)

### Option A: Direct daily run (simplest)

Add to crontab (`crontab -e`):

```cron
# Prune old availability daily at 2:00 AM
0 2 * * * /home/mediva/veccura-mediva/vue-ura-backend/scripts/cron-prune-availability.sh >> /var/log/prune-availability.log 2>&1
```

Make the script executable:
```bash
chmod +x /home/mediva/veccura-mediva/vue-ura-backend/scripts/cron-prune-availability.sh
```

### Option B: Laravel Scheduler

Add to crontab:

```cron
# Laravel scheduler - runs every minute; our availability:prune-old runs daily
* * * * * cd /home/mediva/veccura-mediva/vue-ura-backend && docker exec vue-ura-app php artisan schedule:run >> /dev/null 2>&1
```

The command is already scheduled in `routes/console.php` to run daily.

## Options

```bash
# Delete slots older than 7 days (keep last week)
php artisan availability:prune-old --days=7
```
