# Staff Session Booking & Scheduling System - Design Document

## 📋 Table of Contents
- [System Overview](#system-overview)
- [Core Entities](#core-entities)
- [Business Rules & Constraints](#business-rules--constraints)
- [Booking Flow (End-to-End)](#booking-flow-end-to-end)
- [Scheduling Algorithm Decisions](#scheduling-algorithm-decisions)
- [Edge Cases & Considerations](#edge-cases--considerations)
- [Clarifications Needed](#clarifications-needed)

---

## System Overview

This document outlines the design for a **production-grade Staff Session Booking & Scheduling System** for a healthcare/clinic platform. The system handles real-time slot discovery, resource allocation (staff, rooms), and atomic booking creation while ensuring no double-booking and optimal resource utilization.

**Scale Requirements:**
- 10+ branches
- 100+ staff members
- 1000+ daily bookings
- Real-time slot availability queries
- Data consistency and performance

---

## Core Entities

### 1. **Branch**
- **Purpose**: Represents a physical clinic location
- **Attributes**:
  - `id`, `name`, `city`
  - `opening_hours` (JSON) - Operating hours per day
  - `is_open` (boolean) - Current operational status (needs to be added)
- **Relationships**:
  - Has many `rooms` (BranchRoom)
  - Has many `staff` (Staff)
  - Has many `bookings` (TherapySession)

### 2. **Room (BranchRoom)**
- **Purpose**: Physical treatment rooms within a branch
- **Attributes**:
  - `id`, `name`
  - `branch_id` (foreign key)
  - `gender` (enum: MALE | FEMALE | UNISEX) - Gender constraint
- **Relationships**:
  - Belongs to `branch`
  - Many-to-many with `treatments` via `room_treatment_assignments`
- **Constraints**:
  - One patient per room at a time
  - Must be compatible with patient gender

### 3. **Staff**
- **Purpose**: Doctors/Coaches/Therapists who provide treatments
- **Attributes**:
  - `id`, `name`, `gender`, `role`, `phone`
  - `branch_id` (foreign key)
  - `session_types` (JSON) - Array of treatment types they can perform
  - `availability` (JSON) - Weekly availability schedule (days + time slots)
- **Relationships**:
  - Belongs to `branch`
  - Has many `bookings` (TherapySession)
- **Constraints**:
  - Maximum 2 patients per hour
  - Must support the requested treatment type

### 4. **Treatment**
- **Purpose**: Types of treatments/services offered
- **Attributes**:
  - `id`, `name`
  - `gender` (enum: Unisex | Male | Female) - Gender preference
- **Relationships**:
  - Many-to-many with `rooms` via `room_treatment_assignments`
- **Constraints**:
  - Can be performed in multiple rooms
  - No treatment exceeds 1 hour duration

### 5. **Booking (TherapySession)**
- **Purpose**: Represents a scheduled patient session
- **Current Attributes**:
  - `id`, `patient_name`, `patient_id`, `phone`
  - `therapy_type` (treatment name)
  - `staff_id`, `branch_id`
  - `date`, `start_time`, `end_time`
  - `status` (enum: Planned | Completed | No-show | Conflict)
  - `whatsapp_status`, `notes`
- **Missing Attributes** (needs to be added):
  - `room_id` (foreign key) - Which room is assigned
  - `treatment_id` (foreign key) - Link to treatment entity
  - `patient_gender` - For room compatibility checks

### 6. **Patient** (Conceptual - needs clarification)
- **Current State**: Referenced by `patient_id` and `patient_name` in bookings
- **Questions**:
  - Should there be a separate `patients` table?
  - Where is patient gender stored?

### 7. **Room-Treatment Assignment**
- **Purpose**: Maps which treatments can be performed in which rooms
- **Attributes**:
  - `id`, `treatment_id`, `room_id`
  - `assigned` (boolean)

---

## Business Rules & Constraints

### Session Duration Rules
1. **Each session is exactly 1 hour**
   - Start time to end time = 60 minutes
   - Spans 2 consecutive 30-minute time slots

### Staff Capacity Rules
2. **A doctor/staff can treat maximum 2 patients per session (1 hour)**
   - ⚠️ **Clarification Needed**: The requirement mentions "30 minutes + 30 minutes cooldown" but also "2 patients per session"
   - **Current Interpretation**: Staff can handle 2 patients in a 1-hour window (each patient = 30 min treatment)
   - **Alternative Interpretation**: 1 patient per hour (1 hour session)

### Room Occupancy Rules
3. **One room can host only 1 patient at a time**
   - No overlapping bookings for the same room
   - Room must be free for the entire 1-hour session duration

### Treatment Duration Rules
4. **No treatment lasts more than 1 hour**
   - All treatments fit within the 1-hour session window

### Branch Rules
5. **Each branch has:**
   - Opening and closing time (stored in `opening_hours` JSON)
   - Open/closed status (needs `is_open` field)
   - Bookings can only be made during operating hours

### Gender Compatibility Rules
6. **Room Gender Constraints (CRITICAL - STRICTLY ENFORCED):**
   - `MALE` rooms → **ONLY** accept male patients
     - ❌ **NEVER** assign female patients to male-only rooms
   - `FEMALE` rooms → **ONLY** accept female patients
     - ❌ **NEVER** assign male patients to female-only rooms
   - `UNISEX` rooms → Accept any gender (Male or Female)
   
   **Enforcement Points:**
   - ✅ Room filtering in slot discovery algorithm
   - ✅ Validation in booking creation transaction
   - ✅ Database constraint validation (application layer)
   - ✅ Double-check before room assignment
   
   **Validation Logic:**
   ```
   IF room.gender = 'Male' AND patient_gender != 'Male':
     REJECT (Cannot assign non-male to male-only room)
   
   IF room.gender = 'Female' AND patient_gender != 'Female':
     REJECT (Cannot assign non-female to female-only room)
   
   IF room.gender = 'Unisex':
     ACCEPT (Any gender allowed)
   ```

### Treatment-Room Compatibility
7. **A treatment can be done in multiple rooms**
   - Must be explicitly assigned via `room_treatment_assignments` table
   - Example: Cryotherapy → Room 2, 3 (Unisex)
   - Example: Body Massage (Female) → Room 1, 5

### Staff-Treatment Compatibility
8. **Staff must support the treatment type**
   - Check if treatment name exists in staff's `session_types` JSON array

---

## Booking Flow (End-to-End)

### Step 1: User Input
User provides:
- **Branch** selection
- **Treatment** selection
- **Patient gender** (M/F)
- (Optional) Preferred date/time

### Step 2: System Validation
System checks:
- ✅ Branch exists
- ✅ Branch is currently open (`is_open = true`)
- ✅ Treatment exists
- ✅ Requested time is within branch operating hours (if time specified)

### Step 3: Resource Discovery

#### 3.1 Find Available Staff
Query staff who:
- Belong to the selected branch (`branch_id = X`)
- Support the treatment (`session_types` JSON contains treatment name)
- Are available at the requested time (check `availability` JSON structure)
  - Matches day of week
  - Matches time slot

#### 3.2 Find Compatible Rooms
Query rooms that:
- Belong to the selected branch (`branch_id = X`)
- Are gender-compatible with patient:
  - Room `gender = UNISEX` OR
  - Room `gender = patient_gender`
- Are assigned to the treatment (via `room_treatment_assignments`)

### Step 4: Slot Discovery Algorithm

**Algorithm Logic:**
```
1. Start from inital time to search
2. Scan forward in 30-minute increments
   - Time range: 6:00 AM to 8:00 PM (branch closing time)
3. For each candidate slot (e.g., 8:00, 8:30, 9:00, ...):
   a. Check branch is open at that time
   b. Check room availability:
      - Query existing bookings for candidate rooms
      - Ensure no overlapping booking for 1-hour duration
      - At least 1 room must be free
   c. Check staff capacity:
      - For each available staff, count existing bookings in that 1-hour window
      - Staff must have < 2 bookings in that hour
      - At least 1 staff must have capacity
   d. If both conditions met → Return this slot
4. If no slot found by closing time → Continue to next day
5. Return earliest available slot
```

**Time Slot Model:**
- Time slots are **30-minute intervals**: `0:00, 0:30, 1:00, 1:30, ...`
- A **1-hour session** spans 2 consecutive slots (e.g., 8:00-9:00 = slots 8:00 + 8:30)
- Staff capacity is checked for the **1-hour window** (count bookings in that hour)

### Step 5: Booking Confirmation (Atomic Transaction)

When user confirms the slot:
```
BEGIN TRANSACTION
  1. Lock the slot (prevent concurrent bookings)
  2. Re-verify availability (double-check)
  3. Create booking record:
     - patient_name, patient_id, patient_gender
     - treatment_id, staff_id, room_id, branch_id
     - date, start_time, end_time (1 hour duration)
     - status = 'Planned'
  4. Update staff capacity count (increment)
  5. Mark room as occupied (implicit via booking)
COMMIT TRANSACTION
```

**Return:**
- Booking confirmation with booking ID
- Assigned time slot
- Assigned staff
- Assigned room

---

## Scheduling Algorithm Decisions

### 1. Time Granularity
- **Slots**: 30-minute intervals (0:00, 0:30, 1:00, 1:30, ...)
- **Sessions**: 1 hour (spans 2 consecutive slots)
- **Staff Capacity**: Count bookings in the 1-hour window (must be < 2)

### 2. Slot Availability Check

#### Room Availability:
```sql
-- Check if room is free for 1-hour session starting at slot_time
SELECT COUNT(*) FROM therapy_sessions
WHERE room_id = X
  AND date = Y
  AND (
    (start_time <= slot_time AND end_time > slot_time) OR
    (start_time < slot_time + 1 hour AND end_time >= slot_time + 1 hour) OR
    (start_time >= slot_time AND end_time <= slot_time + 1 hour)
  )
  AND status != 'Cancelled'
-- Must return 0 for room to be available
```

#### Staff Capacity:
```sql
-- Count existing bookings for staff in 1-hour window
SELECT COUNT(*) FROM therapy_sessions
WHERE staff_id = X
  AND date = Y
  AND start_time >= slot_time
  AND start_time < slot_time + 1 hour
  AND status != 'Cancelled'
-- Must return < 2 for staff to have capacity
```

### 3. Conflict Resolution Strategy

**Scenario 1: Multiple Available Resources**
- If multiple rooms/staff available → Return **earliest slot**
- Algorithm prioritizes time efficiency

**Scenario 2: Concurrent Booking Requests**
- Use **database transactions with row-level locking**
- Implement **optimistic locking** or **pessimistic locking**
- If slot becomes unavailable → Automatically find next available slot

**Scenario 3: Race Condition Prevention**
- Use `SELECT FOR UPDATE` when checking availability
- Atomic booking creation within transaction
- Return error if slot no longer available (with next available slot)

### 4. Performance Optimizations

**Database Indexes:**
```sql
-- Fast slot availability queries
CREATE INDEX idx_bookings_branch_date_time 
  ON therapy_sessions(branch_id, date, start_time);

CREATE INDEX idx_bookings_staff_date_time 
  ON therapy_sessions(staff_id, date, start_time);

CREATE INDEX idx_bookings_room_date_time 
  ON therapy_sessions(room_id, date, start_time);

-- Fast resource discovery
CREATE INDEX idx_staff_branch_treatment 
  ON staff(branch_id) WHERE session_types @> '["treatment_name"]';

CREATE INDEX idx_rooms_branch_gender 
  ON branch_rooms(branch_id, gender);
```

**Caching Strategy:**
- Cache branch `opening_hours` (rarely changes)
- Cache staff `availability` patterns (changes infrequently)
- Cache treatment-room assignments (changes infrequently)
- **Do NOT cache** real-time slot availability (changes constantly)

**Query Optimization:**
- Use `EXISTS` instead of `COUNT(*)` when checking availability
- Limit slot scanning range (e.g., next 7 days max)
- Use materialized views for complex availability queries (if needed)

### 5. Slot Scanning Strategy

**Initial Approach (from CSV):**
- Mark all slots as "Unavailable" initially
- When booking requested, scan from current time forward
- Find first available slot meeting all constraints
- If exceeds closing time → Continue to next day

**Optimization:**
- Start scanning from `NOW()` or preferred time
- Stop at branch closing time for that day
- If no slot found → Start from next day's opening time
- Limit scan to next 30 days (configurable)

---

## Edge Cases & Considerations

### 1. Branch Closed Days
- **Handling**: Check `opening_hours` JSON for day-specific hours
- **Implementation**: Skip days where branch is closed
- **Example**: Branch closed on Sundays → Skip Sunday slots

### 2. Staff Unavailability
- **Handling**: Check staff `availability` JSON for day/time restrictions
- **Implementation**: Exclude staff from available pool if not available
- **Example**: Staff only available Mon-Fri 9 AM - 5 PM

### 3. Room Maintenance/Unavailability
- **Handling**: Need `is_available` or `maintenance_mode` flag on rooms
- **Implementation**: Exclude unavailable rooms from slot discovery
- **Future**: Add `room_unavailability` table for scheduled maintenance

### 4. Booking Cancellation
- **Handling**: Update booking `status = 'Cancelled'`
- **Implementation**: Free up the slot (room + staff capacity)
- **Consideration**: Allow re-booking of cancelled slots immediately

### 5. Same-Day vs Future Bookings
- **Handling**: Algorithm works for both scenarios
- **Implementation**: Start scanning from requested date/time
- **Business Rule**: May want to enforce minimum advance booking time

### 6. Timezone Handling
- **Handling**: Store all times in UTC or branch local timezone
- **Implementation**: Convert to branch timezone for display
- **Consideration**: Handle daylight saving time changes

### 7. Double-Booking Prevention
- **Handling**: Database constraints + transaction locking
- **Implementation**: 
  - Unique constraint on `(room_id, date, start_time)` (if 1 room = 1 booking)
  - Application-level check for staff capacity
  - Transaction isolation level: `READ COMMITTED` or `SERIALIZABLE`

### 8. Partial Availability
- **Scenario**: Staff has 1 booking in hour, room is free
- **Handling**: Slot is available (staff can take 1 more patient)
- **Implementation**: Check staff capacity < 2, not = 0

---

## Clarifications - CONFIRMED ✅

### ✅ Question 1: Staff Capacity Model
**Answer**: Staff can handle **2 patients per hour**
- Staff can change rooms between patients
- Patient 1: 30 min treatment + 30 min cooldown (managed by other staff, not tracked)
- Staff moves to another room for Patient 2: 30 min treatment
- **Implementation**: Check staff has < 2 bookings in the 1-hour window

---

### ✅ Question 2: Time Slot Granularity
**Answer**: 
- **Sessions are 1 hour** (confirmed)
- **Time slots are 30-minute intervals** (0:00, 0:30, 1:00, ...) for granularity
- Reason: Different branches may start at different times (7:30, 8:00, etc.)
- **Implementation**: Use 30-min slots, sessions span 2 consecutive slots

---

### ✅ Question 3: Room Assignment Strategy
**Answer**: **Immediately assign specific room at booking time**
- Algorithm assigns room and provides timing
- Example: If slots till 7:30 are unavailable, assign 8:00 slot and mark as booked
- **Implementation**: Assign `room_id` when creating booking

---

### ✅ Question 4: Patient Entity Storage
**Answer**: 
- **Separate `patients` table** (normalized)
- Will integrate with **client EMR software APIs** to fetch patient data
- Store **gender** (Male/Female) in patients table for room compatibility
- **Implementation**: Create `patients` table with `id`, `name`, `gender`, `phone`, `patient_id` (EMR ID), etc.

---

### ✅ Question 5: Branch Status Field
**Answer**: **Both approaches**
- Add `is_open` boolean field (for UI toggle/radio button)
- Keep `opening_hours` JSON (for time-based validation)
- **Implementation**: 
  - `is_open` = current operational status (can be toggled in UI)
  - `opening_hours` = day-wise opening/closing times for validation

---

### ✅ Question 6: Unavailability Handling
**Answer**: 
- "Unavailable" slots = "Booked" slots (user mistakenly wrote "booked" in CSV)
- Slots are **branch-specific** (a slot can be booked/unavailable in one branch but available in another)
- **Implementation**: 
  - Unavailability = existing bookings in `therapy_sessions` table
  - No separate unavailability table needed
  - Check branch-specific bookings when finding available slots

---

### ✅ Question 7: Treatment-Room Assignment
**Answer**: **Remove existing structure, rebuild for best use case**
- Remove `assigned` boolean field
- Use simple many-to-many relationship (existence of record = treatment can be done in room)
- **Implementation**: Clean up `room_treatment_assignments` table, keep only `treatment_id` and `room_id`

---

## Next Steps

Proceeding to Phase 2:

1. **Phase 2A: Database Schema Design**
   - Finalize table structures
   - Define relationships and foreign keys
   - Design indexes for performance
   - Propose any derived tables or materialized views

2. **Phase 2B: Scheduling Algorithm Design**
   - Step-by-step algorithm logic
   - Edge case handling
   - Conflict resolution implementation
   - Performance optimization strategies

3. **Phase 3: API Design**
   - Endpoints for branch setup
   - Room configuration endpoints
   - Staff availability management
   - Real-time slot discovery API
   - Booking creation API
   - Sample queries and request/response formats

---

## References

- Existing Models: `app/Models/`
- Existing Migrations: `database/migrations/`
- Initial Design Concept: `Draft - Sheet2.csv`, `Draft - Sheet3.csv`

---

**Document Version**: 2.0  
**Last Updated**: 2026-01-22  
**Status**: Clarifications Confirmed - Proceeding to Phase 2

---

# Phase 2: Database Schema & Algorithm Design

## 2A. Database Schema Design

### New Tables to Create

#### 1. `patients` Table
```sql
CREATE TABLE patients (
    id BIGSERIAL PRIMARY KEY,
    patient_id VARCHAR(255) UNIQUE NOT NULL, -- EMR system patient ID
    name VARCHAR(255) NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    date_of_birth DATE,
    address TEXT,
    emr_system_id VARCHAR(255), -- Reference to external EMR system
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_patient_id (patient_id),
    INDEX idx_gender (gender)
);
```

#### 2. Updated `branches` Table
```sql
ALTER TABLE branches 
ADD COLUMN is_open BOOLEAN DEFAULT true;

-- opening_hours JSON structure:
-- {
--   "monday": {"open": "06:00", "close": "20:00"},
--   "tuesday": {"open": "06:00", "close": "20:00"},
--   ...
--   "sunday": {"open": "06:00", "close": "20:00"} or null if closed
-- }
```

#### 3. Updated `therapy_sessions` Table (Bookings)
```sql
ALTER TABLE therapy_sessions
ADD COLUMN room_id BIGINT UNSIGNED,
ADD COLUMN treatment_id BIGINT UNSIGNED,
ADD COLUMN patient_id BIGINT UNSIGNED, -- Reference to patients table
MODIFY COLUMN patient_name VARCHAR(255) NULL, -- Keep for backward compatibility
MODIFY COLUMN patient_id VARCHAR(255) NULL, -- Keep EMR ID reference
MODIFY COLUMN therapy_type VARCHAR(255) NULL, -- Keep for backward compatibility

ADD FOREIGN KEY (room_id) REFERENCES branch_rooms(id) ON DELETE RESTRICT,
ADD FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE RESTRICT,
ADD FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT,

-- Ensure no double booking for same room at same time
ADD UNIQUE KEY unique_room_time (room_id, date, start_time),

-- Indexes for performance
ADD INDEX idx_booking_branch_date_time (branch_id, date, start_time),
ADD INDEX idx_booking_staff_date_time (staff_id, date, start_time),
ADD INDEX idx_booking_room_date_time (room_id, date, start_time),
ADD INDEX idx_booking_patient (patient_id),
ADD INDEX idx_booking_treatment (treatment_id);
```

#### 4. Updated `room_treatment_assignments` Table
```sql
-- Remove existing table and recreate
DROP TABLE IF EXISTS room_treatment_assignments;

CREATE TABLE room_treatment_assignments (
    id BIGSERIAL PRIMARY KEY,
    treatment_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES branch_rooms(id) ON DELETE CASCADE,
    
    -- Ensure unique assignment
    UNIQUE KEY unique_treatment_room (treatment_id, room_id),
    
    -- Indexes for fast lookups
    INDEX idx_treatment (treatment_id),
    INDEX idx_room (room_id)
);
```

#### 5. `staff_treatment_assignments` Table (New)
```sql
-- Many-to-many: Staff can perform multiple treatments
CREATE TABLE staff_treatment_assignments (
    id BIGSERIAL PRIMARY KEY,
    staff_id BIGINT UNSIGNED NOT NULL,
    treatment_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_staff_treatment (staff_id, treatment_id),
    
    INDEX idx_staff (staff_id),
    INDEX idx_treatment (treatment_id)
);
```

**Note**: This replaces the JSON `session_types` field in staff table. We can keep both for backward compatibility or migrate.

#### 6. `room_availability_slots` Table (Materialized Availability Grid - Sheet2 Approach)
```sql


CREATE TABLE room_availability_slots (
    id BIGSERIAL PRIMARY KEY,
    branch_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    time_slot TIME NOT NULL, -- 30-minute intervals: 00:00, 00:30, 01:00, ...
    status ENUM('Available', 'Booked', 'Unavailable') DEFAULT 'Available',
    booking_id BIGINT UNSIGNED NULL, -- Reference to therapy_sessions if booked
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES branch_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES therapy_sessions(id) ON DELETE SET NULL,
    
    -- Ensure unique slot per room per time
    UNIQUE KEY unique_room_slot (room_id, date, time_slot),
    
    -- Indexes for fast lookups (critical for performance)
    INDEX idx_branch_date_time (branch_id, date, time_slot),
    INDEX idx_room_date_time (room_id, date, time_slot),
    INDEX idx_status (status),
    INDEX idx_available_slots (branch_id, date, time_slot, status) WHERE status = 'Available'
);

-- Note: This table is maintained/updated when:
-- 1. Booking is created → Mark 2 consecutive slots as 'Booked' (1-hour session)
-- 2. Booking is cancelled → Mark slots as 'Available'
-- 3. Room maintenance → Mark slots as 'Unavailable'
-- 4. Branch hours → Mark slots outside hours as 'Unavailable'
```

**Benefits of Materialized Grid Approach:**
- ✅ Fast lookups (pre-computed availability)
- ✅ Simple grid-based search (like Sheet2 CSV)
- ✅ Easy to visualize and debug
- ✅ Can cache/query efficiently

**Maintenance Strategy:**

1. **Grid Initialization:**
   - Pre-populate grid for next 30 days for all rooms
   - Mark slots outside branch hours as 'Unavailable'
   - Mark slots during branch closed days as 'Unavailable'
   - Default status: 'Available'

2. **Grid Updates (Automatic):**
   - **On Booking Create**: Mark 2 consecutive slots as 'Booked'
   - **On Booking Cancel**: Mark 2 consecutive slots as 'Available'
   - **On Room Maintenance**: Mark slots as 'Unavailable' (future enhancement)
   - **On Branch Hours Change**: Update affected slots

3. **Grid Regeneration (If Needed):**
   - Can rebuild grid from `therapy_sessions` table
   - Useful for data consistency checks
   - Can run as scheduled job (nightly/weekly)

4. **Performance:**
   - Use `ON DUPLICATE KEY UPDATE` for upserts
   - Index on `(room_id, date, time_slot)` for fast lookups
   - Partial index on `status = 'Available'` for availability queries

### Updated Table Relationships

```
Branch (1) ──→ (N) BranchRoom
Branch (1) ──→ (N) Staff
Branch (1) ──→ (N) TherapySession

Patient (1) ──→ (N) TherapySession
Staff (1) ──→ (N) TherapySession
Room (1) ──→ (N) TherapySession
Treatment (1) ──→ (N) TherapySession

Treatment (N) ←──→ (N) BranchRoom [via room_treatment_assignments]
Staff (N) ←──→ (N) Treatment [via staff_treatment_assignments]
```

### Key Indexes for Performance

```sql
-- Fast slot availability queries
CREATE INDEX idx_bookings_branch_date_time 
  ON therapy_sessions(branch_id, date, start_time) 
  WHERE status != 'Cancelled';

CREATE INDEX idx_bookings_staff_date_time 
  ON therapy_sessions(staff_id, date, start_time) 
  WHERE status != 'Cancelled';

CREATE INDEX idx_bookings_room_date_time 
  ON therapy_sessions(room_id, date, start_time) 
  WHERE status != 'Cancelled';

-- Fast resource discovery
CREATE INDEX idx_rooms_branch_gender 
  ON branch_rooms(branch_id, gender);

CREATE INDEX idx_staff_branch 
  ON staff(branch_id);
```

---

## 2B. Scheduling Algorithm Design

### Approach Validation: Materialized Grid (Sheet2) + Room Lookup (Sheet3)

**Your Proposed Approach:**
1. **Sheet3 Logic**: Query `room_treatment_assignments` + `branch_rooms` to get compatible `room_ids` based on:
   - Gender compatibility (Male/Female/Unisex)
   - Treatment compatibility
2. **Sheet2 Logic**: Query `room_availability_slots` grid table to find available time slots for those `room_ids`
3. **Staff Capacity**: Check separately (real-time query, not in grid)

**✅ Validation: APPROVED with Enhancements**

**Benefits:**
- ✅ Fast lookups (pre-computed availability grid)
- ✅ Simple grid-based search (easy to understand and debug)
- ✅ Efficient querying (indexed by room_id, date, time_slot)
- ✅ Scalable (can cache/optimize grid queries)
- ✅ Matches your CSV structure (Sheet2 = grid, Sheet3 = room lookup)

**Enhancements Added:**
1. ✅ Created `room_availability_slots` table (materialized grid)
2. ✅ Grid maintenance on booking create/cancel
3. ✅ Combined with staff capacity checks (separate query)
4. ✅ Gender constraint validation at multiple levels
5. ✅ Handles 1-hour sessions (2 consecutive 30-min slots)

**Hybrid Approach:**
- **Room Availability**: Materialized grid (fast, pre-computed)
- **Staff Capacity**: Real-time query (dynamic, changes frequently)
- **Gender Compatibility**: Validated at room lookup (Sheet3) + safety checks

---

### Algorithm: Find Next Available Slot

#### Input Parameters
- `branch_id` (required)
- `treatment_id` (required)
- `patient_gender` (required: 'Male' | 'Female')
- `preferred_date` (optional: YYYY-MM-DD, defaults to today)
- `preferred_time` (optional: HH:MM, defaults to current time)

#### Output
- `available_slot` object:
  - `date` (YYYY-MM-DD)
  - `start_time` (HH:MM)
  - `end_time` (HH:MM) = start_time + 1 hour
  - `staff_id` (assigned staff)
  - `room_id` (assigned room)
  - `staff_name`
  - `room_name`

#### Algorithm Steps (Hybrid Approach: Materialized Grid + Real-Time Queries)

```
STEP 1: Validate Input
  - Check branch exists and is_open = true
  - Check treatment exists
  - Validate patient_gender

STEP 2: Find Compatible Room IDs (Sheet3 Logic - Room Lookup)
  -- Query: Get room_ids that match gender + treatment criteria
  -- This is like querying Sheet3.csv to get compatible rooms
  
  2.1 Query Compatible Rooms:
      SELECT br.id as room_id, br.name, br.gender
      FROM branch_rooms br
      INNER JOIN room_treatment_assignments rta ON br.id = rta.room_id
      WHERE br.branch_id = :branch_id
        AND rta.treatment_id = :treatment_id
        AND (br.gender = 'Unisex' OR br.gender = :patient_gender)
      -- Returns: List of compatible room_ids
      -- Example: For Male patient + Laser treatment → Returns room_ids [1, 3, 5]
  
  compatible_room_ids = [result from query above]
  
  IF compatible_room_ids is EMPTY:
    RETURN ERROR: "No compatible rooms found for this treatment and gender"

STEP 3: Find Available Staff
  3.1 Query Available Staff:
      - Staff where branch_id = X
      - Staff where treatment_id IN (SELECT treatment_id FROM staff_treatment_assignments WHERE staff_id = staff.id)
      - Staff where availability JSON matches day of week
  
  compatible_staff = [result from query above]
  
  IF compatible_staff is EMPTY:
    RETURN ERROR: "No available staff found for this treatment"

STEP 4: Get Branch Operating Hours
  - Parse opening_hours JSON for current/preferred day
  - Extract open_time and close_time
  - If branch closed on that day, move to next day

STEP 5: Scan Availability Grid (Sheet2 Logic - Time Slot Search)
  -- Query room_availability_slots table for compatible rooms
  -- This is like checking Sheet2.csv grid for available timings
  
  current_date = preferred_date OR today
  initial_time = preferred_time OR NOW() OR branch opening_time
  
  WHILE (current_date < today + 30 days): // Limit to 30 days ahead
    day_of_week = get_day_of_week(current_date)
    opening_hours = get_opening_hours(branch, day_of_week)
    
    IF opening_hours is NULL:
      current_date = current_date + 1 day
      initial_time = opening_hours.open_time
      CONTINUE
    
    start_slot_time = MAX(initial_time, opening_hours.open_time)
    
    // Query availability grid for compatible rooms
    // Check for 1-hour session (2 consecutive 30-min slots must be available)
    WHILE (start_slot_time < opening_hours.close_time):
      end_slot_time = start_slot_time + 1 hour
      
      IF end_slot_time > opening_hours.close_time:
        BREAK // Cannot fit 1-hour session
      
      // Query Sheet2-like grid: Check if room slots are available
      available_rooms = []
      
      FOR EACH room_id IN compatible_room_ids:
        // Check if 2 consecutive slots are available (1-hour session)
        slot1_status = SELECT status FROM room_availability_slots
          WHERE room_id = room_id
            AND date = current_date
            AND time_slot = start_slot_time
            LIMIT 1
        
        slot2_status = SELECT status FROM room_availability_slots
          WHERE room_id = room_id
            AND date = current_date
            AND time_slot = start_slot_time + 30 minutes
            LIMIT 1
        
        // If both slots are available, room is free for 1-hour session
        IF (slot1_status = 'Available' AND slot2_status = 'Available'):
          available_rooms.append({
            room_id: room_id,
            room_name: get_room_name(room_id),
            room_gender: get_room_gender(room_id)
          })
      
      // Check staff capacity (separate from grid, real-time query)
      available_staff = []
      FOR EACH staff IN compatible_staff:
        // Verify staff availability at this time
        staff_available = check_staff_availability(staff, current_date, start_slot_time)
        IF NOT staff_available:
          CONTINUE
        
        // Check staff capacity (max 2 patients per hour)
        bookings_in_hour = COUNT(
          SELECT * FROM therapy_sessions
          WHERE staff_id = staff.id
            AND date = current_date
            AND start_time >= start_slot_time
            AND start_time < end_slot_time
            AND status != 'Cancelled'
        )
        
        IF bookings_in_hour < 2:
          available_staff.append(staff)
      
      // If both room and staff available, return slot
      IF available_rooms.length > 0 AND available_staff.length > 0:
        selected_room = available_rooms[0]
        selected_staff = available_staff[0]
        
        // DOUBLE-CHECK: Verify gender compatibility (safety check)
        IF (selected_room.room_gender != 'Unisex' AND selected_room.room_gender != patient_gender):
          // Should never happen, but safety check
          SKIP this room, try next available room
          CONTINUE
        
        RETURN {
          date: current_date,
          start_time: start_slot_time,
          end_time: end_slot_time,
          staff_id: selected_staff.id,
          room_id: selected_room.room_id,
          staff_name: selected_staff.name,
          room_name: selected_room.room_name
        }
      
      // Move to next 30-minute slot
      start_slot_time = start_slot_time + 30 minutes
    
    // Move to next day
    current_date = current_date + 1 day
    initial_time = opening_hours.open_time
  
  // No slot found in 30 days
  RETURN ERROR: "No available slots found in next 30 days"
```

**Algorithm Summary:**
1. **Step 2**: Query Sheet3 logic → Get compatible room_ids (gender + treatment)
2. **Step 5**: Query Sheet2 logic → Check availability grid for those room_ids
3. **Step 5**: Check staff capacity separately (real-time query)
4. Return first available slot where both room and staff are available

### Atomic Booking Creation

```sql
BEGIN TRANSACTION;

  -- Lock resources to prevent concurrent bookings
  SELECT * FROM branch_rooms WHERE id = :room_id FOR UPDATE;
  SELECT * FROM staff WHERE id = :staff_id FOR UPDATE;
  
  -- CRITICAL: Verify gender compatibility before booking
  room_gender = SELECT gender FROM branch_rooms WHERE id = :room_id;
  IF (room_gender != 'Unisex' AND room_gender != :patient_gender):
    ROLLBACK;
    RETURN ERROR: "Gender mismatch: Patient gender does not match room gender constraint"
  
  -- Re-verify availability (double-check)
  room_bookings = COUNT(
    SELECT * FROM therapy_sessions
    WHERE room_id = :room_id
      AND date = :date
      AND start_time = :start_time
      AND status != 'Cancelled'
  );
  
  IF room_bookings > 0:
    ROLLBACK;
    RETURN ERROR: "Room no longer available"
  
  staff_bookings = COUNT(
    SELECT * FROM therapy_sessions
    WHERE staff_id = :staff_id
      AND date = :date
      AND start_time >= :start_time
      AND start_time < :start_time + 1 hour
      AND status != 'Cancelled'
  );
  
  IF staff_bookings >= 2:
    ROLLBACK;
    RETURN ERROR: "Staff at full capacity"
  
  -- Create booking
  INSERT INTO therapy_sessions (
    patient_id, patient_name, phone,
    treatment_id, staff_id, room_id, branch_id,
    date, start_time, end_time,
    status, whatsapp_status
  ) VALUES (
    :patient_id, :patient_name, :phone,
    :treatment_id, :staff_id, :room_id, :branch_id,
    :date, :start_time, :end_time,
    'Planned', 'No response'
  );
  
  booking_id = LAST_INSERT_ID();
  
  -- UPDATE AVAILABILITY GRID (Sheet2 maintenance)
  -- Mark 2 consecutive slots as 'Booked' (1-hour session = 2 x 30-min slots)
  slot1_time = :start_time
  slot2_time = :start_time + 30 minutes
  
  -- Update or insert slot 1
  INSERT INTO room_availability_slots (branch_id, room_id, date, time_slot, status, booking_id)
  VALUES (:branch_id, :room_id, :date, slot1_time, 'Booked', booking_id)
  ON DUPLICATE KEY UPDATE status = 'Booked', booking_id = booking_id;
  
  -- Update or insert slot 2
  INSERT INTO room_availability_slots (branch_id, room_id, date, time_slot, status, booking_id)
  VALUES (:branch_id, :room_id, :date, slot2_time, 'Booked', booking_id)
  ON DUPLICATE KEY UPDATE status = 'Booked', booking_id = booking_id;

COMMIT;

RETURN booking_id;
```

### Edge Case Handling

#### 1. Concurrent Booking Requests
- Use `SELECT FOR UPDATE` to lock resources
- Re-verify availability before creating booking
- If slot taken, automatically find next available slot

#### 2. Branch Closed Days
- Skip days where `opening_hours[day]` is NULL
- Continue to next day automatically

#### 3. Staff Unavailability
- Check staff `availability` JSON for day/time restrictions
- Exclude unavailable staff from compatible staff list

#### 4. Room Maintenance (Future Enhancement)
- Add `is_available` boolean to `branch_rooms` table
- Exclude unavailable rooms from compatible rooms list

#### 5. Booking Cancellation
- Update `status = 'Cancelled'`
- Slot becomes available immediately for re-booking
- No need to explicitly "free" resources (handled by availability queries)

#### 6. Gender Constraint Validation (CRITICAL)
- **MUST** validate gender compatibility at multiple points:
  1. **Room Filtering**: Only include compatible rooms in search
  2. **Slot Discovery**: Double-check room gender before adding to available list
  3. **Booking Creation**: Verify gender match before transaction commit
  4. **API Validation**: Reject requests with gender mismatch
  
- **Validation Rules:**
  - Male patient + Female room = ❌ REJECT
  - Female patient + Male room = ❌ REJECT
  - Male patient + Male room = ✅ ACCEPT
  - Female patient + Female room = ✅ ACCEPT
  - Any patient + Unisex room = ✅ ACCEPT
  
- **Error Response:**
  ```json
  {
    "success": false,
    "message": "Gender mismatch: Patient gender does not match room gender constraint",
    "errors": {
      "room_id": ["Room is restricted to opposite gender"]
    }
  }
  ```

---

## Phase 3: API Design

### API Structure Overview

Following existing patterns:
- Clean Architecture (Application Services, Domain Entities, Infrastructure Repositories)
- Laravel Sanctum authentication middleware
- Standard JSON response format: `{ success: boolean, data: any, message?: string, errors?: any }`
- Validation using Laravel Validator
- Dependency injection for services

### API Endpoints

#### 1. Branch Management

##### `GET /api/branches`
Get all branches (existing, may need enhancement)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Downtown Branch",
      "city": "New York",
      "is_open": true,
      "opening_hours": {
        "monday": {"open": "06:00", "close": "20:00"},
        "tuesday": {"open": "06:00", "close": "20:00"},
        "wednesday": {"open": "06:00", "close": "20:00"},
        "thursday": {"open": "06:00", "close": "20:00"},
        "friday": {"open": "06:00", "close": "20:00"},
        "saturday": {"open": "08:00", "close": "18:00"},
        "sunday": null
      }
    }
  ]
}
```

##### `PUT /api/branches/{id}/status`
Update branch open/closed status

**Request:**
```json
{
  "is_open": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "is_open": true
  },
  "message": "Branch status updated"
}
```

##### `PUT /api/branches/{id}/opening-hours`
Update branch opening hours

**Request:**
```json
{
  "opening_hours": {
    "monday": {"open": "06:00", "close": "20:00"},
    "tuesday": {"open": "06:00", "close": "20:00"},
    "wednesday": {"open": "06:00", "close": "20:00"},
    "thursday": {"open": "06:00", "close": "20:00"},
    "friday": {"open": "06:00", "close": "20:00"},
    "saturday": {"open": "08:00", "close": "18:00"},
    "sunday": null
  }
}
```

---

#### 2. Room Configuration

##### `GET /api/branches/{branchId}/rooms`
Get all rooms for a branch

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Room 1",
      "branch_id": 1,
      "gender": "Male",
      "treatments": [
        {"id": 1, "name": "Cryotherapy"},
        {"id": 2, "name": "Laser"}
      ]
    }
  ]
}
```

##### `POST /api/branches/{branchId}/rooms`
Create a new room

**Request:**
```json
{
  "name": "Room 5",
  "gender": "Unisex"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 5,
    "name": "Room 5",
    "branch_id": 1,
    "gender": "Unisex"
  },
  "message": "Room created successfully"
}
```

##### `PUT /api/rooms/{id}`
Update room details

**Request:**
```json
{
  "name": "Room 5 - Updated",
  "gender": "Female"
}
```

##### `DELETE /api/rooms/{id}`
Delete a room (soft delete or hard delete based on business rules)

##### `POST /api/rooms/{roomId}/treatments`
Assign treatment to room

**Request:**
```json
{
  "treatment_id": 1
}
```

##### `DELETE /api/rooms/{roomId}/treatments/{treatmentId}`
Remove treatment assignment from room

---

#### 3. Treatment Management

##### `GET /api/treatments`
Get all treatments

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Cryotherapy",
      "gender": "Unisex",
      "rooms": [
        {"id": 2, "name": "Room 2"},
        {"id": 3, "name": "Room 3"}
      ]
    }
  ]
}
```

##### `POST /api/treatments`
Create a new treatment

**Request:**
```json
{
  "name": "Body Massage",
  "gender": "Unisex"
}
```

---

#### 4. Staff Management

##### `GET /api/branches/{branchId}/staff`
Get all staff for a branch

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Coach Riya",
      "gender": "M",
      "role": "Coach",
      "phone": "+1234567890",
      "branch_id": 1,
      "availability": {
        "monday": ["08:00", "09:00", "10:00", "14:00", "15:00"],
        "tuesday": ["08:00", "09:00", "10:00", "14:00", "15:00"],
        "wednesday": ["08:00", "09:00", "10:00", "14:00", "15:00"],
        "thursday": ["08:00", "09:00", "10:00", "14:00", "15:00"],
        "friday": ["08:00", "09:00", "10:00", "14:00", "15:00"],
        "saturday": [],
        "sunday": []
      },
      "treatments": [
        {"id": 1, "name": "Cryotherapy"},
        {"id": 2, "name": "Massage"}
      ]
    }
  ]
}
```

