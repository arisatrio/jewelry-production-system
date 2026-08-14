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

        if (! $schema->hasTable('msitemvariancestone')) {
            return;
        }

        if (
            $schema->hasColumn('msitemvariancestone', 'pcs_per_carat')
            && ! $schema->hasColumn('msitemvariancestone', 'carat_per_pcs')
        ) {
            $schema->table('msitemvariancestone', function (Blueprint $table) {
                $table->renameColumn('pcs_per_carat', 'carat_per_pcs');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('msitemvariancestone')) {
            return;
        }

        if (
            $schema->hasColumn('msitemvariancestone', 'carat_per_pcs')
            && ! $schema->hasColumn('msitemvariancestone', 'pcs_per_carat')
        ) {
            $schema->table('msitemvariancestone', function (Blueprint $table) {
                $table->renameColumn('carat_per_pcs', 'pcs_per_carat');
            });
        }
    }
};
