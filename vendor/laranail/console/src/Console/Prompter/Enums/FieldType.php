<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Prompter\Enums;

use Simtabi\Laranail\Console\Prompter\Contracts\ValidatorInterface;
use Simtabi\Laranail\Console\Prompter\Exceptions\PrompterException;
use Simtabi\Laranail\Console\Prompter\Services\FormBuilder\FormFieldService;
use Simtabi\Laranail\Console\Prompter\Validators\AlphanumericValidator;
use Simtabi\Laranail\Console\Prompter\Validators\AlphaValidator;
use Simtabi\Laranail\Console\Prompter\Validators\ArrayValidator;
use Simtabi\Laranail\Console\Prompter\Validators\BooleanFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\CheckboxFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\ColorValidator;
use Simtabi\Laranail\Console\Prompter\Validators\DateFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\EmailFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\JsonFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\NameFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\NullOrEmptyValidator;
use Simtabi\Laranail\Console\Prompter\Validators\NumberFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\ObjectValidator;
use Simtabi\Laranail\Console\Prompter\Validators\PasswordFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\PathFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\PhoneNumberValidator;
use Simtabi\Laranail\Console\Prompter\Validators\RadioFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\SelectFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\StringFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\TextAreaFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\TextFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\TimeFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\UsernameValidator;
use Simtabi\Laranail\Console\Prompter\Validators\UUIDFieldValidator;
use Simtabi\Laranail\Console\Prompter\Validators\UuidOrIntegerOrSlugValidator;

/**
 * Enum FieldType
 *
 * Represents various form field types.
 */
enum FieldType: string
{
    case TEXT = 'text';
    case NUMBER = 'number';
    case EMAIL = 'email';
    case PASSWORD = 'password';
    case TEXTAREA = 'textarea';
    case DATE = 'date';
    case TIME = 'time';
    case SELECT = 'select';
    case CHECKBOX = 'checkbox';
    case RADIO = 'radio';
    case PATH = 'path';
    case USERNAME = 'username';
    case PHONE = 'phone';
    case COLOR = 'color';
    case NULL_OR_EMPTY = 'null_or_empty';
    case ARRAY = 'array';
    case OBJECT = 'object';
    case UUID = 'uuid';
    case ALPHA = 'alpha';
    case ALPHANUMERIC = 'alphanumeric';
    case UUID_OR_INTEGER_OR_SLUG = 'uuid_or_integer_or_slug';
    case BOOLEAN = 'boolean';
    case NAME = 'name';
    case STRING = 'string';
    case JSON = 'json';

    /**
     * Get the default validator for a given field type.
     *
     * @param self $type The type of the form field.
     *
     * @throws PrompterException
     */
    public static function getDefaultValidator(self $type): ValidatorInterface
    {
        return match ($type) {
            self::TEXT => new TextFieldValidator,
            self::NUMBER => new NumberFieldValidator,
            self::EMAIL => new EmailFieldValidator,
            self::PASSWORD => new PasswordFieldValidator,
            self::TEXTAREA => new TextAreaFieldValidator,
            self::DATE => new DateFieldValidator,
            self::TIME => new TimeFieldValidator,
            self::SELECT => new SelectFieldValidator([]),
            self::CHECKBOX => new CheckboxFieldValidator,
            self::RADIO => new RadioFieldValidator([]),
            self::PATH => new PathFieldValidator,
            self::USERNAME => new UsernameValidator,
            self::PHONE => new PhoneNumberValidator,
            self::COLOR => new ColorValidator,
            self::NULL_OR_EMPTY => new NullOrEmptyValidator,
            self::ARRAY => new ArrayValidator,
            self::OBJECT => new ObjectValidator,
            self::UUID => new UUIDFieldValidator,
            self::ALPHA => new AlphaValidator,
            self::ALPHANUMERIC => new AlphanumericValidator,
            self::UUID_OR_INTEGER_OR_SLUG => new UuidOrIntegerOrSlugValidator,
            self::BOOLEAN => new BooleanFieldValidator,
            self::NAME => new NameFieldValidator,
            self::STRING => new StringFieldValidator,
            self::JSON => new JsonFieldValidator,
            default => throw PrompterException::triggerErrorMessage('unsupported_input_type', ['type' => $type->value]),
        };
    }

    /**
     * Get the mapped validator method.
     *
     * @throws PrompterException
     */
    public static function getValidatorMethod(FormFieldService $formField): string
    {
        $methods = [
            self::TEXT->value => 'text',
            self::NUMBER->value => 'text',
            self::EMAIL->value => 'text',
            self::PASSWORD->value => 'password',
            self::TEXTAREA->value => 'textarea',
            self::DATE->value => 'text',
            self::TIME->value => 'text',
            self::SELECT->value => 'select',
            self::CHECKBOX->value => 'confirm',
            self::RADIO->value => 'select',
            self::PATH->value => 'text',
            self::USERNAME->value => 'text',
            self::PHONE->value => 'text',
            self::COLOR->value => 'text',
            self::NULL_OR_EMPTY->value => 'text',
            self::ARRAY->value => 'textarea',
            self::OBJECT->value => 'textarea',
            self::UUID->value => 'text',
            self::ALPHA->value => 'text',
            self::ALPHANUMERIC->value => 'text',
            self::UUID_OR_INTEGER_OR_SLUG->value => 'text',
            self::BOOLEAN->value => 'confirm',
            self::NAME->value => 'text',
            self::STRING->value => 'text',
            self::JSON->value => 'textarea',
        ];

        if (! isset($methods[$formField->type->value])) {
            throw PrompterException::triggerErrorMessage('unsupported_input_type', ['type' => $formField->type->value]);
        }

        return $methods[$formField->type->value];
    }
}
