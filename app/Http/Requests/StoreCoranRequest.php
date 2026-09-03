<?php

namespace App\Http\Requests;

use App\Models\CoranSpk;
use App\Models\Production;
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

                return [
                    'spk_id' => isset($row['spk_id']) && $row['spk_id'] !== ''
                        ? (int) $row['spk_id']
                        : null,
                    'weight' => filled($row['weight'] ?? null)
                        ? str_replace(',', '.', trim((string) $row['weight']))
                        : null,
                    'status' => CoranSpk::normalizeInputStatus($row['status'] ?? null),
                ];
            })
            ->values()
            ->all();

        $craftsmanId = $this->input('craftsman_id');

        $this->merge([
            'craftsman_id' => filled($craftsmanId) && (int) $craftsmanId > 0
                ? (int) $craftsmanId
                : null,
            'details' => $details,
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
            'details.*.status' => [
                'nullable',
                'string',
                Rule::in(CoranSpk::inputStatuses()),
            ],
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

                if ($craftsmanId <= 0) {
                    return;
                }

                if (! Schema::connection('third')->hasTable('mscraftsman')) {
                    $validator->errors()->add('craftsman_id', 'Pengrajin tidak valid.');

                    return;
                }

                $exists = DB::connection('third')
                    ->table('mscraftsman')
                    ->where('row_id', $craftsmanId)
                    ->where('is_deleted', 0)
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add('craftsman_id', 'Pengrajin tidak valid.');
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
            'details.*.status.in' => 'Status coran tidak valid.',
        ];
    }
}
