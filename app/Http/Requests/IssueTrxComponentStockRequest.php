<?php

namespace App\Http\Requests;

use App\Models\Production;
use App\Models\SpkComponent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueTrxComponentStockRequest extends FormRequest
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

        if ($this->has('spk_chain_line_id')) {
            $lineId = $this->input('spk_chain_line_id');

            $this->merge([
                'spk_chain_line_id' => filled($lineId) && (int) $lineId > 0
                    ? (int) $lineId
                    : null,
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
            'spk_chain_line_id' => [
                'nullable',
                'integer',
                Rule::exists(SpkComponent::class, 'line_id')->where(function ($query): void {
                    $query->where('is_deleted', 0);

                    $spkId = $this->input('spk_id');

                    if (filled($spkId) && (int) $spkId > 0) {
                        $query->where('row_id', (int) $spkId);
                    }
                }),
            ],
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
