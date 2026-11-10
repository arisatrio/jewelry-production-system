<?php

namespace App\Support;

use App\Models\SpkDiamond;
use Illuminate\Support\Facades\Schema;

class SpkDiamondStatus
{
    public const DRAFT = 'draft';

    public const SENT_TO_WORKSHOP = 'sent_to_workshop';

    public const RECEIVED_BY_WORKSHOP = 'received_by_workshop';

    public const INSTALLED = 'installed';

    /**
     * @return list<string>
     */
    public static function orderedStatuses(): array
    {
        return [
            self::DRAFT,
            self::SENT_TO_WORKSHOP,
            self::RECEIVED_BY_WORKSHOP,
            self::INSTALLED,
        ];
    }

    public static function label(string $status): string
    {
        return match (self::normalize($status)) {
            self::DRAFT => 'Draft',
            self::SENT_TO_WORKSHOP => 'Kirim ke Workshop',
            self::RECEIVED_BY_WORKSHOP => 'Diterima Workshop',
            self::INSTALLED => 'Terpasang',
            default => 'Draft',
        };
    }

    public static function normalize(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));

        if (in_array($normalized, self::orderedStatuses(), true)) {
            return $normalized;
        }

        return self::DRAFT;
    }

    public static function columnAvailable(): bool
    {
        $schema = Schema::connection('third');

        return $schema->hasTable('spk_diamond')
            && $schema->hasColumn('spk_diamond', 'status');
    }

    /**
     * @return array{status: string, label: string}
     */
    public static function payloadForDocument(SpkDiamond $document): array
    {
        $status = self::columnAvailable()
            ? self::normalize($document->status)
            : self::DRAFT;

        return [
            'status' => $status,
            'label' => self::label($status),
        ];
    }
}
