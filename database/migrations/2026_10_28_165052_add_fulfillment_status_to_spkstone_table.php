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

        $schema->table('spkstone', function (Blueprint $blueprint) use ($schema): void {
            if (! $schema->hasColumn('spkstone', 'fulfillment_status')) {
                $blueprint->string('fulfillment_status', 50)
                    ->default('not_allocated')
                    ->after('size');
            }
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

        if ($schema->hasColumn('spkstone', 'fulfillment_status')) {
            $schema->table('spkstone', function (Blueprint $blueprint): void {
                $blueprint->dropColumn('fulfillment_status');
            });
        }
    }
};
