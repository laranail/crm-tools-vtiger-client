<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\JSON;

use Exception;
use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use Simtabi\Pheg\Toolbox\Media\File\File;
use Simtabi\Pheg\Toolbox\JSON\Exception\Decode\ControlCharacterException;
use Simtabi\Pheg\Toolbox\JSON\Exception\Decode\DepthException as DecodeDepthException;
use Simtabi\Pheg\Toolbox\JSON\Exception\Decode\StateMismatchException;
use Simtabi\Pheg\Toolbox\JSON\Exception\Decode\SyntaxException;
use Simtabi\Pheg\Toolbox\JSON\Exception\Decode\UTF8Exception;
use Simtabi\Pheg\Toolbox\JSON\Exception\DecodeException;
use Simtabi\Pheg\Toolbox\JSON\Exception\Encode\DepthException as EncodeDepthException;
use Simtabi\Pheg\Toolbox\JSON\Exception\Encode\InfiniteOrNotANumberException;
use Simtabi\Pheg\Toolbox\JSON\Exception\Encode\InvalidPropertyNameException;
use Simtabi\Pheg\Toolbox\JSON\Exception\Encode\RecursionException;
use Simtabi\Pheg\Toolbox\JSON\Exception\Encode\UnsupportedTypeException;
use Simtabi\Pheg\Toolbox\JSON\Exception\EncodeException;
use Simtabi\Pheg\Toolbox\JSON\Exception\LintingException;
use Simtabi\Pheg\Toolbox\JSON\Exception\UnknownException;
use Simtabi\Pheg\Toolbox\JSON\Exception\ValidationException;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use JsonException;

