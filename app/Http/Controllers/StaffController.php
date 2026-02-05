<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Staff;
use App\Models\Treatment;
use App\Models\TherapySession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

final class StaffController extends Controller
{
    /**
     * Get all staff for a branch
     * GET /api/branches/{branchId}/staff
     */
    public function index(int $branchId): JsonResponse
    {
        $branch = Branch::find($branchId);
        
        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found',
            ], 404);
        }

        $staff = Staff::where('branch_id', $branchId)
            ->with('treatments')
            ->get();

        // Get date range for booked dates (next 3 months)
        $today = Carbon::today();
        $endDate = $today->copy()->addMonths(3);

        return response()->json([
            'success' => true,
            'data' => $staff->map(function ($staffMember) use ($today, $endDate) {
                // Get booked sessions for this staff member (exclude cancelled whatsapp status)
                $bookedSessions = TherapySession::where('staff_id', $staffMember->id)
                    ->whereDate('date', '>=', $today)
                    ->whereDate('date', '<=', $endDate)
                    ->where('whatsapp_status', '!=', 'Cancelled')
                    ->select('date', 'start_time', 'end_time')
                    ->get();
                
                // Group by date and collect time information
                $bookedDatesMap = [];
                foreach ($bookedSessions as $session) {
                    $date = $session->date;
                    if ($date instanceof Carbon) {
                        $dateStr = $date->format('Y-m-d');
                        $dayName = $date->format('l'); // Full day name (Monday, Tuesday, etc.)
                    } else {
                        try {
                            $carbonDate = Carbon::parse($date);
                            $dateStr = $carbonDate->format('Y-m-d');
                            $dayName = $carbonDate->format('l');
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                    
                    if (!isset($bookedDatesMap[$dateStr])) {
                        $bookedDatesMap[$dateStr] = [
                            'date' => $dateStr,
                            'day' => $dayName,
                            'times' => [],
                        ];
                    }
                    
                    // Add time slot if not already present
                    $startTime = null;
                    $endTime = null;
                    
                    if ($session->start_time) {
                        if (is_string($session->start_time)) {
                            $startTime = $session->start_time;
                        } elseif (method_exists($session->start_time, 'format')) {
                            $startTime = $session->start_time->format('H:i:s');
                        }
                    }
                    
                    if ($session->end_time) {
                        if (is_string($session->end_time)) {
                            $endTime = $session->end_time;
                        } elseif (method_exists($session->end_time, 'format')) {
                            $endTime = $session->end_time->format('H:i:s');
                        }
                    }
                    
                    if ($startTime && $endTime) {
                        $timeSlot = $startTime . '-' . $endTime;
                        if (!in_array($timeSlot, $bookedDatesMap[$dateStr]['times'])) {
                            $bookedDatesMap[$dateStr]['times'][] = $timeSlot;
                        }
                    }
                }
                
                // Convert to array and also create simple date array for backward compatibility
                $bookedDates = array_values($bookedDatesMap);
                $bookedDateStrings = array_keys($bookedDatesMap);

                // Format availability with dates, days, and timings
                $availability = $staffMember->availability ?? [];
                $formattedAvailability = $this->formatAvailabilityForResponse($availability);
                
                // Extract date-specific availability with day and timings
                $availabilityDates = [];
                foreach ($availability as $key => $value) {
                    // Check if key is a date (YYYY-MM-DD format)
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $key)) {
                        try {
                            $date = Carbon::parse($key);
                            $dayName = $date->format('l'); // Full day name (Monday, Tuesday, etc.)
                            
                            $availabilityDates[] = [
                                'date' => $key,
                                'day' => $dayName,
                                'times' => is_array($value) ? $value : [],
                            ];
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }

                return [
                    'id' => $staffMember->id,
                    'name' => $staffMember->name,
                    'gender' => $staffMember->gender,
                    'role' => $staffMember->role,
                    'phone' => $staffMember->phone,
                    'branch_id' => $staffMember->branch_id,
                    'availability' => $formattedAvailability,
                    'availability_dates' => $availabilityDates, // Dates with day and timings
                    'booked_dates' => $bookedDateStrings, // Simple array of date strings for backward compatibility
                    'booked_dates_detail' => $bookedDates, // Detailed array with date, day, and times
                    'treatments' => $staffMember->treatments->map(function ($treatment) {
                        return [
                            'id' => $treatment->id,
                            'name' => $treatment->name,
                        ];
                    }),
                ];
            }),
        ]);
    }

    /**
     * Create a new staff member
     * POST /api/branches/{branchId}/staff
     */
    public function store(Request $request, int $branchId): JsonResponse
    {
        $branch = Branch::find($branchId);
        
        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:M,F'],
            'role' => ['required', 'string', 'in:Therapist,Nutritionist,Coach'],
            'phone' => ['nullable', 'string', 'max:255'],
            'availability' => ['nullable', 'array'],
            'treatment_ids' => ['nullable', 'array'],
            'treatment_ids.*' => ['integer', 'exists:treatments,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();
            
            // Get treatment names for session_types (legacy field)
            $treatmentIds = $data['treatment_ids'] ?? [];
            $treatments = Treatment::whereIn('id', $treatmentIds)->get();
            $sessionTypes = $treatments->pluck('name')->toArray();

            $staff = Staff::create([
                'name' => $data['name'],
                'gender' => $data['gender'],
                'role' => $data['role'],
                'phone' => $data['phone'] ?? '',
                'branch_id' => $branchId,
                'session_types' => $sessionTypes, // Legacy field
                'availability' => $data['availability'] ?? $this->getDefaultAvailability(),
            ]);

            // Attach treatments via many-to-many relationship
            if (!empty($treatmentIds)) {
                $staff->treatments()->attach($treatmentIds);
            }

            DB::commit();

            // Reload with relationships
            $staff->load('treatments');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'gender' => $staff->gender,
                    'role' => $staff->role,
                    'phone' => $staff->phone,
                    'branch_id' => $staff->branch_id,
                    'availability' => $staff->availability,
                    'treatments' => $staff->treatments->map(function ($treatment) {
                        return [
                            'id' => $treatment->id,
                            'name' => $treatment->name,
                        ];
                    }),
                ],
                'message' => 'Staff created successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create staff: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update staff availability
     * PUT /api/staff/{id}/availability
     * 
     * Expected format:
     * {
     *   "availability": {
     *     "2024-01-03": ["09:00", "09:30", "10:00", ...]  // Date as key, time slots as array
     *   }
     * }
     */
    public function updateAvailability(Request $request, int $id): JsonResponse
    {
        $staff = Staff::find($id);
        
        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'availability' => ['required', 'array'],
            'availability.*' => ['array'], // Each date key should have an array of time slots
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $newAvailability = $validator->validated()['availability'];
        
        // Get current availability or initialize with default day-based structure
        $currentAvailability = $staff->availability ?? $this->getDefaultAvailability();
        
        // Process each date in the new availability
        foreach ($newAvailability as $dateStr => $timeSlots) {
            // Validate date format (YYYY-MM-DD)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                continue; // Skip invalid date formats
            }
            
            try {
                $date = Carbon::parse($dateStr);
                $dayName = strtolower($date->format('l')); // monday, tuesday, etc.
                
                // Store date-specific availability (date as key with time slots)
                if (!empty($timeSlots) && is_array($timeSlots)) {
                    $currentAvailability[$dateStr] = $timeSlots;
                    // Also update day-based availability for recurring schedule
                    $currentAvailability[$dayName] = $timeSlots;
                } else {
                    // If time slots are empty, remove the date-specific availability
                    unset($currentAvailability[$dateStr]);
                }
            } catch (\Exception $e) {
                continue; // Skip invalid dates
            }
        }
        
        $staff->availability = $currentAvailability;
        $staff->save();

        $staff->load('treatments');

        // Format availability for response with dates, days, and timings
        $formattedAvailability = $this->formatAvailabilityForResponse($currentAvailability);
        
        // Extract date-specific availability with day and timings
        $availabilityDates = [];
        foreach ($currentAvailability as $key => $value) {
            // Check if key is a date (YYYY-MM-DD format)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $key)) {
                try {
                    $date = Carbon::parse($key);
                    $dayName = $date->format('l'); // Full day name (Monday, Tuesday, etc.)
                    
                    $availabilityDates[] = [
                        'date' => $key,
                        'day' => $dayName,
                        'times' => is_array($value) ? $value : [],
                    ];
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'gender' => $staff->gender,
                'role' => $staff->role,
                'phone' => $staff->phone,
                'branch_id' => $staff->branch_id,
                'availability' => $formattedAvailability,
                'availability_dates' => $availabilityDates, // Dates with day and timings
                'treatments' => $staff->treatments->map(function ($treatment) {
                    return [
                        'id' => $treatment->id,
                        'name' => $treatment->name,
                    ];
                }),
            ],
            'message' => 'Availability updated successfully',
        ]);
    }

    /**
     * Update working hours for a specific date
     * PUT /api/staff/{id}/working-hours
     */
    public function updateWorkingHours(Request $request, int $id): JsonResponse
    {
        $staff = Staff::find($id);
        
        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'date' => ['required', 'date'],
            'startTime' => ['required', 'string', 'regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/'],
            'endTime' => ['required', 'string', 'regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $date = Carbon::parse($data['date']);
        $dayName = strtolower($date->format('l')); // monday, tuesday, etc.
        $dateStr = $date->format('Y-m-d'); // YYYY-MM-DD format for date-specific availability

        // Generate time slots between startTime and endTime (30-minute intervals)
        $timeSlots = $this->generateTimeSlots($data['startTime'], $data['endTime']);

        // Get current availability or initialize
        $availability = $staff->availability ?? $this->getDefaultAvailability();
        
        // Update day-based availability (for recurring weekly schedule)
        $availability[$dayName] = $timeSlots;
        
        // Also save date-specific availability (for one-off date changes)
        $availability[$dateStr] = $timeSlots;

        $staff->availability = $availability;
        $staff->save();

        $staff->load('treatments');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'gender' => $staff->gender,
                'role' => $staff->role,
                'phone' => $staff->phone,
                'branch_id' => $staff->branch_id,
                'availability' => $staff->availability,
                'treatments' => $staff->treatments->map(function ($treatment) {
                    return [
                        'id' => $treatment->id,
                        'name' => $treatment->name,
                    ];
                }),
            ],
            'message' => 'Working hours updated successfully',
        ]);
    }

    /**
     * Generate time slots between start and end time (30-minute intervals)
     */
    private function generateTimeSlots(string $startTime, string $endTime): array
    {
        $slots = [];
        $start = new \DateTime($startTime);
        $end = new \DateTime($endTime);

        while ($start < $end) {
            $slots[] = $start->format('H:i');
            $start->modify('+30 minutes');
        }

        return $slots;
    }

    /**
     * Get default availability
     */
    private function getDefaultAvailability(): array
    {
        return [
            'monday' => [],
            'tuesday' => [],
            'wednesday' => [],
            'thursday' => [],
            'friday' => [],
            'saturday' => [],
            'sunday' => [],
        ];
    }

    /**
     * Update staff treatments (session types)
     * PUT /api/staff/{id}/treatments
     */
    public function updateTreatments(Request $request, int $id): JsonResponse
    {
        $staff = Staff::find($id);
        
        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'treatment_ids' => ['required', 'array'],
            'treatment_ids.*' => ['integer', 'exists:treatments,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $treatmentIds = $validator->validated()['treatment_ids'];
            
            // Sync treatments (this will detach old ones and attach new ones)
            $staff->treatments()->sync($treatmentIds);
            
            // Update legacy session_types field for backward compatibility
            $treatments = Treatment::whereIn('id', $treatmentIds)->get();
            $sessionTypes = $treatments->pluck('name')->toArray();
            $staff->session_types = $sessionTypes;
            $staff->save();

            DB::commit();

            // Reload with relationships
            $staff->load('treatments');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'gender' => $staff->gender,
                    'role' => $staff->role,
                    'phone' => $staff->phone,
                    'branch_id' => $staff->branch_id,
                    'treatments' => $staff->treatments->map(function ($treatment) {
                        return [
                            'id' => $treatment->id,
                            'name' => $treatment->name,
                        ];
                    }),
                ],
                'message' => 'Session types updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update session types: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format availability for response with dates, days, and timings
     * Returns availability in format: { "2024-01-03": ["09:00", "09:30", ...] }
     * Also includes date-specific entries with day information
     */
    private function formatAvailabilityForResponse(array $availability): array
    {
        $formatted = [];
        
        foreach ($availability as $key => $value) {
            // Check if key is a date (YYYY-MM-DD format)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $key)) {
                // This is a date-specific availability
                $date = Carbon::parse($key);
                $dayName = $date->format('l'); // Full day name (Monday, Tuesday, etc.)
                
                // Store with date as key and time slots as value
                $formatted[$key] = is_array($value) ? $value : [];
            } else {
                // This is a day-based availability (monday, tuesday, etc.)
                // Keep it for backward compatibility
                $formatted[$key] = is_array($value) ? $value : [];
            }
        }
        
        return $formatted;
    }
}
