<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Media\Image\Services;

use Simtabi\Pheg\Toolbox\Filter as VarFilter;
use Simtabi\Pheg\Toolbox\Vars;
use Simtabi\Pheg\Core\Exceptions\ImageException;
use Simtabi\Pheg\Toolbox\Media\Image\ImageHandler;

/**
 * Class Filter
 * @package JBZoo\Image
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class Filter
{
    public const BLUR_SEL            = 0;
    public const BLUR_GAUS           = 1;

    private const DEFAULT_BACKGROUND = '#000000';
    private const MAX_PERCENT        = 100;

    private ImageHandler $imageHandler;
    private Vars         $vars;

    private function __construct()
    {
        $this->imageHandler = new ImageHandler;
        $this->vars         = new Vars;
    }

    public function invoke(): self
    {
        return new self();
    }

    /**
     * Add sepia effect (emulation)
     *
     * @param resource $image Image GD resource
     */
    public function sepia($image): void
    {
        $this->grayscale($image);
        \imagefilter($image, \IMG_FILTER_COLORIZE, 100, 50, 0);
    }

    /**
     * Add grayscale effect
     *
     * @param resource $image Image GD resource
     */
    public function grayscale($image): void
    {
        \imagefilter($image, \IMG_FILTER_GRAYSCALE);
    }

    /**
     * Pixelate effect
     *
     * @param resource $image     Image GD resource
     * @param int      $blockSize Size in pixels of each resulting block
     */
    public function pixelate($image, int $blockSize = 10): void
    {
        \imagefilter($image, \IMG_FILTER_PIXELATE, (new VarFilter)->int($blockSize));
    }

    /**
     * Edge Detect
     *
     * @param resource $image Image GD resource
     */
    public function edges($image): void
    {
        \imagefilter($image, \IMG_FILTER_EDGEDETECT);
    }

    /**
     * Emboss
     *
     * @param resource $image Image GD resource
     */
    public function emboss($image): void
    {
        \imagefilter($image, \IMG_FILTER_EMBOSS);
    }

    /**
     * Negative
     *
     * @param resource $image Image GD resource
     */
    public function invert($image): void
    {
        \imagefilter($image, \IMG_FILTER_NEGATE);
    }

    /**
     * Blur effect
     *
     * @param resource $image  Image GD resource
     * @param int      $passes Number of times to apply the filter
     * @param int      $type   BLUR_SEL|BLUR_GAUS
     */
    public function blur($image, int $passes = 1, int $type = self::BLUR_SEL): void
    {
        $passes    = $this->imageHandler->blur($passes);

        $filterType = \IMG_FILTER_SELECTIVE_BLUR;
        if (self::BLUR_GAUS === $type) {
            $filterType = \IMG_FILTER_GAUSSIAN_BLUR;
        }

        for ($i = 0; $i < $passes; $i++) {
            \imagefilter($image, $filterType);
        }
    }

    /**
     * Change brightness
     *
     * @param resource $image Image GD resource
     * @param int      $level Darkest = -255, lightest = 255
     */
    public function brightness($image, int $level): void
    {
        \imagefilter($image, \IMG_FILTER_BRIGHTNESS, $this->imageHandler->brightness($level));
    }

    /**
     * Change contrast
     *
     * @param resource $image Image GD resource
     * @param int      $level Min = -100, max = 100
     */
    public function contrast($image, int $level): void
    {
        \imagefilter($image, \IMG_FILTER_CONTRAST, $this->imageHandler->contrast($level));
    }

    /**
     * Set colorize
     *
     * @param resource $image       Image GD resource
     * @param string   $color       Hex color string, array(red, green, blue) or array(red, green, blue, alpha).
     *                              Where red, green, blue - integers 0-255, alpha - integer 0-127
     * @param float    $opacity     0-100
     *
     * @throws ImageException
     */
    public function colorize($image, string $color, float $opacity): void
    {
        $rgba  = $this->imageHandler->normalizeColor($color);
        $alpha = $this->imageHandler->opacity2Alpha($opacity);

        $red   = $this->imageHandler->color($rgba[0]);
        $green = $this->imageHandler->color($rgba[1]);
        $blue  = $this->imageHandler->color($rgba[2]);

        \imagefilter($image, \IMG_FILTER_COLORIZE, $red, $green, $blue, $alpha);
    }

    /**
     * Mean Remove
     *
     * @param resource $image Image GD resource
     */
    public function meanRemove($image): void
    {
        \imagefilter($image, \IMG_FILTER_MEAN_REMOVAL);
    }

    /**
     * Smooth effect
     *
     * @param resource $image  Image GD resource
     * @param int      $passes Number of times to apply the filter (1 - 2048)
     */
    public function smooth($image, int $passes = 1): void
    {
        \imagefilter($image, \IMG_FILTER_SMOOTH, $this->imageHandler->smooth($passes));
    }

    /**
     * Desaturate
     *
     * @param resource $image   Image GD resource
     * @param int      $percent Level of desaturization.
     * @return resource
     */
    public function desaturate($image, int $percent = 100)
    {
        // Determine percentage
        $percent = $this->imageHandler->percent($percent);
        $width   = (int)\imagesx($image);
        $height  = (int)\imagesy($image);

        if ($percent === self::MAX_PERCENT) {
            $this->grayscale($image);
        } elseif ($newImage = \imagecreatetruecolor($width, $height)) { // Make a desaturated copy of the image
            \imagealphablending($newImage, false);
            \imagecopy($newImage, $image, 0, 0, 0, 0, $width, $height);
            \imagefilter($newImage, \IMG_FILTER_GRAYSCALE);

            // Merge with specified percentage
            $this->imageHandler->imageCopyMergeAlpha(
                $image,
                $newImage,
                [0, 0],
                [0, 0],
                [$width, $height],
                $percent
            );
            return $newImage;
        } else {
            throw new ImageException("Can't handle image resource by 'imagecreatetruecolor'");
        }

        return $image;
    }

    /**
     * Changes the opacity level of the image
     *
     * @param resource  $image   Image GD resource
     * @param float|int $opacity 0-1 or 0-100
     *
     * @return resource|false
     */
    public function opacity($image, $opacity)
    {
        // Determine opacity
        $opacity = $this->imageHandler->opacity($opacity);

        $width   = (int)\imagesx($image);
        $height  = (int)\imagesy($image);

        if ($newImage = \imagecreatetruecolor($width, $height)) {
            // Set a White & Transparent Background Color
            if ($background = \imagecolorallocatealpha($newImage, 0, 0, 0, 127)) {
                \imagefill($newImage, 0, 0, $background);

                // Copy and merge
                $this->imageHandler->imageCopyMergeAlpha(
                    $newImage,
                    $image,
                    [0, 0],
                    [0, 0],
                    [$width, $height],
                    $opacity
                );

                \imagedestroy($image);

                return $newImage;
            }

            throw new ImageException('Image resourced can\'t be handle by "imagecolorallocatealpha"');
        }

        throw new ImageException('Image resourced can\'t be handle by "imagecreatetruecolor"');
    }

    /**
     * Rotate an image
     *
     * @param resource     $image   Image GD resource
     * @param int          $angle   -360 < x < 360
     * @param string|array $bgColor Hex color string, array(red, green, blue) or array(red, green, blue, alpha).
     *                              Where red, green, blue - integers 0-255, alpha - integer 0-127
     * @return resource|false
     * @throws ImageException
     */
    public function rotate($image, int $angle, $bgColor = self::DEFAULT_BACKGROUND)
    {
        // Perform the rotation
        $angle      = $this->imageHandler->rotate($angle);
        $rgba       = $this->imageHandler->normalizeColor($bgColor);

        $newBgColor = (int)\imagecolorallocatealpha($image, $rgba[0], $rgba[1], $rgba[2], $rgba[3]);
        $newImage   = \imagerotate($image, -($angle), $newBgColor);

        $this->imageHandler->addAlpha($newImage);

        return $newImage;
    }

    /**
     * Flip an image horizontally or vertically
     *
     * @param resource $image     GD resource
     * @param string   $direction Direction of flipping - x|y|yx|xy
     * @return resource
     */
    public function flip($image, string $direction)
    {
        $direction = $this->imageHandler->direction($direction);
        $width     = (int)\imagesx($image);
        $height    = (int)\imagesy($image);

        if ($newImage = \imagecreatetruecolor($width, $height)) {
            $this->imageHandler->addAlpha($newImage);

            if ($direction === 'y') {
                for ($y = 0; $y < $height; $y++) {
                    \imagecopy($newImage, $image, 0, $y, 0, $height - $y - 1, $width, 1);
                }
            } elseif ($direction === 'x') {
                for ($x = 0; $x < $width; $x++) {
                    \imagecopy($newImage, $image, $x, 0, $width - $x - 1, 0, 1, $height);
                }
            } elseif ($direction === 'xy' || $direction === 'yx') {
                $newImage = $this->flip($image, 'x');
                $newImage = $this->flip($newImage, 'y');
            }

            return $newImage;
        }

        throw new ImageException("Image resource can't be handle by \"imagecreatetruecolor\"");
    }

    /**
     * Fill image with color
     *
     * @param resource     $image GD resource
     * @param array|string $color Hex color string, array(red, green, blue) or array(red, green, blue, alpha).
     *                            Where red, green, blue - integers 0-255, alpha - integer 0-127
     * @throws ImageException
     */
    public function fill($image, $color = self::DEFAULT_BACKGROUND): void
    {
        $width     = (int)\imagesx($image);
        $height    = (int)\imagesy($image);

        $rgba      = $this->imageHandler->normalizeColor($color);
        $fillColor = (int)\imagecolorallocatealpha($image, $rgba[0], $rgba[1], $rgba[2], $rgba[3]);

        $this->imageHandler->addAlpha($image, false);
        \imagefilledrectangle($image, 0, 0, $width, $height, $fillColor);
    }

    /**
     * Add text to an image
     *
     * @param resource $image    GD resource
     * @param string   $text     Some text to output on image as watermark
     * @param string   $fontFile TTF font file path
     * @param array    $params
     * @throws ImageException
     * @throws ImageException
     */
    public function text($image, string $text, string $fontFile, array $params = []): void
    {
        Text::render($image, $text, $fontFile, $params);
    }

    /**
     * Add border to an image
     *
     * @param resource $image  Image GD resource
     * @param array    $params Some
     * @throws ImageException
     */
    public function border($image, array $params = []): void
    {
        $params = \array_merge([
            'color' => '#333',
            'size'  => 1,
        ], $params);

        $size   = $this->vars->range((int)$params['size'], 1, 1000);
        $rgba   = $this->imageHandler->normalizeColor((string)$params['color']);
        $width  = (int)\imagesx($image);
        $height = (int)\imagesy($image);

        $posX1  = 0;
        $posY1  = 0;
        $posX2  = $width - 1;
        $posY2  = $height - 1;

        $color  = (int)\imagecolorallocatealpha($image, $rgba[0], $rgba[1], $rgba[2], $rgba[3]);

        for ($i = 0; $i < $size; $i++) {
            \imagerectangle($image, $posX1++, $posY1++, $posX2--, $posY2--, $color);
        }
    }
}
