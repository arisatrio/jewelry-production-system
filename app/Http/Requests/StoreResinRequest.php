<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Production;
use App\Models\ResinDetail;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResinRequest extends FormRequest
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
                    'berat_resin' => filled($row['berat_resin'] ?? null)
                        ? str_replace(',', '.', trim((string) $row['berat_resin']))
                        : null,
                    'status_resin' => ResinDetail::normalizeInputStatus($row['status_resin'] ?? null),
                    'catatan' => filled($row['catatan'] ?? null)
                        ? trim((string) $row['catatan'])
                        : null,
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'operator' => $this->filled('operator')
                ? $this->string('operator')->trim()->toString()
                : null,
            'notes' => $this->filled('notes')
                ? $this->string('notes')->trim()->toString()
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
            'operator' => [
                'required',
                'string',
                'max:150',
                Rule::exists(Employee::class, 'nama_lengkap')
                    ->where('department_id', Employee::DEPARTMENT_PRODUCTION)
                    ->where('status', Employee::STATUS_ACTIVE)
                    ->where('is_deleted', 0),
            ],
            'trans_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.spk_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Production::class, 'row_id')->where(
                    fn ($query) => $query->where('is_deleted', 0),
                ),
            ],
            'details.*.berat_resin' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'details.*.status_resin' => [
                'nullable',
                'string',
                Rule::in(ResinDetail::inputStatuses()),
            ],
            'details.*.catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operator.required' => 'Operator resin wajib dipilih.',
            'operator.exists' => 'Operator resin tidak valid.',
            'trans_date.required' => 'Tanggal resin wajib diisi.',
            'details.required' => 'Minimal satu SPK wajib ditambahkan.',
            'details.min' => 'Minimal satu SPK wajib ditambahkan.',
            'details.*.spk_id.required' => 'SPK wajib dipilih.',
            'details.*.spk_id.distinct' => 'SPK tidak boleh duplikat.',
            'details.*.spk_id.exists' => 'SPK yang dipilih tidak valid.',
            'details.*.berat_resin.numeric' => 'Berat resin harus berupa angka.',
            'details.*.status_resin.in' => 'Status resin tidak valid.',
            'details.*.catatan.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
