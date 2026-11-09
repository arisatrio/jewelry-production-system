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

        if (! $schema->hasTable('spk_diamond') || $schema->hasColumn('spk_diamond', 'diamondmounting_id')) {
            return;
        }

        $schema->table('spk_diamond', function (Blueprint $table): void {
            $table->unsignedInteger('diamondmounting_id')
                ->nullable()
                ->index()
                ->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('spk_diamond') || ! $schema->hasColumn('spk_diamond', 'diamondmounting_id')) {
            return;
        }

        $schema->table('spk_diamond', function (Blueprint $table): void {
            $table->dropColumn('diamondmounting_id');
        });
    }
};
