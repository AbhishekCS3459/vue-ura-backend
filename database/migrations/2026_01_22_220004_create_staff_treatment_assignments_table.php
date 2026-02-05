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
        Schema::create('staff_treatment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->foreignId('treatment_id')->constrained('treatments')->onDelete('cascade');
            $table->timestamps();

            // Ensure unique assignment (staff can only have one record per treatment)
            $table->unique(['staff_id', 'treatment_id'], 'unique_staff_treatment');

            // Indexes for fast lookups
            $table->index('staff_id', 'idx_staff');
            $table->index('treatment_id', 'idx_treatment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_treatment_assignments');
    }
};
