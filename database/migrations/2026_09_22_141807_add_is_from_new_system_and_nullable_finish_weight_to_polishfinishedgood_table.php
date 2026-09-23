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

        if (! $schema->hasTable('polishfinishedgood')) {
            return;
        }

        if (! $schema->hasColumn('polishfinishedgood', 'is_from_new_system')) {
            $schema->table('polishfinishedgood', function (Blueprint $table): void {
                $table->unsignedTinyInteger('is_from_new_system')
                    ->default(0)
                    ->after('status');
            });
        }

        if ($schema->hasColumn('polishfinishedgood', 'finish_weight')) {
            $schema->table('polishfinishedgood', function (Blueprint $table): void {
                $table->decimal('finish_weight', 15, 2)->nullable()->default(null)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('polishfinishedgood')) {
            return;
        }

        if ($schema->hasColumn('polishfinishedgood', 'finish_weight')) {
            $schema->table('polishfinishedgood', function (Blueprint $table): void {
                $table->decimal('finish_weight', 15, 2)->nullable(false)->default(0)->change();
            });
        }

        if ($schema->hasColumn('polishfinishedgood', 'is_from_new_system')) {
            $schema->table('polishfinishedgood', function (Blueprint $table): void {
                $table->dropColumn('is_from_new_system');
            });
        }
    }
};