##### `POST /api/branches/{branchId}/staff`
Create a new staff member

**Request:**
```json
{
  "name": "Dr. Smith",
  "gender": "M",
  "role": "Therapist",
  "phone": "+1234567890",
  "availability": {
    "monday": ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00"],
    "tuesday": ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00"],
    "wednesday": ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00"],
    "thursday": ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00"],
    "friday": ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00"],
    "saturday": [],
    "sunday": []
  },
  "treatment_ids": [1, 2, 3]
}
```

##### `PUT /api/staff/{id}`
Update staff details

##### `PUT /api/staff/{id}/availability`
Update staff availability

**Request:**
```json
{
  "availability": {
    "monday": ["08:00", "09:00", "10:00"],
    "tuesday": ["08:00", "09:00", "10:00"]
  }
}
```

##### `POST /api/staff/{staffId}/treatments`
Assign treatment to staff

**Request:**
```json
{
  "treatment_id": 1
}
```

##### `DELETE /api/staff/{staffId}/treatments/{treatmentId}`
Remove treatment assignment from staff

---

#### 5. Patient Management

##### `GET /api/patients`
Get all patients (with pagination)

**Query Parameters:**
- `page` (default: 1)
- `per_page` (default: 15)
- `search` (optional: search by name, phone, patient_id)

**Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "data": [
      {
        "id": 1,
        "patient_id": "EMR-12345",
        "name": "John Doe",
        "gender": "Male",
        "phone": "+1234567890",
        "email": "john@example.com",
        "date_of_birth": "1990-01-01"
      }
    ]
  }
}
```

##### `GET /api/patients/{id}`
Get patient by ID

##### `POST /api/patients`
Create or sync patient from EMR

**Request:**
```json
{
  "patient_id": "EMR-12345",
  "name": "John Doe",
  "gender": "Male",
  "phone": "+1234567890",
  "email": "john@example.com",
  "date_of_birth": "1990-01-01",
  "emr_system_id": "external-emr-id"
}
```

**Note**: If `patient_id` exists, update; otherwise create new.

##### `PUT /api/patients/{id}`
Update patient details

##### `GET /api/patients/search`
Search patients (by name, phone, patient_id)

**Query Parameters:**
- `q` (required): search query

---

#### 6. Real-Time Slot Discovery ⭐

##### `POST /api/bookings/find-available-slot`
Find next available slot for booking

**Request:**
```json
{
  "branch_id": 1,
  "treatment_id": 1,
  "patient_gender": "Male",
  "preferred_date": "2026-01-25",  // optional, defaults to today
  "preferred_time": "14:00"        // optional, defaults to current time
}
```

**Response (Success):**
```json
{
  "success": true,
  "data": {
    "date": "2026-01-25",
    "start_time": "14:00",
    "end_time": "15:00",
    "staff": {
      "id": 1,
      "name": "Coach Riya"
    },
    "room": {
      "id": 3,
      "name": "Room 3"
    },
    "treatment": {
      "id": 1,
      "name": "Cryotherapy"
    }
  }
}
```

**Response (No Slot Found):**
```json
{
  "success": false,
  "message": "No available slots found in the next 30 days",
  "data": null
}
```

**Response (Validation Error):**
```json
{
  "success": false,
  "errors": {
    "branch_id": ["The branch id field is required."],
    "treatment_id": ["The treatment id field is required."],
    "patient_gender": ["The patient gender must be Male or Female."]
  }
}
```

---

#### 7. Booking Management

##### `POST /api/bookings`
Create a new booking (atomic)

**Request:**
```json
{
  "patient_id": 1,              // or use patient_name + phone for new patients
  "branch_id": 1,
  "treatment_id": 1,
  "staff_id": 1,                // optional, will auto-assign if not provided
  "room_id": 3,                 // optional, will auto-assign if not provided
  "date": "2026-01-25",
  "start_time": "14:00",
  "end_time": "15:00",
  "phone": "+1234567890",       // required if patient_id not provided
  "notes": "First time patient"
}
```

**Response (Success):**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "patient": {
      "id": 1,
      "name": "John Doe",
      "gender": "Male"
    },
    "treatment": {
      "id": 1,
      "name": "Cryotherapy"
    },
    "staff": {
      "id": 1,
      "name": "Coach Riya"
    },
    "room": {
      "id": 3,
      "name": "Room 3"
    },
    "branch": {
      "id": 1,
      "name": "Downtown Branch"
    },
    "date": "2026-01-25",
    "start_time": "14:00",
    "end_time": "15:00",
    "status": "Planned",
    "whatsapp_status": "No response",
    "notes": "First time patient"
  },
  "message": "Booking created successfully"
}
```

