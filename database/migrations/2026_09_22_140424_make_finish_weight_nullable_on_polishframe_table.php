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

        if (! $schema->hasTable('polishframe')
            || ! $schema->hasColumn('polishframe', 'finish_weight')) {
            return;
        }

        $schema->table('polishframe', function (Blueprint $table): void {
            $table->decimal('finish_weight', 15, 2)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('polishframe')
            || ! $schema->hasColumn('polishframe', 'finish_weight')) {
            return;
        }

        $schema->table('polishframe', function (Blueprint $table): void {
            $table->decimal('finish_weight', 15, 2)->nullable(false)->default(0)->change();
        });
    }
};
