<?php

namespace App\Http\Requests;

use App\Models\PolishFrame;
use App\Models\Production;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StorePolishFrameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $craftsmanId = $this->input('craftsman_id');
        $spkId = $this->input('spk_id');

        $this->merge([
            'spk_id' => filled($spkId) && (int) $spkId > 0
                ? (int) $spkId
                : null,
            'craftsman_id' => filled($craftsmanId) && (int) $craftsmanId > 0
                ? (int) $craftsmanId
                : null,
            'notes' => filled($this->input('notes'))
                ? trim((string) $this->input('notes'))
                : null,
            'status_item' => filled($this->input('status_item'))
                ? strtoupper(trim((string) $this->input('status_item')))
                : null,
            'start_weight' => filled($this->input('start_weight'))
                ? str_replace(',', '.', trim((string) $this->input('start_weight')))
                : null,
            'finish_weight' => filled($this->input('finish_weight'))
                ? str_replace(',', '.', trim((string) $this->input('finish_weight')))
                : null,
            'send_craftsman_date' => filled($this->input('send_craftsman_date'))
                ? trim((string) $this->input('send_craftsman_date'))
                : null,
            'received_craftsman_date' => filled($this->input('received_craftsman_date'))
                ? trim((string) $this->input('received_craftsman_date'))
                : null,
        ]);
    }

    /**
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
            'craftsman_id' => ['nullable', 'integer'],
            'send_craftsman_date' => ['nullable', 'date_format:Y-m-d H:i'],
            'received_craftsman_date' => ['nullable', 'date_format:Y-m-d H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status_item' => [
                'nullable',
                'string',
                Rule::in(PolishFrame::statusItemOptions()),
            ],
            'start_weight' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'finish_weight' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
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
            'status_item.in' => 'Status QC tidak valid.',
            'start_weight.numeric' => 'Berat awal harus berupa angka.',
            'finish_weight.numeric' => 'Berat akhir harus berupa angka.',
        ];
    }
}
