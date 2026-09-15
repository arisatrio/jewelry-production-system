<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSpkProcessSlaRequest;
use App\Support\SpkProcessSlaResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpkProcessSlaController extends Controller
{
    public function __construct(
        private readonly SpkProcessSlaResolver $slaResolver,
    ) {}

    /**
     * Halaman pengaturan SLA target hari kerja per proses produksi SPK.
     */
    public function edit(): Response
    {
        $targets = $this->slaResolver->forSettings();

        return Inertia::render('master-data/spk-process-sla/edit', [
            'targets' => $targets,
            'totalWorkingDays' => array_sum(array_column($targets, 'workingDays')),
        ]);
    }

    /**
     * Simpan SLA target hari kerja untuk semua proses.
     */
    public function update(UpdateSpkProcessSlaRequest $request): RedirectResponse
    {
        /** @var list<array{process_key: string, working_days: int}> $rows */
        $rows = $request->validated('targets');

        $mapped = [];

        foreach ($rows as $row) {
            $mapped[$row['process_key']] = (int) $row['working_days'];
        }

        $this->slaResolver->sync($mapped, $this->actorName($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'SLA target hari kerja berhasil disimpan.',
        ]);

        return to_route('master-data.spk-process-sla.edit');
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
