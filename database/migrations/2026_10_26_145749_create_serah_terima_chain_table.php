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

        if ($schema->hasTable('serah_terima_chain')) {
            return;
        }

        $schema->create('serah_terima_chain', function (Blueprint $table): void {
            $table->id();
            $table->string('doc_no', 50)->unique();
            $table->date('tanggal');
            $table->string('dari', 50)->nullable();
            $table->string('untuk', 50)->nullable();
            $table->string('diserahkan_oleh', 150)->nullable();
            $table->string('diterima_oleh', 150)->nullable();
            $table->string('diketahui_oleh', 150)->nullable();
            $table->unsignedInteger('spk_row_id');
            $table->unsignedInteger('jumlah_baris');
            $table->json('spk_chain_line_ids');
            $table->json('items');
            $table->string('created_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('deleted_by', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('third')->dropIfExists('serah_terima_chain');
    }
};
