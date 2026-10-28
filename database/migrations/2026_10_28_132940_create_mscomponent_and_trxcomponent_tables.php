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

        if (! $schema->hasTable('mscomponent')) {
            $schema->create('mscomponent', function (Blueprint $table) {
                $table->increments('row_id');
                $table->string('component_category', 100);
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
                    ['component_category', 'item', 'item_code', 'length'],
                    'mscomponent_category_item_code_length_unique',
                );
            });
        }

        if (! $schema->hasTable('trxcomponent')) {
            $schema->create('trxcomponent', function (Blueprint $table) {
                $table->increments('row_id');
                $table->string('unit_no', 20)->nullable()->unique();
                $table->unsignedInteger('mscomponent_id')->index();
                $table->string('source_type', 50)->index();
                $table->unsignedInteger('parent_row_id')->nullable()->index();
                $table->string('name', 500);
                $table->string('item_code', 255)->nullable();
                $table->decimal('component_size', 15, 2);
                $table->string('color', 1000)->nullable();
                $table->decimal('weight', 15, 2)->nullable();
                $table->string('supplier', 255)->nullable();
                $table->decimal('purchase_price', 15, 2)->nullable();
                $table->date('entry_date')->nullable()->index();
                $table->date('out_date')->nullable()->index();
                $table->unsignedInteger('spk_id')->nullable()->index();
                $table->integer('is_used')->default(0)->index();
                $table->text('notes')->nullable();
                $table->integer('is_deleted')->default(0)->index();
                $table->dateTime('created_date')->nullable();
                $table->string('created_by', 100)->nullable();
                $table->dateTime('modified_date')->nullable()->index();
                $table->string('modified_by', 100)->nullable();
                $table->dateTime('deleted_date')->nullable();
                $table->string('deleted_by', 100)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        $schema->dropIfExists('trxcomponent');
        $schema->dropIfExists('mscomponent');
    }
};
