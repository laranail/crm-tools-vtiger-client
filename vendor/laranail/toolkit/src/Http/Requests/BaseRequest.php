<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * FormRequest base that sanitizes every string input on
 * {@see BaseRequest::prepareForValidation()} before the validator runs.
 *
 * Sanitization is **always on** by design (defence in depth): HTML tags are
 * stripped and whitespace trimmed for every string field, then field-specific
 * normalisation is applied based on the field name (email/username/url/db_/name).
 *
 * The name normaliser is **Unicode-aware**: it preserves letters in any script
 * (accents, apostrophes, hyphens — e.g. `José`, `O'Brien`, `Müller`,
 * `Renée-Claire`) while still removing HTML and control/punctuation noise. The
 * legacy `[^a-zA-Z\s'-]` filter corrupted international names; this does not.
 */
abstract class BaseRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Prepare the data for validation by sanitizing all input.
     */
    protected function prepareForValidation(): void
    {
        $this->sanitizeInput();
    }

    /**
     * Sanitize all string input. Always enabled (defensive by design).
     */
    protected function sanitizeInput(): void
    {
        $input = $this->all();

        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $value = trim(strip_tags($value));

                $input[$key] = $this->sanitizeFieldByType((string) $key, $value);
            }
        }

        $this->replace($input);
    }

    /**
     * Apply field-specific sanitization based on the field name.
     *
     * Bug fix vs. legacy: the legacy `sanitizeInput()` discarded this method's
     * return value (it only stored the strip_tags/trim result), so the
     * field-specific rules never actually applied. Here the return value is
     * used.
     */
    protected function sanitizeFieldByType(string $fieldName, string $value): string
    {
        // Email fields — normalise to lowercase.
        if (str_contains($fieldName, 'email')) {
            return Str::lower($value);
        }

        // Username fields — ascii-safe handle: letters, digits, underscore, hyphen.
        if (str_contains($fieldName, 'username')) {
            return Str::lower((string) preg_replace('/[^a-zA-Z0-9_-]/', '', $value));
        }

        // URL fields — strip characters illegal in a URL.
        if (str_contains($fieldName, 'url')) {
            $sanitized = filter_var($value, FILTER_SANITIZE_URL);

            return $sanitized === false ? '' : $sanitized;
        }

        // Database identifier fields — conservative identifier charset.
        if (str_starts_with($fieldName, 'db_')) {
            return (string) preg_replace('/[^a-zA-Z0-9._-]/', '', $value);
        }

        // Name fields — Unicode letters (any script), marks (combining accents),
        // spaces, apostrophes and hyphens. Preserves international names while
        // dropping control chars / punctuation noise. HTML is already stripped above.
        if (str_contains($fieldName, 'name')) {
            $cleaned = preg_replace("/[^\p{L}\p{M}\s'-]/u", '', $value);

            // preg_replace returns null on a malformed-UTF-8 subject; fall back
            // to the (already HTML-stripped, trimmed) value rather than nulling it.
            return $cleaned ?? $value;
        }

        return $value;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator): void {
            $this->afterValidation($validator);
        });
    }

    /**
     * Additional validation after the main rules. Override in child classes.
     */
    protected function afterValidation(ValidatorContract $validator): void
    {
        // Override in child classes if needed.
    }

    /**
     * Get the validated data, re-applying field-specific sanitization so the
     * validated set matches the sanitized input.
     *
     * @param array<string>|int|string|null $key
     * @param mixed                         $default
     *
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if (is_array($validated)) {
            foreach ($validated as $field => $value) {
                if (is_string($value)) {
                    $validated[$field] = $this->sanitizeFieldByType((string) $field, $value);
                }
            }
        }

        return $validated;
    }

    /**
     * Throw a validation exception on a failed validation attempt.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new ValidationException($validator);
    }

    /**
     * Throw an authorization exception on a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('This action is unauthorized.');
    }
}
