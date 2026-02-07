# Treatment/Services Sync from Client API

Sync treatments from client's VeCura API (APIServiceMaster.jsp).

## Mapping

| Client API Field | Our DB Field |
|------------------|--------------|
| `ServiceName`    | `name`       |
| `id` (e.g. SER-0001) | `external_id` |
| `NoofSessions`   | `noof_sessions` |

## Sync Options

### UI (Branch Settings)
Click **"Sync from Client API"** in the Services section. New treatments appear with a "New" badge.

### CLI Commands

```bash
# Merge: Add new treatments, update existing by external_id
php artisan treatments:sync-from-client

# Replace all: Delete ALL treatments and sync only from client
php artisan treatments:sync-from-client --replace-all --force
```

### API
`POST /api/treatments/sync` - Syncs from client API, returns `{ created, updated, skipped, newTreatments }`.

## API Response Format (matches client)

```json
{
  "results": [
    {
      "id": 1,
      "external_id": "SER-0001",
      "ServiceName": "Cool Sculpting - 1 SESSION PACKAGE",
      "NoofSessions": "1"
    }
  ]
}
```

## Permissions

- **Branch Manager + Super Admin**: Create, edit, delete treatments
- Services synced from client (with external_id) cannot be deleted via UI
