<?php

namespace App\Http\Requests;

use App\Models\Production;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueTrxChainStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $spkId = $this->input('spk_id');

        if ($this->has('spk_id')) {
            $this->merge([
                'spk_id' => filled($spkId) && (int) $spkId > 0
                    ? (int) $spkId
                    : null,
            ]);
        }

        if ($this->has('notes')) {
            $notes = trim((string) $this->input('notes'));
            $this->merge([
                'notes' => $notes === '' ? null : $notes,
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'out_date' => ['required', 'date'],
            'spk_id' => [
                'required',
                'integer',
                Rule::exists(Production::class, 'row_id')->where(
                    fn ($query) => $query->where('is_deleted', 0)->whereNotNull('spk_no'),
                ),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'out_date.required' => 'Tanggal keluar wajib diisi.',
            'out_date.date' => 'Tanggal keluar tidak valid.',
            'spk_id.required' => 'SPK wajib dipilih.',
            'spk_id.exists' => 'SPK yang dipilih tidak valid.',
        ];
    }
}
