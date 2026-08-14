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

        if ($schema->hasTable('msitemvariancestone')) {
            return;
        }

        $schema->create('msitemvariancestone', function (Blueprint $table) {
            $table->increments('row_id');
            $table->unsignedInteger('item_variance_id')->index();
            $table->unsignedInteger('shape_id')->nullable()->index();
            $table->integer('pcs')->nullable();
            $table->decimal('carat_per_pcs', 15, 3)->nullable();
            $table->decimal('total_carat', 15, 3)->nullable();
            $table->decimal('size', 15, 2)->nullable();
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
        Schema::connection('third')->dropIfExists('msitemvariancestone');
    }
};
