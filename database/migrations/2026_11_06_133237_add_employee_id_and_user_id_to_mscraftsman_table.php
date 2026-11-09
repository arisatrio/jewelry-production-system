<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link pengrajin ke pegawai dan akun user.
     *
     * employee dan users ada di koneksi default, jadi kolom ini tidak memakai
     * foreign key lintas database.
     */
    public function up(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('mscraftsman')) {
            return;
        }

        if (! $schema->hasColumn('mscraftsman', 'employee_id')) {
            $schema->table('mscraftsman', function (Blueprint $table): void {
                $table->unsignedBigInteger('employee_id')->nullable()->index()->after('name');
            });
        }

        if (! $schema->hasColumn('mscraftsman', 'user_id')) {
            $schema->table('mscraftsman', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->nullable()->index()->after('employee_id');
            });
        }
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

        $columns = array_values(array_filter(
            ['user_id', 'employee_id'],
            fn (string $column): bool => $schema->hasColumn('mscraftsman', $column),
        ));

        if ($columns === []) {
            return;
        }

        $schema->table('mscraftsman', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
