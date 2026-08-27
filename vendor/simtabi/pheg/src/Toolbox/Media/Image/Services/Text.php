<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Media\Image\Services;

use Exception;
use Simtabi\Enekia\Vanilla\Validators;
use Simtabi\Pheg\Toolbox\Media\File\FileSystem;
use Simtabi\Pheg\Toolbox\Media\Image\ImageHandler;
use Simtabi\Pheg\Core\Exceptions\ImageException;

/**
 * Class Text
 * @package JBZoo\Image
 */
final class Text
{
    /**
     * @var array
     */
    protected static $default = [
        'position'       => 'bottom',
        'angle'          => 0,
        'font-size'      => 32,
        'color'          => '#ffffff',
        'offset-x'       => 0,
        'offset-y'       => 20,
        'stroke-color'   => '#222',
        'stroke-size'    => 2,
        'stroke-spacing' => 3,
    ];
    
    private ImageHandler $imageHandler;

    private function __construct()
    {
        $this->imageHandler = new ImageHandler;
    }

    public static function invoke(): self
    {
        return new self();
    }

    /**
     * Add text to an image
     *
     * @param resource $image    GD resource
     * @param string   $text     Some text to output on image as watermark
     * @param string   $fontFile TTF font file path
     * @param array    $params   Additional render params
     *
     * @throws ImageException
     * @throws ImageException|Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function render($image, string $text, string $fontFile, array $params = []): void
    {
        // Set vars
        $params        = \array_merge(self::$default, $params);
        $angle         = $this->imageHandler->rotate((float)$params['angle']);
        $position      = $this->imageHandler->position((string)$params['position']);

        $fSize         = (int)$params['font-size'];

        $offsetX       = (int)$params['offset-x'];
        $offsetY       = (int)$params['offset-y'];

        $strokeSize    = (int)$params['stroke-size'];
        $strokeSpacing = (int)$params['stroke-spacing'];

        $imageWidth    = (int)\imagesx($image);
        $imageHeight   = (int)\imagesy($image);

        $color         = \is_string($params['color']) ? $params['color'] : (array)$params['color'];
        $strokeColor   = \is_string($params['stroke-color']) ? $params['stroke-color'] : (array)$params['stroke-color'];

        $colorArr                 = $this->getColor($image, $color);
        [$textWidth, $textHeight] = $this->getTextBoxSize($fSize, $angle, $fontFile, $text);
        $textCoords               = $this->imageHandler->getInnerCoords(
            $position,
            [$imageWidth, $imageHeight],
            [$textWidth, $textHeight],
            [$offsetX, $offsetY]
        );

        $textX = (int)($textCoords[0] ?? null);
        $textY = (int)($textCoords[1] ?? null);

        if ($strokeColor && $strokeSize) {
            if (\is_array($color) || \is_array($strokeColor)) {
                // Multi colored text and/or multi colored stroke
                $strokeColor = $this->getColor($image, $strokeColor);
                $chars = \str_split($text, 1);

                foreach ($chars as $key => $char) {
                    if ($key > 0) {
                        $textX = $this->getStrokeX($fSize, $angle, $fontFile, $chars, $key, $strokeSpacing, $textX);
                    }

                    // If the next letter is empty, we just move forward to the next letter
                    if ($char === ' ') {
                        continue;
                    }

                    $this->renderStroke(
                        $image,
                        $char,
                        [$fontFile, $fSize, \current($colorArr), $angle],
                        [$textX, $textY],
                        [$strokeSize, \current($strokeColor)]
                    );

                    // #000 is 0, black will reset the array so we write it this way
                    if (\next($colorArr) === false) {
                        \reset($colorArr);
                    }

                    // #000 is 0, black will reset the array so we write it this way
                    if (\next($strokeColor) === false) {
                        \reset($strokeColor);
                    }
                }
            } else {
                $rgba = $this->imageHandler->normalizeColor($strokeColor);
                $strokeColor = \imagecolorallocatealpha($image, $rgba[0], $rgba[1], $rgba[2], $rgba[3]);
                $this->renderStroke(
                    $image,
                    $text,
                    [$fontFile, $fSize, \current($colorArr), $angle],
                    [$textX, $textY],
                    [$strokeSize, $strokeColor]
                );
            }
        } elseif (\is_array($color)) { // Multi colored text
            $chars = \str_split($text, 1);
            foreach ($chars as $key => $char) {
                if ($key > 0) {
                    $textX = $this->getStrokeX($fSize, $angle, $fontFile, $chars, $key, $strokeSpacing, $textX);
                }

                // If the next letter is empty, we just move forward to the next letter
                if ($char === ' ') {
                    continue;
                }

                $fontInfo = [$fontFile, $fSize, \current($colorArr), $angle];
                $this->internalRender($image, $char, $fontInfo, [$textX, $textY]);

                // #000 is 0, black will reset the array, so we write it this way
                if (\next($colorArr) === false) {
                    \reset($colorArr);
                }
            }
        } else {
            $this->internalRender($image, $text, [$fontFile, $fSize, $colorArr[0], $angle], [$textX, $textY]);
        }
    }

    /**
     * Determine text color
     *
     * @param resource     $image GD resource
     * @param string|array $colors
     * @return array
     * @throws ImageException
     */
    protected function getColor($image, $colors): array
    {
        $colors = (array)$colors;

        $result = [];
        foreach ($colors as $color) {
            $rgba = $this->imageHandler->normalizeColor($color);
            $result[] = \imagecolorallocatealpha($image, $rgba[0], $rgba[1], $rgba[2], $rgba[3]);
        }

        return $result;
    }