**Response (Slot No Longer Available):**
```json
{
  "success": false,
  "message": "The selected slot is no longer available",
  "data": {
    "next_available_slot": {
      "date": "2026-01-25",
      "start_time": "15:00",
      "end_time": "16:00"
    }
  }
}
```

**Response (Gender Mismatch Error):**
```json
{
  "success": false,
  "message": "Gender mismatch: Patient gender does not match room gender constraint",
  "errors": {
    "room_id": ["Room is restricted to opposite gender. Male patients cannot be assigned to Female-only rooms and vice versa."]
  }
}
```

##### `GET /api/bookings`
Get all bookings (with filters)

**Query Parameters:**
- `branch_id` (optional)
- `staff_id` (optional)
- `room_id` (optional)
- `patient_id` (optional)
- `date` (optional: YYYY-MM-DD)
- `date_from` (optional)
- `date_to` (optional)
- `status` (optional: Planned, Completed, No-show, Conflict, Cancelled)
- `page` (default: 1)
- `per_page` (default: 15)

**Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "per_page": 15,
    "total": 50,
    "data": [
      {
        "id": 123,
        "patient": {
          "id": 1,
          "name": "John Doe",
          "gender": "Male"
        },
        "treatment": {
          "id": 1,
          "name": "Cryotherapy"
        },
        "staff": {
          "id": 1,
          "name": "Coach Riya"
        },
        "room": {
          "id": 3,
          "name": "Room 3"
        },
        "branch": {
          "id": 1,
          "name": "Downtown Branch"
        },
        "date": "2026-01-25",
        "start_time": "14:00",
        "end_time": "15:00",
        "status": "Planned",
        "whatsapp_status": "No response"
      }
    ]
  }
}
```

##### `GET /api/bookings/{id}`
Get booking by ID

##### `PUT /api/bookings/{id}`
Update booking (e.g., change time, status, notes)

**Request:**
```json
{
  "status": "Completed",
  "notes": "Patient arrived on time"
}
```

##### `PUT /api/bookings/{id}/cancel`
Cancel a booking

**Request:**
```json
{
  "reason": "Patient requested cancellation"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "status": "Cancelled"
  },
  "message": "Booking cancelled successfully"
}
```

##### `GET /api/bookings/calendar`
Get bookings in calendar format (for UI calendar view)

**Query Parameters:**
- `branch_id` (required)
- `date_from` (required: YYYY-MM-DD)
- `date_to` (required: YYYY-MM-DD)
- `room_id` (optional)
- `staff_id` (optional)

**Response:**
```json
{
  "success": true,
  "data": {
    "2026-01-25": [
      {
        "id": 123,
        "start_time": "08:00",
        "end_time": "09:00",
        "patient": {"name": "John Doe"},
        "treatment": {"name": "Cryotherapy"},
        "staff": {"name": "Coach Riya"},
        "room": {"name": "Room 3"}
      },
      {
        "id": 124,
        "start_time": "09:00",
        "end_time": "10:00",
        "patient": {"name": "Jane Smith"},
        "treatment": {"name": "Massage"},
        "staff": {"name": "Coach Riya"},
        "room": {"name": "Room 1"}
      }
    ],
    "2026-01-26": [...]
  }
}
```

---

### Sample Database Queries

#### Query 1: Find Compatible Staff
```sql
SELECT s.*
FROM staff s
INNER JOIN staff_treatment_assignments sta ON s.id = sta.staff_id
WHERE s.branch_id = :branch_id
  AND sta.treatment_id = :treatment_id
  AND JSON_EXTRACT(s.availability, CONCAT('$.', DAYNAME(:date))) IS NOT NULL
  AND JSON_CONTAINS(
    JSON_EXTRACT(s.availability, CONCAT('$.', DAYNAME(:date))),
    JSON_QUOTE(:time_slot)
  );
