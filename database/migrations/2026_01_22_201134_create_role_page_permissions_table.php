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
        Schema::create('role_page_permissions', function (Blueprint $table) {
            $table->id();
            $table->enum('role', ['super_admin', 'branch_manager', 'staff']);
            $table->foreignId('page_permission_id')->constrained('page_permissions')->onDelete('cascade');
            $table->boolean('is_allowed')->default(true);
            $table->unique(['role', 'page_permission_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_page_permissions');
    }
};
