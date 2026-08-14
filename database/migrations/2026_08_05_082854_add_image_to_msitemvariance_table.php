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

        if (! $schema->hasTable('msitemvariance')) {
            return;
        }

        $schema->table('msitemvariance', function (Blueprint $table) use ($schema): void {
            if (! $schema->hasColumn('msitemvariance', 'image')) {
                $table->string('image', 255)->nullable()->after('jwcad_3d');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('msitemvariance')) {
            return;
        }

        $schema->table('msitemvariance', function (Blueprint $table) use ($schema): void {
            if ($schema->hasColumn('msitemvariance', 'image')) {
                $table->dropColumn('image');
            }
        });
    }
};
