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
        Schema::create('therapy_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('patient_name');
            $table->string('patient_id');
            $table->string('phone')->nullable();
            $table->string('therapy_type');
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['Planned', 'Completed', 'No-show', 'Conflict'])->default('Planned');
            $table->enum('whatsapp_status', ['Confirmed', 'No response', 'Cancelled'])->default('No response');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapy_sessions');
    }
};
