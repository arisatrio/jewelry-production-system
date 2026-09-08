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

        if ($schema->hasColumn('coranspk', 'kadar')) {
            return;
        }

        $schema->table('coranspk', function (Blueprint $table): void {
            $table->decimal('kadar', 6, 2)->nullable()->after('weight');
        });
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

        if (! $schema->hasColumn('coranspk', 'kadar')) {
            return;
        }

        $schema->table('coranspk', function (Blueprint $table): void {
            $table->dropColumn('kadar');
        });
    }
};
