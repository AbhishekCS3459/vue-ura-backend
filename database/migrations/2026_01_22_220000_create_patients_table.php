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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_id')->unique()->comment('EMR system patient ID');
            $table->string('name');
            $table->enum('gender', ['Male', 'Female'])->comment('Patient gender for room compatibility');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('emr_system_id')->nullable()->comment('Reference to external EMR system');
            $table->timestamps();

            // Indexes for performance
            $table->index('patient_id');
            $table->index('gender');
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
