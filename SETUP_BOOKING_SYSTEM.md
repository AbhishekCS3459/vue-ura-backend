# Booking System Setup Guide

## Step 1: Run Migrations

```bash
cd vue-ura-backend
php artisan migrate
```

This will create all the necessary tables:
- `patients` table
- Updated `branches` table (with `is_open`)
- Updated `therapy_sessions` table (with `room_id`, `treatment_id`, `patient_id`)
- `staff_treatment_assignments` table
- `room_availability_slots` table (materialized grid)
- And more...

## Step 2: Initialize Availability Grid

After migrations, initialize the availability grid for all rooms:

```bash
php artisan grid:initialize --days=30
```

Options:
- `--days=30` - Number of days to initialize (default: 30)
- `--branch=1` - Initialize for specific branch only (optional)
- `--force` - Force re-initialization (overwrites existing slots)

Example:
```bash
# Initialize for next 30 days for all branches
php artisan grid:initialize --days=30

# Initialize for specific branch
php artisan grid:initialize --branch=1 --days=30

# Force re-initialization
php artisan grid:initialize --days=30 --force
```

## Step 3: Seed Test Data (Optional)

Make sure you have:
- At least 1 branch
- At least 1 treatment
- At least 1 room (with gender: Male, Female, or Unisex)
- At least 1 staff member
- Room-treatment assignments (which rooms can perform which treatments)
- Staff-treatment assignments (which staff can perform which treatments)

## Step 4: Test the API

### Test Find Available Slot

```bash
curl -X POST http://127.0.0.1:8000/api/bookings/find-available-slot \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "branch_id": 1,
    "treatment_id": 1,
    "patient_gender": "Male"
  }'
```

### Test Create Booking

```bash
curl -X POST http://127.0.0.1:8000/api/bookings \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "branch_id": 1,
    "treatment_id": 1,
    "patient_name": "John Doe",
    "patient_gender": "Male",
    "phone": "+1234567890",
    "date": "2026-01-25",
    "start_time": "14:00"
  }'
```

## Step 5: Test in UI

1. Start the frontend:
```bash
cd vue-ura-dashboard
npm run dev
```

2. Navigate to: `http://localhost:5173/booking-test`

3. You can:
   - Find available slots
   - Create bookings
   - Test the full flow

## API Endpoints

### Booking Endpoints
- `POST /api/bookings/find-available-slot` - Find next available slot
- `GET /api/bookings` - List all bookings (with filters)
- `POST /api/bookings` - Create new booking
- `GET /api/bookings/{id}` - Get booking by ID
- `PUT /api/bookings/{id}` - Update booking
- `PUT /api/bookings/{id}/cancel` - Cancel booking

### Patient Endpoints
- `GET /api/patients` - List patients (with pagination)
- `GET /api/patients/search?q=query` - Search patients
- `POST /api/patients` - Create/update patient
- `GET /api/patients/{id}` - Get patient by ID
- `PUT /api/patients/{id}` - Update patient

## Notes

- All booking endpoints require authentication (`auth:sanctum` middleware)
- The availability grid is automatically updated when bookings are created/cancelled
- Gender constraints are enforced at multiple levels (room lookup, booking creation)
- Staff capacity is checked (max 2 patients per hour)

## Troubleshooting

### Grid not initialized?
Run: `php artisan grid:initialize --days=30`

### No slots found?
- Check branch is open (`is_open = true`)
- Check branch opening hours
- Check room-treatment assignments exist
- Check staff-treatment assignments exist
- Check staff availability JSON

### Gender mismatch errors?
- Ensure patient gender matches room gender constraint
- Male patients → Only Male or Unisex rooms
- Female patients → Only Female or Unisex rooms
