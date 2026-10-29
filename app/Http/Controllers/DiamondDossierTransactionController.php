<?php

namespace App\Http\Controllers;

use App\Support\DiamondDossierTransactionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DiamondDossierTransactionController extends Controller
{
    public function index(Request $request, DiamondDossierTransactionLog $log): Response
    {
        $filters = Validator::make($request->only([
            'search',
            'date_from',
            'date_to',
            'event_type',
            'per_page',
        ]), [
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'event_type' => ['nullable', Rule::in([
                DiamondDossierTransactionLog::EVENT_ENTRY,
                DiamondDossierTransactionLog::EVENT_OUT,
            ])],
            'per_page' => ['nullable', 'integer'],
        ])->validate();

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

        return Inertia::render('inventory/batu-dossier/transactions/index', [
            'transactions' => $log->tableAvailable()
                ? $log->paginate($search, $dateFrom, $dateTo, $eventType, $perPage)
                : $this->emptyPaginator($perPage),
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'event_type' => $eventType,
                'per_page' => $perPage,
            ],
        ]);
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
