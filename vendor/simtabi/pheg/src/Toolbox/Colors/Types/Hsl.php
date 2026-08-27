<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Colors\Types;

use Exception;
use Simtabi\Pheg\Toolbox\Colors\Traits\WithHslTrait;

class Hsl extends BaseFactory
{
    use WithHslTrait;

    /**
     * @param string $color
     *
     * @return array
     */
    protected function initialize($color)
    {
        return [$this->hue, $this->saturation, $this->lightness] = explode(',', $color);
    }

    /**
     * @return array
     */
    public function values()
    {
        return $this->getValues();
    }

    /**
     * @throws Exception
     * @return Hex
     */
    public function toHex()
    {
        return $this->toRgb()->toHex();
    }

    /**
     * @throws Exception
     * @return Hexa
     */
    public function toHexa()
    {
        return $this->toHex()->toHexa();
    }

    /**
     * @return Hsl
     */
    public function toHsl()
    {
        return $this;
    }

    /**
     * @throws Exception
     * @return Hsla
     */
    public function toHsla()
    {
        return new Hsla(implode(',', array_merge($this->values(), [1.0])));
    }

    /**
     * Source: https://en.wikipedia.org/wiki/HSL_and_HSV#Interconversion
     *
     * @throws Exception
     * @return Hsv
     */
    public function toHsv()
    {
        [$h, $s, $l] = $this->valuesInUnitInterval();
        $v = $s * min($l, 1 - $l) + $l;
        $s = $v ? 2 * (1 - $l / $v) : 0;
        $code = implode(',', [round($h * 360), round($s * 100), round($v * 100)]);
        return new Hsv($code);
    }

    /**
     * @throws Exception
     * @return Rgb
     */
    public function toRgb()
    {
        return $this->convertToRgb();
    }

    /**
     * @throws Exception
     * @return Rgba
     */
    public function toRgba()
    {
        return $this->toRgb()->toRgba();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return 'hsl(' . implode(',', $this->values()) . ')';
    }
}
