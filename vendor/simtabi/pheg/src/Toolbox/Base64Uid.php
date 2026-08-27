<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

use GpsLab\Component\Base64UID\Base64UID as B64Uid;
use GpsLab\Component\Base64UID\BitmapEncoder\HexToBase64BitmapEncoder;
use GpsLab\Component\Base64UID\Generator\Binary\FloatingTimeGenerator;
use GpsLab\Component\Base64UID\Generator\Binary\RandomBinaryGenerator;
use GpsLab\Component\Base64UID\Generator\Binary\SnowflakeGenerator;
use GpsLab\Component\Base64UID\Generator\Binary\TimeBinaryGenerator;
use GpsLab\Component\Base64UID\Generator\EncodeBitmapGenerator;
use GpsLab\Component\Base64UID\Generator\RandomBytesGenerator;
use GpsLab\Component\Base64UID\Generator\RandomCharGenerator;

final class Base64Uid
{
    public const  BASE64_UID_CHARSET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const  BASE64_UID_LENGTH  = 16;

    private bool   $nonColliding = true;
    private int    $generatorId  = 0;
    private string $charset      = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-';
    private int    $length       = 16;

    public function __construct() {}

    /**
     * @return bool
     */
    public function isNonColliding(): bool
    {
        return $this->nonColliding;
    }

    /**
     * @param bool $nonColliding
     * @return self
     */
    public function setNonColliding(bool $nonColliding): self
    {
        $this->nonColliding = $nonColliding;
        return $this;
    }

    /**
     * @return int
     */
    public function getGeneratorId(): int
    {
        return $this->generatorId;
    }

    /**
     * @param int $generatorId
     * @return self
     */
    public function setGeneratorId(int $generatorId): self
    {
        $this->generatorId = $generatorId;
        return $this;
    }

    /**
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @param string $charset
     * @return self
     */
    public function setCharset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * @param int $length
     * @return self
     */
    public function setLength(int $length): self
    {
        $this->length = $length;
        return $this;
    }


    public function getUID(): string
    {
        return $this->isNonColliding() ? $this->getEncodedSnowflakeId() : B64Uid::generate($this->length, $this->charset);
    }

    public function getRandomChar(): string
    {

        return (new RandomCharGenerator($this->length, $this->charset))->generate();
    }

    public function getRandomBytes(): string
    {
        return (new RandomBytesGenerator($this->length))->generate();
    }

    public function getRandomEncodedBytes(): string
    {
        return (new EncodeBitmapGenerator(new RandomBinaryGenerator($this->length), new HexToBase64BitmapEncoder()))->generate();
    }

    public function getEncodedBitmapOfTime(): string
    {
        return (new EncodeBitmapGenerator(new TimeBinaryGenerator(), new HexToBase64BitmapEncoder()))->generate();
    }

    public function getEncodedBitmapOfFloatingTime(): string
    {
        return (new EncodeBitmapGenerator(new FloatingTimeGenerator(), new HexToBase64BitmapEncoder()))->generate();
    }

    public function getEncodedSnowflakeId(): string
    {
        return (new EncodeBitmapGenerator(new SnowflakeGenerator($this->generatorId), new HexToBase64BitmapEncoder()))->generate();
    }

    public function generate($charset = null, $prefix = null, int $length = 16) : string
    {

        $charset = $charset ?? $this->charset;
        $prefix  = $prefix && strlen($prefix) > 0 ? $prefix . '_' : '';

        return $prefix . (new self())->setCharset($charset)->setLength($length)->getUID();
    }



}