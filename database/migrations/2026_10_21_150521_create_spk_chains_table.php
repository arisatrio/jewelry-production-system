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

        if ($schema->hasTable('spkchains')) {
            return;
        }

        $schema->create('spkchains', function (Blueprint $table) {
            $table->bigIncrements('line_id');
            $table->unsignedBigInteger('row_id')->index();
            $table->string('category', 255)->nullable();
            $table->string('item', 255)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('size', 100)->nullable();
            $table->unsignedTinyInteger('is_deleted')->default(0);
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
        Schema::connection('third')->dropIfExists('spkchains');
    }
};
