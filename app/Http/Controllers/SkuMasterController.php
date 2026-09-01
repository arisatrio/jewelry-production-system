<?php

namespace App\Http\Controllers;

use App\Support\SkuMasterHierarchyBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SkuMasterController extends Controller
{
    /**
     * Display Master SKU as a hierarchical folder tree by prefix.
     */
    public function index(Request $request, SkuMasterHierarchyBuilder $hierarchy): Response
    {
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('master-data/master-sku/index', [
            'tree' => $hierarchy->build($search !== '' ? $search : null),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
