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

        if (! $schema->hasTable('serah_terima_spk') || $schema->hasColumn('serah_terima_spk', 'deleted_at')) {
            return;
        }

        $schema->table('serah_terima_spk', function (Blueprint $table): void {
            $table->softDeletes();
            $table->string('deleted_by', 100)->nullable()->after('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasColumn('serah_terima_spk', 'deleted_at')) {
            return;
        }

        $schema->table('serah_terima_spk', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn('deleted_by');
        });
    }
};
