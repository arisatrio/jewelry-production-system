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

        if (! $schema->hasColumn('coranspk', 'kadar_rosegold')) {
            $schema->table('coranspk', function (Blueprint $table): void {
                $table->decimal('kadar_rosegold', 6, 2)->nullable()->after('kadar');
            });
        }

        if (! $schema->hasColumn('coranspk', 'kadar_whitegold')) {
            $schema->table('coranspk', function (Blueprint $table): void {
                $table->decimal('kadar_whitegold', 6, 2)->nullable()->after('kadar_rosegold');
            });
        }

        if (! $schema->hasColumn('coranspk', 'kadar_yellowgold')) {
            $schema->table('coranspk', function (Blueprint $table): void {
                $table->decimal('kadar_yellowgold', 6, 2)->nullable()->after('kadar_whitegold');
            });
        }

        if (! $schema->hasColumn('coranspk', 'status_rosegold')) {
            $schema->table('coranspk', function (Blueprint $table): void {
                $table->string('status_rosegold', 20)->nullable()->after('status');
            });
        }

        if (! $schema->hasColumn('coranspk', 'status_whitegold')) {
            $schema->table('coranspk', function (Blueprint $table): void {
                $table->string('status_whitegold', 20)->nullable()->after('status_rosegold');
            });
        }

        if (! $schema->hasColumn('coranspk', 'status_yellowgold')) {
            $schema->table('coranspk', function (Blueprint $table): void {
                $table->string('status_yellowgold', 20)->nullable()->after('status_whitegold');
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

        $columns = collect([
            'kadar_rosegold',
            'kadar_whitegold',
            'kadar_yellowgold',
            'status_rosegold',
            'status_whitegold',
            'status_yellowgold',
        ])
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
