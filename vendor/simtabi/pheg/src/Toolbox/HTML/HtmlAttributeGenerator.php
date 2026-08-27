<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\HTML;

class HtmlAttributeGenerator {

    protected array $attributes = [];
    private const string BOOLEAN_ATTRIBUTE_KEY = '__BOOLEAN_ATTRIBUTE__';

    public function __construct()
    {
        $this->attributes = [];
    }

    /**
     * Cleans a string by removing HTML tags, decoding HTML entities, and restricting it to specific characters.
     *
     * This function decodes all HTML entities to their applicable characters, strips all HTML and PHP tags,
     * and then removes all characters not explicitly allowed. By default, it allows letters (both uppercase and
     * lowercase), numbers, spaces, dashes (-), and underscores (_). Additional characters can be allowed by
     * specifying them in the $additionalChars parameter.
     *
     * @param string|null $string $string The input string to be cleaned.
     * @param string $additionalChars Optional. A string of additional characters to allow in the cleaned output,
     *                                specified without any delimiters. Special characters that are part of regex
     *                                syntax must be escaped if included. For example, to allow a dot (.) and a
     *                                comma (,), you would pass '.,' as this parameter.
     *
     * @return string The cleaned string, with HTML tags removed, HTML entities decoded, and containing only
     *                the allowed characters.
     */
    private function cleanString(?string $string, string $additionalChars = ''): string
    {
        if (empty($string)) {
            return '';
        }

        // Decode HTML entities to their corresponding characters
        $decoded = html_entity_decode($string, ENT_QUOTES | ENT_HTML5);

        // Strip all HTML and PHP tags from the decoded string
        $noTags = strip_tags($decoded);

        // Escape special regex characters in $additionalChars to prevent unintended behavior
        $additionalChars = preg_quote($additionalChars, '/');

        // Define a whitelist pattern of characters to allow
        $pattern = '/[^A-Za-z0-9 _\-' . $additionalChars . ']/';

        // Remove characters not in the whitelist
        return trim(preg_replace($pattern, '', $noTags));
    }

    /**
     * Utility method to format an HTML attribute.
     * Returns a formatted attribute string or an empty string if the value is empty.
     *
     * @param string $key The attribute key.
     * @param string|null $value The attribute value.
     * @return string Formatted attribute or empty string.
     */
    private function formatAttribute(string $key, ?string $value): string {
        return !empty($value) ? $this->cleanString($key) . '=' . $this->cleanString($value) . '' : '';
    }

    /**
     * Adds content to an array, merging arrays or appending elements as necessary.
     *
     * @param array $targetArray The target array to modify.
     * @param mixed $content The content to add or merge.
     * @return array The modified array.
     */
    public function addOrMergeToArray(array &$targetArray, mixed $content): array
    {
        // Check if the target array is empty
        if (empty($targetArray)) {
            // Directly assign if the target array is empty
            $targetArray = is_array($content) ? $content : [$content];
        } else {
            // If the content is an array, and you want to merge it
            if (is_array($content)) {
                $targetArray = array_merge($targetArray, $content);
            } else {
                // If content is not an array, add it as a single element
                $targetArray[] = $content;
            }
        }

        return array_values(array_unique($targetArray));
    }

    /**
     * Removes empty values from an array, optionally preserving numeric zeros.
     *
     * @param array $array The array to filter.
     * @param bool $keepNumericZero Whether to keep numeric zeros.
     * @return array The filtered array.
     * https://tecadmin.net/removing-empty-values-from-array-in-php/
     */
    private function removeEmptyArrayValues(array $array, bool $keepNumericZero = false): array
    {
        $filtered = array_filter($array, function ($value) use ($keepNumericZero) {
            return $keepNumericZero ? ($value === '0' || $value === 0 || !empty($value)) : !empty($value);
        });

        return array_values($filtered);
    }

    /**
     * Removes specified values from an array.
     *
     * @param mixed $values The values to remove.
     * @param array $array The array to modify.
     * @return array The modified array.
     */
    private function removeFromArrayByValue($values, array $array): array
    {
        $values = is_array($values) ? $values : [$values];
        return array_filter($array, fn($item) => !in_array($item, $values, true));

        // alt method
        //  if (($key = array_search($value, $array)) !== false) {
        //            unset($array[$key]);
        //        }
        //
        //        return $array;
    }

