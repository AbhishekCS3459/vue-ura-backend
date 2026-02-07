# Branch Sync from Client API

One-time sync to align branches with client's VeCura API (APILocation.jsp).

## Mapping

| Client API Field | Our DB Field |
|------------------|--------------|
| `LocationName`    | `name`       |
| `state_name`     | `city`       |
| `id` (e.g. TPT, VJY) | `external_id` |

## Commands

```bash
# Merge: Add new branches from client, update existing by external_id
php artisan branches:sync-from-client

# Replace: Delete previously synced branches, then re-sync
php artisan branches:sync-from-client --replace

# Replace all: Delete ALL branches and sync only from client (use for cleanup)
php artisan branches:sync-from-client --replace-all

# Same with --force to skip confirmation (for scripts)
php artisan branches:sync-from-client --replace-all --force
```

**To remove legacy/seeded branches (vecura-indranagar, vecura-whitefield, etc.) and use only client API data:**
```bash
php artisan branches:sync-from-client --replace-all --force
```

## API Response

Branches now include `external_id` in API responses for future client integration (e.g. patient RegistrationNo prefix CBT → branch external_id CBT).

## Future Phase

When creating new branches, accept `external_id` to match client's branch codes.
