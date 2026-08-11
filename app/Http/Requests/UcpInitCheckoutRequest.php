<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UcpInitCheckoutRequest extends FormRequest
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
        return [
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.product_id' => ['required', 'integer', 'exists:skus,id'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'buyer' => ['required', 'array'],
            'buyer.shipping_address' => ['required', 'array'],
            'buyer.shipping_address.postal_code' => ['required', 'string'],
            'buyer.shipping_address.country_code' => ['required', 'string', 'size:2'],
        ];
    }
}
