<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class FakturBarangJadiWorkflowStatus
{
    public const CREATED = 'created';

    public const SENT = 'sent';

    public const RECEIVED = 'received';

    /**
     * @var array<string, string>
     */
    public const LABELS = [
        self::CREATED => 'Faktur dibuat',
        self::SENT => 'Dikirim',
        self::RECEIVED => 'Diterima',
    ];

    public static function columnAvailable(): bool
    {
        return Schema::connection('third')->hasColumn('serah_terima_faktur_barang_jadi', 'workflow_status');
    }

    public static function normalize(mixed $value): string
    {
        $status = strtolower(trim((string) $value));

        return match ($status) {
            self::SENT, self::RECEIVED => $status,
            default => self::CREATED,
        };
    }

    public static function label(?string $status): string
    {
        $normalized = self::normalize($status);

        return self::LABELS[$normalized] ?? self::LABELS[self::CREATED];
    }
}
