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
        Schema::table('inactive_patients', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('patient_id')->nullable()->after('branch_id')->constrained('patients')->onDelete('cascade');
            
            // Add indexes for performance
            $table->index('branch_id', 'idx_inactive_patients_branch');
            $table->index('patient_id', 'idx_inactive_patients_patient');
            $table->index('last_session_date', 'idx_inactive_patients_last_session');
            $table->index(['branch_id', 'status'], 'idx_inactive_patients_branch_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inactive_patients', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['patient_id']);
            $table->dropIndex('idx_inactive_patients_branch');
            $table->dropIndex('idx_inactive_patients_patient');
            $table->dropIndex('idx_inactive_patients_last_session');
            $table->dropIndex('idx_inactive_patients_branch_status');
            $table->dropColumn(['branch_id', 'patient_id']);
        });
    }
};
