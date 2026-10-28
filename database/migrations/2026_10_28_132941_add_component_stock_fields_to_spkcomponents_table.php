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

        if (! $schema->hasTable('spkcomponents')) {
            return;
        }

        $schema->table('spkcomponents', function (Blueprint $blueprint) use ($schema): void {
            if (! $schema->hasColumn('spkcomponents', 'mscomponent_id')) {
                $blueprint->unsignedInteger('mscomponent_id')
                    ->nullable()
                    ->index()
                    ->after('row_id');
            }

            if (! $schema->hasColumn('spkcomponents', 'fulfillment_status')) {
                $blueprint->string('fulfillment_status', 50)
                    ->default('not_allocated')
                    ->after('size');
            }

            if (! $schema->hasColumn('spkcomponents', 'finishing_id')) {
                $afterColumn = $schema->hasColumn('spkcomponents', 'fulfillment_status')
                    ? 'fulfillment_status'
                    : 'size';

                $blueprint->unsignedBigInteger('finishing_id')
                    ->nullable()
                    ->index()
                    ->after($afterColumn);
            }

            if (! $schema->hasColumn('spkcomponents', 'workshop_received_at')) {
                $afterColumn = $schema->hasColumn('spkcomponents', 'fulfillment_status')
                    ? 'fulfillment_status'
                    : 'size';

                $blueprint->dateTime('workshop_received_at')
                    ->nullable()
                    ->after($afterColumn);
            }

            if (! $schema->hasColumn('spkcomponents', 'workshop_received_by')) {
                $blueprint->string('workshop_received_by', 100)
                    ->nullable()
                    ->after(
                        $schema->hasColumn('spkcomponents', 'workshop_received_at')
                            ? 'workshop_received_at'
                            : 'size',
                    );
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('spkcomponents')) {
            return;
        }

        $schema->table('spkcomponents', function (Blueprint $blueprint) use ($schema): void {
            foreach ([
                'workshop_received_by',
                'workshop_received_at',
                'finishing_id',
                'fulfillment_status',
                'mscomponent_id',
            ] as $column) {
                if ($schema->hasColumn('spkcomponents', $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
