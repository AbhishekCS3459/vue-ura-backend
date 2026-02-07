<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds external_id for client API branch mapping (e.g. TPT, VJY, CBT).
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('external_id', 50)->nullable()->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('external_id');
        });
    }
};
