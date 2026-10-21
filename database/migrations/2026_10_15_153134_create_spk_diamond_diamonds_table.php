<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('third');

        if ($schema->hasTable('spk_diamond_diamond')) {
            return;
        }

        $schema->create('spk_diamond_diamond', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('spk_diamond_id');
            $table->unsignedBigInteger('trdiamond_id');
            $table->unsignedTinyInteger('is_deleted')->default(0);
            $table->timestamp('created_date')->nullable();
            $table->string('created_by', 100)->nullable();

            $table->index('spk_diamond_id');
            $table->index('trdiamond_id');
        });
    }

    public function down(): void
    {
        Schema::connection('third')->dropIfExists('spk_diamond_diamond');
    }
};
