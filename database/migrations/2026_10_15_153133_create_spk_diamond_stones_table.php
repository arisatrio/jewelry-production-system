<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('third');

        if ($schema->hasTable('spk_diamond_stone')) {
            return;
        }

        $schema->create('spk_diamond_stone', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('spk_diamond_id');
            $table->unsignedBigInteger('stone_id');
            $table->decimal('pcs', 12, 3)->nullable();
            $table->decimal('crt', 12, 3)->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('trstone_row_id')->nullable();
            $table->unsignedTinyInteger('is_deleted')->default(0);
            $table->timestamp('created_date')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamp('modified_date')->nullable();
            $table->string('modified_by', 100)->nullable();

            $table->index('spk_diamond_id');
            $table->index('stone_id');
        });
    }

    public function down(): void
    {
        Schema::connection('third')->dropIfExists('spk_diamond_stone');
    }
};
