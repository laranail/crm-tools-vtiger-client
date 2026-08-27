<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

use DOMNode;
use DOMDocument;
use DOMElement;

/**
 * Class Xml
 * @package Simtabi\Pheg\Toolbox
 */
final class Xml
{
    public const VERSION  = '1.0';
    public const ENCODING = 'UTF-8';

    public function __construct() {}

    /**
     * Escape string before save it as xml content
     *
     * @param string|float|int|null $rawXmlContent
     * @return string
     */
    public function escape($rawXmlContent): string
    {
        $rawXmlContent = (string)preg_replace(
            '/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u',
            ' ',
            (string)$rawXmlContent
        );

        $rawXmlContent = str_replace(
            ['&', '<', '>', '"', "'"],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
            $rawXmlContent
        );

        return $rawXmlContent;
    }

    /**
     * Create DOMDocument object from XML-string
     *
     * @param string|null $source
     * @param bool        $preserveWhiteSpace
     * @return DOMDocument
     */
    public function createFromString(?string $source = null, bool $preserveWhiteSpace = false): \DOMDocument
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = $preserveWhiteSpace;

        if ($source) {
            $document->loadXML($source);
        }

        $document->version      = self::VERSION;
        $document->encoding     = self::ENCODING;
        $document->formatOutput = true;

        return $document;
    }

    /**
     * Convert array to PHP DOMDocument object.
     * Format of input array
     * $source = [
     *     '_node'     => '#document',
     *     '_text'     => null,
     *     '_cdata'    => null,
     *     '_attrs'    => [],
     *     '_children' => [
     *         [
     *             '_node'     => 'parent',
     *             '_text'     => "Content of parent tag",
     *             '_cdata'    => null,
     *             '_attrs'    => ['parent-attribute' => 'value'],
     *             '_children' => [
     *                 [
     *                     '_node'     => 'child',
     *                     '_text'     => "Content of child tag",
     *                     '_cdata'    => null,
     *                     '_attrs'    => [],
     *                     '_children' => [],
     *                 ],
     *             ]
     *         ]
     *     ]
     * ];
     *
     * Format of output
     *     <?xml version="1.0" encoding="UTF-8"?>
     *     <parent parent-attribute="value">Content of parent tag<child>Content of child tag</child></parent>
     *
     *
     * @param array             $xmlAsArray
     * @param DOMElement|null  $domElement
     * @param DOMDocument|null $document
     * @return DOMDocument
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function array2Dom(array $xmlAsArray, ?DOMElement $domElement = null, ?DOMDocument $document = null): DOMDocument {
        if (null === $document) {
            $document = $this->createFromString();
        }

        $domElement = $domElement ?? $document;

        if (array_key_exists('_text', $xmlAsArray) && $xmlAsArray['_text'] !== null) {
            $newNode = $document->createTextNode($xmlAsArray['_text']);
            if ($newNode !== false) {
                $domElement->appendChild($newNode);
            }
        }

        if (array_key_exists('_cdata', $xmlAsArray) && $xmlAsArray['_cdata'] !== null) {
            $newNode = $document->createCDATASection($xmlAsArray['_cdata']);
            if ($newNode !== false) {
                $domElement->appendChild($newNode);
            }
        }

        if ($domElement instanceof DOMElement && array_key_exists('_attrs', $xmlAsArray)) {
            foreach ($xmlAsArray['_attrs'] as $name => $value) {
                $domElement->setAttribute($name, $value);
            }
        }

        if (array_key_exists('_children', $xmlAsArray)) {
            foreach ($xmlAsArray['_children'] as $mixedElement) {
                if (array_key_exists('_node', $mixedElement) && '#' !== $mixedElement['_node'][0]) {
                    $newNode = $document->createElement($mixedElement['_node']);
                    if ($newNode !== false) {
                        $domElement->appendChild($newNode);
                    }

                    /** @phan-suppress-next-line PhanPossiblyFalseTypeArgument */
                    $this->array2Dom($mixedElement, $newNode, $document);
                }
            }
        }

        return $document;
    }

    /**
     * Convert PHP \DOMDocument or \DOMNode object to simple array
     * Format of input XML (as string)
     *     <?xml version="1.0" encoding="UTF-8"?>
     *     <parent parent-attribute="value">Content of parent tag<child>Content of child tag</child></parent>
     *
     * Format of output array
     * $result = [
     *     '_node'     => '#document',
     *     '_text'     => null,
     *     '_cdata'    => null,
     *     '_attrs'    => [],
     *     '_children' => [
     *         [
     *             '_node'     => 'parent',
     *             '_text'     => "Content of parent tag",
     *             '_cdata'    => null,
     *             '_attrs'    => ['parent-attribute' => 'value'],
     *             '_children' => [
     *                 [
     *                     '_node'     => 'child',
     *                     '_text'     => "Content of child tag",
     *                     '_cdata'    => null,
     *                     '_attrs'    => [],
     *                     '_children' => [],
     *                 ],
     *             ]
     *         ]
     *     ]
     * ];
     *
     * @param DOMNode $element
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function dom2Array(DOMNode $element): array
    {
        $result = [
            '_node'     => $element->nodeName,
            '_text'     => null,
            '_cdata'    => null,
            '_attrs'    => [],
            '_children' => [],
        ];

        if ($element->attributes && $element->hasAttributes()) {
            foreach ($element->attributes as $attr) {
                $result['_attrs'][$attr->name] = $attr->value;
            }
        }

        if ($element->hasChildNodes()) {
            $children = $element->childNodes;

            if ($children->length === 1 && $child = $children->item(0)) {
                if ($child->nodeType === XML_TEXT_NODE) {
                    $result['_text'] = $child->nodeValue;
                    return $result;
                }

                if ($child->nodeType === XML_CDATA_SECTION_NODE) {
                    $result['_cdata'] = $child->nodeValue;
                    return $result;
                }
            }

            foreach ($children as $child) {
                if ($child->nodeType !== XML_COMMENT_NODE) {
                    $result['_children'][] = $this->dom2Array($child);
                }
            }
        }

        return $result;
    }

    /**
     * Proofs if an attribute exists and returns its content or returns the option parameter instead.
     *
     * @param DOMElement $element
     * @param string $attribute
     * @param mixed $option
     * @return mixed
     */
    public function hasGetOr(DOMElement $element, string $attribute, $option = false)
    {
        return $element->hasAttribute($attribute) ? $element->getAttribute($attribute) : $option;
    }

    /**
     * Checks if an XML element has an attribute with a given value.
     *
     * @param DOMElement $element
     * @param string $attribute
     * @param mixed $value
     * @return boolean
     */
    public function hasXmlAttribute(DOMElement $element, string $attribute, $value): bool
    {
        return $value === $this->hasGetOr($element, $attribute, null);
    }

    /**
     * Loads HTML safely and ignores errors.
     *
     * @param DOMDocument $domDocument
     * @param string $input
     * @return void
     */
    public function loadDomHTML(DOMDocument $domDocument, string $input): void
    {
        libxml_use_internal_errors(true);

        $domDocument->loadHTML(
            mb_convert_encoding($input, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors(true);
    }
}
