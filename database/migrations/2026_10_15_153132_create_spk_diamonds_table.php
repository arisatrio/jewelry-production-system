<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('third');

        if ($schema->hasTable('spk_diamond')) {
            return;
        }

        $schema->create('spk_diamond', function (Blueprint $table): void {
            $table->id();
            $table->string('no_spk_diamond', 100);
            $table->unsignedBigInteger('spk_id');
            $table->string('spk_no', 100)->nullable();
            $table->string('dmd_no', 100)->nullable();
            $table->date('tanggal');
            $table->decimal('weight_frame', 12, 2)->nullable();
            $table->decimal('weight_stone', 12, 3)->nullable();
            $table->decimal('weight_total', 12, 3)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status', 30)->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->string('submitted_by', 100)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('completed_by', 100)->nullable();
            $table->unsignedTinyInteger('is_deleted')->default(0);
            $table->timestamp('created_date')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamp('modified_date')->nullable();
            $table->string('modified_by', 100)->nullable();
            $table->timestamp('deleted_date')->nullable();
            $table->string('deleted_by', 100)->nullable();

            $table->index('spk_id');
            $table->index('status');
            $table->index('no_spk_diamond');
        });
    }

    public function down(): void
    {
        Schema::connection('third')->dropIfExists('spk_diamond');
    }
};
