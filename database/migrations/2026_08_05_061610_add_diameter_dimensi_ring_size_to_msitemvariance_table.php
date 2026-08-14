<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('msitemvariance')) {
            return;
        }

        $schema->table('msitemvariance', function (Blueprint $table) use ($schema): void {
            if (! $schema->hasColumn('msitemvariance', 'diameter')) {
                $table->string('diameter', 100)->nullable()->after('description');
            }

            if (! $schema->hasColumn('msitemvariance', 'dimensi')) {
                $table->string('dimensi', 100)->nullable()->after('diameter');
            }

            if (! $schema->hasColumn('msitemvariance', 'ring_size')) {
                $table->string('ring_size', 100)->nullable()->after('dimensi');
            }
        });

        if (
            $schema->hasColumn('msitemvariance', 'diameter_length_ringsize')
            && $schema->hasColumn('msitemvariance', 'diameter')
        ) {
            DB::connection('third')->table('msitemvariance')
                ->whereNotNull('diameter_length_ringsize')
                ->where('diameter_length_ringsize', '!=', '')
                ->where(function ($query): void {
                    $query->whereNull('diameter')
                        ->orWhere('diameter', '');
                })
                ->update([
                    'diameter' => DB::raw('diameter_length_ringsize'),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('msitemvariance')) {
            return;
        }

        $schema->table('msitemvariance', function (Blueprint $table) use ($schema): void {
            $columns = array_values(array_filter([
                $schema->hasColumn('msitemvariance', 'diameter') ? 'diameter' : null,
                $schema->hasColumn('msitemvariance', 'dimensi') ? 'dimensi' : null,
                $schema->hasColumn('msitemvariance', 'ring_size') ? 'ring_size' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
