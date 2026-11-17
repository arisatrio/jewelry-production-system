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

        if (! $schema->hasTable('spk')) {
            return;
        }

        if (! $schema->hasColumn('spk', 'location')) {
            $schema->table('spk', function (Blueprint $table): void {
                $table->string('location', 50)->default('Workshop')->after('spk_no');
            });
        }

        if (! $schema->hasColumn('spk', 'plu')) {
            $schema->table('spk', function (Blueprint $table): void {
                $table->string('plu', 50)->nullable()->after('location');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('spk')) {
            return;
        }

        $drops = [];

        if ($schema->hasColumn('spk', 'plu')) {
            $drops[] = 'plu';
        }

        if ($schema->hasColumn('spk', 'location')) {
            $drops[] = 'location';
        }

        if ($drops === []) {
            return;
        }

        $schema->table('spk', function (Blueprint $table) use ($drops): void {
            $table->dropColumn($drops);
        });
    }
};
