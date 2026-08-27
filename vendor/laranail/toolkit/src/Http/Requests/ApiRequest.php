<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * FormRequest base for JSON APIs.
 *
 * Inherits {@see BaseRequest}'s always-on input sanitization, and on a failed
 * validation throws a {@see ValidationException} carrying a standardized
 * `{success:false, message, errors}` JSON envelope at HTTP 422 (rather than the
 * framework default redirect/HTML flow).
 */
abstract class ApiRequest extends BaseRequest
{
    /**
     * Throw a validation exception carrying a JSON error envelope.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new ValidationException($validator, new JsonResponse([
            'success' => false,
            'message' => 'The provided data failed validation.',
            'errors' => $validator->errors()->toArray(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
