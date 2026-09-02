<?php

namespace App\Http\Requests;

use App\Models\Production;
use App\Models\ResinDetail;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResinProgressRequest extends FormRequest
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
                        ? trim((string) $row['berat_resin'])
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
            'details.required' => 'Minimal satu SPK wajib ada.',
            'details.min' => 'Minimal satu SPK wajib ada.',
            'details.*.spk_id.required' => 'SPK wajib dipilih.',
            'details.*.spk_id.distinct' => 'SPK tidak boleh duplikat.',
            'details.*.spk_id.exists' => 'SPK yang dipilih tidak valid.',
            'details.*.berat_resin.numeric' => 'Berat resin harus berupa angka.',
            'details.*.status_resin.in' => 'Status resin tidak valid.',
            'details.*.catatan.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
