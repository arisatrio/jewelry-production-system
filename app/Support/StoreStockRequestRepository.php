<?php

namespace App\Support;

use App\Exceptions\StoreStockSpkSyncException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class StoreStockRequestRepository
{
    private const PENDING_COUNT_CACHE_KEY = 'store-stock-requests:approved-count';

    private const PENDING_COUNT_CACHE_SECONDS = 60;

    private const FINAL_APPROVAL_SEQUENCE = 2;

    /**
     * Jumlah request stok approved dari Store (API Store); null jika API tidak dapat diakses.
     */
    public function pendingSpkCount(): ?int
    {
        $cached = Cache::get(self::PENDING_COUNT_CACHE_KEY);

        if (is_int($cached)) {
            return $cached;
        }

        try {
            $total = (int) data_get($this->fetchApproved(['page' => 1, 'per_page' => 1]), 'meta.total', 0);
        } catch (Throwable $exception) {
            Log::warning('Gagal mengambil jumlah request stok dari API Store.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        Cache::put(self::PENDING_COUNT_CACHE_KEY, $total, self::PENDING_COUNT_CACHE_SECONDS);

        return $total;
    }

    /**
     * Daftar request stok approved dari Store (API Store) untuk modal di halaman index SPK.
     *
     * @return array{
     *     data: list<array{rowId: int, docNo: string, transDate: string, transDateIso: string|null, estimatedDate: string, estimatedDateIso: string|null, targetDaysLeft: int|null, store: string, item: string, refSku: string|null, typeOrder: string|null, status: string|null, approvedAt: string|null, notes: string|null, imageUrl: string|null, goldInfo: string|null, goldWeight: string|null, createdBy: string|null}>,
     *     meta: array{currentPage: int, lastPage: int, perPage: int, total: int}
     * }
     *
     * @throws RuntimeException
     */
    public function paginatePendingSpk(string $search = '', int $page = 1, int $perPage = 25): array
    {
        $payload = $this->fetchApproved(array_filter([
            'page' => max(1, $page),
            'per_page' => $perPage,
            'search' => $search !== '' ? $search : null,
            'sort_by' => 'created_date',
            'sort_order' => 'desc',
        ], fn (mixed $value): bool => $value !== null));

        $rows = data_get($payload, 'data', []);
        $total = (int) data_get($payload, 'meta.total', 0);

        if ($search === '') {
            Cache::put(self::PENDING_COUNT_CACHE_KEY, $total, self::PENDING_COUNT_CACHE_SECONDS);
        }

        return [
            'data' => array_values(array_map(
                fn (array $row): array => $this->toListRow($row),
                is_array($rows) ? array_filter($rows, is_array(...)) : [],
            )),
            'meta' => [
                'currentPage' => (int) data_get($payload, 'meta.current_page', $page),
                'lastPage' => max(1, (int) data_get($payload, 'meta.last_page', 1)),
                'perPage' => (int) data_get($payload, 'meta.per_page', $perPage),
                'total' => $total,
            ],
        ];
    }

    /**
     * @param  array<string, int|string>  $parameters
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    private function fetchApproved(array $parameters): array
    {
        try {
            $payload = $this->client()
                ->post('request/stock/list/approved', $parameters)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException('API Store merespons dengan error HTTP '.$exception->response->status().'.', previous: $exception);
        }

        if (! is_array($payload)) {
            throw new RuntimeException('Respons API Store tidak valid.');
        }

        return $payload;
    }

    /**
     * Kirim nomor SPK yang baru dibuat ke dokumen request stok di Store.
     *
     * @throws StoreStockSpkSyncException
     */
    public function assignSpkNumber(string $docNo, string $spkNo, string $modifiedBy): void
    {
        try {
            $this->client()
                ->connectTimeout(3)
                ->post('production/spk/update-spk-no', [
                    'doc_no' => $docNo,
                    'spk_no' => $spkNo,
                    'modified_by' => $modifiedBy,
                ])
                ->throw();
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('Gagal mengirim nomor SPK ke API Store.', [
                'doc_no' => $docNo,
                'spk_no' => $spkNo,
                'message' => $exception->getMessage(),
            ]);

            throw StoreStockSpkSyncException::failed($exception);
        }
    }

    /**
     * @throws RuntimeException
     */
    private function client(): PendingRequest
    {
        $apiKey = (string) config('services.store_api.key');

        if ($apiKey === '') {
            throw new RuntimeException('STORE_API_KEY belum dikonfigurasi.');
        }

        return Http::baseUrl(rtrim((string) config('services.store_api.base_url'), '/'))
            ->withHeaders(['X-API-KEY' => $apiKey])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.store_api.timeout', 10));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{rowId: int, docNo: string, transDate: string, transDateIso: string|null, estimatedDate: string, estimatedDateIso: string|null, targetDaysLeft: int|null, store: string, item: string, refSku: string|null, typeOrder: string|null, status: string|null, approvedAt: string|null, notes: string|null, imageUrl: string|null, goldInfo: string|null, goldWeight: string|null, createdBy: string|null}
     */
    private function toListRow(array $row): array
    {
        return [
            'rowId' => (int) ($row['row_id'] ?? 0),
            'docNo' => (string) ($row['doc_no'] ?? '-'),
            'transDate' => $this->displayDate($row['trans_date'] ?? null),
            'transDateIso' => $this->isoDate($row['trans_date'] ?? null),
            'estimatedDate' => $this->displayDate($row['estimated_date'] ?? null),
            'estimatedDateIso' => $this->isoDate($row['estimated_date'] ?? null),
            'targetDaysLeft' => $this->daysLeft($row['estimated_date'] ?? null),
            'store' => $this->filledString(data_get($row, 'store.name')) ?? '-',
            'item' => $this->filledString($row['nama_item'] ?? null) ?? '-',
            'refSku' => $this->filledString($row['ref_sku'] ?? null),
            'typeOrder' => $this->filledString($row['type_order'] ?? null),
            'status' => $this->statusLabel($row),
            'approvedAt' => $this->approvedAt($row),
            'notes' => $this->filledString($row['notes'] ?? null),
            'imageUrl' => $this->filledString($row['photo_file'] ?? null) ?? $this->filledString(data_get($row, 'sku.image_url')),
            'goldInfo' => $this->goldInfo($row),
            'goldWeight' => is_numeric($row['berat_emas'] ?? null) && (float) $row['berat_emas'] > 0
                ? number_format((float) $row['berat_emas'], 2, '.', '')
                : null,
            'createdBy' => $this->filledString($row['created_by'] ?? null),
        ];
    }

    /**
     * "Approved by {approver}" dari approval sequence 2 (approval final di Store).
     *
     * @param  array<string, mixed>  $row
     */
    private function statusLabel(array $row): ?string
    {
        $finalApproval = $this->finalApproval($row);
        $approverName = $this->filledString(data_get($finalApproval, 'approver.name'))
            ?? $this->filledString(data_get($finalApproval, 'modified_by'));

        if ($finalApproval !== null && $approverName !== null) {
            return "Approved by {$approverName}";
        }

        return $this->filledString($row['status_info'] ?? null) ?? $this->filledString($row['status'] ?? null);
    }

    /**
     * Waktu approval final (sequence 2) dalam format d-M-Y H:i.
     *
     * @param  array<string, mixed>  $row
     */
    private function approvedAt(array $row): ?string
    {
        $finalApproval = $this->finalApproval($row);
        $approvalDate = $this->filledString(data_get($finalApproval, 'approval_date'))
            ?? $this->filledString(data_get($finalApproval, 'modified_date'));

        if ($approvalDate === null) {
            return null;
        }

        try {
            return Carbon::parse($approvalDate)->format('d-M-Y H:i');
        } catch (Throwable) {
            return $approvalDate;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function finalApproval(array $row): ?array
    {
        return collect(is_array($row['approvals'] ?? null) ? $row['approvals'] : [])
            ->filter(fn (mixed $approval): bool => is_array($approval)
                && (int) ($approval['sequence'] ?? 0) === self::FINAL_APPROVAL_SEQUENCE
                && (int) ($approval['is_deleted'] ?? 0) === 0)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function goldInfo(array $row): ?string
    {
        $parts = array_filter([
            $this->filledString($row['warna_emas'] ?? null),
            $this->filledString($row['kadar_emas'] ?? null),
            $this->filledString($row['berat_emas'] ?? null) !== null ? $row['berat_emas'].' gr' : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function filledString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function daysLeft(mixed $value): ?int
    {
        $date = $this->filledString($value);

        if ($date === null) {
            return null;
        }

        try {
            return (int) now()->startOfDay()->diffInDays(Carbon::parse($date)->startOfDay(), false);
        } catch (Throwable) {
            return null;
        }
    }

    private function displayDate(mixed $value): string
    {
        $date = $this->filledString($value);

        if ($date === null) {
            return '-';
        }

        try {
            return Carbon::parse($date)->format('d-M-Y');
        } catch (Throwable) {
            return $date;
        }
    }

    private function isoDate(mixed $value): ?string
    {
        $date = $this->filledString($value);

        if ($date === null) {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
