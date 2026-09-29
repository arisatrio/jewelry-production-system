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

        if ($schema->hasTable('serah_terima_spk')) {
            return;
        }

        $schema->create('serah_terima_spk', function (Blueprint $table): void {
            $table->id();
            $table->string('doc_no', 50)->unique();
            $table->date('tanggal');
            $table->string('dari', 50)->nullable();
            $table->string('untuk', 50)->nullable();
            $table->string('diserahkan_oleh', 150)->nullable();
            $table->string('diterima_oleh', 150)->nullable();
            $table->string('diketahui_oleh', 150)->nullable();
            $table->unsignedInteger('jumlah_spk');
            $table->json('spk_row_ids');
            $table->json('items');
            $table->string('created_by', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('third')->dropIfExists('serah_terima_spk');
    }
};
