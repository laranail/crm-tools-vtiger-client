<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Media\Image;

use Simtabi\Pheg\Toolbox\Filter as VarFilter;
use Simtabi\Pheg\Toolbox\Media\File\FileSystem;
use Simtabi\Pheg\Toolbox\System;
use Simtabi\Pheg\Toolbox\Url;
use Simtabi\Enekia\Vanilla\Validators;
use Simtabi\Pheg\Core\Exceptions\ImageException;

/**
 * Class Image
 * @package JBZoo\Image
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
final class ImageManipulator
{
    public const LANDSCAPE = 'landscape';
    public const PORTRAIT  = 'portrait';
    public const SQUARE    = 'square';

    public const FLIP_HORIZONTAL                           = 2;
    public const FLIP_180_COUNTERCLOCKWISE                 = 3;
    public const FLIP_VERTICAL                             = 4;
    public const FLIP_ROTATE_90_CLOCKWISE_AND_VERTICALLY   = 5;
    public const FLIP_ROTATE_90_CLOCKWISE                  = 6;
    public const FLIP_ROTATE_90_CLOCKWISE_AND_HORIZONTALLY = 7;
    public const FLIP_ROTATE_90_COUNTERCLOCKWISE           = 8;

    public const DEFAULT_QUALITY = 95;
    public const DEFAULT_MIME    = 'image/png';

    /**
     * GD Resource or bin data
     * @var resource|null
     */
    protected $image;

    /**
     * @var int
     */
    protected $quality = self::DEFAULT_QUALITY;

    /**
     * @var string|null
     */
    protected $filename;

    /**
     * @var array
     */
    protected $exif = [];

    /**
     * @var int
     */
    protected $width = 0;

    /**
     * @var int
     */
    protected $height = 0;

    /**
     * @var string|null
     */
    protected $orient;

    /**
     * @var string|null
     */
    protected $mime;

    private ImageHandler $handler;
    private FileSystem   $fileSystem;
    private VarFilter    $filter;
    private System       $system;
    private Url          $url;
    private Validators   $validators;

    /**
     * Image constructor.
     *
     * @param resource|string|null $filename
     * @param bool                 $strict
     *
     * @throws ImageException
     * @throws ImageException
     */
    public function __construct($filename = null, bool $strict = false)
    {

        $this->handler    = new ImageHandler;
        $this->fileSystem = new FileSystem;
        $this->filter     = new VarFilter;
        $this->system     = new System;
        $this->url        = new Url;
        $this->validators = new Validators();

        $this->handler->checkGD();

        if (
            $filename
            && \is_string($filename)
            && \ctype_print($filename)
            && $this->validators->file()->isFile($filename)
        ) {
            $this->loadFile($filename);
        } elseif (\is_resource($filename) && $this->validators->file()->isGdResource($filename)) {
            $this->loadResource($filename);
        } elseif ($filename && \is_string($filename)) {
            $this->loadString($filename, $strict);
        }
    }

    public static function invoke($filename = null, bool $strict = false): self
    {
        return new self($filename, $strict);
    }

    /**
     * Destroy image resource
     */
    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * @return array
     */
    public function getInfo(): array
    {
        return [
            'filename' => $this->filename,
            'width'    => $this->width,
            'height'   => $this->height,
            'mime'     => $this->mime,
            'quality'  => $this->quality,
            'exif'     => $this->exif,
            'orient'   => $this->orient,
        ];
    }

    /**
     * Get the current width
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Get the current height
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Get the current image resource
     * @return resource|null
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * @param int $newQuality
     * @return $this
     */
    public function setQuality(int $newQuality): self
    {
        $this->quality = $this->handler->quality($newQuality);
        return $this;
    }

    /**
     * Save an image
     * The resulting format will be determined by the file extension.
     *
     * @param int|null $quality Output image quality in percents 0-100
     * @return $this
     * @throws ImageException
     */
    public function save(?int $quality = null): self
    {
        $quality = $quality ?: $this->quality;

        if ($this->filename) {
            $this->internalSave($this->filename, $quality);
            return $this;
        }

        throw new ImageException('Filename is not defined');
    }

    /**
     * Save an image
     * The resulting format will be determined by the file extension.
     *
     * @param string   $filename If omitted - original file will be overwritten
     * @param null|int $quality  Output image quality in percents 0-100
     * @return $this
     *
     * @throws ImageException
     */
    public function saveAs(string $filename, ?int $quality = null): self
    {
        if (!$filename) {
            throw new ImageException('Empty filename to save image');
        }

        $dir = $this->fileSystem->dirName($filename);
        if (\is_dir($dir)) {
            $this->internalSave($filename, $quality);
        } else {
            throw new ImageException("Target directory \"{$dir}\" not exists");
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isGif(): bool
    {
        return $this->mime && $this->validators->file()->isGif($this->mime);
    }

    /**
     * @return bool
     */
    public function isPng(): bool
    {
        return $this->mime && $this->validators->file()->isPng($this->mime);
    }

    /**
     * @return bool
     */
    public function isWebp(): bool
    {
        return $this->mime && $this->validators->file()->isWebp($this->mime);
    }

    /**
     * @return bool
     */
    public function isJpeg(): bool
    {
        return $this->mime && $this->validators->file()->isJpeg($this->mime);
    }

    /**
     * @param string $filename
     * @param int    $quality
     * @return bool
     */
    protected function savePng(string $filename, int $quality = self::DEFAULT_QUALITY): bool
    {
        if ($this->image) {
            return \imagepng($this->image, $filename ?: null, (int)\round(9 * $quality / 100));
        }

        throw new ImageException('Image resource ins not defined');
    }

    /**
     * @param string $filename
     * @param int    $quality
     * @return bool
     */
    protected function saveJpeg(string $filename, int $quality = self::DEFAULT_QUALITY): bool
    {
        if ($this->image) {
            //imageinterlace($this->image, true);
            return \imagejpeg($this->image, $filename ?: null, (int)\round($quality));
        }

        throw new ImageException('Image resource ins not defined');
    }

    /**
     * @param string $filename
     * @return bool
     */
    protected function saveGif(string $filename): bool
    {
        if ($this->image) {
            return \imagegif($this->image, $filename ?: null);
        }

        throw new ImageException('Image resource ins not defined');
    }

    /**
     * @param string $filename
     * @return bool
     * @phan-suppress-next-line PhanUndeclaredFunction
     */
    protected function saveWebP(string $filename): bool
    {
        if (!\function_exists('\imagewebp')) {
            throw new ImageException('Function imagewebp() is not available. Rebuild your ext-gd for PHP');
        }

        if ($this->image) {
            return \imagewebp($this->image, $filename ?: null);
        }

        throw new ImageException('Image resource ins not defined');
    }

    /**
     * Save image to file
     *
     * @param string   $filename
     * @param int|null $quality
     * @return bool
     *
     * @throws ImageException
     */
    protected function internalSave(string $filename, ?int $quality): bool
    {
        $quality = $quality ?: $this->quality;
        $quality = $this->handler->quality($quality);

        $format = \strtolower($this->fileSystem->ext($filename));
        if (!$this->validators->file()->isSupportedFormat($format)) {
            $format = $this->mime;
        }

        $filename = $this->fileSystem->clean($filename);

        // Create the image
        if ($this->renderImageByFormat($format, $filename, $quality)) {
            $this->loadFile($filename);
            $this->quality = $quality;
            return true;
        }

        return false;
    }

    /**
     * Render image resource as binary
     *
     * @param string|null $format
     * @param string      $filename
     * @param int         $quality
     * @return string|null
     *
     * @throws ImageException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function renderImageByFormat(?string $format, string $filename, int $quality = self::DEFAULT_QUALITY): ?string
    {
        if (!$this->image) {
            throw new ImageException('Image resource not defined');
        }

        $format = (string)($format ?: $this->mime);

        $result = null;
        if ($this->validators->file()->isJpeg($format)) {
            if ($this->saveJpeg($filename, $quality)) {
                $result = 'image/jpeg';
            }
        } elseif ($this->validators->file()->isPng($format)) {
            if ($this->savePng($filename, $quality)) {
                $result = 'image/png';
            }
        } elseif ($this->validators->file()->isGif($format)) {
            if ($this->saveGif($filename)) {
                $result = 'image/gif';
            }
        } elseif ($this->validators->file()->isWebp($format)) {
            if ($this->saveWebP($filename)) {
                $result = 'image/webp';
            }
        } else {
            throw new ImageException("Undefined format: {$format}");
        }

        return $result;
    }

    /**
     * Load an image
     *
     * @param string $filename Path to image file
     * @return $this
     *
     * @throws ImageException
     */
    public function loadFile(string $filename): self
    {
        $cleanFilename = $this->fileSystem->clean($filename);
        if (!$this->validators->file()->isFile($cleanFilename)) {
            throw new ImageException('Image file not found: ' . $filename);
        }

        $this->cleanup();
        $this->filename = $cleanFilename;
        $this->loadMeta();

        return $this;
    }

    /**
     * Load an image
     *
     * @param string|null $imageString Binary images
     * @param bool        $strict
     * @return $this
     *
     * @throws ImageException
     */
    public function loadString(?string $imageString, bool $strict = false): self
    {
        if (!$imageString) {
            throw new ImageException('Image string is empty!');
        }

        $this->cleanup();
        $this->loadMeta($imageString, $strict);

        return $this;
    }

    /**
     * Load image resource
     *
     * @param resource|null $imageRes Image GD Resource
     * @return $this
     *
     * @throws ImageException
     */
    public function loadResource($imageRes): self
    {
        if (!$this->validators->file()->isGdResource($imageRes)) {
            throw new ImageException('Image is not GD resource!');
        }

        $this->cleanup();
        $this->loadMeta($imageRes);

        return $this;
    }

    /**
     * Get meta data of image or base64 string
     *
     * @param resource|string|null $image
     * @param bool                 $strict
     *
     * @return $this
     * @throws ImageException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function loadMeta($image = null, bool $strict = false): self
    {
        // Gather meta data
        if (null === $image && $this->filename) {
            if ($imageInfo = \getimagesize($this->filename)) {
                $this->image = $this->imageCreate((string)($imageInfo['mime'] ?? ''));
            }
        } elseif (\is_resource($image) && $this->validators->file()->isGdResource($image)) {
            $this->image = $image;
            $imageInfo = [
                '0'    => (int)\imagesx($this->image),
                '1'    => (int)\imagesy($this->image),
                'mime' => self::DEFAULT_MIME,
            ];
        } elseif (\is_string($image)) {
            if ($strict) {
                $cleanedString = \str_replace(
                    ' ',
                    '+',
                    (string)\preg_replace('#^data:image/[^;]+;base64,#', '', $image)
                );

                if (\base64_decode($cleanedString, true) === false) {
                    throw new ImageException('Invalid image source.');
                }
            }

            $imageBin = $this->handler->strToBin($image);
            $imageInfo = \getimagesizefromstring($imageBin);
            $this->image = \imagecreatefromstring($imageBin) ?: null;
        } else {
            throw new ImageException('Undefined format of source. Only "resource|string" are expected');
        }

        // Set internal state
        if (\is_array($imageInfo)) {
            $this->mime = $imageInfo['mime'] ?? null;
            $this->width = (int)($imageInfo['0'] ?? 0);
            $this->height = (int)($imageInfo['1'] ?? 0);
        }
        $this->exif = $this->getExif();
        $this->orient = $this->getOrientation();

        // Prepare alpha chanel
        $this->handler->addAlpha($this->image);

        return $this;
    }

    /**
     * Clean whole image object
     * @return $this
     */
    public function cleanup(): self
    {
        $this->filename = null;

        $this->mime     = null;
        $this->width    = 0;
        $this->height   = 0;
        $this->exif     = [];
        $this->orient   = null;
        $this->quality  = self::DEFAULT_QUALITY;

        $this->destroyImage();

        return $this;
    }

    /**
     * Destroy image resource if not empty
     */
    protected function destroyImage(): void
    {
        if ($this->image && $this->validators->file()->isGdResource($this->image)) {
            \imagedestroy($this->image);
            $this->image = null;
        }
    }

    /**
     * @return array
     */
    protected function getExif(): array
    {
        $result = [];

        if ($this->filename && $this->system->isFunc('exif_read_data') && $this->validators->file()->isJpeg($this->mime)) {
            $result = \exif_read_data($this->filename) ?: [];
        }

        return $result;
    }

    /**
     * Create image resource
     *
     * @param string|null $format
     * @return resource
     *
     * @throws ImageException
     */
    protected function imageCreate(?string $format)
    {
        if (!$this->filename) {
            throw new ImageException('Filename is undefined');
        }

        if ($this->validators->file()->isJpeg($format)) {
            $result = \imagecreatefromjpeg($this->filename);
        } elseif ($this->validators->file()->isPng($format)) {
            $result = \imagecreatefrompng($this->filename);
        } elseif ($this->validators->file()->isGif($format)) {
            $result = \imagecreatefromgif($this->filename);
        } elseif (\function_exists('imagecreatefromwebp') && $this->validators->file()->isWebp($format)) {
            /** @phan-suppress-next-line PhanUndeclaredFunction */
            $result = \imagecreatefromwebp($this->filename);
        } else {
            throw new ImageException("Invalid image: {$this->filename}");
        }

        if (!$result) {
            throw new ImageException("Can't create new image resource by filename: {$this->filename}; format: {$format}");
        }

        return $result;
    }

    /**
     * Get the current orientation
     * @return string
     */
    protected function getOrientation(): string
    {
        if ($this->width > $this->height) {
            return self::LANDSCAPE;
        }

        if ($this->width < $this->height) {
            return self::PORTRAIT;
        }

        return self::SQUARE;
    }

    /**
     * @return bool
     */
    public function isPortrait(): bool
    {
        return $this->orient === self::PORTRAIT;
    }

    /**
     * @return bool
     */
    public function isLandscape(): bool
    {
        return $this->orient === self::LANDSCAPE;
    }

    /**
     * @return bool
     */
    public function isSquare(): bool
    {
        return $this->orient === self::SQUARE;
    }

    /**
     * Create an image from scratch
     *
     * @param int               $width  Image width
     * @param int|null          $height If omitted - assumed equal to $width
     * @param array|string|null $color  Hex color string, array(red, green, blue) or array(red, green, blue, alpha).
     *                                  Where red, green, blue - integers 0-255, alpha - integer 0-127
     * @return $this
     * @throws ImageException
     */
    public function create(int $width, ?int $height = null, $color = null): self
    {
        $this->cleanup();

        $height       = $height ?: $width;

        $this->width  = $this->filter->int($width);
        $this->height = $this->filter->int($height);
        if ($newImageRes = \imagecreatetruecolor($this->width, $this->height)) {
            $this->image = $newImageRes;
        } else {
            throw new ImageException("Can't create empty image resource");
        }

        $this->mime = self::DEFAULT_MIME;
        $this->exif = [];

        $this->orient = $this->getOrientation();

        if (null !== $color) {
            return $this->addFilter('fill', $color);
        }

        return $this;
    }

    /**
     * Resize an image to the specified dimensions
     *
     * @param float $width
     * @param float $height
     * @return $this
     */
    public function resize(float $width, float $height): self
    {
        $width  = $this->filter->int($width);
        $height = $this->filter->int($height);

        // Generate new GD image
        if (!$newImage = \imagecreatetruecolor($width, $height)) {
            throw new ImageException("Can't create new image resource");
        }

        if (!$this->image) {
            throw new ImageException('Image resource in not defined');
        }

        if ($this->isGif()) {
            // Preserve transparency in GIFs
            $transIndex = (int)\imagecolortransparent($this->image);
            $palletSize = \imagecolorstotal($this->image);

            if ($transIndex >= 0 && $transIndex < $palletSize) {
                $trColor = \imagecolorsforindex($this->image, $transIndex);

                $red     = 0;
                $green   = 0;
                $blue    = 0;

                if ($trColor) {
                    $red   = $this->filter->int($trColor['red']);
                    $green = $this->filter->int($trColor['green']);
                    $blue  = $this->filter->int($trColor['blue']);
                }

                $transIndex = (int)\imagecolorallocate($newImage, $red, $green, $blue);

                \imagefill($newImage, 0, 0, $transIndex);
                \imagecolortransparent($newImage, $transIndex);
            }
        } else {
            // Preserve transparency in PNG
            $this->handler->addAlpha($newImage, false);
        }

        // Resize
        \imagecopyresampled($newImage, $this->image, 0, 0, 0, 0, $width, $height, $this->width, $this->height);

        // Update meta data
        $this->replaceImage($newImage);
        $this->width  = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * Best fit (proportionally resize to fit in specified width/height)
     * Shrink the image proportionally to fit inside a $width x $height box
     *
     * @param int $maxWidth
     * @param int $maxHeight
     * @return $this
     */
    public function bestFit(int $maxWidth, int $maxHeight): self
    {
        // If it already fits, there's nothing to do
        if ($this->width <= $maxWidth && $this->height <= $maxHeight) {
            return $this;
        }

        // Determine aspect ratio
        $aspectRatio = $this->height / $this->width;

        $width       = $this->width;
        $height      = $this->height;

        // Make width fit into new dimensions
        if ($this->width > $maxWidth) {
            $width  = $maxWidth;
            $height = $width * $aspectRatio;
        }

        // Make height fit into new dimensions
        if ($height > $maxHeight) {
            $height = $maxHeight;
            $width  = $height / $aspectRatio;
        }

        return $this->resize($width, $height);
    }

    /**
     * Thumbnail.
     * This function attempts to get the image to as close to the provided dimensions as possible, and then crops the
     * remaining overflow (from the center) to get the image to be the size specified. Useful for generating thumbnails.
     *
     * @param int      $width
     * @param int|null $height    If omitted - assumed equal to $width
     * @param bool     $topIsZero Force top offset = 0
     *
     * @return $this
     */
    public function thumbnail(int $width, ?int $height = null, bool $topIsZero = false): self
    {
        $width  = $this->filter->int($width);
        $height = $this->filter->int($height);

        // Determine height
        $height = $height ?: $width;

        // Determine aspect ratios
        $currentAspectRatio = $this->height / $this->width;
        $newAspectRatio     = $height / $width;

        // Fit to height/width
        if ($newAspectRatio > $currentAspectRatio) {
            $this->fitToHeight($height);
        } else {
            $this->fitToWidth($width);
        }

        $left = (int)\floor(($this->width / 2) - ($width / 2));
        $top  = (int)\floor(($this->height / 2) - ($height / 2));

        // Return trimmed image
        $right = $width + $left;
        $bottom = $height + $top;

        if ($topIsZero) {
            $bottom -= $top;
            $top = 0;
        }

        return $this->crop($left, $top, $right, $bottom);
    }

    /**
     * Fit to height (proportionally resize to specified height)
     *
     * @param int $height
     * @return $this
     */
    public function fitToHeight(int $height): self
    {
        $height = $this->filter->int($height);
        $width  = $height / ($this->height / $this->width);

        return $this->resize($width, $height);
    }

    /**
     * Fit to width (proportionally resize to specified width)
     *
     * @param int $width
     * @return $this
     */
    public function fitToWidth(int $width): self
    {
        $width  = $this->filter->int($width);
        $height = $width * ($this->height / $this->width);

        return $this->resize($width, $height);
    }

    /**
     * Crop an image
     *
     * @param int $left   Left
     * @param int $top    Top
     * @param int $right  Right
     * @param int $bottom Bottom
     *
     * @return $this
     */
    public function crop(int $left, int $top, int $right, int $bottom): self
    {
        $left   = $this->filter->int($left);
        $top    = $this->filter->int($top);
        $right  = $this->filter->int($right);
        $bottom = $this->filter->int($bottom);

        // Determine crop size
        if ($right < $left) {
            [$left, $right] = [$right, $left];
        }

        if ($bottom < $top) {
            [$top, $bottom] = [$bottom, $top];
        }

        $croppedW = $right - $left;
        $croppedH = $bottom - $top;

        // Perform crop
        $newImage = \imagecreatetruecolor($croppedW, $croppedH);
        $this->handler->addAlpha($newImage);

        if (\is_resource($newImage) && \is_resource($this->image)) {
            \imagecopyresampled($newImage, $this->image, 0, 0, $left, $top, $croppedW, $croppedH, $croppedW, $croppedH);
        } else {
            throw new ImageException("Can't crop image, image resource is undefined");
        }

        // Update meta data
        $this->replaceImage($newImage);
        $this->width = $croppedW;
        $this->height = $croppedH;

        return $this;
    }

    /**
     * @param resource $newImage
     */
    protected function replaceImage($newImage): void
    {
        if (!$this->isSameResource($this->image, $newImage)) {
            $this->destroyImage();

            $this->image = $newImage;
            $this->width = (int)\imagesx($this->image);
            $this->height = (int)\imagesy($this->image);
        }
    }

    /**
     * Rotates and/or flips an image automatically so the orientation will be correct (based on exif 'Orientation')
     *
     * @return $this
     * @throws ImageException
     */
    public function autoOrient(): self
    {
        if (!\array_key_exists('Orientation', $this->exif)) {
            return $this;
        }

        $orient = (int)$this->exif['Orientation'];

        if ($orient === self::FLIP_HORIZONTAL) {
            $this->addFilter('flip', 'x');
        } elseif ($orient === self::FLIP_180_COUNTERCLOCKWISE) {
            $this->addFilter('rotate', -180);
        } elseif ($orient === self::FLIP_VERTICAL) {
            $this->addFilter('flip', 'y');
        } elseif ($orient === self::FLIP_ROTATE_90_CLOCKWISE_AND_VERTICALLY) {
            $this->addFilter('flip', 'y');
            $this->addFilter('rotate', 90);
        } elseif ($orient === self::FLIP_ROTATE_90_CLOCKWISE) {
            $this->addFilter('rotate', 90);
        } elseif ($orient === self::FLIP_ROTATE_90_CLOCKWISE_AND_HORIZONTALLY) {
            $this->addFilter('flip', 'x');
            $this->addFilter('rotate', 90);
        } elseif ($orient === self::FLIP_ROTATE_90_COUNTERCLOCKWISE) {
            $this->addFilter('rotate', -90);
        }

        return $this;
    }

    /**
     * Overlay an image on top of another, works with 24-bit PNG alpha-transparency
     *
     * @param string|ImageManipulator $overlay     An image filename or a Image object
     * @param string       $position    center|top|left|bottom|right|top left|top right|bottom left|bottom right
     * @param float        $opacity     Overlay opacity 0-1 or 0-100
     * @param int          $globOffsetX Horizontal offset in pixels
     * @param int          $globOffsetY Vertical offset in pixels
     *
     * @return $this
     * @throws ImageException
     */
    public function overlay(
        $overlay,
        string $position = 'bottom right',
        float $opacity = .4,
        int $globOffsetX = 0,
        int $globOffsetY = 0
    ): self {
        // Load overlay image
        if (!($overlay instanceof self)) {
            $overlay = new self($overlay);
        }

        // Convert opacity
        $opacity      = $this->handler->opacity($opacity);
        $globOffsetX  = $this->filter->int($globOffsetX);
        $globOffsetY  = $this->filter->int($globOffsetY);

        // Determine position
        $offsetCoords = $this->handler->getInnerCoords(
            $position,
            [$this->width, $this->height],
            [$overlay->getWidth(), $overlay->getHeight()],
            [$globOffsetX, $globOffsetY]
        );

        $xOffset = (int)($offsetCoords[0] ?? null);
        $yOffset = (int)($offsetCoords[1] ?? null);

        // Perform the overlay
        $this->handler->imageCopyMergeAlpha(
            $this->image,
            $overlay->getImage(),
            [$xOffset, $yOffset],
            [0, 0],
            [$overlay->getWidth(), $overlay->getHeight()],
            $opacity
        );

        return $this;
    }

    /**
     * Add filter to current image
     *
     * @param mixed $filter
     * @return $this
     */
    public function addFilter($filter): self
    {
        $args = \func_get_args();
        $args[0] = $this->image;

        if (\is_string($filter)) {
            if (\method_exists(Filter::class, $filter)) {
                /** @var \Closure $filterFunc */
                $filterFunc = [Filter::class, $filter];
                $newImage = \call_user_func_array($filterFunc, $args);
            } else {
                throw new ImageException("Undefined Image Filter: {$filter}");
            }
        } elseif (\is_callable($filter)) {
            $newImage = \call_user_func_array($filter, $args);
        } else {
            throw new ImageException('Undefined filter type');
        }

        if ($this->validators->file()->isGdResource($newImage)) {
            $this->replaceImage($newImage);
        }

        return $this;
    }

    /**
     * Outputs image as data base64 to use as img src
     *
     * @param string|null $format  If omitted or null - format of original file will be used, may be gif|jpg|png
     * @param int|null    $quality Output image quality in percents 0-100
     * @param bool        $addMime
     * @return string
     *
     * @throws ImageException
     */
    public function getBase64(?string $format = 'gif', ?int $quality = null, bool $addMime = true): string
    {
        [$mimeType, $binaryData] = $this->renderBinary($format, $quality);

        $result = \base64_encode($binaryData);

        if ($addMime) {
            $result = 'data:' . $mimeType . ';base64,' . $result;
        }

        return $result;
    }

    /**
     * Outputs image as binary data
     *
     * @param string|null $format  If omitted or null - format of original file will be used, may be gif|jpg|png
     * @param int|null    $quality Output image quality in percents 0-100
     * @return string
     *
     * @throws ImageException
     */
    public function getBinary(?string $format = null, ?int $quality = null): string
    {
        $result = $this->renderBinary($format, $quality);

        return $result[1];
    }

    /**
     * @param string|null $format
     * @param int|null    $quality
     * @return array
     * @throws ImageException
     */
    protected function renderBinary(?string $format, ?int $quality): array
    {
        if (!$this->image) {
            throw new ImageException('Image resource not defined');
        }

        \ob_start();
        $mimeType = $this->renderImageByFormat($format, '', (int)$quality);
        $imageData = \ob_get_clean();

        return [$mimeType, $imageData];
    }

    /**
     * Get relative path to image
     *
     * @return string
     * @throws ImageException
     */
    public function getPath(): string
    {
        if (!$this->filename) {
            throw new ImageException('File not find!');
        }

        return $this->url->pathToRel($this->filename);
    }

    /**
     * Get full URL to image (if not CLI mode)
     *
     * @return string
     * @throws ImageException
     */
    public function getUrl(): string
    {
        $rootPath = $this->url->root();
        $relPath  = $this->getPath();

        return "{$rootPath}/{$relPath}";
    }

    /**
     * @param resource|null $resource1
     * @param resource|null $resource2
     * @return bool
     */
    protected function isSameResource($resource1 = null, $resource2 = null): bool
    {
        if (!$resource1 || !$resource2) {
            return false;
        }

        if ($this->validators->file()->isGdResource($resource1) && $this->validators->file()->isGdResource($resource2)) {
            return (int)$resource1 === (int)$resource2 && (int)$resource1 > 0;
        }

        return false;
    }
    
}
