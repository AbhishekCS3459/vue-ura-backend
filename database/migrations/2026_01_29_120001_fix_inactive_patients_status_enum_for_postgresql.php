<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PostgreSQL doesn't support MySQL-style ENUM, so we use VARCHAR with CHECK constraint
     */
    public function up(): void
    {
        // Check if we're using PostgreSQL
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            // Drop the existing enum constraint if it exists
            DB::statement("ALTER TABLE inactive_patients DROP CONSTRAINT IF EXISTS inactive_patients_status_check");
            DB::statement("ALTER TABLE inactive_patients DROP CONSTRAINT IF EXISTS inactive_patients_last_action_check");
            
            // Change enum columns to varchar
            DB::statement("ALTER TABLE inactive_patients ALTER COLUMN status TYPE VARCHAR(50)");
            DB::statement("ALTER TABLE inactive_patients ALTER COLUMN last_action TYPE VARCHAR(50)");
            
            // Add CHECK constraints for valid values
            DB::statement("ALTER TABLE inactive_patients ADD CONSTRAINT inactive_patients_status_check CHECK (status IN ('Follow-up', 'Did not reply', 'Did not pick up', 'Next', 'Ask for callback'))");
            DB::statement("ALTER TABLE inactive_patients ALTER COLUMN status SET DEFAULT 'Follow-up'");
            
            DB::statement("ALTER TABLE inactive_patients ADD CONSTRAINT inactive_patients_last_action_check CHECK (last_action IN ('None', 'Message sent', 'Follow up call made'))");
            DB::statement("ALTER TABLE inactive_patients ALTER COLUMN last_action SET DEFAULT 'None'");
        } else {
            // For MySQL/MariaDB
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
            // Drop CHECK constraints
            DB::statement("ALTER TABLE inactive_patients DROP CONSTRAINT IF EXISTS inactive_patients_status_check");
            DB::statement("ALTER TABLE inactive_patients DROP CONSTRAINT IF EXISTS inactive_patients_last_action_check");
            
            // Revert to old values
            DB::statement("ALTER TABLE inactive_patients ADD CONSTRAINT inactive_patients_status_check CHECK (status IN ('No action', 'Message sent', 'No response', 'Call scheduled', 'Asked to call back'))");
            DB::statement("ALTER TABLE inactive_patients ALTER COLUMN status SET DEFAULT 'No action'");
            
            DB::statement("ALTER TABLE inactive_patients ADD CONSTRAINT inactive_patients_last_action_check CHECK (last_action IN ('None', 'Message sent', 'Follow up call made'))");
        } else {
            // For MySQL/MariaDB
            DB::statement("ALTER TABLE inactive_patients MODIFY COLUMN status ENUM('No action', 'Message sent', 'No response', 'Call scheduled', 'Asked to call back') DEFAULT 'No action'");
        }
    }
};
