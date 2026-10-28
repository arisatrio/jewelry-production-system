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

        if (! $schema->hasTable('mscomponent')) {
            return;
        }

        $schema->table('mscomponent', function (Blueprint $table) use ($schema): void {
            if ($schema->hasColumn('mscomponent', 'length')) {
                $table->dropUnique('mscomponent_category_item_code_length_unique');
                $table->dropColumn('length');
            }
        });

        $schema->table('mscomponent', function (Blueprint $table) use ($schema): void {
            if (! $schema->hasIndex('mscomponent', 'mscomponent_category_item_code_unique')) {
                $table->unique(
                    ['component_category', 'item', 'item_code'],
                    'mscomponent_category_item_code_unique',
                );
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('mscomponent')) {
            return;
        }

        $schema->table('mscomponent', function (Blueprint $table) use ($schema): void {
            if ($schema->hasIndex('mscomponent', 'mscomponent_category_item_code_unique')) {
                $table->dropUnique('mscomponent_category_item_code_unique');
            }

            if (! $schema->hasColumn('mscomponent', 'length')) {
                $table->decimal('length', 15, 2)->default(0)->after('item_code');
            }
        });

        $schema->table('mscomponent', function (Blueprint $table): void {
            $table->unique(
                ['component_category', 'item', 'item_code', 'length'],
                'mscomponent_category_item_code_length_unique',
            );
        });
    }
};
