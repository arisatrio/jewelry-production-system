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

        if ($schema->hasTable('mschain')) {
            return;
        }

        $schema->create('mschain', function (Blueprint $table) {
            $table->increments('row_id');
            $table->string('chain_category', 100);
            $table->string('item', 255);
            $table->string('item_code', 255);
            $table->decimal('length', 15, 2);
            $table->string('item_name', 500)->nullable();
            $table->text('description')->nullable();
            $table->integer('is_deleted')->default(0)->index();
            $table->dateTime('created_date')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->dateTime('modified_date')->nullable()->index();
            $table->string('modified_by', 100)->nullable();
            $table->dateTime('deleted_date')->nullable();
            $table->string('deleted_by', 100)->nullable();

            $table->unique(
                ['chain_category', 'item', 'item_code', 'length'],
                'mschain_category_item_code_length_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('third')->dropIfExists('mschain');
    }
};
