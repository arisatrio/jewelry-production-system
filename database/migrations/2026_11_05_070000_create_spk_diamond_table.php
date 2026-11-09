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

        if ($schema->hasTable('spk_diamond')) {
            return;
        }

        $schema->create('spk_diamond', function (Blueprint $table): void {
            $table->increments('row_id');
            $table->string('doc_no', 50)->index();
            $table->date('tanggal');
            $table->unsignedInteger('spk_row_id')->index();
            $table->string('spk_no', 100)->nullable();
            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->string('status', 50)->default('draft');
            $table->unsignedInteger('diamondmounting_id')->nullable()->index();
            $table->json('allocation_row_ids')->nullable();
            $table->json('items')->nullable();
            $table->integer('is_deleted')->default(0)->index();
            $table->dateTime('created_date')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->dateTime('modified_date')->nullable();
            $table->string('modified_by', 100)->nullable();
            $table->dateTime('deleted_date')->nullable();
            $table->string('deleted_by', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if ($schema->hasTable('spk_diamond')) {
            $schema->drop('spk_diamond');
        }
    }
};
