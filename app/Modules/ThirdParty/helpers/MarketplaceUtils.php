<?php 

namespace App\Modules\ThirdParty\Helpers;

use DateTimeZone;
use DateInterval;
use Response;
use DateTime;
use Log;
use App\Modules\ThirdParty\Config;

/**
 * Contains varoius methods for dealing with Marketplaces common
 * issues.
 *
 * @version   1.0
 * @author    Raheel Masood <raheel@hubwire.com>
 * @author    Yuki <yuki@hubwire.com>
 */


class MarketplaceUtils
{
	/**
	 * Get Marketplace Type code
	 *
	 * @param  string $str
	 * @return int
	 * @version 1.0
	 */
	public static function getTypeCode($str='')
	{
		if(strlen($str) > 0)
			return Config::get('marketplace.type.'.$str);
		else
			return Config::get('marketplace.type');

	}

	/**
	 * Get Marketplace Status code
	 *
	 * @param  string $str
	 * @return int
	 * @version 1.0
	 */
	public static function getStatusCode($str)
	{
		return Config::get('marketplace.status_code.'.$str);
	}

	/**
	 * Get Marketplace Sales status
	 *
	 * @param  string $str
	 * @return string
	 * @version 1.0
	 */
	public static function getSalesStatus($str='')
	{
		if(strlen($str) > 0)
			return Config::get('marketplace.sales_status.'.$str);
		else
			return Config::get('marketplace.sales_status');
	}

	/**
	 * Get Marketplace Date format
	 *
	 * @return string
	 * @version 1.0
	 */
	public static function getDateFormat()
	{
		return Config::get('marketplace.std_date_format');
	}

	/**
	 * Get Marketplace Delete status
	 *
	 * @param  string $str
	 * @return string
	 * @version 1.0
	 */
	public static function getDeleteStatus($str)
	{
		return Config::get('marketplace.delete_status.'.$str);
	}

	/**
	 * Return Error Response
	 *
	 * @param  array $response
	 * @param  string $function_name
	 * @return array $return
	 * @version 1.0
	 * @author Raheel <raheel@hubwire.com>
	 */
	public static function errorResponse($response, $function_name, $line_number)
	{
		$return['success'] = false;
		$return['method'] = $function_name;
		$return['line'] = $line_number;

		try
		{
			$return['status_code'] = isset($response['status_code']) ? $response['status_code'] : self::getStatusCode('UNKNOWN');
			$return['error_desc'] = isset($response['error_desc']) ? $response['error_desc'] : $response['ErrorMessage'];
		}
		catch (Exception $e)
		{
			$return['status_code'] = 999;
			$return['error_desc'] = 'Unable to trace Error, refer Error object . Exception :'.$e->getMessage();
			Log::info(print_r($response, true));
		}
		return $return;
	}

	public static function errorDescription($error, $function_name, $line_number)
	{
		if(!isset($error['success']))
			return 'No error status is captured. Original error: '.print_r($error, true);

		if(!$error['success']) {
			return 'Error ['.$error['status_code'].'] '.$error['error_desc'].' in '.$error['method'].' at line '.$error['line'];
		} else {
			return 'No error description captured in '.$function_name.' at line '.$line_number;
		}
	}

	/**
	 * Convert Time
	 *
	 * @param  string $date
	 * @param  string $format        [description]
	 * @param  string $from_timezone [description]
	 * @param  string $to_timezone   [description]
	 * @return object $date
	 * @version 1.0
	 * @author Raheel <raheel@hubwire.com>
	 */
	public static function convertTime($date, $format, $from_timezone, $to_timezone)
	{
		if (empty($date) ==true) return $date;
		$date = new DateTime($date, new DateTimeZone($from_timezone));
        $date->setTimezone(new DateTimeZone($to_timezone));
        return $date->format($format);
    }

    /**
     * Convert Object to Array type
     *
     * @param  object $d
     * @return array $d
     * @version 1.0
     * @author Yuki <yuki@hubwire.com>
     */
    public static function objectToArray($d)
	{
		if (is_object($d)) {
		 	// Gets the properties of the given object
		 	// with get_object_vars function
		 	$d = get_object_vars($d);
		}

		if (is_array($d)) {
		/*
		* Return array converted to object
		* Using __FUNCTION__ (Magic constant)
		* for recursive call
		*/
	 		return array_map(array('self','objectToArray'), $d);
		}
		else
		{
		 	// Return array
		 	return $d;
	 	}
	}

	/**
	 * Convert date to ISO8601 Format
	 *
	 * @param string $date
	 * @param string $format
	 * @version 1.0
	 * @author Raheel <raheel@hubwire.com>
	 */
	public static function ISO8601Date($date, $format)
	{
		$date = DateTime::createFromFormat($format, $date)->format(DateTime::ISO8601);
		return $date;
	}

	/**
	 * Convert Underscore sepadated string to Camel case
	 *
	 * @param  string $str
	 * @return string $str
	 * @version 1.0
	 * @author Raheel <raheel@hubwire.com>
	 */
	public static function underscore2Camelcase($str)
	{
		// Split string in words.
		$words = explode('_', strtolower($str));

		$return = '';
		foreach ($words as $word)
		{
			$return .= ucfirst(trim($word));
		}

		return $return;
	}


}
