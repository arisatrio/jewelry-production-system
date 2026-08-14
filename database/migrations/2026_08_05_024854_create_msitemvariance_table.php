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

        if ($schema->hasTable('msitemvariance')) {
            return;
        }

        $schema->create('msitemvariance', function (Blueprint $table) {
            $table->increments('row_id');
            $table->unsignedInteger('item_id')->index();
            $table->string('name', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('diameter_length_ringsize', 100)->nullable();
            $table->decimal('gold_weight', 15, 2)->nullable();
            $table->string('gold_color', 100)->nullable();
            $table->string('jwcad_3d', 100)->nullable();
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
        Schema::connection('third')->dropIfExists('msitemvariance');
    }
};
