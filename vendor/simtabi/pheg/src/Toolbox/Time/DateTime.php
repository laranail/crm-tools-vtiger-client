<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Time;

use BadMethodCallException;
use DateTime as BaseDatetime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Throwable;

/**
 * Class DateTime
 * @package Simtabi\Pheg\Toolbox\Time\DateTime
 *
 * @method formatDateAndTime()
 * @method formatDate()
 * @method formatTime()
 * @method formatSql()
 * @method formatJs()
 * @method formatRss()
 * @method formatSqlDate()
 */
class DateTime extends BaseDatetime
{
    public const FORMAT_TYPE_DATE_AND_TIME = "dateAndTime";
    public const FORMAT_TYPE_DATE          = "date";
    public const FORMAT_TYPE_TIME          = "time";
    public const FORMAT_TYPE_SQL           = "sql";
    public const FORMAT_TYPE_SQL_DATE      = "sqlDate";
    public const FORMAT_TYPE_JS            = "js";
    public const FORMAT_TYPE_RSS           = "rss";

    /**
     * The timezone to use for the "toServer" helper.
     * @var string|DateTimeZone
     */
    protected static string|DateTimeZone $serverTimezone = "UTC";

    /**
     * The default timezone to use for newly created objects
     * @var null|DateTimeZone
     */
    protected static ?DateTimeZone $clientTimezone;

    /**
     * The existing formats we can handle internally
     * @var string[]
     */
    protected static array $formats = [
        self::FORMAT_TYPE_DATE_AND_TIME => "Y.m.d H:i",
        self::FORMAT_TYPE_DATE          => "Y.m.d",
        self::FORMAT_TYPE_TIME          => "H:i",
        self::FORMAT_TYPE_SQL           => "Y-m-d H:i:s",
        self::FORMAT_TYPE_SQL_DATE      => "Y-m-d",
        self::FORMAT_TYPE_JS            => "D M d Y H:i:s O",
        self::FORMAT_TYPE_RSS           => "D, d M Y H:i:s T",
    ];

    /**
     * Determines if a registered format should be localized or not
     * @var array
     */
    protected static array $formatLocalization = [
        self::FORMAT_TYPE_DATE_AND_TIME => TRUE,
        self::FORMAT_TYPE_DATE          => TRUE,
        self::FORMAT_TYPE_TIME          => TRUE,
        self::FORMAT_TYPE_SQL           => FALSE,
        self::FORMAT_TYPE_SQL_DATE      => FALSE,
        self::FORMAT_TYPE_JS            => FALSE,
        self::FORMAT_TYPE_RSS           => FALSE,
    ];

    /**
     * DateTime constructor.
     *
     * @param string|DateTime|BaseDatetime|int $time     Either a DateTime string, a unix timestamp or a formatted string
     * @param string|DateTimeZone|null         $timezone The timezone that should be assumed for $time. By default the
     *                                                method assumes that the incoming $time argument matches the
     *                                                $serverTimezone timezone. There are two special time zones
     *                                                "client" and "server" which refer to the respective, configured
     *                                                timezones.
     * @param string|null                      $format   A format accepted by date() to decode the $time input with. Can
     *                                                also be one of the FORMAT_TYPE_ constants.
     *
     * @throws Exception
     */
    public function __construct($time = "now", $timezone = NULL, ?string $format = NULL) {
        // Prepare the server timezone
        $serverTimezone = static::getServerTimezone();

        // Find the correct input timezone
        $timezone = empty($timezone) ? $serverTimezone : $timezone;
        if (is_string($timezone)) {
            if ($timezone === "server") $timezone = $serverTimezone;
            else if ($timezone === "client") $timezone = static::getClientTimeZone();
        }
        if (!$timezone instanceof DateTimeZone) $timezone = new DateTimeZone($timezone);

        // Handle already existing datetime objects
        if ($time instanceof BaseDatetime || $time instanceof DateTimeImmutable)
            $time = (clone $time)->setTimezone($serverTimezone)->getTimestamp();

        // Check if we have to apply a special format
        if (!empty($format) && !is_numeric($time)) {
            if (!empty(static::getFormat($format))) $format = static::getFormat($format);
            try {
                $timeParsed = parent::createFromFormat($format, $time, $timezone);
            } catch (Throwable $e) {
                $timeParsed = FALSE;
                if ($format !== "Y-m-d H:i:s")
                    $timeParsed = parent::createFromFormat("Y-m-d H:i:s", $time, $timezone);
            }
            if ($timeParsed !== FALSE) $time = $timeParsed->getTimestamp();
        }

        // Convert unix timestamps
        if (is_numeric($time)) $time = "@" . $time;

        // Initialize the root instance
        parent::__construct($time, $timezone);

        // Set default timezone
        $this->setTimezone($serverTimezone);
    }

    /**
     * @inheritDoc
     * @return self|false
     */
    public static function createFromFormat($format, $time, DateTimeZone $timezone = NULL) {
        return new static($time, $timezone, (string)$format);
    }

