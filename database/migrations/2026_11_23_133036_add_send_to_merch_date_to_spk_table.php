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

        if (! $schema->hasTable('spk') || $schema->hasColumn('spk', 'send_to_merch_date')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->date('send_to_merch_date')->nullable()->after('plu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('spk') || ! $schema->hasColumn('spk', 'send_to_merch_date')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->dropColumn('send_to_merch_date');
        });
    }
};
