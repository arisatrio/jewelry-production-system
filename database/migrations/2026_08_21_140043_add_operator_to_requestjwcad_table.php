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

        if ($schema->hasTable('requestjwcad')
            && ! $schema->hasColumn('requestjwcad', 'operator')) {
            $schema->table('requestjwcad', function (Blueprint $table): void {
                $table->string('operator', 150)
                    ->nullable()
                    ->after('doc_no');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if ($schema->hasTable('requestjwcad')
            && $schema->hasColumn('requestjwcad', 'operator')) {
            $schema->table('requestjwcad', function (Blueprint $table): void {
                $table->dropColumn('operator');
            });
        }
    }
};
