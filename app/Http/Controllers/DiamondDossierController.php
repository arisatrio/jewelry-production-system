<?php

namespace App\Http\Controllers;

use App\Support\DiamondDossierInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DiamondDossierController extends Controller
{
    public function index(Request $request, DiamondDossierInventory $inventory): Response
    {
        $filters = Validator::make($request->only([
            'search',
            'status',
            'per_page',
        ]), [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                DiamondDossierInventory::STATUS_AVAILABLE,
                DiamondDossierInventory::STATUS_USED,
            ])],
            'per_page' => ['nullable', 'integer'],
        ])->validate();

        $search = trim((string) ($filters['search'] ?? ''));
        $status = filled($filters['status'] ?? null)
            ? (string) $filters['status']
            : null;
        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        return Inertia::render('inventory/batu-dossier/index', [
            'diamonds' => $inventory->paginate($search, $status, $perPage),
            'summary' => $inventory->summary($search, $status),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
    }
}
