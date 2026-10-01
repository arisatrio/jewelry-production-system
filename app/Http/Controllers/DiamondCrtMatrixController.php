<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDiamondCrtMatrixRequest;
use App\Support\DiamondCrtMatrixSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiamondCrtMatrixController extends Controller
{
    public function __construct(
        private readonly DiamondCrtMatrixSettings $matrixSettings,
    ) {}

    /**
     * Halaman pengaturan matrix range CRT batu dossier per shape.
     */
    public function edit(): Response
    {
        return Inertia::render('master-data/diamond-crt-matrix/edit', [
            'shapes' => $this->matrixSettings->shapeOptions(),
            'rows' => $this->matrixSettings->rows(),
            'lastUpdate' => $this->matrixSettings->lastUpdate(),
        ]);
    }

    /**
     * Simpan seluruh matrix range CRT.
     */
    public function update(UpdateDiamondCrtMatrixRequest $request): RedirectResponse
    {
        /** @var list<array{shape_id: int|string, crt_min: float|string, crt_max: float|string}> $rows */
        $rows = $request->validated('rows');

        $this->matrixSettings->sync($rows, $this->actorName($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Matrix CRT dossier berhasil disimpan.',
        ]);

        return to_route('master-data.diamond-crt-matrix.edit');
    }

    private function actorName(Request $request): string
    {
        $user = $request->user();

        if ($user === null) {
            return 'system';
        }

        $name = trim((string) ($user->name ?? ''));

        return $name !== '' ? $name : 'system';
    }
}
