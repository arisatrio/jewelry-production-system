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

        if (! $schema->hasTable('msitemvariance')) {
            return;
        }

        $schema->table('msitemvariance', function (Blueprint $table) use ($schema): void {
            if (! $schema->hasColumn('msitemvariance', 'diameter_length_ringsize')) {
                $table->string('diameter_length_ringsize', 100)->nullable()->after('description');
            }

            if (! $schema->hasColumn('msitemvariance', 'gold_weight')) {
                $table->decimal('gold_weight', 15, 2)->nullable()->after('diameter_length_ringsize');
            }

            if (! $schema->hasColumn('msitemvariance', 'gold_color')) {
                $table->string('gold_color', 100)->nullable()->after('gold_weight');
            }

            if (! $schema->hasColumn('msitemvariance', 'jwcad_3d')) {
                $table->string('jwcad_3d', 100)->nullable()->after('gold_color');
            }
        });
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
            $columns = collect([
                'diameter_length_ringsize',
                'gold_weight',
                'gold_color',
                'jwcad_3d',
            ])->filter(
                fn (string $column): bool => $schema->hasColumn('msitemvariance', $column),
            )->values()->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
