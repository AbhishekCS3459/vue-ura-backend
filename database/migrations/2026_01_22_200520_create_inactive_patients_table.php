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
        Schema::create('inactive_patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->date('last_session_date');
            $table->integer('days_since_last_session');
            $table->string('last_therapist');
            $table->foreignId('last_therapist_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->enum('status', ['No action', 'Message sent', 'No response', 'Call scheduled', 'Asked to call back'])->default('No action');
            $table->enum('last_action', ['None', 'Message sent', 'Follow up call made'])->default('None');
            $table->timestamp('last_status_update')->nullable();
            $table->date('next_follow_up_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inactive_patients');
    }
};