```

#### Query 2: Find Compatible Room IDs (Sheet3 Logic - Room Lookup)
```sql
-- STEP 2 of Algorithm: Get compatible room_ids based on gender + treatment
-- This is like querying Sheet3.csv to get room_ids
-- CRITICAL: This query ensures gender compatibility
-- Male patients: Only returns rooms with gender = 'Male' OR 'Unisex'
-- Female patients: Only returns rooms with gender = 'Female' OR 'Unisex'
-- This prevents:
--   - Male patients from being assigned to Female-only rooms
--   - Female patients from being assigned to Male-only rooms

SELECT br.id as room_id, br.name, br.gender
FROM branch_rooms br
INNER JOIN room_treatment_assignments rta ON br.id = rta.room_id
WHERE br.branch_id = :branch_id
  AND rta.treatment_id = :treatment_id
  -- Gender compatibility check (MANDATORY):
  AND (
    br.gender = 'Unisex' 
    OR br.gender = :patient_gender  -- Must match exactly: 'Male' or 'Female'
  );
  
-- Example scenarios:
-- patient_gender = 'Male', treatment_id = 1 (Laser)  
--   → Returns: room_ids [1, 3, 5] (rooms with gender = 'Male' OR 'Unisex' that support Laser)
-- patient_gender = 'Female', treatment_id = 2 (Massage)
--   → Returns: room_ids [2, 4] (rooms with gender = 'Female' OR 'Unisex' that support Massage)
-- This query will NEVER return incompatible rooms
```

#### Query 2B: Check Room Availability in Grid (Sheet2 Logic - Time Slot Search)
```sql
-- STEP 5 of Algorithm: Check availability grid for compatible rooms
-- This is like checking Sheet2.csv grid for available timings
-- Check if 2 consecutive slots are available (1-hour session = 2 x 30-min slots)

