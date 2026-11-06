<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\MsCraftsman;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMsCraftsmanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rate' => $this->filled('rate') ? $this->input('rate') : null,
            'employee_id' => $this->filled('employee_id') ? $this->input('employee_id') : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var MsCraftsman $msCraftsman */
        $msCraftsman = $this->route('msCraftsman');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique(MsCraftsman::class, 'name')
                    ->where(fn ($query) => $query->where('is_deleted', 0))
                    ->ignore($msCraftsman->row_id, 'row_id'),
            ],
            'rate' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'is_active' => ['required', Rule::in(['YES', 'NO'])],
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists(Employee::class, 'id')->where(
                    function ($query) use ($msCraftsman): void {
                        $query->where('is_deleted', 0)->where(
                            function ($query) use ($msCraftsman): void {
                                $query
                                    ->where('status', Employee::STATUS_ACTIVE)
                                    ->where('department_id', Employee::DEPARTMENT_PRODUCTION);

                                if ($msCraftsman->employee_id !== null) {
                                    $query->orWhere('id', $msCraftsman->employee_id);
                                }
                            },
                        );
                    },
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama pengrajin wajib diisi.',
            'name.unique' => 'Nama pengrajin sudah digunakan.',
            'rate.numeric' => 'Tarif harus berupa angka.',
            'rate.min' => 'Tarif tidak boleh kurang dari 0.',
            'rate.decimal' => 'Tarif maksimal 2 angka di belakang koma.',
            'is_active.required' => 'Status pengrajin wajib dipilih.',
            'is_active.in' => 'Status pengrajin tidak valid.',
            'employee_id.exists' => 'Pegawai tidak valid.',
        ];
    }
}
