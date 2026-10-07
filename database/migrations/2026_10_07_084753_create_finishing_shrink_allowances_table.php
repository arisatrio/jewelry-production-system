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

        if ($schema->hasTable('finishing_shrink_allowances')) {
            return;
        }

        $schema->create('finishing_shrink_allowances', function (Blueprint $table): void {
            $table->id();
            $table->string('work_category', 100);
            $table->string('work_type', 100);
            $table->string('item_category', 100);
            $table->decimal('allowance_percent', 5, 2);
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            $table->unique(['work_type', 'item_category']);
            $table->index('work_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('third')->dropIfExists('finishing_shrink_allowances');
    }
};
