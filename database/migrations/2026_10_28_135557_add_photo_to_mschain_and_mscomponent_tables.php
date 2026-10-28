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

        if ($schema->hasTable('mschain') && ! $schema->hasColumn('mschain', 'photo')) {
            $schema->table('mschain', function (Blueprint $table): void {
                $table->string('photo', 1000)->nullable()->after('description');
            });
        }

        if ($schema->hasTable('mscomponent') && ! $schema->hasColumn('mscomponent', 'photo')) {
            $schema->table('mscomponent', function (Blueprint $table): void {
                $table->string('photo', 1000)->nullable()->after('description');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if ($schema->hasTable('mschain') && $schema->hasColumn('mschain', 'photo')) {
            $schema->table('mschain', function (Blueprint $table): void {
                $table->dropColumn('photo');
            });
        }

        if ($schema->hasTable('mscomponent') && $schema->hasColumn('mscomponent', 'photo')) {
            $schema->table('mscomponent', function (Blueprint $table): void {
                $table->dropColumn('photo');
            });
        }
    }
};
