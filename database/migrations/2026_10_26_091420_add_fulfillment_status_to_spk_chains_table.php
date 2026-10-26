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

        foreach (['spk_chains', 'spkchains'] as $tableName) {
            if (! $schema->hasTable($tableName)) {
                continue;
            }

            $schema->table($tableName, function (Blueprint $blueprint) use ($schema, $tableName): void {
                if (! $schema->hasColumn($tableName, 'fulfillment_status')) {
                    $blueprint->string('fulfillment_status', 50)
                        ->default('not_allocated')
                        ->after('size');
                }

                if (! $schema->hasColumn($tableName, 'finishing_id')) {
                    $afterColumn = $schema->hasColumn($tableName, 'fulfillment_status')
                        ? 'fulfillment_status'
                        : 'size';

                    $blueprint->unsignedBigInteger('finishing_id')
                        ->nullable()
                        ->index()
                        ->after($afterColumn);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        foreach (['spk_chains', 'spkchains'] as $tableName) {
            if (! $schema->hasTable($tableName)) {
                continue;
            }

            $schema->table($tableName, function (Blueprint $blueprint) use ($schema, $tableName): void {
                if ($schema->hasColumn($tableName, 'finishing_id')) {
                    $blueprint->dropColumn('finishing_id');
                }

                if ($schema->hasColumn($tableName, 'fulfillment_status')) {
                    $blueprint->dropColumn('fulfillment_status');
                }
            });
        }
    }
};