-- Check slot 1 (start_time)
SELECT status, booking_id
FROM room_availability_slots
WHERE room_id IN (:compatible_room_ids)  -- Room IDs from Query 2
  AND date = :date
  AND time_slot = :start_time
  AND status = 'Available'
LIMIT 1;

-- Check slot 2 (start_time + 30 minutes)
SELECT status, booking_id
FROM room_availability_slots
WHERE room_id IN (:compatible_room_ids)
  AND date = :date
  AND time_slot = DATE_ADD(:start_time, INTERVAL 30 MINUTE)
  AND status = 'Available'
LIMIT 1;

-- Combined query: Get rooms where BOTH slots are available
SELECT ras1.room_id, br.name as room_name, br.gender
FROM room_availability_slots ras1
INNER JOIN room_availability_slots ras2 
  ON ras1.room_id = ras2.room_id 
  AND ras1.date = ras2.date
INNER JOIN branch_rooms br ON ras1.room_id = br.id
WHERE ras1.room_id IN (:compatible_room_ids)
  AND ras1.date = :date
  AND ras1.time_slot = :start_time
  AND ras1.status = 'Available'
  AND ras2.time_slot = DATE_ADD(:start_time, INTERVAL 30 MINUTE)
  AND ras2.status = 'Available'
ORDER BY ras1.room_id
LIMIT 10;
```

#### Query 3: Update Availability Grid on Booking (Grid Maintenance)
```sql
-- When booking is created, update availability grid (Sheet2 maintenance)
-- Mark 2 consecutive slots as 'Booked' (1-hour session = 2 x 30-min slots)

