<?php

namespace App\Http\Controllers;

use App\Support\DiamondDossierTransactionLog;
use App\Support\DiamondDossierTransactionLogExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DiamondDossierTransactionController extends Controller
{
    public function index(Request $request, DiamondDossierTransactionLog $log): Response
    {
        $filters = $this->validatedFilters($request, includePerPage: true);
        $perPage = $filters['per_page'];

        return Inertia::render('inventory/batu-dossier/transactions/index', [
            'transactions' => $log->tableAvailable()
                ? $log->paginate(
                    $filters['search'],
                    $filters['date_from'],
                    $filters['date_to'],
                    $filters['event_type'],
                    $perPage,
                )
                : $this->emptyPaginator($perPage),
            'filters' => [
                'search' => $filters['search'],
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'event_type' => $filters['event_type'],
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request, DiamondDossierTransactionLog $log): BinaryFileResponse
    {
        $filters = $this->validatedFilters($request, includePerPage: false);

        $rows = $log->tableAvailable()
            ? $log->exportRows(
                $filters['search'],
                $filters['date_from'],
                $filters['date_to'],
                $filters['event_type'],
            )
            : [];

        $fileNameParts = array_filter([
            'log-transaksi-batu-dossier',
            $filters['event_type'] !== null
                ? Str::slug(DiamondDossierTransactionLog::eventLabel($filters['event_type']))
                : null,
            $filters['date_from'] !== null && $filters['date_to'] !== null
                ? "{$filters['date_from']}_{$filters['date_to']}"
                : ($filters['date_from'] ?? $filters['date_to']),
            $filters['search'] !== ''
                ? Str::slug(Str::limit($filters['search'], 40, ''))
                : null,
        ]);

        return Excel::download(
            new DiamondDossierTransactionLogExport($rows),
            implode('-', $fileNameParts).'.xlsx',
        );
    }

    /**
     * @return array{
     *     search: string,
     *     date_from: string|null,
     *     date_to: string|null,
     *     event_type: string|null,
     *     per_page: int
     * }
     */
    private function validatedFilters(Request $request, bool $includePerPage): array
    {
        $rules = [
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'event_type' => ['nullable', Rule::in([
                DiamondDossierTransactionLog::EVENT_ENTRY,
                DiamondDossierTransactionLog::EVENT_OUT,
            ])],
        ];

        if ($includePerPage) {
            $rules['per_page'] = ['nullable', 'integer'];
        }

        $filters = Validator::make($request->only(array_keys($rules)), $rules)->validate();

        $search = trim((string) ($filters['search'] ?? ''));
        $dateFrom = filled($filters['date_from'] ?? null)
            ? (string) $filters['date_from']
            : null;
        $dateTo = filled($filters['date_to'] ?? null)
            ? (string) $filters['date_to']
            : null;
        $eventType = filled($filters['event_type'] ?? null)
            ? (string) $filters['event_type']
            : null;
        $perPage = (int) ($filters['per_page'] ?? 50);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;

        return [
            'search' => $search,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'event_type' => $eventType,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array{
     *     data: array<int, mixed>,
     *     current_page: int,
     *     last_page: int,
     *     per_page: int,
     *     total: int
     * }
     */
    private function emptyPaginator(int $perPage): array
    {
        return [
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
        ];
    }
}
