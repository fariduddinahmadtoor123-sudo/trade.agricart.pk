<?php

namespace Modules\Trading\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteMt5OrderRequest extends FormRequest
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
        return [
            'status' => ['required', 'in:filled,rejected'],
            'ticket' => ['required_if:status,filled', 'nullable', 'integer', 'min:1'],
            'price' => ['required_if:status,filled', 'nullable', 'numeric'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }
}
