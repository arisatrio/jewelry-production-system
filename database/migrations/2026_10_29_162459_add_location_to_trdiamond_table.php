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

        if ($schema->hasTable('trdiamond') && ! $schema->hasColumn('trdiamond', 'location')) {
            $schema->table('trdiamond', function (Blueprint $table): void {
                $table->string('location', 50)
                    ->default('Head Office')
                    ->after('supplier');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if ($schema->hasTable('trdiamond') && $schema->hasColumn('trdiamond', 'location')) {
            $schema->table('trdiamond', function (Blueprint $table): void {
                $table->dropColumn('location');
            });
        }
    }
};
