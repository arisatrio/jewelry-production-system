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

        if (! $schema->hasTable('spk_chains')) {
            return;
        }

        $schema->table('spk_chains', function (Blueprint $table) use ($schema): void {
            if (! $schema->hasColumn('spk_chains', 'mschain_id')) {
                $table->unsignedInteger('mschain_id')->nullable()->index()->after('row_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('spk_chains')) {
            return;
        }

        $schema->table('spk_chains', function (Blueprint $table) use ($schema): void {
            if ($schema->hasColumn('spk_chains', 'mschain_id')) {
                $table->dropColumn('mschain_id');
            }
        });
    }
};
