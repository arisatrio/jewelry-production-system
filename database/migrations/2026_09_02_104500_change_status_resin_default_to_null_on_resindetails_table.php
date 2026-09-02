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

        if ($schema->hasTable('resindetails')
            && $schema->hasColumn('resindetails', 'status_resin')) {
            $schema->table('resindetails', function (Blueprint $table): void {
                $table->string('status_resin', 20)->nullable()->default(null)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if ($schema->hasTable('resindetails')
            && $schema->hasColumn('resindetails', 'status_resin')) {
            $schema->table('resindetails', function (Blueprint $table): void {
                $table->string('status_resin', 20)->nullable()->default('NOT OK')->change();
            });
        }
    }
};
