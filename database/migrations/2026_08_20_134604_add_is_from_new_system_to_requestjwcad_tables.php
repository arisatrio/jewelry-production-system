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
        $schema = Schema::connection('third');

        if ($schema->hasTable('requestjwcad')
            && ! $schema->hasColumn('requestjwcad', 'is_from_new_system')) {
            $schema->table('requestjwcad', function (Blueprint $table): void {
                $table->unsignedTinyInteger('is_from_new_system')
                    ->default(0)
                    ->after('status');
            });
        }

        if ($schema->hasTable('requestjwcaddetails')
            && ! $schema->hasColumn('requestjwcaddetails', 'is_from_new_system')) {
            $schema->table('requestjwcaddetails', function (Blueprint $table): void {
                $table->unsignedTinyInteger('is_from_new_system')
                    ->default(0)
                    ->after('notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if ($schema->hasTable('requestjwcaddetails')
            && $schema->hasColumn('requestjwcaddetails', 'is_from_new_system')) {
            $schema->table('requestjwcaddetails', function (Blueprint $table): void {
                $table->dropColumn('is_from_new_system');
            });
        }

        if ($schema->hasTable('requestjwcad')
            && $schema->hasColumn('requestjwcad', 'is_from_new_system')) {
            $schema->table('requestjwcad', function (Blueprint $table): void {
                $table->dropColumn('is_from_new_system');
            });
        }
    }
};
