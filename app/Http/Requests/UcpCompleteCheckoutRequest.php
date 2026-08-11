<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UcpCompleteCheckoutRequest extends FormRequest
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
            'buyer.email' => ['required', 'email'],
            'buyer.name' => ['required', 'string'],
            'buyer.shipping_address' => ['required', 'array'],
            'buyer.shipping_address.street_address' => ['required', 'string'],
            'buyer.shipping_address.city' => ['required', 'string'],
            'buyer.shipping_address.postal_code' => ['required', 'string'],
            'buyer.shipping_address.country_code' => ['required', 'string', 'size:2'],
            'payment_method' => ['required', 'array'],
            'payment_method.gateway' => ['required', 'string', 'in:stripe,paypal'],
            'payment_method.token' => ['required', 'string'], // Token Stripe (tok_X) o OrderID PayPal
        ];
    }
}
