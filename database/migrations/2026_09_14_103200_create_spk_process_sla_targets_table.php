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
        Schema::create('spk_process_sla_targets', function (Blueprint $table) {
            $table->id();
            $table->string('process_key', 64)->unique();
            $table->unsignedSmallInteger('working_days');
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spk_process_sla_targets');
    }
};
