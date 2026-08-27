<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Colors\Types;

use Exception;
use Simtabi\Pheg\Toolbox\Colors\Helpers\DefinedColor;
use Simtabi\Pheg\Toolbox\Colors\Traits\WithAlphaTrait;
use Simtabi\Pheg\Toolbox\Colors\Traits\WithHslTrait;

class Hsla extends BaseFactory
{
    use WithAlphaTrait;
    use WithHslTrait;

    /**
     * @param string $code
     *
     * @return bool|mixed|string
     */
    protected function validate($code)
    {
        [$class, $index] = property_exists($this, 'lightness') ? ['hsl', 2] : ['hsv', 3];
        $color = str_replace(["{$class}a", '(', ')', ' ', '%'], '', DefinedColor::find($code, $index));
        if (substr_count($color, ',') === 2) {
            $color = "{$color},1.0";
        }
        $color = $this->fixPrecision($color);
        if (preg_match($this->validationRules(), $color, $matches)) {
            if ($matches[1] > 360 || $matches[2] > 100 || $matches[3] > 100 || $matches[4] > 1) {
                return false;
            }
            return $color;
        }
        return false;
    }

    /**
     * @param string $color
     *
     * @return void
     */
    protected function initialize($color)
    {
        [$this->hue, $this->saturation, $this->lightness, $this->alpha] = explode(',', $color);
        $this->alpha = (double) $this->alpha;
    }

    /**
     * @return array
     */
    public function values()
    {
        return array_merge($this->getValues(), [$this->alpha()]);
    }

    /**
     * @throws Exception
     * @return Hsl
     */
    public function toHsl()
    {
        return $this->toRgba()->toHsl();
    }

    /**
     * @throws Exception
     * @return Rgba
     */
    public function toRgba()
    {
        return $this->convertToRgb()->toRgba()->alpha($this->alpha());
    }

    /**
     * @throws Exception
     * @return Rgb
     */
    public function toRgb()
    {
        return $this->toRgba()->toRgb();
    }

    /**
     * @return Hsla
     */
    public function toHsla()
    {
        return $this;
    }

    /**
     * @throws Exception
     * @return Hsv
     */
    public function toHsv()
    {
        return $this->toRgba()->toHsv();
    }

    /**
     * @throws Exception
     * @return Hex
     */
    public function toHex()
    {
        return $this->toRgba()->toHex();
    }

    /**
     * @throws Exception
     * @return Hexa
     */
    public function toHexa()
    {
        return $this->toHex()->toHexa()->alpha($this->alpha());
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return 'hsla(' . implode(',', $this->values()) . ')';
    }
}
