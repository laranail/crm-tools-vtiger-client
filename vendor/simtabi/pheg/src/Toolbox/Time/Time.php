<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Time;

use DateTime as BaseDateTime;
use DateTimeZone;
use Exception;
use Moment\Moment;
use Carbon\Carbon;
use Simtabi\Pheg\Toolbox\Transfigures\Transfigure;
use Westsworld\TimeAgo;
use Simtabi\Enekia\Vanilla\Validators;
use DateTimeInterface;
use BadMethodCallException;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

final class Time
{

    public const MINUTE     = 60;
    public const HOUR       = 3600;
    public const DAY        = 86400;
    public const WEEK       = 604800;      // 7 days
    public const MONTH      = 2592000;     // 30 days
    public const YEAR       = 31536000;    // 365 days

    public const SQL_FORMAT = 'Y-m-d H:i:s';
    public const SQL_NULL   = '0000-00-00 00:00:00';

    private Validators $validators;

    public function __construct()
    {
        $this->validators = new Validators();
    }

    public function period($startDateTime = null, $endDateTime = null): DatePeriod
    {
        return new DatePeriod($startDateTime, $endDateTime );
    }

    public function dateTime($time = "now", $timezone = null, ?string $format = null): DateTime
    {
        return new DateTime($time, $timezone, $format);
    }

    /**
     * Build PHP DateTime object from mixed input
     *
     * @param mixed $time
     * @param null $timezone
     *
     * @return BaseDateTime
     * @throws Exception
     */
    public function factory($time = null, $timezone = null): BaseDateTime
    {
        $timezone = $this->getTimezoneObject($timezone);

        if ($time instanceof BaseDateTime) {
            return $time->setTimezone($timezone);
        }

        $dateTime = new BaseDateTime('@' . $this->convertToTimestamp($time));
        $dateTime->setTimezone($timezone);

        return $dateTime;
    }

    public function getCurrentTime($asTimestamp = false, $format = "Y-m-d H:i:s", $defaultTime = null, $timezone = "Africa/Nairobi") {

        $objDateTime = new BaseDateTime();
        $objDateTime->setTimezone(new DateTimeZone($timezone));

        if (!empty($defaultTime)) {
            $floatUnixTime = (is_string($defaultTime)) ? strtotime($defaultTime) : $defaultTime;
            if (method_exists($objDateTime, "setTimestamp")) {
                $objDateTime->setTimestamp($floatUnixTime);
            }
            else {
                $arrDate = getdate($floatUnixTime);
                $objDateTime->setDate($arrDate['year'],  $arrDate['mon'],     $arrDate['day']);
                $objDateTime->setTime($arrDate['hours'], $arrDate['minutes'], $arrDate['seconds']);
            }
        }

        return $asTimestamp ? strtotime($objDateTime->format($format)): $objDateTime->format($format);

    }

    public function getTimezones(): array
    {

        $lastRegion = null;
        $timezones  = DateTimeZone::listIdentifiers();
        $grouped    = [];
        $formed     = [];
        $flat       = [];

        $formatName = function ($name) {
            $name = str_replace('/', '_', $name);
            $name = str_replace('-', '_', $name);
            return strtolower(trim($name));
        };

        if (is_array($timezones)) {
            foreach ($timezones as $key => $timezone) {

                $dateTimeZone = new DateTimeZone($timezone);
                $expTimezone  = explode('/', $timezone);

                // Let's sample the time there right now
                $currentTime = new BaseDateTime('', $dateTimeZone);
                if (isset($expTimezone[0])) {
                    if ($expTimezone[0] !== $lastRegion) {
                        $lastRegion = $expTimezone[0];
                    }
                    $getOffset = $this->formatDisplayOffset($dateTimeZone->getOffset(new BaseDateTime()));
                    $grouped[$formatName($lastRegion)][$formatName($timezone)] = [
                        'timezone' => $timezone,
                        'offset'   => $getOffset,
                        'time'     => [
                            'military' => $currentTime->format('H:i'),
                            // Americans can't handle 24hrs, so we give them am pm time
                            'am_pm'    => $currentTime->format('H') > 12 ? $currentTime->format('g:i a') : null,
                        ],
                    ];
                    $formed[$lastRegion][$formatName($timezone)] = $timezone ." (". $getOffset . ")";
                    $flat[$formatName($timezone)]   = $timezone ." (". $getOffset . ")";
                    unset($getOffset);
                }
                unset($dateTimeZone, $expTimezone);
            }
            unset($key, $timezone);
        }

        unset($lastRegion, $timezones);

        return [
            'grouped' => $grouped,
            'formed'  => $formed,
            'flat'    => $flat,
        ];
    }

