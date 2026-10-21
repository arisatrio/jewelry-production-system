<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('trdiamond')) {
            return;
        }

        if (! $schema->hasColumn('trdiamond', 'spk_diamond_id')) {
            $schema->table('trdiamond', function (Blueprint $table): void {
                $table->unsignedBigInteger('spk_diamond_id')
                    ->nullable()
                    ->default(0)
                    ->after('diamondmounting_id');
                $table->index('spk_diamond_id');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('trdiamond')) {
            return;
        }

        if ($schema->hasColumn('trdiamond', 'spk_diamond_id')) {
            $schema->table('trdiamond', function (Blueprint $table): void {
                $table->dropIndex(['spk_diamond_id']);
                $table->dropColumn('spk_diamond_id');
            });
        }
    }
};
