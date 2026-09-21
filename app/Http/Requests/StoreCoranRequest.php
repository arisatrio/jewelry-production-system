<?php

namespace App\Http\Requests;

use App\Models\CoranSpk;
use App\Models\Production;
use App\Support\CoranMaterialGoldSynchronizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreCoranRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare incoming values before validation.
     */
    protected function prepareForValidation(): void
    {
        $details = collect($this->input('details', []))
            ->map(function (mixed $detail): array {
                $row = is_array($detail) ? $detail : [];

                $weightRosegold = filled($row['weight_rosegold'] ?? null)
                    ? str_replace(',', '.', trim((string) $row['weight_rosegold']))
                    : null;
                $weightWhitegold = filled($row['weight_whitegold'] ?? null)
                    ? str_replace(',', '.', trim((string) $row['weight_whitegold']))
                    : null;
                $weightYellowgold = filled($row['weight_yellowgold'] ?? null)
                    ? str_replace(',', '.', trim((string) $row['weight_yellowgold']))
                    : null;

                $hasColorWeight = $weightRosegold !== null
                    || $weightWhitegold !== null
                    || $weightYellowgold !== null;

                $weight = filled($row['weight'] ?? null)
                    ? str_replace(',', '.', trim((string) $row['weight']))
                    : null;

                if ($hasColorWeight) {
                    $weight = number_format(
                        (float) ($weightRosegold ?? 0)
                        + (float) ($weightWhitegold ?? 0)
                        + (float) ($weightYellowgold ?? 0),
                        3,
                        '.',
                        '',
                    );
                }

                return [
                    'spk_id' => isset($row['spk_id']) && $row['spk_id'] !== ''
                        ? (int) $row['spk_id']
                        : null,
                    'weight' => $weight,
                    'weight_rosegold' => $weightRosegold,
                    'weight_whitegold' => $weightWhitegold,
                    'weight_yellowgold' => $weightYellowgold,
                    'kadar' => filled($row['kadar'] ?? null)
                        ? str_replace(',', '.', trim((string) $row['kadar']))
                        : null,
                    'status' => CoranSpk::normalizeInputStatus($row['status'] ?? null),
                ];
            })
            ->values()
            ->all();

        $materials = collect($this->input('materials', []))
            ->map(function (mixed $material): ?array {
                $row = is_array($material) ? $material : [];
                $section = trim((string) ($row['section'] ?? ''));
                $materialId = isset($row['materialgold_id']) && $row['materialgold_id'] !== ''
                    ? (int) $row['materialgold_id']
                    : null;
                $weight = filled($row['weight'] ?? null)
                    ? str_replace(',', '.', trim((string) $row['weight']))
                    : null;
                $notes = isset($row['notes'])
                    ? trim((string) $row['notes'])
                    : '';

                if ($section === '' && $materialId === null && $weight === null && $notes === '') {
                    return null;
                }

                return [
                    'section' => $section !== '' ? $section : null,
                    'materialgold_id' => $materialId,
                    'weight' => $weight,
                    'notes' => $notes !== '' ? $notes : null,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $craftsmanId = $this->input('craftsman_id');

        $this->merge([
            'craftsman_id' => filled($craftsmanId) && (int) $craftsmanId > 0
                ? (int) $craftsmanId
                : null,
            'details' => $details,
            'materials' => $materials,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'trans_date' => ['required', 'date'],
            'craftsman_id' => ['nullable', 'integer'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.spk_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Production::class, 'row_id')->where(
                    fn ($query) => $query->where('is_deleted', 0),
                ),
            ],
            'details.*.weight' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'details.*.weight_rosegold' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'details.*.weight_whitegold' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'details.*.weight_yellowgold' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'details.*.kadar' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'details.*.status' => [
                'nullable',
                'string',
                Rule::in(CoranSpk::inputStatuses()),
            ],
            'materials' => ['nullable', 'array'],
            'materials.*.section' => [
                'required',
                'string',
                Rule::in(CoranMaterialGoldSynchronizer::sectionKeys()),
            ],
            'materials.*.materialgold_id' => ['required', 'integer', 'min:1'],
            'materials.*.weight' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'materials.*.notes' => ['nullable', 'string', 'max:500'],
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

                $materials = collect($this->input('materials', []));

                if ($materials->isEmpty()) {
                    return;
                }

                if (! Schema::connection('third')->hasTable('msmaterialgold')) {
                    $validator->errors()->add('materials', 'Master bahan emas tidak tersedia.');

                    return;
                }

                $materialIds = $materials
                    ->pluck('materialgold_id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                if ($materialIds === []) {
                    return;
                }

                $query = DB::connection('third')
                    ->table('msmaterialgold')
                    ->whereIn('row_id', $materialIds);

                if (Schema::connection('third')->hasColumn('msmaterialgold', 'is_deleted')) {
                    $query->where('is_deleted', 0);
                }

                $validIds = $query->pluck('row_id')->map(fn (mixed $id): int => (int) $id)->all();

                foreach ($materials as $index => $material) {
                    $materialId = (int) ($material['materialgold_id'] ?? 0);

                    if ($materialId > 0 && ! in_array($materialId, $validIds, true)) {
                        $validator->errors()->add(
                            "materials.{$index}.materialgold_id",
                            'Bahan emas tidak valid.',
                        );
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'trans_date.required' => 'Tanggal coran wajib diisi.',
            'details.required' => 'Minimal satu SPK wajib ditambahkan.',
            'details.min' => 'Minimal satu SPK wajib ditambahkan.',
            'details.*.spk_id.required' => 'SPK wajib dipilih.',
            'details.*.spk_id.distinct' => 'SPK tidak boleh duplikat.',
            'details.*.spk_id.exists' => 'SPK yang dipilih tidak valid.',
            'details.*.weight.numeric' => 'Berat coran harus berupa angka.',
            'details.*.weight_rosegold.numeric' => 'Berat Rose Gold harus berupa angka.',
            'details.*.weight_whitegold.numeric' => 'Berat White Gold harus berupa angka.',
            'details.*.weight_yellowgold.numeric' => 'Berat Yellow Gold harus berupa angka.',
            'details.*.kadar.numeric' => 'Kadar harus berupa angka.',
            'details.*.status.in' => 'Status coran tidak valid.',
            'materials.*.section.required' => 'Kategori bahan emas wajib dipilih.',
            'materials.*.section.in' => 'Kategori bahan emas tidak valid.',
            'materials.*.materialgold_id.required' => 'Bahan emas wajib dipilih.',
            'materials.*.weight.required' => 'Berat bahan emas wajib diisi.',
            'materials.*.weight.numeric' => 'Berat bahan emas harus berupa angka.',
        ];
    }
}