    /**
     * Determine textbox size
     *
     * @param int    $fontSize
     * @param int    $angle
     * @param string $fontFile
     * @param string $text
     * @return array
     *
     * @throws ImageException
     */
    protected function getTextBoxSize(int $fontSize, int $angle, string $fontFile, string $text): array
    {

        // Determine textbox size
        $fontPath   = (new FileSystem())->clean($fontFile);

        if (!(new Validators())->file()->isFile($fontPath)) {
            throw new ImageException("Unable to load font: {$fontFile}");
        }

        $box = \imagettfbbox($fontSize, $angle, $fontFile, $text);
        if ($box) {
            $boxWidth = (int)\abs($box[6] - $box[2]);
            $boxHeight = (int)\abs($box[7] - $box[1]);
        } else {
            throw new ImageException("Can't get box size for {$fontSize}; {$angle}; {$fontFile}; {$text}");
        }

        return [$boxWidth, $boxHeight];
    }

    /**
     * Compact args for imagettftext()
     *
     * @param resource $image  A GD image object
     * @param string   $text   The text to output
     * @param array    $font   [$fontfile, $fontsize, $color, $angle]
     * @param array    $coords [X,Y] Coordinate of the starting position
     */
    protected function internalRender($image, string $text, array $font, array $coords): void
    {
        [$coordX, $coordY] = $coords;
        [$file, $size, $color, $angle] = $font;

        \imagettftext($image, $size, $angle, $coordX, $coordY, $color, $file, $text);
    }

    /**
     *  Same as imagettftext(), but allows for a stroke color and size
     *
     * @param resource $image  A GD image object
     * @param string   $text   The text to output
     * @param array    $font   [$fontfile, $fontsize, $color, $angle]
     * @param array    $coords [X,Y] Coordinate of the starting position
     * @param array    $stroke [$strokeSize, $strokeColor]
     */
    protected function renderStroke($image, string $text, array $font, array $coords, array $stroke): void
    {
        [$coordX, $coordY] = $coords;
        [$file, $size, $color, $angle] = $font;
        [$strokeSize, $strokeColor] = $stroke;

        for ($x = ($coordX - \abs($strokeSize)); $x <= ($coordX + \abs($strokeSize)); $x++) {
            for ($y = ($coordY - \abs($strokeSize)); $y <= ($coordY + \abs($strokeSize)); $y++) {
                \imagettftext($image, $size, $angle, (int)$x, (int)$y, $strokeColor, $file, $text);
            }
        }

        \imagettftext($image, $size, $angle, $coordX, $coordY, $color, $file, $text);
    }

    /**
     * Get X offset for stroke rendering mode
     *
     * @param float  $fontSize
     * @param int    $angle
     * @param string $fontFile
     * @param array  $letters
     * @param int    $charKey
     * @param int    $strokeSpacing
     * @param int    $textX
     * @return int
     * @noinspection PhpTooManyParametersInspection
     */
    protected function getStrokeX(
        float $fontSize,
        int $angle,
        string $fontFile,
        array $letters,
        int $charKey,
        int $strokeSpacing,
        int $textX
    ): int {
        $charSize = \imagettfbbox($fontSize, $angle, $fontFile, $letters[$charKey - 1]);
        if (!$charSize) {
            throw new ImageException("Can't get StrokeX");
        }

        $textX += \abs($charSize[4] - $charSize[0]) + $strokeSpacing;

        return (int)$textX;
    }
}
