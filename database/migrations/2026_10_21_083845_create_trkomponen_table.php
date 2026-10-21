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

        if ($schema->hasTable('trkomponen')) {
            return;
        }

        $schema->create('trkomponen', function (Blueprint $table): void {
            $table->increments('row_id');
            $table->date('entry_date')->nullable();
            $table->date('out_date')->nullable();
            $table->string('jenis_komponen', 100)->nullable()->index();
            $table->string('name', 100)->nullable();
            $table->string('item_code', 100)->nullable();
            $table->string('supplier', 100)->nullable();
            $table->decimal('length', 15, 2)->nullable();
            $table->string('color', 1000)->nullable();
            $table->decimal('weight', 15, 2)->nullable();
            $table->integer('spk_id')->nullable()->index();
            $table->integer('is_used')->nullable()->index();
            $table->integer('is_reserved')->nullable()->index();
            $table->integer('is_deleted')->default(0)->index();
            $table->dateTime('created_date')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->dateTime('modified_date')->nullable()->index();
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
        Schema::connection('third')->dropIfExists('trkomponen');
    }
};
