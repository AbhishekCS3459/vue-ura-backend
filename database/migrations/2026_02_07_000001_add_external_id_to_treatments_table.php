<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds external_id for client API service mapping (e.g. SER-0001).
     */
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->string('external_id', 50)->nullable()->unique()->after('id');
            $table->string('noof_sessions', 20)->nullable()->default('1')->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'noof_sessions']);
        });
    }
};
