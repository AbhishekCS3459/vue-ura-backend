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
     * We need to drop and recreate the constraint
     */
    public function up(): void
    {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("
            ALTER TABLE therapy_sessions 
            DROP CONSTRAINT IF EXISTS therapy_sessions_status_check
        ");
        
        DB::statement("
            ALTER TABLE therapy_sessions 
            ADD CONSTRAINT therapy_sessions_status_check 
            CHECK (status IN ('Planned', 'Completed', 'No-show', 'Conflict'))
        ");
    }
};
