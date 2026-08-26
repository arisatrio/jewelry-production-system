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

        if (! $schema->hasTable('requestjwcaddetails')) {
            return;
        }

        if (! $schema->hasColumn('requestjwcaddetails', 'estimation_brj')) {
            return;
        }

        $schema->table('requestjwcaddetails', function (Blueprint $table): void {
            $table->decimal('estimation_brj', 15, 3)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('requestjwcaddetails')) {
            return;
        }

        if (! $schema->hasColumn('requestjwcaddetails', 'estimation_brj')) {
            return;
        }

        $schema->table('requestjwcaddetails', function (Blueprint $table): void {
            $table->decimal('estimation_brj', 15, 2)->nullable()->change();
        });
    }
};
