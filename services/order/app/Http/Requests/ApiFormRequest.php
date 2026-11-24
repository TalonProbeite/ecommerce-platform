<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Responses\ProblemResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ProblemResponse::make(
            422,
            'Unprocessable Entity',
            'The request is invalid.',
            ['errors' => $validator->errors()->toArray()],
        ));
    }
}
