<?php

namespace App\Http\Requests;

use App\Models\DiamondMounting;
use App\Models\Production;
use App\Models\SpkDiamond;
use App\Support\DiamondDossierInventory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreDiamondMountingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $craftsmanId = $this->input('craftsman_id');
        $spkId = $this->input('spk_id');
        $spkDiamondId = $this->input('spk_diamond_id');

        $resolvedSpkDiamondId = filled($spkDiamondId) && (int) $spkDiamondId > 0
            ? (int) $spkDiamondId
            : null;

        if ($resolvedSpkDiamondId !== null && (! filled($spkId) || (int) $spkId <= 0)) {
            $spkId = SpkDiamond::query()
                ->notDeleted()
                ->whereKey($resolvedSpkDiamondId)
                ->value('spk_id');
        }

        $this->merge([
            'spk_diamond_id' => $resolvedSpkDiamondId,
            'spk_id' => filled($spkId) && (int) $spkId > 0
                ? (int) $spkId
                : null,
            'process_name' => filled($this->input('process_name'))
                ? trim((string) $this->input('process_name'))
                : null,
            'craftsman_id' => filled($craftsmanId) && (int) $craftsmanId > 0
                ? (int) $craftsmanId
                : null,
            'notes' => filled($this->input('notes'))
                ? trim((string) $this->input('notes'))
                : null,
            'weight_frame' => filled($this->input('weight_frame'))
                ? str_replace(',', '.', trim((string) $this->input('weight_frame')))
                : null,
            'weight_diamond' => filled($this->input('weight_diamond'))
                ? str_replace(',', '.', trim((string) $this->input('weight_diamond')))
                : null,
            'weight_finish_goods' => filled($this->input('weight_finish_goods'))
                ? str_replace(',', '.', trim((string) $this->input('weight_finish_goods')))
                : null,
            'send_craftsman_date' => filled($this->input('send_craftsman_date'))
                ? trim((string) $this->input('send_craftsman_date'))
                : null,
            'received_craftsman_date' => filled($this->input('received_craftsman_date'))
                ? trim((string) $this->input('received_craftsman_date'))
                : null,
            'setting_stones' => $this->normalizeStoneLines($this->input('setting_stones', [])),
            'return_stones' => $this->normalizeStoneLines($this->input('return_stones', [])),
            'diamonds' => $this->normalizeDiamondLines($this->input('diamonds', [])),
            'mounted_stones' => $this->normalizeMountedLines($this->input('mounted_stones', [])),
            'setting_awal_lines' => $this->normalizeSettingAwalLines($this->input('setting_awal_lines', [])),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'process_name' => [
                'nullable',
                'string',
                Rule::in(DiamondMounting::processNameOptions()),
            ],
            'spk_diamond_id' => ['nullable', 'integer', 'min:1'],
            'spk_id' => [
                'required_without:spk_diamond_id',
                'nullable',
                'integer',
                Rule::exists(Production::class, 'row_id')->where(
                    fn ($query) => $query->where('is_deleted', 0),
                ),
            ],
            'craftsman_id' => ['nullable', 'integer'],
            'send_craftsman_date' => ['nullable', 'date_format:Y-m-d H:i'],
            'received_craftsman_date' => ['nullable', 'date_format:Y-m-d H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'weight_frame' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'weight_diamond' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'weight_finish_goods' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'setting_stones' => ['nullable', 'array'],
            'setting_stones.*.stone_id' => ['required', 'integer', 'min:1'],
            'setting_stones.*.pcs' => ['nullable', 'numeric', 'min:0'],
            'setting_stones.*.crt' => ['nullable', 'numeric', 'min:0'],
            'setting_stones.*.notes' => ['nullable', 'string', 'max:500'],
            'return_stones' => ['nullable', 'array'],
            'return_stones.*.stone_id' => ['required', 'integer', 'min:1'],
            'return_stones.*.pcs' => ['nullable', 'numeric', 'min:0'],
            'return_stones.*.crt' => ['nullable', 'numeric', 'min:0'],
            'return_stones.*.notes' => ['nullable', 'string', 'max:500'],
            'diamonds' => ['nullable', 'array'],
            'diamonds.*.diamond_id' => ['required', 'integer', 'min:1', 'distinct'],
            'mounted_stones' => ['nullable', 'array'],
            'mounted_stones.*.diamond_code' => ['nullable', 'string', 'max:150'],
            'mounted_stones.*.shape_id' => ['nullable', 'integer', 'min:1'],
            'mounted_stones.*.pcs' => ['nullable', 'numeric', 'min:0'],
            'mounted_stones.*.crt' => ['nullable', 'numeric', 'min:0'],
            'mounted_stones.*.size' => ['nullable', 'string', 'max:50'],
            'setting_awal_lines' => ['nullable', 'array'],
            'setting_awal_lines.*.source_type' => ['nullable', 'string', 'max:20'],
            'setting_awal_lines.*.source_line_id' => ['nullable', 'integer', 'min:1'],
            'setting_awal_lines.*.stone_id' => ['nullable', 'integer', 'min:1'],
            'setting_awal_lines.*.trdiamond_id' => ['nullable', 'integer', 'min:1'],
            'setting_awal_lines.*.pcs_terpasang' => ['nullable', 'numeric', 'min:0'],
            'setting_awal_lines.*.crt_terpasang' => ['nullable', 'numeric', 'min:0'],
            'setting_awal_lines.*.pcs_retur' => ['nullable', 'numeric', 'min:0'],
            'setting_awal_lines.*.crt_retur' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array{
     *     setting: list<array<string, mixed>>,
     *     return: list<array<string, mixed>>,
     *     diamonds: list<array<string, mixed>>,
     *     mounted: list<array<string, mixed>>
     * }
     */
    public function stonePayload(): array
    {
        return [
            'setting' => $this->input('setting_stones', []),
            'return' => $this->input('return_stones', []),
            'diamonds' => $this->input('diamonds', []),
            'mounted' => $this->input('mounted_stones', []),
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $craftsmanId = $this->integer('craftsman_id');

                if ($craftsmanId > 0) {
                    if (! Schema::connection('third')->hasTable('mscraftsman')) {
                        $validator->errors()->add('craftsman_id', 'Pengrajin tidak valid.');
                    } else {
                        $exists = DB::connection('third')
                            ->table('mscraftsman')
                            ->where('row_id', $craftsmanId)
                            ->where('is_deleted', 0)
                            ->exists();

                        if (! $exists) {
                            $validator->errors()->add('craftsman_id', 'Pengrajin tidak valid.');
                        }
                    }
                }

                $this->validateSpkDiamond($validator);
                $this->validateStoneIds($validator, 'setting_stones');
                $this->validateStoneIds($validator, 'return_stones');
                $this->validateDiamondIds($validator);
                $this->validateShapeIds($validator, 'mounted_stones');
            },
        ];
    }

    private function validateSpkDiamond(Validator $validator): void
    {
        $spkDiamondId = $this->integer('spk_diamond_id');

        if ($spkDiamondId <= 0) {
            return;
        }

        $spkDiamond = SpkDiamond::query()
            ->notDeleted()
            ->whereKey($spkDiamondId)
            ->first();

        if ($spkDiamond === null) {
            $validator->errors()->add('spk_diamond_id', 'SPK Diamond tidak valid.');

            return;
        }

        if (! $spkDiamond->isSubmitted()) {
            $validator->errors()->add('spk_diamond_id', 'SPK Diamond harus berstatus kirim ke produksi.');

            return;
        }

        $hasMounting = DiamondMounting::query()
            ->notDeleted()
            ->where('spk_diamond_id', $spkDiamondId)
            ->exists();

        if ($hasMounting) {
            $validator->errors()->add('spk_diamond_id', 'SPK Diamond sudah memiliki dokumen Pasang Batu.');
        }

        $spkId = $this->integer('spk_id');

        if ($spkId > 0 && $spkId !== (int) $spkDiamond->spk_id) {
            $validator->errors()->add('spk_id', 'SPK produksi tidak sesuai dengan SPK Diamond.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'spk_id.required' => 'SPK wajib dipilih.',
            'spk_id.exists' => 'SPK yang dipilih tidak valid.',
            'weight_frame.numeric' => 'Berat rangka harus berupa angka.',
            'weight_diamond.numeric' => 'Berat batu harus berupa angka.',
            'weight_finish_goods.numeric' => 'Berat akhir harus berupa angka.',
            'setting_stones.*.stone_id.required' => 'Batu setting wajib dipilih.',
            'return_stones.*.stone_id.required' => 'Batu retur wajib dipilih.',
            'diamonds.*.diamond_id.required' => 'Batu Dossier wajib dipilih.',
            'diamonds.*.diamond_id.distinct' => 'Batu Dossier tidak boleh dipilih lebih dari sekali.',
        ];
    }

    /**
     * @return list<array{stone_id: int, pcs: string|null, crt: string|null, notes: string|null}>
     */
    private function normalizeStoneLines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        $normalized = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $stoneId = (int) ($line['stone_id'] ?? 0);

            if ($stoneId <= 0) {
                continue;
            }

            $normalized[] = [
                'stone_id' => $stoneId,
                'pcs' => filled($line['pcs'] ?? null)
                    ? str_replace(',', '.', trim((string) $line['pcs']))
                    : null,
                'crt' => filled($line['crt'] ?? null)
                    ? str_replace(',', '.', trim((string) $line['crt']))
                    : null,
                'notes' => filled($line['notes'] ?? null)
                    ? trim((string) $line['notes'])
                    : null,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{diamond_id: int}>
     */
    private function normalizeDiamondLines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        $normalized = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $diamondId = (int) ($line['diamond_id'] ?? 0);

            if ($diamondId <= 0) {
                continue;
            }

            $normalized[] = ['diamond_id' => $diamondId];
        }

        return $normalized;
    }

    /**
     * @return list<array{diamond_code: string|null, shape_id: int|null, pcs: string|null, crt: string|null, size: string|null}>
     */
    private function normalizeMountedLines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        $normalized = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $shapeId = filled($line['shape_id'] ?? null) && (int) $line['shape_id'] > 0
                ? (int) $line['shape_id']
                : null;

            $normalized[] = [
                'diamond_code' => filled($line['diamond_code'] ?? null)
                    ? trim((string) $line['diamond_code'])
                    : null,
                'shape_id' => $shapeId,
                'pcs' => filled($line['pcs'] ?? null)
                    ? str_replace(',', '.', trim((string) $line['pcs']))
                    : null,
                'crt' => filled($line['crt'] ?? null)
                    ? str_replace(',', '.', trim((string) $line['crt']))
                    : null,
                'size' => filled($line['size'] ?? null) ? trim((string) $line['size']) : null,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeSettingAwalLines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        $normalized = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $normalized[] = [
                'source_type' => filled($line['source_type'] ?? null)
                    ? strtolower(trim((string) $line['source_type']))
                    : null,
                'source_line_id' => filled($line['source_line_id'] ?? null)
                    ? (int) $line['source_line_id']
                    : null,
                'stone_id' => filled($line['stone_id'] ?? null)
                    ? (int) $line['stone_id']
                    : null,
                'trdiamond_id' => filled($line['trdiamond_id'] ?? null)
                    ? (int) $line['trdiamond_id']
                    : null,
                'pcs_terpasang' => filled($line['pcs_terpasang'] ?? null)
                    ? str_replace(',', '.', trim((string) $line['pcs_terpasang']))
                    : null,
                'crt_terpasang' => filled($line['crt_terpasang'] ?? null)
                    ? str_replace(',', '.', trim((string) $line['crt_terpasang']))
                    : null,
                'pcs_retur' => filled($line['pcs_retur'] ?? null)
                    ? str_replace(',', '.', trim((string) $line['pcs_retur']))
                    : null,
                'crt_retur' => filled($line['crt_retur'] ?? null)
                    ? str_replace(',', '.', trim((string) $line['crt_retur']))
                    : null,
            ];
        }

        return $normalized;
    }

    private function validateStoneIds(Validator $validator, string $key): void
    {
        $lines = collect($this->input($key, []));

        if ($lines->isEmpty()) {
            return;
        }

        if (! Schema::connection('third')->hasTable('msstone')) {
            $validator->errors()->add($key, 'Master batu tidak tersedia.');

            return;
        }

        $stoneIds = $lines
            ->pluck('stone_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($stoneIds === []) {
            return;
        }

        $existing = DB::connection('third')
            ->table('msstone')
            ->whereIn('row_id', $stoneIds)
            ->when(
                Schema::connection('third')->hasColumn('msstone', 'is_deleted'),
                fn ($query) => $query->where('is_deleted', 0),
            )
            ->pluck('row_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $existingLookup = array_flip($existing);

        foreach ($lines as $index => $line) {
            $stoneId = (int) ($line['stone_id'] ?? 0);

            if ($stoneId > 0 && ! isset($existingLookup[$stoneId])) {
                $validator->errors()->add("{$key}.{$index}.stone_id", 'Batu tidak valid.');
            }
        }
    }

    private function validateDiamondIds(Validator $validator): void
    {
        if ($this->integer('spk_diamond_id') > 0) {
            return;
        }

        $mountingDocument = $this->route('pasangBatu');

        if (
            $mountingDocument instanceof DiamondMounting
            && filled($mountingDocument->spk_diamond_id)
        ) {
            return;
        }

        $lines = $this->collect('diamonds');

        if ($lines->isEmpty()) {
            return;
        }

        if (! Schema::connection('third')->hasTable('trdiamond')) {
            $validator->errors()->add('diamonds', 'Data Batu Dossier tidak tersedia.');

            return;
        }

        $diamondIds = array_values($lines
            ->pluck('diamond_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->all());

        $mountingId = $mountingDocument instanceof DiamondMounting
            ? (int) $mountingDocument->row_id
            : null;

        $selectableLookup = array_flip(
            app(DiamondDossierInventory::class)->selectableIdsForMounting($diamondIds, $mountingId),
        );

        foreach ($lines as $index => $line) {
            $diamondId = (int) ($line['diamond_id'] ?? 0);

            if ($diamondId > 0 && ! isset($selectableLookup[$diamondId])) {
                $validator->errors()->add(
                    "diamonds.{$index}.diamond_id",
                    'Batu Dossier tidak tersedia atau sudah dipakai dokumen lain.',
                );
            }
        }
    }

    private function validateShapeIds(Validator $validator, string $key): void
    {
        $lines = collect($this->input($key, []));

        if ($lines->isEmpty() || ! Schema::connection('third')->hasTable('msshape')) {
            return;
        }

        $shapeIds = $lines
            ->pluck('shape_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($shapeIds === []) {
            return;
        }

        $existing = DB::connection('third')
            ->table('msshape')
            ->whereIn('row_id', $shapeIds)
            ->when(
                Schema::connection('third')->hasColumn('msshape', 'is_deleted'),
                fn ($query) => $query->where('is_deleted', 0),
            )
            ->pluck('row_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $existingLookup = array_flip($existing);

        foreach ($lines as $index => $line) {
            $shapeId = (int) ($line['shape_id'] ?? 0);

            if ($shapeId > 0 && ! isset($existingLookup[$shapeId])) {
                $validator->errors()->add("{$key}.{$index}.shape_id", 'Bentuk tidak valid.');
            }
        }
    }
}