    /**
     * @inheritDoc
     * @return self|false
     */
    public static function createFromImmutable($dateTimeImmutable) {
        return new static($dateTimeImmutable);
    }

    /**
     * Can be used to change the date formats to match your needs
     *
     * @param string      $type     The type of the format one of the FORMAT_TYPE_ constants
     * @param string|null $format   The valid format() string that should be applied for the type
     *                              NULL to skip the format input -> if you want to toggle $localize
     * @param bool        $localize True to use the localization when this format is applied
     *                              false to use the english as language for this format
     */
    public static function configureFormat(string $type, ?string $format, bool $localize = TRUE): void {
        if ($format !== NULL)
            static::$formats[$type] = $format;
        static::$formatLocalization[$type] = $localize;
    }

    /**
     * Can be used to configure the internal time zone definitions.
     * The one timezone is for the output to the "client" it is in general the default timezone,
     * the second timezone is for storing dates on the "server" which might not be the timezone
     * where the client resides. With this method you can configure both.
     *
     * By default both timezones are set to date_default_timezone_get() for the client and "UTC" for the server.
     *
     * @param DateTimeZone|string $timeZone The timezone to set
     * @param bool                $server   True if you want to update the server timezone
     */
    public static function configureTimezone($timeZone, bool $server = FALSE): void {
        if (!$timeZone instanceof DateTimeZone) {
            if (is_string($timeZone)) $timeZone = new DateTimeZone(trim($timeZone));
            else throw new InvalidArgumentException("The given timezone is invalid! Only strings and DateTimeZone objects are allowed!");
        }
        if (!$server) static::$clientTimezone = $timeZone;
        else static::$serverTimezone = $timeZone;
    }

    /**
     * Returns the configured server timezone object
     *
     * @return DateTimeZone
     */
    public static function getServerTimezone(): DateTimeZone {
        return is_string(static::$serverTimezone) ? new DateTimeZone(static::$serverTimezone) : static::$serverTimezone;
    }

    /**
     * Returns the configured client timezone object
     *
     * @return DateTimeZone
     */
    public static function getClientTimeZone(): DateTimeZone {
        return empty(static::$clientTimezone) ?
            new DateTimeZone(date_default_timezone_get()) : static::$clientTimezone;
    }

    /**
     * Returns a configured format string for a given type or null if the given type was not found
     *
     * @param string $type one of the FORMAT_TYPE_ constants
     *
     * @return string|null
     */
    public static function getFormat(string $type): ?string {
        return static::$formats[$type] ?? NULL;
    }

    /**
     * Returns a new instance with the same values, but converted to the server time zone
     * @return self
     */
    public function toServerTimezone(): DateTime {
        $this->setTimezone(static::getServerTimezone());
        return $this;
    }

    /**
     * Returns a new instance with the same values, but converted to the client time zone
     * @return self
     */
    public function toClientTimezone(): DateTime {
        $this->setTimezone(static::getClientTimeZone());
        return $this;
    }

    /**
     * Set the TimeZone associated with the DateTime
     *
     * @param DateTimeZone|string $timezone
     *
     * @return self
     */
    public function setTimezone($timezone) {
        if (is_string($timezone)) $timezone = new DateTimeZone($timezone);
        parent::setTimezone($timezone);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function format($format) {
        if (is_string($format) && !empty(static::getFormat($format)))
            $format = (string)static::getFormat($format);
        return parent::format($format);
    }

    /**
     * Applies a given format to the given time object.
     * Use the format from: http://php.net/manual/en/function.date.php
     *
     * Modifiers like F, l, M or D will be translated into your LOCALE language as they would with strftime()
     *
     * @param string $format The format to apply as you would with the default format() function
     *
     * @return string
     */
    public function formatLocalized(string $format): string {
        if (!empty(static::getFormat($format))) $format = (string)static::getFormat($format);
        $format = preg_replace_callback('/(?<!\\\\)[FlMD]/s', function ($v) {
            return preg_replace('/(.)/', '\\\\$1',
                strftime(
                    str_replace(
                        ['F', 'l', 'M', 'D'],
                        ['%B', '%A', '%b', '%a'],
                        reset($v)
                    ),
                    $this->getTimestamp()
                )
            );
        }, $format);
        return (string)$this->format($format);
    }

    /**
     * Magic method to provide the easy lookup for all registered formats
     *
     * @param $name
     * @param $arguments
     *
     * @return string|null
     */
    public function __call($name, $arguments) {
        if (strpos($name, "format") !== 0) return NULL;
        $type = lcfirst(substr($name, 6));
        if (empty(self::getFormat($type)))
            throw new BadMethodCallException("There is no format for type: \"$type\"");
        return static::$formatLocalization[$type] ? $this->formatLocalized($type) : $this->format($type);
    }

    /**
     * Allows to convert the instance directly into a string
     * @return string
     */
    public function __toString() {
        return $this->format(static::FORMAT_TYPE_DATE_AND_TIME);
    }
    
}