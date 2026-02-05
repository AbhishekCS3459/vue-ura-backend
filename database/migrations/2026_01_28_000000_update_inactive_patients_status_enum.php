<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if we're using PostgreSQL
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            // For PostgreSQL, drop and recreate the constraint
            DB::statement("ALTER TABLE inactive_patients DROP CONSTRAINT IF EXISTS inactive_patients_status_check");
            DB::statement("ALTER TABLE inactive_patients ALTER COLUMN status TYPE VARCHAR(50)");
            DB::statement("ALTER TABLE inactive_patients ADD CONSTRAINT inactive_patients_status_check CHECK (status IN ('Follow-up', 'Did not reply', 'Did not pick up', 'Next', 'Ask for callback'))");
            DB::statement("ALTER TABLE inactive_patients ALTER COLUMN status SET DEFAULT 'Follow-up'");
        } else {
            // For MySQL
            DB::statement("ALTER TABLE inactive_patients MODIFY COLUMN status ENUM('Follow-up', 'Did not reply', 'Did not pick up', 'Next', 'Ask for callback') DEFAULT 'Follow-up'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            // Revert to old enum values for PostgreSQL
            DB::statement("ALTER TABLE inactive_patients DROP CONSTRAINT IF EXISTS inactive_patients_status_check");
            DB::statement("ALTER TABLE inactive_patients ADD CONSTRAINT inactive_patients_status_check CHECK (status IN ('No action', 'Message sent', 'No response', 'Call scheduled', 'Asked to call back'))");
            DB::statement("ALTER TABLE inactive_patients ALTER COLUMN status SET DEFAULT 'No action'");
        } else {
            // For MySQL
            DB::statement("ALTER TABLE inactive_patients MODIFY COLUMN status ENUM('No action', 'Message sent', 'No response', 'Call scheduled', 'Asked to call back') DEFAULT 'No action'");
        }
    }
};
