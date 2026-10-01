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

        if (! $schema->hasTable('finishinghandmade')) {
            return;
        }

        if (! $schema->hasColumn('finishinghandmade', 'work_category')) {
            $schema->table('finishinghandmade', function (Blueprint $table): void {
                $table->string('work_category', 100)
                    ->nullable()
                    ->after('item_category');
            });
        }

        if (! $schema->hasColumn('finishinghandmade', 'work_type')) {
            $schema->table('finishinghandmade', function (Blueprint $table): void {
                $table->string('work_type', 100)
                    ->nullable()
                    ->after('work_category');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('finishinghandmade')) {
            return;
        }

        if ($schema->hasColumn('finishinghandmade', 'work_type')) {
            $schema->table('finishinghandmade', function (Blueprint $table): void {
                $table->dropColumn('work_type');
            });
        }

        if ($schema->hasColumn('finishinghandmade', 'work_category')) {
            $schema->table('finishinghandmade', function (Blueprint $table): void {
                $table->dropColumn('work_category');
            });
        }
    }
};
