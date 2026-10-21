<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public const TRANSTYPE_ID = 18;

    public function up(): void
    {
        if (! Schema::connection('third')->hasTable('mstranstype')) {
            return;
        }

        $exists = DB::connection('third')
            ->table('mstranstype')
            ->where('row_id', self::TRANSTYPE_ID)
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();

        DB::connection('third')->table('mstranstype')->insert([
            'row_id' => self::TRANSTYPE_ID,
            'name' => 'SPK Diamond - Keluar',
            'table_name' => 'spk_diamond',
            'in_out' => 'OUT',
            'is_deleted' => 0,
            'created_date' => $now,
            'created_by' => 'system',
            'modified_date' => $now,
            'modified_by' => 'system',
            'deleted_date' => null,
            'deleted_by' => null,
        ]);
    }

    public function down(): void
    {
        if (! Schema::connection('third')->hasTable('mstranstype')) {
            return;
        }

        DB::connection('third')
            ->table('mstranstype')
            ->where('row_id', self::TRANSTYPE_ID)
            ->delete();
    }
};
