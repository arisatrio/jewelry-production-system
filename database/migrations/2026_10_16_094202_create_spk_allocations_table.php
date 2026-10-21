<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('spk_allocation')) {
            $schema->create('spk_allocation', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('spk_id')->unique();
                $table->string('spk_no', 100)->nullable();
                $table->unsignedTinyInteger('is_deleted')->default(0);
                $table->timestamp('created_date')->nullable();
                $table->string('created_by', 100)->nullable();
                $table->timestamp('modified_date')->nullable();
                $table->string('modified_by', 100)->nullable();

                $table->index('spk_id');
            });
        }

        if (! $schema->hasTable('spk_allocation_stone')) {
            $schema->create('spk_allocation_stone', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('spk_allocation_id');
                $table->unsignedBigInteger('stone_id');
                $table->decimal('pcs', 12, 3)->nullable();
                $table->decimal('crt', 12, 3)->nullable();
                $table->string('notes', 500)->nullable();
                $table->unsignedTinyInteger('is_deleted')->default(0);
                $table->timestamp('created_date')->nullable();
                $table->string('created_by', 100)->nullable();
                $table->timestamp('modified_date')->nullable();
                $table->string('modified_by', 100)->nullable();

                $table->index('spk_allocation_id');
                $table->index('stone_id');
            });
        }

        if (! $schema->hasTable('spk_allocation_diamond')) {
            $schema->create('spk_allocation_diamond', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('spk_allocation_id');
                $table->unsignedBigInteger('trdiamond_id');
                $table->unsignedTinyInteger('is_deleted')->default(0);
                $table->timestamp('created_date')->nullable();
                $table->string('created_by', 100)->nullable();

                $table->index('spk_allocation_id');
                $table->index('trdiamond_id');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('third');

        $schema->dropIfExists('spk_allocation_diamond');
        $schema->dropIfExists('spk_allocation_stone');
        $schema->dropIfExists('spk_allocation');
    }
};
