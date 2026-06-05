<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'string', 'max:128'],
            'status' => ['required', 'string', 'in:approved,declined'],
            'gateway' => ['required', 'string', 'max:32'],
        ];
    }
}
