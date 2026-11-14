<?php

namespace Modules\Trading\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMarketDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tickRules = $this->tickRules();

        if ($this->has('ticks')) {
            return [
                'ticks' => ['required', 'array', 'min:1', 'max:500'],
                'ticks.*' => ['required', 'array'],
                ...collect($tickRules)
                    ->mapWithKeys(fn (array $rules, string $field): array => ["ticks.*.{$field}" => $rules])
                    ->all(),
            ];
        }

        return $tickRules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->has('ticks')) {
                return;
            }

            if (! $this->filled('symbol')) {
                $validator->errors()->add('symbol', 'A symbol is required when ticks is not provided.');
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ticks(): array
    {
        if ($this->has('ticks')) {
            return $this->validated('ticks');
        }

        return [$this->safe()->only([
            'symbol',
            'bid',
            'ask',
            'last',
            'volume',
            'source',
            'payload',
            'feed_at',
        ])];
    }

    /**
     * @return array<string, list<string>>
     */
    protected function tickRules(): array
    {
        return [
            'symbol' => ['required', 'string', 'max:32'],
            'bid' => ['nullable', 'numeric'],
            'ask' => ['nullable', 'numeric'],
            'last' => ['nullable', 'numeric'],
            'volume' => ['nullable', 'numeric'],
            'source' => ['nullable', 'string', 'max:64'],
            'payload' => ['nullable', 'array'],
            'feed_at' => ['nullable', 'date'],
        ];
    }
}
