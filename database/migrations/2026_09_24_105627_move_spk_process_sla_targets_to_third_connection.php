<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pindahkan spk_process_sla_targets dari DB default ke koneksi third
     * untuk environment yang sudah pernah menjalankan migration create lama.
     */
    public function up(): void
    {
        $third = Schema::connection('third');

        if (! $third->hasTable('spk_process_sla_targets')) {
            $third->create('spk_process_sla_targets', function (Blueprint $table): void {
                $table->id();
                $table->string('process_key', 64)->unique();
                $table->unsignedSmallInteger('working_days');
                $table->string('updated_by', 100)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('spk_process_sla_targets')) {
            return;
        }

        $rows = DB::table('spk_process_sla_targets')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        if ($rows !== []) {
            DB::connection('third')
                ->table('spk_process_sla_targets')
                ->insertOrIgnore($rows);
        }

        Schema::dropIfExists('spk_process_sla_targets');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('spk_process_sla_targets')) {
            Schema::create('spk_process_sla_targets', function (Blueprint $table): void {
                $table->id();
                $table->string('process_key', 64)->unique();
                $table->unsignedSmallInteger('working_days');
                $table->string('updated_by', 100)->nullable();
                $table->timestamps();
            });
        }

        $third = Schema::connection('third');

        if (! $third->hasTable('spk_process_sla_targets')) {
            return;
        }

        $rows = DB::connection('third')
            ->table('spk_process_sla_targets')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        if ($rows !== []) {
            DB::table('spk_process_sla_targets')->insertOrIgnore($rows);
        }

        $third->dropIfExists('spk_process_sla_targets');
    }
};
