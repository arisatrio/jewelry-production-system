<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Label tukang untuk klasifikasi pengrajin (select + custom).
     */
    public function up(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('mscraftsman')) {
            return;
        }

        if ($schema->hasColumn('mscraftsman', 'label_tukang')) {
            return;
        }

        $after = $schema->hasColumn('mscraftsman', 'user_id') ? 'user_id' : 'name';

        $schema->table('mscraftsman', function (Blueprint $table) use ($after): void {
            $table->string('label_tukang', 100)->nullable()->after($after);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('mscraftsman')) {
            return;
        }

        if ($schema->hasColumn('mscraftsman', 'label_tukang')) {
            $schema->table('mscraftsman', function (Blueprint $table): void {
                $table->dropColumn('label_tukang');
            });
        }
    }
};
