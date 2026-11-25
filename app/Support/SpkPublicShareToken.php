<?php

namespace App\Support;

use App\Models\Production;

class SpkPublicShareToken
{
    private const SIGNATURE_LENGTH = 16;

    public static function forProduction(Production $production, ?int $sharedByUserId = null): string
    {
        $payload = (string) $production->row_id;

        if ($sharedByUserId !== null && $sharedByUserId > 0) {
            $payload .= '.'.(string) $sharedByUserId;
        }

        return $payload.'.'.self::signatureForPayload($payload);
    }

    /**
     * @return array{rowId: int, sharedByUserId: int|null}|null
     */
    public static function resolve(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) < 2 || count($parts) > 3) {
            return null;
        }

        $signature = array_pop($parts);

        if (! is_string($signature) || ! preg_match('/^[a-f0-9]{'.self::SIGNATURE_LENGTH.'}$/', $signature)) {
            return null;
        }

        $payload = implode('.', $parts);

        if ($payload === '' || ! preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $payload)) {
            return null;
        }

        if (! hash_equals(self::signatureForPayload($payload), $signature)) {
            return null;
        }

        $payloadParts = explode('.', $payload);

        if (count($payloadParts) === 1) {
            return [
                'rowId' => (int) $payloadParts[0],
                'sharedByUserId' => null,
            ];
        }

        if (count($payloadParts) === 2) {
            return [
                'rowId' => (int) $payloadParts[0],
                'sharedByUserId' => (int) $payloadParts[1],
            ];
        }

        return null;
    }

    public static function resolveProduction(string $token): ?Production
    {
        $resolved = self::resolve($token);

        if ($resolved === null) {
            return null;
        }

        return Production::query()
            ->notDeleted()
            ->where('row_id', $resolved['rowId'])
            ->whereNotNull('spk_no')
            ->first();
    }

    public static function resolveSharedByUserId(string $token): ?int
    {
        $resolved = self::resolve($token);

        return $resolved['sharedByUserId'] ?? null;
    }

    public static function resolveRowId(string $token): ?int
    {
        $resolved = self::resolve($token);

        return $resolved['rowId'] ?? null;
    }

    private static function signatureForPayload(string $payload): string
    {
        return substr(
            hash_hmac('sha256', $payload, self::key()),
            0,
            self::SIGNATURE_LENGTH,
        );
    }

    private static function key(): string
    {
        $key = (string) config('app.key');

        return $key !== '' ? $key : 'spk-public-share';
    }
}
