<?php

use App\Support\FakturBarangJadiWorkflowStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = Schema::connection('third');

        if (! $schema->hasTable('serah_terima_faktur_barang_jadi')) {
            return;
        }

        if (! $schema->hasColumn('serah_terima_faktur_barang_jadi', 'workflow_status')) {
            $schema->table('serah_terima_faktur_barang_jadi', function (Blueprint $table): void {
                $table->string('workflow_status', 20)->default(FakturBarangJadiWorkflowStatus::CREATED);
            });
        }

        if (FakturBarangJadiWorkflowStatus::columnAvailable()) {
            DB::connection('third')
                ->table('serah_terima_faktur_barang_jadi')
                ->whereNull('deleted_at')
                ->whereNotNull('diterima_oleh')
                ->where('diterima_oleh', '!=', '')
                ->update(['workflow_status' => FakturBarangJadiWorkflowStatus::RECEIVED]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('third');

        if (
            $schema->hasTable('serah_terima_faktur_barang_jadi')
            && $schema->hasColumn('serah_terima_faktur_barang_jadi', 'workflow_status')
        ) {
            $schema->table('serah_terima_faktur_barang_jadi', function (Blueprint $table): void {
                $table->dropColumn('workflow_status');
            });
        }
    }
};