    /**
     * Splits a string by multiple delimiters.
     *
     * @param string $string The string to split.
     * @param array $separators The separators to use.
     * @return array The split parts.
     */
    private function explodeWithMultipleSeparators(string $string, array $separators = [",", " ", "|", "/"]): array
    {
        $pattern = "/" . implode("|", array_map(fn($sep) => preg_quote($sep, '/'), $separators)) . "/";
        return preg_split($pattern, $string, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Removes all special characters from a string except for those specified.
     *
     * This function uses a regular expression to remove all characters from the input string,
     * except for alphanumeric characters (both letters and numbers) and any additional characters
     * specified by the caller. By default, spaces, dashes, and underscores are allowed.
     *
     * @param string $string The input string from which to remove special characters.
     * @param string $allowedChars A string containing additional characters to allow in the output.
     *                             These characters should be specified without any delimiters or regex syntax.
     *                             For example, to allow periods and commas, pass them as ".,"
     *
     * @return string The sanitized string, with all disallowed characters removed.
     */
    public function removeSpecialCharsExcept(string $string, string $allowedChars = '-_'): string
    {
        // Escape special characters in the list of additional allowed characters to ensure they are treated as literals in the regex
        $allowedChars = preg_quote($allowedChars, '/');

        // Build the regex pattern dynamically to include the allowed additional characters
        $pattern = '/[^a-zA-Z0-9' . $allowedChars . ']/';

        // Replace characters not matching the pattern with an empty string
        return preg_replace($pattern, '', $string);
    }

    /**
     * Sanitizes the input to avoid XSS attacks.
     *
     * @param mixed $value The value to sanitize.
     * @return mixed The sanitized value.
     */
    protected function sanitize(mixed $value): mixed
    {
        if (empty($value)) {
            return '';
        }

        // If the value is an array, sanitize each element.
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }
        // For strings, apply HTML entity encoding.
        return is_string($value) ? htmlentities(trim($value), ENT_QUOTES, 'UTF-8') : $value;
    }

    /**
     * Evaluates the given value, returning true if:
     * - The value is a boolean and is true.
     * - The value is a non-empty array or object.
     * - The value is a non-empty string.
     * - The value is a non-zero integer.
     * Returns false otherwise, including for null values and empty strings/arrays/objects.
     *
     * @param mixed $value The value to evaluate.
     * @return bool The result of the evaluation.
     */
    public function evaluateToBoolean(mixed $value): bool
    {
        // Directly return true for true booleans, non-zero integers, and non-empty strings
        if ($value === true || (is_int($value) && $value !== 0) || (is_string($value) && $value !== '')) {
            return true;
        }

        // Return false for false booleans, zero integers, null, and empty strings
        if ($value === false || $value === null || $value === 0 || $value === '') {
            return false;
        }

        // Check for non-empty arrays or objects
        if (is_array($value) || is_object($value)) {
            return !empty((array)$value);
        }

        // Default to false for any other data types not explicitly evaluated
        return false;
    }


    /**
     * Adds or updates an HTML attribute. Handles boolean attributes by setting a special key.
     * Throws an exception for empty attribute names to ensure valid HTML output.
     *
     * @param string $attributeName The name of the HTML attribute.
     * @param mixed|null $attributeValue The value of the HTML attribute, or null for boolean attributes.
     * @param bool $isBooleanAttribute Indicates if the attribute is a boolean attribute.
     * @param bool $isAppend Indicates whether to append the value to an existing attribute.
     * @return self Chainable method pattern.
     * @throws \InvalidArgumentException If the attribute name is empty.
     */
    public function setAttribute(string $attributeName, mixed $attributeValue = null, bool $isBooleanAttribute = false, bool $isAppend = true): self {
        if (empty($attributeName)) {
            throw new \InvalidArgumentException('The attribute name cannot be empty.');
        }

        if ($attributeValue === null && !$isBooleanAttribute) {
            // If the value is null and this is not a boolean attribute, do nothing
            return $this;
        }

        // Directly handle boolean attributes to avoid duplication and ensure correct output
        if ($isBooleanAttribute) {
            $this->attributes[$attributeName] = self::BOOLEAN_ATTRIBUTE_KEY;
            return $this;
        }

        $sanitizedValue = $this->sanitize($attributeValue);
        if ($isAppend && isset($this->attributes[$attributeName]) && $this->attributes[$attributeName] !== self::BOOLEAN_ATTRIBUTE_KEY) {
            // Manage appending values, ensuring no duplicates for non-boolean attributes
            $existingValues = explode(' ', $this->attributes[$attributeName]);
            if (!in_array($sanitizedValue, $existingValues)) {
                $existingValues[] = $sanitizedValue;
            }
            $this->attributes[$attributeName] = implode(' ', $existingValues);
        } else {
            // Set or replace the attribute value
            $this->attributes[$attributeName] = $sanitizedValue;
        }
        return $this;
    }

    public function addAttribute(string $attributeName, mixed $attributeValue = null, bool $isBooleanAttribute = false, bool $isAppend = true): static
    {
        return $this->setAttribute($attributeName, $attributeValue, $isBooleanAttribute, $isAppend);
    }

    public function addAttributeIf(mixed $condition, string $attributeName, string $valueIfTrue, ?string $valueIfFalse = null, bool $isAppend = true): self
    {

        if ($this->evaluateToBoolean($condition)) {
            $this->setAttribute($attributeName, $valueIfTrue, false, $isAppend);
        } else {
            if ($valueIfFalse !== null) {
                $this->setAttribute($attributeName, $valueIfFalse, false, $isAppend);
            }
        }

        return $this;
    }

    public function addBooleanAttribute(string $attributeName, bool $isAppend = true): self
    {
        $this->setAttribute($attributeName, null, true, $isAppend);
        return $this;
    }

    public function addBooleanAttributeIf(mixed $condition, string $attributeName, bool $isAppend = true): self
    {
        if ($this->evaluateToBoolean($condition)) {
            $this->setAttribute($attributeName, null, true, $isAppend);
        } else {
            $this->setAttribute($attributeName, null, true, $isAppend);
        }

        return $this;
    }

    public function addAttributeIfElse(mixed $condition, string $attributeName, string $attributeValueIfTrue, ?string $attributeValueIfFalse = null, bool $isAppend = true): self
    {
        if ($this->evaluateToBoolean($condition)) {
            $this->setAttribute($attributeName, $attributeValueIfTrue, false, $isAppend);
        } else {
            $this->setAttribute($attributeName, $attributeValueIfFalse, false, $isAppend);
        }
        return $this;
    }

    public function addAlternateAttributeIfElse(mixed $condition, string $attributeNameIfTrue, string $attributeValueIfTrue, ?string $alternateAttributeNameIfFalse = null, ?string $alternateAttributeValueIfFalse = null, bool $isAppend = true): self
    {
        if ($this->evaluateToBoolean($condition)) {
            $this->setAttribute($attributeNameIfTrue, $attributeValueIfTrue, false, $isAppend);
        } elseif ($alternateAttributeNameIfFalse !== null && $alternateAttributeValueIfFalse !== null) {
            $this->setAttribute($alternateAttributeNameIfFalse, $alternateAttributeValueIfFalse, false, $isAppend);
        }
        return $this;
    }

    public function addClass(string $classValue, bool $isAppend = true): self
    {
        return $this->setAttribute('class', $classValue, false, $isAppend);
    }

    public function addClassIf(mixed $condition, string $classValueIf, ?string $classValueIfElse = null): self
    {
        return $this->addAttributeIf($this->evaluateToBoolean($condition), 'class', $classValueIf, $classValueIfElse);
    }

    public function addClassIfElse(mixed $condition, string $classIf, string $classIfElse, bool $isAppend = true): self
    {
        return $this->addAttributeIfElse($condition, 'class', $classIf, $classIfElse, $isAppend);
    }

    public function addId(string $classValue, bool $isBooleanAttribute = false, bool $isAppend = true): self
    {
        return $this->setAttribute('id', $classValue, $isBooleanAttribute, $isAppend);
    }

    public function addIdIf(mixed $condition, string $idValueIf, ?string $idValueIfElse = null): self
    {
        return $this->addAttributeIf($condition, 'id', $idValueIf, $idValueIfElse);
    }

    public function addIdIfElse(mixed $condition, string $idValueIf, string $idIfElse, bool $isAppend = true): self
    {
        return $this->addAttributeIfElse($condition, 'id', $idValueIf, $idIfElse, $isAppend);
    }

    /**
     * Generates the HTML attribute string, correctly handling boolean attributes.
     *
     * @param bool $reset
     * @return string The generated HTML attribute string.
     */
    public function generate(bool $reset = true): string {
        $htmlAttributes = [];
        foreach ($this->attributes as $name => $value) {
            if ($value === self::BOOLEAN_ATTRIBUTE_KEY) {
                // For boolean attributes, output just the attribute name
                $htmlAttributes[] = $name;
            } else {
                $htmlAttributes[] = sprintf('%s="%s"', $name, htmlentities($value, ENT_QUOTES, 'UTF-8'));
            }
        }

        $attributesString = implode(' ', $htmlAttributes);

        if ($reset) {
            $this->attributes = [];
        }

        return $attributesString;
    }

}
