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

        if (! $schema->hasTable('spk') || $schema->hasColumn('spk', 'foto_brj')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->string('foto_brj', 255)->nullable()->after('file_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('spk') || ! $schema->hasColumn('spk', 'foto_brj')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->dropColumn('foto_brj');
        });
    }
};
