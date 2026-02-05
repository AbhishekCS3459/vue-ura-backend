# Seed Treatments

To populate the treatments table with the default treatments, run:

```bash
cd vue-ura-backend
php artisan db:seed --class=TreatmentSeeder
```

Or if you want to seed all seeders (including treatments):

```bash
php artisan db:seed
```

## Treatments that will be seeded:

1. Cryotherapy
2. Body Massage
3. Diet Consultation
4. Laser
5. Cupping Therapy
6. Wellness Assessment

## Verify

After seeding, you can verify by calling the API:

```bash
curl "http://127.0.0.1:8000/api/treatments" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

You should see all 6 treatments in the response.
