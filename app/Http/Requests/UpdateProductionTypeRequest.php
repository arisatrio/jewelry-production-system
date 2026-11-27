<?php

namespace App\Http\Requests;

use App\Models\Production;
use App\Support\RequestOrderRepository;
use App\Support\SpkService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('request_stock_no')) {
            $this->merge([
                'request_stock_no' => strtoupper($this->string('request_stock_no')->trim()->toString()),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = (string) $this->input('spk_type');
        $needsReference = in_array($type, SpkService::REFERENCE_TYPES, true);

        return [
            'spk_type' => ['required', 'string', Rule::in(SpkService::TYPES)],
            'request_order_no' => [
                Rule::requiredIf($type === 'Pesanan'),
                'nullable',
                'string',
                'max:100',
            ],
            'request_stock_no' => ['nullable', 'string', 'max:50', 'regex:/^RS-[A-Z0-9]+$/'],
            'ref_spk_id' => [
                Rule::requiredIf($needsReference),
                'nullable',
                'integer',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'spk_type.required' => 'Tipe produksi wajib dipilih.',
            'spk_type.in' => 'Tipe produksi tidak valid.',
            'request_order_no.required' => 'Nomor pesanan wajib dipilih untuk tipe Pesanan.',
            'request_stock_no.regex' => 'Nomor permintaan stok harus format RS-XXXX.',
            'ref_spk_id.required' => 'SPK referensi wajib dipilih untuk tipe Exchange, Refund, atau Reparasi.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $type = (string) $this->input('spk_type');

                if ($type === 'Pesanan') {
                    $docNo = (string) $this->input('request_order_no');
                    $exists = app(RequestOrderRepository::class)->existsByDocNo($docNo);

                    if (! $exists) {
                        $validator->errors()->add(
                            'request_order_no',
                            'Nomor pesanan tidak ditemukan di daftar request order.',
                        );
                    }
                }

                if (in_array($type, SpkService::REFERENCE_TYPES, true)) {
                    $refId = (int) $this->input('ref_spk_id');
                    $reference = Production::query()
                        ->notDeleted()
                        ->where('row_id', $refId)
                        ->where('status', 'SPKDONE')
                        ->exists();

                    if (! $reference) {
                        $validator->errors()->add(
                            'ref_spk_id',
                            'SPK referensi harus ada dan berstatus SPKDONE.',
                        );
                    }
                }
            },
        ];
    }
}
