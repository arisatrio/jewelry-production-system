<?php

namespace App\Http\Requests;

use App\Models\FinishingHandmade;
use App\Models\Production;
use App\Support\FinishingMaterialGoldSynchronizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreFinishingRequest extends FormRequest
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
        $spkId = $this->input('spk_id');

        $this->merge([
            'spk_id' => filled($spkId) && (int) $spkId > 0
                ? (int) $spkId
                : null,
            'craftsman_id' => filled($craftsmanId) && (int) $craftsmanId > 0
                ? (int) $craftsmanId
                : null,
            'process_name' => filled($this->input('process_name'))
                ? trim((string) $this->input('process_name'))
                : null,
            'item_category' => filled($this->input('item_category'))
                ? trim((string) $this->input('item_category'))
                : null,
            'notes' => filled($this->input('notes'))
                ? trim((string) $this->input('notes'))
                : null,
            'start_weight' => filled($this->input('start_weight'))
                ? str_replace(',', '.', trim((string) $this->input('start_weight')))
                : null,
            'finish_weight' => filled($this->input('finish_weight'))
                ? str_replace(',', '.', trim((string) $this->input('finish_weight')))
                : null,
            'shrink_tolerance' => filled($this->input('shrink_tolerance'))
                ? str_replace(',', '.', trim((string) $this->input('shrink_tolerance')))
                : null,
            'send_craftsman_date' => filled($this->input('send_craftsman_date'))
                ? trim((string) $this->input('send_craftsman_date'))
                : null,
            'received_craftsman_date' => filled($this->input('received_craftsman_date'))
                ? trim((string) $this->input('received_craftsman_date'))
                : null,
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
            'spk_id' => [
                'required',
                'integer',
                Rule::exists(Production::class, 'row_id')->where(
                    fn ($query) => $query->where('is_deleted', 0),
                ),
            ],
            'process_name' => [
                'required',
                'string',
                Rule::in(FinishingHandmade::processNameOptions()),
            ],
            'craftsman_id' => ['nullable', 'integer'],
            'send_craftsman_date' => ['nullable', 'date'],
            'received_craftsman_date' => ['nullable', 'date'],
            'item_category' => [
                'nullable',
                'string',
                Rule::in(FinishingHandmade::itemCategoryOptions()),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'start_weight' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'finish_weight' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'shrink_tolerance' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'materials' => ['nullable', 'array'],
            'materials.*.section' => [
                'required',
                'string',
                Rule::in(FinishingMaterialGoldSynchronizer::sectionKeys()),
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
            'spk_id.required' => 'SPK wajib dipilih.',
            'spk_id.exists' => 'SPK yang dipilih tidak valid.',
            'process_name.required' => 'Proses wajib dipilih.',
            'process_name.in' => 'Proses tidak valid.',
            'item_category.in' => 'Kategori tidak valid.',
            'start_weight.numeric' => 'Berat awal harus berupa angka.',
            'finish_weight.numeric' => 'Berat akhir harus berupa angka.',
            'shrink_tolerance.numeric' => 'Toleransi susut harus berupa angka.',
            'materials.*.section.required' => 'Kategori bahan emas wajib dipilih.',
            'materials.*.section.in' => 'Kategori bahan emas tidak valid.',
            'materials.*.materialgold_id.required' => 'Bahan emas wajib dipilih.',
            'materials.*.weight.required' => 'Berat bahan emas wajib diisi.',
            'materials.*.weight.numeric' => 'Berat bahan emas harus berupa angka.',
        ];
    }
}
