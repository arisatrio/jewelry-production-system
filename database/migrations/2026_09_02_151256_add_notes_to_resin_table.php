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

        if ($schema->hasTable('resin')
            && ! $schema->hasColumn('resin', 'notes')) {
            $schema->table('resin', function (Blueprint $table): void {
                $table->text('notes')
                    ->nullable()
                    ->after('operator');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if ($schema->hasTable('resin')
            && $schema->hasColumn('resin', 'notes')) {
            $schema->table('resin', function (Blueprint $table): void {
                $table->dropColumn('notes');
            });
        }
    }
};
