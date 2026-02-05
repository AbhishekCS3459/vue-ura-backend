<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Materialized availability grid (Sheet2 approach)
     * Stores room availability status for each time slot
     */
    public function up(): void
    {
        Schema::create('room_availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('room_id')->constrained('branch_rooms')->onDelete('cascade');
            $table->date('date');
            $table->time('time_slot')->comment('30-minute intervals: 00:00, 00:30, 01:00, ...');
            $table->enum('status', ['Available', 'Booked', 'Unavailable'])->default('Available');
            $table->foreignId('booking_id')->nullable()->constrained('therapy_sessions')->onDelete('set null');
            $table->timestamps();

            // Ensure unique slot per room per time
            $table->unique(['room_id', 'date', 'time_slot'], 'unique_room_slot');

            // Indexes for fast lookups (critical for performance)
            $table->index(['branch_id', 'date', 'time_slot'], 'idx_branch_date_time');
            $table->index(['room_id', 'date', 'time_slot'], 'idx_room_date_time');
            $table->index('status', 'idx_status');
            $table->index(['branch_id', 'date', 'time_slot', 'status'], 'idx_available_slots');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_availability_slots');
    }
};
