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

        if ($schema->hasTable('diamond_crt_matrix')) {
            return;
        }

        $schema->create('diamond_crt_matrix', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('shape_id')->comment('msshape.row_id');
            $table->decimal('crt_min', 8, 3);
            $table->decimal('crt_max', 8, 3);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            $table->unique(['shape_id', 'crt_min', 'crt_max']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('third')->dropIfExists('diamond_crt_matrix');
    }
};
