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

        if ($schema->hasColumn('spk', 'is_from_new_system')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->unsignedTinyInteger('is_from_new_system')->default(0)->after('is_inprocess');
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

        if (! $schema->hasColumn('spk', 'is_from_new_system')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->dropColumn('is_from_new_system');
        });
    }
};