-- Update slot 1
INSERT INTO room_availability_slots (branch_id, room_id, date, time_slot, status, booking_id)
VALUES (:branch_id, :room_id, :date, :start_time, 'Booked', :booking_id)
ON DUPLICATE KEY UPDATE 
  status = 'Booked', 
  booking_id = :booking_id,
  updated_at = CURRENT_TIMESTAMP;

-- Update slot 2 (start_time + 30 minutes)
INSERT INTO room_availability_slots (branch_id, room_id, date, time_slot, status, booking_id)
VALUES (:branch_id, :room_id, :date, DATE_ADD(:start_time, INTERVAL 30 MINUTE), 'Booked', :booking_id)
ON DUPLICATE KEY UPDATE 
  status = 'Booked', 
  booking_id = :booking_id,
  updated_at = CURRENT_TIMESTAMP;

-- When booking is cancelled, mark slots as 'Available'
UPDATE room_availability_slots
SET status = 'Available', booking_id = NULL, updated_at = CURRENT_TIMESTAMP
WHERE booking_id = :booking_id;
```
<｜tool▁calls▁begin｜><｜tool▁call▁begin｜>
read_file

#### Query 4: Check Staff Capacity
```sql
SELECT COUNT(*) as booking_count
FROM therapy_sessions
WHERE staff_id = :staff_id
  AND date = :date
  AND start_time >= :start_time
  AND start_time < :end_time
  AND status != 'Cancelled';
