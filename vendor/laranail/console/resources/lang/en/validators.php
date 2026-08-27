<?php

declare(strict_types=1);

return [
    'text' => 'The input must be a string and less than 255 characters.',
    'number' => 'The input must be a number.',
    'email' => 'The input must be a valid email address.',
    'path' => 'Must be a valid file path.',
    'password' => 'The password must be at least 8 characters long.',
    'textarea' => 'The input must be a string.',
    'date' => 'The input must be a valid date.',
    'time' => 'The input must be a valid time.',
    'select' => 'The selected value is invalid.',
    'checkbox' => 'The input must be a boolean.',
    'radio' => 'The selected value is invalid.',
    'uuid' => 'The input must be a valid UUID.',
    'boolean' => 'The input must be a valid boolean value (yes, no, true, false, 0, 1).',
    'json' => 'The input must be a valid JSON string.',
    'string' => 'The input must be a string of valid length.',
    'name' => 'The input must be a valid name (letters, spaces, hyphens, and apostrophes only).',
    'username' => 'The username must be between 3 and 20 characters and can only contain letters, numbers, and underscores.',
    'phone' => 'The phone number must be between 10 and 15 digits and can optionally start with a +.',
    'color' => 'The color must be a valid RGB, RGBA, or HEX value.',
    'null_or_empty' => 'The field must be null or empty.',
    'array' => 'The field must be an array.',
    'object' => 'The field must be an object.',
    'alpha' => 'The input must contain only alphabetic characters.',
    'alphanumeric' => 'The input must contain only alphanumeric characters.',
    'uuid_or_integer_or_slug' => 'The input must be a valid UUID, integer ID, or slug.',
];
