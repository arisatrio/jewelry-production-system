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
                if (! $schema->hasColumn($tableName, 'workshop_received_at')) {
                    $afterColumn = $schema->hasColumn($tableName, 'fulfillment_status')
                        ? 'fulfillment_status'
                        : 'size';

                    $blueprint->dateTime('workshop_received_at')
                        ->nullable()
                        ->after($afterColumn);
                }

                if (! $schema->hasColumn($tableName, 'workshop_received_by')) {
                    $blueprint->string('workshop_received_by', 100)
                        ->nullable()
                        ->after(
                            $schema->hasColumn($tableName, 'workshop_received_at')
                                ? 'workshop_received_at'
                                : 'size',
                        );
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
                foreach (['workshop_received_by', 'workshop_received_at'] as $column) {
                    if ($schema->hasColumn($tableName, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }
};
