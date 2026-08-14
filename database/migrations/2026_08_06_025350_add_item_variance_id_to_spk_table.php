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

        if (! $schema->hasTable('spk')) {
            return;
        }

        if ($schema->hasColumn('spk', 'item_variance_id')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->unsignedInteger('item_variance_id')->nullable()->index()->after('item_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('spk')) {
            return;
        }

        if (! $schema->hasColumn('spk', 'item_variance_id')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->dropColumn('item_variance_id');
        });
    }
};
