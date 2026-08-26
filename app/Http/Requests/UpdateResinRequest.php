<?php

namespace App\Http\Requests;

use App\Models\MsShape;
use App\Models\Production;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResinRequest extends FormRequest
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
        $stones = collect($this->input('stones', []))
            ->map(function (mixed $stone): array {
                $row = is_array($stone) ? $stone : [];

                return [
                    'shape_id' => isset($row['shape_id']) && $row['shape_id'] !== ''
                        ? (int) $row['shape_id']
                        : null,
                    'pcs' => isset($row['pcs']) && $row['pcs'] !== ''
                        ? (int) $row['pcs']
                        : null,
                    'carat' => isset($row['carat']) && $row['carat'] !== ''
                        ? (int) $row['carat']
                        : null,
                    'size' => filled($row['size'] ?? null)
                        ? trim((string) $row['size'])
                        : null,
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'spk_id' => $this->filled('spk_id') ? (int) $this->input('spk_id') : null,
            'stones' => $stones,
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
            'doc_no' => ['required', 'string', 'max:50'],
            'trans_date' => ['required', 'date'],
            'spk_id' => [
                'required',
                'integer',
                Rule::exists(Production::class, 'row_id')->where(
                    fn ($query) => $query->where('is_deleted', 0),
                ),
            ],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
            'stones' => ['nullable', 'array'],
            'stones.*.shape_id' => [
                'nullable',
                'integer',
                Rule::exists(MsShape::class, 'row_id')->where(
                    fn ($query) => $query->where('is_deleted', 0),
                ),
            ],
            'stones.*.pcs' => ['nullable', 'integer', 'min:0'],
            'stones.*.carat' => ['nullable', 'integer', 'min:0'],
            'stones.*.size' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doc_no.required' => 'Nomor dokumen wajib diisi.',
            'trans_date.required' => 'Tanggal resin wajib diisi.',
            'spk_id.required' => 'SPK wajib dipilih.',
            'spk_id.exists' => 'SPK yang dipilih tidak valid.',
            'file.mimes' => 'Format file harus jpg, jpeg, png, pdf, atau webp.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
            'stones.*.shape_id.exists' => 'Shape batu tidak valid.',
            'stones.*.pcs.min' => 'Pcs tidak boleh negatif.',
            'stones.*.carat.min' => 'Carat tidak boleh negatif.',
            'stones.*.size.numeric' => 'Size harus berupa angka.',
        ];
    }
}
