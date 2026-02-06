<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add 'Cancelled' status to therapy_sessions
     * In PostgreSQL, Laravel uses check constraints for enums
     * In MySQL/MariaDB, we use MODIFY COLUMN ENUM
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // For PostgreSQL, drop the existing check constraint and recreate with new value
            DB::statement("
                ALTER TABLE therapy_sessions
                DROP CONSTRAINT IF EXISTS therapy_sessions_status_check
            ");

            DB::statement("
                ALTER TABLE therapy_sessions
                ADD CONSTRAINT therapy_sessions_status_check
                CHECK (status IN ('Planned', 'Completed', 'No-show', 'Conflict', 'Cancelled'))
            ");
        } else {
            // For MySQL/MariaDB
            DB::statement("
                ALTER TABLE therapy_sessions
                MODIFY COLUMN status ENUM('Planned', 'Completed', 'No-show', 'Conflict', 'Cancelled') DEFAULT 'Planned'
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Revert to original enum values for PostgreSQL
            DB::statement("
                ALTER TABLE therapy_sessions
                DROP CONSTRAINT IF EXISTS therapy_sessions_status_check
            ");

            DB::statement("
                ALTER TABLE therapy_sessions
                ADD CONSTRAINT therapy_sessions_status_check
                CHECK (status IN ('Planned', 'Completed', 'No-show', 'Conflict'))
            ");
        } else {
            // For MySQL/MariaDB
            DB::statement("
                ALTER TABLE therapy_sessions
                MODIFY COLUMN status ENUM('Planned', 'Completed', 'No-show', 'Conflict') DEFAULT 'Planned'
            ");
        }
    }
};
