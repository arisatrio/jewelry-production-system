<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCraftsmanPerformanceJobsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var list<array{table: string}> $sources */
        $sources = config('spk_processes.shrink_sources', []);
        $processKeys = collect($sources)->pluck('table')->push('all')->all();

        return [
            'craftsman_id' => ['required', 'integer', 'min:1'],
            'process' => ['nullable', 'string', Rule::in($processKeys)],
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }

    public function craftsmanId(): int
    {
        return (int) $this->validated('craftsman_id');
    }

    public function processTableKey(): ?string
    {
        $process = $this->validated('process');

        if (! is_string($process) || $process === '' || $process === 'all') {
            return null;
        }

        return $process;
    }
}
