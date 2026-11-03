<?php

namespace App\Http\Controllers;

use App\Support\SpkStoneOrderingList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class SpkStonePurchaseController extends Controller
{
    public function index(Request $request, SpkStoneOrderingList $orderingList): Response
    {
        $filters = Validator::make($request->only([
            'search',
            'per_page',
            'page',
        ]), [
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 25);
        $page = (int) ($filters['page'] ?? 1);

        return Inertia::render('inventory/pembelian-batu/index', [
            'stones' => $orderingList->paginate($search, $perPage, $page),
            'listAvailable' => $orderingList->listAvailable(),
            'filters' => [
                'search' => $search,
                'per_page' => in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25,
            ],
        ]);
    }
}