    public function getTimezoneObject($timezone = null): DateTimeZone
    {
        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        $timezone = $timezone ?: date_default_timezone_get();

        return new DateTimeZone($timezone);
    }

    public function getTimeAgo($time, $fromTimestamp = false, $tense = "ago"){

        return (new TimeAgo())->inWordsFromStrings($time);

        if(empty($time)) return "n/a";
        $time       = true === $fromTimestamp ? $time : strtotime($time);
        $periods    = ["second", "minute", "hour", "day", "week", "month", "year", "decade"];
        $lengths    = ["60","60","24","7","4.35","12","10"];
        $now        = time();
        $difference = $now - $time;
        for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
            $difference /= $lengths[$j];
        }
        $difference = round($difference);
        if($difference != 1) {
            $periods[$j].= "s";
        }
        return "$difference $periods[$j] $tense ";
    }

    public function timelyGreetings($timezone = 'Europe/London'): string | bool
    {

        $time = (Carbon::now(new DateTimeZone($timezone)))->hour;

        /* If the time is less than 1200 hours, show good morning */
        if ($time < 12)
        {
            $greetings = "Good morning";
        }

        /* If the time is grater than or equal to 1200 hours, but less than 1700 hours, so good afternoon */
        elseif ($time >= 12 && $time < 17)
        {
            $greetings = "Good afternoon";
        }

        /* Should the time be between or equal to 1700 and 1900 hours, show good evening */
        elseif ($time >= 17 && $time < 19)
        {
            $greetings = "Good evening";
        }

        /* Finally, show good night if the time is greater than or equal to 1900 hours */
        elseif ($time >= 19)
        {
            $greetings = "Good night";
        }

        return $greetings ?? false;
    }

    public function getDateDifference($end, $start, $endTimeZone = 'Africa/Nairobi', $startTimeZone = 'Africa/Nairobi'){
        return (new Moment($end, $endTimeZone))->from($start, $startTimeZone);
    }

    public function getDateTimeDifference($endTime, $startTime, $twoDigitView = false){

        $fmt  = 'Y-m-d H:i:s';
        $str  = $this->convertToSimpleTime($startTime, $fmt);
        $now = new BaseDateTime($str);
        $end  = $this->convertToSimpleTime($endTime, $fmt);
        $ref = new BaseDateTime($end);
        $diff = $now->diff($ref);

        // build formats
        if ($twoDigitView){
            $_y     = $diff->format("%Y");
            $y_s    = $diff->format("%Y years");

            $_mn    = $diff->format("%a");
            $mn_s   = $diff->format("%a months");

            $_d     = $diff->format("%D");
            $d_s    = $diff->format("%D days");

            $_h     = $diff->format("%H");
            $h_s    = $diff->format("%H hours");

            $_m     = $diff->format("%I");
            $m_s    = $diff->format("%I minutes");

            $_s     = $diff->format("%S");
            $s_s    = $diff->format("%S seconds");

            $string = $diff->format("%Y years %a months %D days %H hours %I minutes %S seconds");
        }
        else{
            $_y     = $diff->format("%y");
            $y_s    = $diff->format("%y years");

            $_mn    = $diff->format("%a");
            $mn_s   = $diff->format("%a months");

            $_d     = $diff->format("%y");
            $d_s    = $diff->format("%y days");

            $_h     = $diff->format("%i");
            $h_s    = $diff->format("%i hours");

            $_m     = $diff->format("%i");
            $m_s    = $diff->format("%i minutes");

            $_s     = $diff->format("%s");
            $s_s    = $diff->format("%s seconds");

            $string = $diff->format("%y years %a months %d days %h hours %i minutes %s seconds");
        }

        return (new Transfigure())->toObject(array(
            'years' => [
                'digits' => $_y,
                'string' => $y_s,
            ],
            'months' => [
                'digits' => $_mn,
                'string' => $mn_s,
            ],
            'days' => [
                'digits' => $_d,
                'string' => $d_s,
            ],
            'hours' => [
                'digits' => $_h,
                'string' => $h_s,
            ],
            'minutes' => [
                'digits' => $_m,
                'string' => $m_s,
            ],
            'seconds' => [
                'digits' => $_s,
                'string' => $s_s,
            ],

            'string' => $string,
        ));
    }

    public function getYearsInRange($endYear = '', $startYear = 1900, $sort = true){

        // Year to start available options at
        if(empty($startYear)){
            $startYear = 1900;
        }
        if(true !== $this->validators->time()->isYear($startYear)){
            $startYear = 1900;
        }

        // Set your latest year you want in the range, in this case we use PHP to
        # just set it to the current year.
        if(empty($endYear)){
            $endYear = date('Y');
        }
        if(true !== $this->validators->time()->isYear($endYear)){
            $endYear = date('Y');
        }

        // build year ranges
        $years = range( $endYear, $startYear );
        $out = array();
        for($i = 0; $i < count($years); $i++){
            $out[$years[$i]] = $years[$i];
        }

        // if sort
        if($sort){
            natsort($out);
            $out = array_reverse($out, true);
        }
        return $out;
    }

    public function getYearsInRangeByOrder($startYear = 1900, $endYear = null, $sort = false){

        $currentYear = empty($endYear) ? date('Y') : $endYear;

        // range of years
        $years = range($startYear, $currentYear);

        // if sort
        if($sort){
            natsort($years);
            $years = array_reverse($years, true);
        }
        return $years;
    }

    public function getDateOrdinalSuffix(){
        return date('M j<\s\up>S</\s\up> Y'); // >= PHP 5.2.2
    }

    /**
     * Creating date collection between two dates
     *
     * Example 1
     * date_range("2014-01-01", "2014-01-20", "+1 day", "m/d/Y");
     *
     * Example 2 - you can use even time
     * date_range("01:00:00", "23:00:00", "+1 hour", "H:i:s");
     *
     * @author Ali OYGUR <alioygur@gmail.com>
     * @param string since any date, time or datetime format
     * @param string until any date, time or datetime format
     * @param string step
     * @param string date of output format
     * @return array
     */
    public function getIndexedDatesInArray($from, $to, $step = '+1 day', $outputFormat = 'Y-m-d'): array
    {
        $dates   = [];
        $current = strtotime($from);
        $last    = strtotime($to);

        while($current <= $last) {
            $dates[] = date($outputFormat, $current);
            $current = strtotime($step, $current);
        }

        return $dates;
    }

    /**
     * Create associative date collection between two dates
     *
     * Example 1
     * date_range("2014-01-01", "2014-01-20", 0, "+1 day", "m/d/Y");
     *
     * Example 2 - you can use even time
     * date_range("01:00:00", "23:00:00", 0, "+1 hour", "H:i:s");
     *
     * @param $from
     * @param $to
     * @param null $default
     * @param string $step
     * @param string $outputFormat
     * @return array
     */
    public function getAssociativeDatesInArray($from, $to, $default = null, string $step = '+1 day', string $outputFormat = 'Y-m-d'): array
    {
        return array_fill_keys($this->getIndexedDatesInArray($from, $to, $step, $outputFormat), $default);
    }

    /**
     * Convert to timestamp
     *
     * @param string|int|BaseDateTime|null $time
     * @param bool                         $currentIsDefault
     *
     * @return int
     */
    public function convertToTimestamp($time = null, bool $currentIsDefault = true): int
    {
        if ($time instanceof BaseDateTime) {
            return (int) $time->format('U');
        }

        if (null !== $time) {
            $time = is_numeric($time) ? (int)$time : (int)strtotime($time);
        }

        if (!$time) {
            $time = $currentIsDefault ? time() : 0;
        }

        return $time;
    }

    public function convertYearsToSeconds($value = '1'){
        return ceil($value * 31536000);
    }

    public function convertMonthsToSeconds($value = '1'){
        return ceil($value * 2592000);
    }

    public function convertWeeksToSeconds($value = '1'){
        return ceil($value * 604800);
    }

    public function convertDaysToSeconds($value = '1'){
        return $value * (24*(60*60));
    }

    public function convertHoursToSeconds($value){
        return $value * (60*60);
    }

    public function convertMinutesToSeconds($value){
        return $value *60;
    }

    public function convertTimestamp(int $timestamp, $format = 'j M Y H:i'): ?string
    {
        $baseTime  = Carbon::create(0000, 0, 0, 00, 00, 00);
        $timestamp = Carbon::parse($timestamp);
        if ($timestamp->lte($baseTime)) {
            return null;
        }

        return $timestamp->format($format);
    }

    public function convertSecondsToTime(float $seconds, int $minValuableSeconds = 2): string
    {
        if ($seconds < $minValuableSeconds) {
            return number_format($seconds, 3) . ' sec';
        }

        return gmdate('H:i:s', (int)round($seconds, 0)) ?: '';
    }

    public function convertMinutesToTime(int $minutes)
    {
        $minutes_per_day = (Carbon::HOURS_PER_DAY * Carbon::MINUTES_PER_HOUR);
        $days            = floor($minutes / ($minutes_per_day));
        $hours           = floor(($minutes - $days * ($minutes_per_day)) / Carbon::MINUTES_PER_HOUR);
        $mins            = (int) ($minutes - ($days * ($minutes_per_day)) - ($hours * 60));

        return "{$days} Days {$hours} Hours {$mins} Mins";
    }

    public function convertStringToDate($string, $fromFormat = 'Y-m-d', $toFormat = 'F j, Y')
    {
        $date = BaseDateTime::createFromFormat($fromFormat, $string);
        return ($date instanceof BaseDateTime) ? $date->format($toFormat) : '';
    }

    public function convertToSqlFormat(?string $time, $forSql = true, $readFormat = self::SQL_FORMAT, $storeFormat = self::SQL_FORMAT): string|null
    {

        if (empty($time))
        {
            return  null;
        }

        $strReplace = function ($char, $time) {

            $time = str_replace( "/", $char, trim($time));
            $time = str_replace( ",", $char, $time);
            $time = str_replace( ".", $char, $time);

            return $time;
        };

        $time = Carbon::parse($strReplace('/', $time))->format($readFormat);

        if ($forSql) {
            return $this->factory($strReplace('-', $time))->format($storeFormat);
        }

        return $this->factory($time)->format($readFormat);
    }

    public function convertToSimpleTime($time, $outputFormat = 'M j, Y g:i a', $inputFormat = 'Y-m-d H:i:s', $timezone = "Africa/Nairobi"){

        // set default fallback format
        $inputFormat = empty($inputFormat) ? 'Y-m-d H:i:s' : $inputFormat;

        // init date object
        $dateObj = new BaseDateTime();
        $dateObj->setTimezone(new DateTimeZone($timezone));

        $time        = $dateObj->setTimestamp($time)->format($inputFormat);
        $formatted = BaseDateTime::createFromFormat($inputFormat, $time);
        if($formatted && $formatted->format($inputFormat) == $time){
            return (new BaseDateTime($time))->format($outputFormat);
        }
    }

    public function convertTime($datetime, string $format = 'M jS, Y H:i T', string $timezone = 'America/New_York'): string
    {
        return $this->factory($datetime, $timezone)->format($format);
    }

    public function formatDisplayOffset($offset, $showUTC = true): ?string
    {
        $initial = new BaseDateTime();
        $initial->setTimestamp(abs($offset));

        return ($showUTC === true ? "UTC " : null) . ($offset >= 0 ? '+':'-') . $initial->format('H:i');
    }

    public function string2CarbonObject(string $string): Carbon
    {
        return Carbon::parse($string);
    }

    /**
     * @throws Exception
     */
    public function evaluateCertainTime($dateTimeStr, $operand = '>', $datetimeFormat = "Y-m-d H:i:s"): bool
    {
        $timeNow = new BaseDateTime($this->getCurrentTime(true, $datetimeFormat));
        $timeAgo = new BaseDateTime($dateTimeStr);

        return match (strtolower($operand)) {
            '>'  => ($timeAgo > $timeNow),
            '>=' => ($timeAgo >= $timeNow),
            '<'  => ($timeAgo < $timeNow),
            '<=' => ($timeAgo <= $timeNow),
            default => throw new Exception('Operand not set or is invalid'),
        };
    }


    /**
     * Return a timestamp as DateTime object.
     *
     * @param mixed $value
     * @param string $datetimeFormat
     * @return Carbon
     */
    public function timestamp2DateTime(mixed $value, string $datetimeFormat = 'Y-m-d H:i:s'): Carbon
    {
        // If this value is already a Carbon instance, we shall just return it as is.
        // This prevents us having to re-instantiate a Carbon instance when we know
        // it already is one, which wouldn't be fulfilled by the DateTime check.
        if ($value instanceof Carbon) {
            return $value;
        }

        // If the value is already a DateTime instance, we will just skip the rest of
        // these checks since they will be a waste of time, and hinder performance
        // when checking the field. We will just return the DateTime right away.
        if ($value instanceof DateTimeInterface) {
            return new Carbon(
                $value->format('Y-m-d H:i:s.u'), $value->getTimeZone()
            );
        }

        // If this value is an integer, we will assume it is a UNIX timestamp's value
        // and format a Carbon object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        // If the value is in simply year, month, day format, we will instantiate the
        // Carbon instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Carbonized conversion.
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        }

        // Parse ISO 8061 date
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})\\+(\d{2}):(\d{2})$/', $value)) {
            return Carbon::createFromFormat('Y-m-d\TH:i:s+P', $value);
        }
        elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2}T(\d{2}):(\d{2}):(\d{2})\\.(\d{1,3})Z)$/', $value)) {
            return Carbon::createFromFormat('Y-m-d\TH:i:s.uZ', $value);
        }

        // Finally, we will just assume this date is in the format used by default on
        // the database connection and use that format to create the Carbon object
        // that is returned out to the developers after we convert it here.
        return Carbon::createFromFormat($datetimeFormat, $value);
    }




    /**
     * Creating date collection between two dates
     *
     * Example 1
     * date_range("2014-01-01", "2014-01-20", "+1 day", "m/d/Y");
     *
     * Example 2 - you can use even time
     * date_range("01:00:00", "23:00:00", "+1 hour", "H:i:s");
     *
     * @author Ali OYGUR <alioygur@gmail.com>
     * @param string since any date, time or datetime format
     * @param string until any date, time or datetime format
     * @param string step
     * @param string date of output format
     * @return array
     */
    public function dates2range($from, $to, string $step = '+1 day', string $outputFormat = 'Y-m-d'): array
    {
        $current = strtotime($from);
        $last    = strtotime($to);
        $dates   = [];

        while($current <= $last) {
            $dates[] = date($outputFormat, $current);
            $current = strtotime($step, $current);
        }

        return $dates;
    }

    /**
     * Create associative date collection between two dates
     *
     * Example 1
     * date_range("2014-01-01", "2014-01-20", 0, "+1 day", "m/d/Y");
     *
     * Example 2 - you can use even time
     * date_range("01:00:00", "23:00:00", 0, "+1 hour", "H:i:s");
     *
     * @param $from
     * @param $to
     * @param null $default
     * @param string $step
     * @param string $outputFormat
     * @return array
     */
    public function dates2rangeAssoc($from, $to, $default = null, string $step = '+1 day', string $outputFormat = 'Y-m-d'): array
    {
        return array_fill_keys($this->dates2range($from, $to, $step, $outputFormat), $default);
    }

    public function getDefaultDateFormats(): array
    {
        $formats = [
            'Y-m-d',
            'Y-M-d',
            'y-m-d',
            'm-d-Y',
            'M-d-Y',
        ];

        foreach ($formats as $format) {
            $formats[] = str_replace('-', '/', $format);
        }

        $formats[] = 'M d, Y';

        return $formats;
    }

    public function formatTime(Carbon $timestamp, ?string $format = 'j M Y H:i'): string
    {
        $first = Carbon::create(0000, 0, 0, 00, 00, 00);

        if ($timestamp->lte($first)) {
            return '';
        }

        return $timestamp->format($format);
    }

    public function formatDate(?string $date, ?string $format = null): ?string
    {
        if (empty($format)) {
            $format = 'Y-m-d';
        }

        if (empty($date)) {
            return $date;
        }

        return $this->formatTime(Carbon::parse($date), $format);
    }

    public function formatDateJs(?string $date, ?string $format = null): ?string
    {
        if (empty($format)) {
            $format = 'yyyy-mm-dd';
        }

        return $this->formatDate($date, $format);
    }

    public function formatDateTime(?string $date, ?string $format = null): ?string
    {
        if (empty($format)) {
            $format ='Y-m-d H:i:s';
        }

        if (empty($date)) {
            return $date;
        }

        return $this->formatTime(Carbon::parse($date), $format);
    }

    public function formatDateTimeJs(?string $date, ?string $format = null): ?string
    {
        if (empty($format)) {
            $format ='yyyy-mm-dd H:i:s';
        }

        return $this->formatDateTime($date, $format);
    }

}
