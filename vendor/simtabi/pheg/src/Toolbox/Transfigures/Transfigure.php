<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Transfigures;

/**
 * A class that handles the detection and conversion of certain resource formats / content types into other formats.
 * The current formats are supported: XML, JSON, Array, Object, Serialized
 *
 * @version	2.0.0
 * @package	mjohnson.utility
 */
use Exception;
use DOMDocument;
use Simtabi\Enekia\Vanilla\Validators;
use Simtabi\Pheg\Toolbox\Serialize;
use stdClass;

final class Transfigure {

    private Validators $validators;
    private Serialize  $serialize;
    private            $resource;

    private const      UNKNOWN_DATA_TYPE_MSG = 'Unknown data type';
    private const      DATA_IS_EMPTY_MSG     = 'Data to be converted can not be empty';

    public function __construct()
    {
        $this->validators = new Validators();
        $this->serialize  = new Serialize;
    }

    public function arrayToXml(array $config = ArrayToXmlConfig::DEFAULTS): ArrayToXml
    {
        return new ArrayToXml($config);
    }

    public function xml2Array(array $config = XmlToArrayConfig::DEFAULTS): Xml2Array
    {
        return new Xml2Array($config);
    }

    public function xmlResponse(array $array): XmlResponse
    {
        return new XmlResponse($array);
    }

    public function utf8Encode(string|array|object $resource): object|array|string
    {
        if (is_string($resource)) {
            return utf8_encode($resource);
        } elseif (is_array($resource)) {
            foreach ($resource as $key => $value) {
                $resource[utf8_encode($key)] = $this->utf8Encode($value);
            }
        } elseif (is_object($resource)) {
            foreach ($resource as $key => $value) {
                $resource->{$key} = $this->utf8Encode($value);
            }
        }

        return $resource;
    }

    public function utf8Decode(string|array|object $resource): object|array|string
    {
        if (is_string($resource)) {
            return utf8_decode($resource);
        } elseif (is_array($resource)) {
            foreach ($resource as $key => $value) {
                $resource[utf8_decode($key)] = $this->utf8Decode($value);
            }
        } elseif (is_object($resource)) {
            foreach ($resource as $key => $value) {
                $resource->{$key} = $this->utf8Decode($value);
            }
        }

        return $resource;
    }

    public function json2Array(string $resource, $associative = true)
    {

        if ($this->validators->transfigure()->isArray($resource)) {
            return $resource;
        }

        return json_decode($resource, $associative);
    }

    public function array2Json($resource): bool|string
    {

        if ($this->validators->transfigure()->isJson($resource)) {
            return $resource;
        }

        return json_encode($resource);
    }

    public function string2Json(string $resource): bool|string
    {
        return json_encode([$resource]);
    }

    public function string2Object(string $resource): stdClass
    {
        return $this->array2Object([$resource]);
    }

    public function json2Object(string $resource)
    {
        return json_decode($resource, true);
    }

    public function serialize(mixed $resource)
    {
        return $this->serialize->serialize($resource);
    }

    public function unserialze(string $resource)
    {
        return $this->serialize->unserialize($resource);
    }

    public function arrayObject2Xml(array|object $resource, array $config = ArrayToXmlConfig::DEFAULTS): DOMDocument
    {
        return (new ArrayToXml($config))->buildXml($resource);
    }

    public function xmlToArray(mixed $resource, bool $fromDOM = false, array $config = XmlToArrayConfig::DEFAULTS): array|XmlResponse|string
    {
        return (new Xml2Array($config))->convert($resource, $fromDOM, $config);
    }

    public function array2Object($resource): object
    {

        if ($this->validators->transfigure()->isObject($resource))
        {
            return $resource;
        }

        $object = new stdClass();

        foreach ($resource as $key => $value)
        {
            if (is_array($value)) {
                $object->{$key} = $this->array2Object($value);
            } else {
                $object->{$key} = $value;
            }
        }

        return $object;
    }

    public function object2Array(object $resource): array
    {
        return json_decode(json_encode($resource), true);
    }

    private function throwUnknownDataTypeError()
    {
        throw new Exception(self::UNKNOWN_DATA_TYPE_MSG);
    }

    private function throwEmptyDataError()
    {
        throw new Exception(self::DATA_IS_EMPTY_MSG);
    }

    private function validate(): Validators
    {
        return $this->validators;
    }

    public function getDataType(): string
    {
        $resource = $this->resource;
        return match ($resource) {
            $this->validate()->transfigure()->isArray($resource)      => 'array',
            $this->validate()->transfigure()->isObject($resource)     => 'object',
            $this->validate()->transfigure()->isJson($resource)       => 'json',
            $this->validate()->transfigure()->isSerialized($resource) => 'serialized',
            $this->validate()->transfigure()->isXml($resource)        => 'xml',
            $this->validate()->transfigure()->isString($resource)     => 'string',
            default                                                   => $this->throwUnknownDataTypeError(),
        };
    }

    public function toArray($resource): mixed
    {
        if (empty($resource)) $this->throwEmptyDataError();

        $this->resource = $resource;
        return match ($resource) {
            $this->validate()->transfigure()->isArray($resource)      => $resource,
            $this->validate()->transfigure()->isObject($resource)     => $this->object2Array($resource),
            $this->validate()->transfigure()->isJson($resource)       => $this->json2Array($resource),
            $this->validate()->transfigure()->isSerialized($resource) => $this->json2Array($resource),
            $this->validate()->transfigure()->isXml($resource)        => $this->xmlToArray($resource),
            $this->validate()->transfigure()->isString($resource)     => $this->stringToArray($resource),
            default                                                   => [$resource],
        };
    }

    public function toJson($resource)
    {
        if (empty($resource)) $this->throwEmptyDataError();

        $this->resource = $resource;
        return match ($resource) {
            $this->validate()->transfigure()->isJson($resource)       => $resource,
            $this->validate()->transfigure()->isArray($resource)      => $this->array2Json($resource),
            $this->validate()->transfigure()->isObject($resource)     => $this->object2Array($resource),
            $this->validate()->transfigure()->isSerialized($resource) => $this->json2Array($resource),
            $this->validate()->transfigure()->isXml($resource)        => $this->xmlToArray($resource),
            $this->validate()->transfigure()->isString($resource)     => $this->string2Json($resource),
            default                                                   => $this->array2Json($resource),
        };
    }

    public function toObject($resource)
    {

        $this->resource = $resource;
        return match ($resource) {
            $this->validate()->transfigure()->isObject($resource)     => $resource,
            $this->validate()->transfigure()->isArray($resource)      => $this->array2Object($resource),
            $this->validate()->transfigure()->isJson($resource)       => $this->json2Object($resource),
            $this->validate()->transfigure()->isSerialized($resource) => $this->array2Object($this->serialize($resource)),
            $this->validate()->transfigure()->isXml($resource)        => $this->xmlToArray($resource),
            $this->validate()->transfigure()->isString($resource)     => $this->string2Object($resource),
            default                                                   => $this->array2Object($resource),
        };
    }

    public function toBool($value): bool
    {
        if (
            strcasecmp($value,"false") == 0 ||
            strcasecmp($value,"no")    == 0 ||
            $value === '0' ||
            $value === 0
        ) {
            return false;
        }

        return (bool) $value;
    }

    public function toFloat($value): float
    {
        return (float) str_replace(',','', $value.'');
    }

    public function toInteger($value): int
    {
        return (int) str_replace(',','', $value.'');
    }

}