/**
 * Manages encoding, decoding, linting, and validation of JSON data.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class JSON implements JSONInterface
{
    /**
     * The JSON linter.
     *
     * @var JsonParser
     */
    private $linter;

    public function __construct() {}

    /**
     * {@inheritdoc}
     */
    public function decode($json, $associative = false, $depth = 512, $options = 0)
    {
        $decoded = json_decode($json, $associative, $depth, $options);

        if ($this->hasError()) {
            throw match (json_last_error()) {
                JSON_ERROR_DEPTH          => new DecodeDepthException('The maximum stack depth of %d was exceeded.', $depth),
                JSON_ERROR_STATE_MISMATCH => new StateMismatchException('The value is not JSON or is malformed.'),
                JSON_ERROR_CTRL_CHAR      => new ControlCharacterException('An unexpected control character was found.'),
                JSON_ERROR_SYNTAX         => new SyntaxException('The encoded JSON value has a syntax error.'),
                JSON_ERROR_UTF8           => new UTF8Exception('The encoded JSON value contains invalid UTF-8 characters.'),
                default                   => new UnknownException('An unrecognized decoding error was encountered: %s', json_last_error_msg()),
            };
        }

        return $decoded;
    }

    /**
     * {@inheritdoc}
     */
    public function decodeFile($file, $associative = false, $depth = 512, $options = 0)
    {
        try {
            return $this->decode((new File($file, 'r'))->read(), $associative, $depth, $options);
        } catch (Exception $exception) {
            throw new DecodeException(
                'The JSON encoded file "%s" could not be decoded.',
                $file,
                $exception
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function encode($value, $options = 0, $depth = 512)
    {
        $encoded = json_encode($value, $options, $depth);

        if ($this->hasError()) {
            switch (json_last_error()) {
                case JSON_ERROR_DEPTH:
                    throw new EncodeDepthException('The maximum stack depth of %d was exceeded.', $depth);

                case JSON_ERROR_RECURSION:
                    if (0 === ($options & JSON_PARTIAL_OUTPUT_ON_ERROR))
                    {
                        throw new RecursionException('A recursive object was found and partial output is not enabled.');
                    }

                    break;

                case JSON_ERROR_INF_OR_NAN:
                    if (0 === ($options & JSON_PARTIAL_OUTPUT_ON_ERROR)) {
                        throw new InfiniteOrNotANumberException('An INF or NAN value was found an partial output is not enabled.');
                    }

                    break;

                case JSON_ERROR_UNSUPPORTED_TYPE:
                    if (0 === ($options & JSON_PARTIAL_OUTPUT_ON_ERROR)) {
                        throw new UnsupportedTypeException('An unsupported value type was found an partial output is not enabled.');
                    }

                    break;

                case JSON_ERROR_INVALID_PROPERTY_NAME:
                    throw new InvalidPropertyNameException('The value contained a property with an invalid JSON key name.');

                    break;

                default:
                    throw new UnknownException('An unrecognized encoding error was encountered: %s', json_last_error_msg());
            }
        }

        return $encoded;
    }

    /**
     * {@inheritdoc}
     */
    public function encodeFile($value, $file, $options = 0, $depth = 512)
    {
        try {
            (new File($file, 'w'))->write(
                $this->encode($value, $options, $depth)
            );
        } catch (Exception $exception) {
            throw new EncodeException(
                'The value could not be encoded and saved to "%s".',
                $file
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lint($json)
    {
        $result = $this->doLint($json);

        if ($result instanceof ParsingException) {
            throw new LintingException(
                'The encoded JSON value is not valid.',
                $result
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lintFile($file)
    {
        $result = $this->doLint((new File($file, 'r'))->read());

        if ($result instanceof ParsingException) {
            throw new LintingException(
                'The encoded JSON value in the file "%s" is not valid.',
                $file,
                $result
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validate($schema, $decoded)
    {
        $storage = new SchemaStorage();
        $storage->addSchema('file://schema', $schema);

        $validator = new Validator(new Factory($storage));

        $validator->check($decoded, $schema);

        if ($validator->isValid()) {
            return null;
        }

        $errors = [];

        foreach ($validator->getErrors() as $error) {
            $errors[] = sprintf(
                '[%s] %s',
                $error['property'],
                $error['message']
            );
        }

        if (!empty($errors)) {
            throw new ValidationException(
                "The decoded JSON value failed validation:\n%s",
                join("\n", $errors)
            );
        }
    }

    /**
     * Checks if the last JSON related operation resulted in an error.
     *
     * @return boolean Returns `true` if it did or `false` if not.
     */
    private function hasError()
    {
        return (JSON_ERROR_NONE !== json_last_error());
    }

    /**
     * Actually performs the linting operation.
     *
     * @param string $json The encoded JSON value.
     *
     * @return null|ParsingException The linting error.
     */
    private function doLint($json)
    {
        if (null === $this->linter) {
            $this->linter = new JsonParser();
        }

        return $this->linter->lint($json);
    }

    public function prettyPrint($data, $html = false, $raw_array = true, $config = false) {

        if($raw_array){
            $json = json_encode($data, $config);
        }elseif($raw_array == false && (Validator::isJSON($data))){
            $json = $data;
        }else{
            return false;
        }

        $out = ''; $nl = "\n"; $cnt = 0; $tab = 4; $len = strlen($json); $space = ' ';
        if($html) {
            $space = '&nbsp;';
            $nl    = '<br/>';
        }

        $k = strlen($space)?strlen($space):1;
        for ($i=0; $i<=$len; $i++) {
            $char = substr($json, $i, 1);
            if($char == '}' || $char == ']') {
                $cnt --;
                $out .= $nl . str_pad('', ($tab * $cnt * $k), $space);
            } else if($char == '{' || $char == '[') {
                $cnt ++;
            }
            $out .= $char;
            if($char == ',' || $char == '{' || $char == '[') {
                $out .= $nl . str_pad('', ($tab * $cnt * $k), $space);
            }
            if($char == ':') {
                $out .= ' ';
            }
        }

        return $out;
    }

    public function escapeUnicode($str){
        return json_decode((preg_replace('/\\\u([0-9a-z]{4})/', '&#x$1;', Transfigure::toJson($str))));
    }

    public function toArray(string $jsonString, $parentId = 0)
    {
        $data = json_decode($jsonString, true);
        $out  = [];

        foreach ($data as $order => $datum) {
            $subArray = [];
            if (isset($datum['children'])) {
                $subArray = $this->toArray($datum['children'], $datum['id']);
            }
            $out[] = [
                'parent_id' => $parentId,
                'order'     => $order,
                'id'        => $datum['id'],
            ];
            $out = array_merge($out, $subArray);
        }

        return $out;
    }

    public function json2file(string $file): Json2File
    {
        return new Json2File($file);
    }

    /**
     * @throws JsonException
     */
    public function encodePrettify(array|string $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function stringify($content): ?string
    {
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            return json_encode($content);
        }

        return null;
    }
}
