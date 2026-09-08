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

        if ($schema->hasTable('coran')
            && ! $schema->hasColumn('coran', 'is_from_new_system')) {
            $schema->table('coran', function (Blueprint $table): void {
                $table->unsignedTinyInteger('is_from_new_system')
                    ->default(0)
                    ->after('status');
            });
        }

        if ($schema->hasTable('resin')
            && ! $schema->hasColumn('resin', 'is_from_new_system')) {
            $schema->table('resin', function (Blueprint $table): void {
                $table->unsignedTinyInteger('is_from_new_system')
                    ->default(0)
                    ->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if ($schema->hasTable('resin')
            && $schema->hasColumn('resin', 'is_from_new_system')) {
            $schema->table('resin', function (Blueprint $table): void {
                $table->dropColumn('is_from_new_system');
            });
        }

        if ($schema->hasTable('coran')
            && $schema->hasColumn('coran', 'is_from_new_system')) {
            $schema->table('coran', function (Blueprint $table): void {
                $table->dropColumn('is_from_new_system');
            });
        }
    }
};
