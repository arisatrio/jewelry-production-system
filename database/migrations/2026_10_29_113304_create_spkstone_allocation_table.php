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

        if ($schema->hasTable('spkstone_allocation')) {
            return;
        }

        $schema->create('spkstone_allocation', function (Blueprint $table): void {
            $table->increments('row_id');
            $table->unsignedInteger('line_id')->index();
            $table->string('source', 20)->index();
            $table->unsignedInteger('diamond_id')->nullable()->index();
            $table->unsignedInteger('msstone_id')->nullable()->index();
            $table->unsignedInteger('period_id')->nullable()->index();
            $table->decimal('reserved_pcs', 15, 4)->default(1);
            $table->decimal('reserved_crt', 15, 4)->nullable();
            $table->string('status', 30)->default('reserved')->index();
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

        if ($schema->hasTable('spkstone_allocation')) {
            $schema->drop('spkstone_allocation');
        }
    }
};
