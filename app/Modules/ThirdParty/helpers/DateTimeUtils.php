<?php

namespace App\Modules\ThirdParty\Helpers;

use \DateTime;
use \DateTimeZone;

/**
 * Hepler Class to deal with DateTime on
 * different marketplaces.
 *
 * @version   1.0
 * @author    Raheel Masood <raheel@hubwire.com>
 */

class DateTimeUtils
{
    /**
     * Converts the date to ISO8601 Format
     *
     * @param string $date A valid date string
     * @param string $format One of a predefined format from PHP manual
     * @version 1.0
     * @author Raheel Masood <raheel@hubwire.com>
     */
    public static function ISO8601Date($date, $format)
    {
        $date = DateTime::createFromFormat($format, $date)->format(DateTime::ISO8601);
        return $date;
    }

    /**
     * Convert Timezone
     *
     * @param  string $date  A valid date string
     * @param  string $format A valid format on which DateTime is required
     * @param  string $from_timezone Current timezone
     * @param  string $to_timezone Timezone required
     * @return object $date
     * @version 1.0
     * @author Raheel Masood <raheel@hubwire.com>
     */
    public static function convertTime($date, $format, $from_timezone, $to_timezone)
    {
        if (empty($date) ==true) return $date;
        $date = new DateTime($date, new DateTimeZone($from_timezone));
        $date->setTimezone(new DateTimeZone($to_timezone));
        return $date->format($format);
    }
}