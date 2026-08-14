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

        if (! $schema->hasTable('spkstone')) {
            return;
        }

        if ($schema->hasColumn('spkstone', 'position_id')) {
            return;
        }

        $schema->table('spkstone', function (Blueprint $table) {
            $table->unsignedBigInteger('position_id')->nullable()->index()->after('shape_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('spkstone')) {
            return;
        }

        if (! $schema->hasColumn('spkstone', 'position_id')) {
            return;
        }

        $schema->table('spkstone', function (Blueprint $table) {
            $table->dropColumn('position_id');
        });
    }
};
