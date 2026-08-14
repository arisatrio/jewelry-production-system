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

        if ($schema->hasColumn('spk', 'satuan')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->string('satuan', 20)->nullable()->after('qty');
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

        if (! $schema->hasColumn('spk', 'satuan')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->dropColumn('satuan');
        });
    }
};
