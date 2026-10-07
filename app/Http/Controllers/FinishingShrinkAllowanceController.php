<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFinishingShrinkAllowanceRequest;
use App\Support\FinishingShrinkAllowanceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinishingShrinkAllowanceController extends Controller
{
    public function __construct(
        private readonly FinishingShrinkAllowanceSettings $allowanceSettings,
    ) {}

    /**
     * Halaman pengaturan matrix jatah susut proses finishing.
     */
    public function edit(): Response
    {
        return Inertia::render('master-data/finishing-shrink-allowance/edit', [
            'itemCategories' => $this->allowanceSettings->itemCategories(),
            'rows' => $this->allowanceSettings->rows(),
            'lastUpdate' => $this->allowanceSettings->lastUpdate(),
        ]);
    }

    /**
     * Simpan seluruh matrix jatah susut.
     */
    public function update(UpdateFinishingShrinkAllowanceRequest $request): RedirectResponse
    {
        /** @var list<array{work_type: string, item_category: string, allowance_percent: float|string}> $cells */
        $cells = $request->validated('cells');

        $this->allowanceSettings->sync($cells, $this->actorName($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Matrix jatah susut finishing berhasil disimpan.',
        ]);

        return to_route('master-data.finishing-shrink-allowance.edit');
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
