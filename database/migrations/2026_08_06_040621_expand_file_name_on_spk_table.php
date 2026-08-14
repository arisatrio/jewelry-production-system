<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('spk') || ! $schema->hasColumn('spk', 'file_name')) {
            return;
        }

        DB::connection('third')->statement(
            'ALTER TABLE `spk` MODIFY `file_name` VARCHAR(255) NULL',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('spk') || ! $schema->hasColumn('spk', 'file_name')) {
            return;
        }

        DB::connection('third')->statement(
            'ALTER TABLE `spk` MODIFY `file_name` VARCHAR(50) NULL',
        );
    }
};
