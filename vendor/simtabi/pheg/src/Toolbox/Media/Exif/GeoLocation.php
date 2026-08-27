<?php
declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Media\Exif;

use Simtabi\Pheg\Toolbox\Media\Exif\GeoLocation\Altitude;
use Simtabi\Pheg\Toolbox\Media\Exif\GeoLocation\Latitude;
use Simtabi\Pheg\Toolbox\Media\Exif\GeoLocation\Longitude;

class GeoLocation
{
    /** @var Latitude */
    private $latitude;

    /** @var Longitude */
    private $longitude;

    /** @var Altitude */
    private $altitude;

    public function __construct(Latitude $latitude, Longitude $longitude, Altitude $altitude)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->altitude = $altitude;
    }

    /** @psalm-suppress MixedArgumentTypeCoercion */
    public static function fromExifArray(array $exifArray): self
    {
        return new self(
            isset($exifArray['GPSLatitude']) && is_array($exifArray['GPSLatitude']) && isset($exifArray['GPSLatitudeRef'])
                ? Latitude::fromExifArray($exifArray['GPSLatitude'], (string) $exifArray['GPSLatitudeRef'])
                : Latitude::undefined(),
            isset($exifArray['GPSLongitude']) && is_array($exifArray['GPSLongitude']) && isset($exifArray['GPSLongitudeRef'])
                ? Longitude::fromExifArray($exifArray['GPSLongitude'], (string) $exifArray['GPSLongitudeRef'])
                : Longitude::undefined(),
            isset($exifArray['GPSAltitude'])
                ? Altitude::fromString((string) $exifArray['GPSAltitude'])
                : Altitude::undefined()
        );
    }

    public function getLatitude(): Latitude
    {
        return $this->latitude;
    }

    public function getLongitude(): Longitude
    {
        return $this->longitude;
    }

    public function getAltitude(): Altitude
    {
        return $this->altitude;
    }
}
