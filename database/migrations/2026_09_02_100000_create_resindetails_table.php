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

        if ($schema->hasTable('resindetails')) {
            return;
        }

        $schema->create('resindetails', function (Blueprint $table): void {
            $table->unsignedInteger('line_id', true);
            $table->unsignedInteger('row_id');
            $table->unsignedInteger('spk_id');
            $table->decimal('berat_resin', 10, 3)->nullable();
            $table->string('status_resin', 20)->default('RES010');
            $table->string('catatan', 500)->nullable();
            $table->unsignedTinyInteger('is_deleted')->default(0);
            $table->dateTime('created_date')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->dateTime('modified_date')->nullable();
            $table->string('modified_by', 100)->nullable();
            $table->dateTime('deleted_date')->nullable();
            $table->string('deleted_by', 100)->nullable();

            $table->index(['row_id', 'is_deleted']);
            $table->index(['spk_id', 'is_deleted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if ($schema->hasTable('resindetails')) {
            $schema->drop('resindetails');
        }
    }
};
