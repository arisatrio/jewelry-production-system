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
            $schema->hasColumn('msitemvariancestone', 'shape_id')
            && ! $schema->hasColumn('msitemvariancestone', 'stone_id')
        ) {
            $schema->table('msitemvariancestone', function (Blueprint $table) {
                $table->renameColumn('shape_id', 'stone_id');
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
            $schema->hasColumn('msitemvariancestone', 'stone_id')
            && ! $schema->hasColumn('msitemvariancestone', 'shape_id')
        ) {
            $schema->table('msitemvariancestone', function (Blueprint $table) {
                $table->renameColumn('stone_id', 'shape_id');
            });
        }
    }
};
