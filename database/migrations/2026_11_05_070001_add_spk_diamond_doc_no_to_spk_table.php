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

        if (! $schema->hasTable('spk') || $schema->hasColumn('spk', 'spk_diamond_doc_no')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->string('spk_diamond_doc_no', 50)->nullable()->after('spk_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('spk') || ! $schema->hasColumn('spk', 'spk_diamond_doc_no')) {
            return;
        }

        $schema->table('spk', function (Blueprint $table): void {
            $table->dropColumn('spk_diamond_doc_no');
        });
    }
};