```

#### Query 5: Find Next Available Slot (Optimized)
```sql
-- This would be implemented in application layer with proper indexing
-- Pseudo-query structure:

WITH time_slots AS (
  SELECT 
    :start_date + INTERVAL (n * 30) MINUTE as slot_time
  FROM (
    SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION ... -- Generate 30-min slots
  ) numbers
  WHERE slot_time BETWEEN :start_date AND :end_date
),
available_rooms AS (
  SELECT br.id, br.name
  FROM branch_rooms br
  INNER JOIN room_treatment_assignments rta ON br.id = rta.room_id
  WHERE br.branch_id = :branch_id
    AND rta.treatment_id = :treatment_id
    AND (br.gender = 'Unisex' OR br.gender = :patient_gender)
),
available_staff AS (
  SELECT s.id, s.name
  FROM staff s
  INNER JOIN staff_treatment_assignments sta ON s.id = sta.staff_id
  WHERE s.branch_id = :branch_id
    AND sta.treatment_id = :treatment_id
)
-- Then in application: check each slot for room and staff availability
```

---

### Error Handling

All endpoints should handle:

1. **Validation Errors (422)**
```json
{
  "success": false,
  "errors": {
    "field_name": ["Error message"]
  }
}
```

2. **Authentication Errors (401)**
```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

3. **Authorization Errors (403)**
```json
{
  "success": false,
  "message": "Unauthorized to perform this action"
}
```

4. **Not Found Errors (404)**
```json
{
  "success": false,
  "message": "Resource not found"
}
```

5. **Conflict Errors (409)**
```json
{
  "success": false,
  "message": "Slot no longer available",
  "data": {
    "next_available_slot": {...}
  }
}
```

6. **Server Errors (500)**
```json
{
  "success": false,
  "message": "Internal server error"
}
```

---

### Authentication & Authorization

- All booking endpoints require `auth:sanctum` middleware
- Branch-specific data filtered by user's `branch_id` (except Super Admin)
- Role-based permissions:
  - **Super Admin**: Full access
  - **Branch Manager**: Manage own branch resources
  - **Staff**: View own bookings, limited management

---

**Document Version**: 3.0  
**Last Updated**: 2026-01-22  
**Status**: Complete - Ready for Implementation
