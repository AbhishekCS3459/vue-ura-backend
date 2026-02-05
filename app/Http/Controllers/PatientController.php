<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class PatientController extends Controller
{
    /**
     * Get all patients with pagination
     * GET /api/patients
     */
    public function index(Request $request): JsonResponse
    {
        $query = Patient::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('patient_id', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $patients = $query->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => $patients->currentPage(),
                'per_page' => $patients->perPage(),
                'total' => $patients->total(),
                'data' => $patients->map(fn ($patient) => $this->formatPatient($patient)),
            ],
        ]);
    }

    /**
     * Get patient by ID
     * GET /api/patients/{id}
     */
    public function show(int $id): JsonResponse
    {
        $patient = Patient::find($id);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatPatient($patient),
        ]);
    }

    /**
     * Create or update patient (sync from EMR)
     * POST /api/patients
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'string', 'unique:patients,patient_id'],
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:Male,Female'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'emr_system_id' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if patient already exists by patient_id (EMR ID)
        $patient = Patient::where('patient_id', $request->patient_id)->first();

        if ($patient) {
            // Update existing patient
            $patient->update($validator->validated());
            $message = 'Patient updated successfully';
        } else {
            // Create new patient
            $patient = Patient::create($validator->validated());
            $message = 'Patient created successfully';
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatPatient($patient),
            'message' => $message,
        ], $patient->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Update patient
     * PUT /api/patients/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $patient = Patient::find($id);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'gender' => ['sometimes', 'required', 'string', 'in:Male,Female'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $patient->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $this->formatPatient($patient->fresh()),
            'message' => 'Patient updated successfully',
        ]);
    }

    /**
     * Search patients
     * GET /api/patients/search
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => ['required', 'string', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = $validator->validated()['q'];
        $patients = Patient::where('name', 'ilike', "%{$query}%")
            ->orWhere('phone', 'ilike', "%{$query}%")
            ->orWhere('patient_id', 'ilike', "%{$query}%")
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $patients->map(fn ($patient) => $this->formatPatient($patient)),
        ]);
    }

    /**
     * Format patient for JSON response
     */
    private function formatPatient(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'patient_id' => $patient->patient_id,
            'name' => $patient->name,
            'gender' => $patient->gender,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'date_of_birth' => $patient->date_of_birth?->format('Y-m-d'),
            'address' => $patient->address,
            'emr_system_id' => $patient->emr_system_id,
        ];
    }
}
