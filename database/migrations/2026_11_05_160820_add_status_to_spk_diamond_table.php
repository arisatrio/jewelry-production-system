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

        if (! $schema->hasTable('spk_diamond') || $schema->hasColumn('spk_diamond', 'status')) {
            return;
        }

        $schema->table('spk_diamond', function (Blueprint $table): void {
            $table->string('status', 50)
                ->default('draft')
                ->after('jumlah_baris');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('spk_diamond') || ! $schema->hasColumn('spk_diamond', 'status')) {
            return;
        }

        $schema->table('spk_diamond', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
