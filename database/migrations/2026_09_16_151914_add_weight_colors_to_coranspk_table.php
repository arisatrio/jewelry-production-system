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

        if (! $schema->hasTable('coranspk')) {
            return;
        }

        if (! $schema->hasColumn('coranspk', 'weight_rosegold')) {
            $schema->table('coranspk', function (Blueprint $table): void {
                $table->decimal('weight_rosegold', 12, 3)->nullable()->after('weight');
            });
        }

        if (! $schema->hasColumn('coranspk', 'weight_whitegold')) {
            $schema->table('coranspk', function (Blueprint $table): void {
                $table->decimal('weight_whitegold', 12, 3)->nullable()->after('weight_rosegold');
            });
        }

        if (! $schema->hasColumn('coranspk', 'weight_yellowgold')) {
            $schema->table('coranspk', function (Blueprint $table): void {
                $table->decimal('weight_yellowgold', 12, 3)->nullable()->after('weight_whitegold');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('coranspk')) {
            return;
        }

        $columns = collect(['weight_rosegold', 'weight_whitegold', 'weight_yellowgold'])
            ->filter(fn (string $column): bool => $schema->hasColumn('coranspk', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        $schema->table('coranspk', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
