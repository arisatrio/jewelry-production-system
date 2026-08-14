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

        if (! $schema->hasTable('msitemvariancestone')) {
            return;
        }

        if (! $schema->hasColumn('msitemvariancestone', 'size')) {
            return;
        }

        $schema->table('msitemvariancestone', function (Blueprint $table): void {
            $table->string('size', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('msitemvariancestone')) {
            return;
        }

        if (! $schema->hasColumn('msitemvariancestone', 'size')) {
            return;
        }

        $schema->table('msitemvariancestone', function (Blueprint $table): void {
            $table->decimal('size', 15, 2)->nullable()->change();
        });
    }
};
