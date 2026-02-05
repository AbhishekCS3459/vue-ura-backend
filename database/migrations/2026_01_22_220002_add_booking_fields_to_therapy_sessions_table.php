<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            // Add new foreign key fields
            $table->foreignId('room_id')->nullable()->after('branch_id')->constrained('branch_rooms')->onDelete('restrict');
            $table->foreignId('treatment_id')->nullable()->after('room_id')->constrained('treatments')->onDelete('restrict');
            $table->foreignId('patient_id')->nullable()->after('treatment_id')->constrained('patients')->onDelete('restrict');
            
            // Make existing fields nullable for backward compatibility
            $table->string('patient_name')->nullable()->change();
            $table->string('emr_patient_id')->nullable()->change(); // Renamed from patient_id
            $table->string('therapy_type')->nullable()->change();
            
            // Add unique constraint to prevent double booking for same room at same time
            $table->unique(['room_id', 'date', 'start_time'], 'unique_room_time');
            
            // Add indexes for performance
            $table->index(['branch_id', 'date', 'start_time'], 'idx_booking_branch_date_time');
            $table->index(['staff_id', 'date', 'start_time'], 'idx_booking_staff_date_time');
            $table->index(['room_id', 'date', 'start_time'], 'idx_booking_room_date_time');
            $table->index('patient_id', 'idx_booking_patient');
            $table->index('treatment_id', 'idx_booking_treatment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('idx_booking_branch_date_time');
            $table->dropIndex('idx_booking_staff_date_time');
            $table->dropIndex('idx_booking_room_date_time');
            $table->dropIndex('idx_booking_patient');
            $table->dropIndex('idx_booking_treatment');
            
            // Drop unique constraint
            $table->dropUnique('unique_room_time');
            
            // Drop foreign keys
            $table->dropForeign(['room_id']);
            $table->dropForeign(['treatment_id']);
            $table->dropForeign(['patient_id']);
            
            // Drop columns
            $table->dropColumn(['room_id', 'treatment_id', 'patient_id']);
            
            // Revert nullable changes (optional, may cause issues if data exists)
            // $table->string('patient_name')->nullable(false)->change();
            // $table->string('patient_id')->nullable(false)->change();
            // $table->string('therapy_type')->nullable(false)->change();
        });
    }
};
