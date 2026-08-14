<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror skema mahakarya (role / permissions / role_permissions) agar
 * tes sqlite punya tabel yang sama. Di DB shared, buat hanya jika belum ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role')) {
            Schema::create('role', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_deleted')->default(false)->nullable();
                $table->string('created_by')->nullable();
                $table->string('modified_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('display_name')->nullable();
                $table->text('description')->nullable();
                $table->string('module')->nullable();
                $table->string('category')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('permission_id');
                $table->timestamps();

                $table->unique(['role_id', 'permission_id']);
            });
        }

        if (! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id')->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        // Jangan drop tabel shared mahakarya.
    }
};
