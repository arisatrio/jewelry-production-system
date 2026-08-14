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

        if ($schema->hasColumn('spk', 'category_prefix_id')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->unsignedBigInteger('category_prefix_id')->nullable()->index()->after('sku_id');
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

        if (! $schema->hasColumn('spk', 'category_prefix_id')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->dropColumn('category_prefix_id');
        });
    }
};
