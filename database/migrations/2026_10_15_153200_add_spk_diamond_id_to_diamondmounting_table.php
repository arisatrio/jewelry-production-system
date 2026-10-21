<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('diamondmounting')) {
            return;
        }

        if (! $schema->hasColumn('diamondmounting', 'spk_diamond_id')) {
            $schema->table('diamondmounting', function (Blueprint $table): void {
                $table->unsignedBigInteger('spk_diamond_id')
                    ->nullable()
                    ->after('spk_id');
                $table->unique('spk_diamond_id');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('diamondmounting')) {
            return;
        }

        if ($schema->hasColumn('diamondmounting', 'spk_diamond_id')) {
            $schema->table('diamondmounting', function (Blueprint $table): void {
                $table->dropUnique(['spk_diamond_id']);
                $table->dropColumn('spk_diamond_id');
            });
        }
    }
};
