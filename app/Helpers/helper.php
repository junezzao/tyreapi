<?php
namespace App\Helpers;

use Carbon\Carbon;
use Log;
use Config;

class Helper {

	static function formatDate($date, $format = '')
	{
		if(empty($format)) $format = config('globals.format.date');
		if(empty($date) || strtotime($date) == false) return $date;
	    return date($format, strtotime($date));
	}

	static function formatEmpty($value)
	{
		return empty($value) ? '(empty)' : $value;
	}

	// fromTimezone - Timezone of date to be converted
	static function convertTimeToUTC($date, $fromTimezone, $format = 'Y-m-d H:i:s')
	{
	    return Carbon::createFromFormat('Y-m-d H:i:s', $date, $fromTimezone)->setTimezone('UTC')->format($format);
	}

	static function convertTimeToUserTimezone($date, $userTimezone, $format = 'Y-m-d H:i:s')
	{
	    return Carbon::createFromFormat('Y-m-d H:i:s', $date)->setTimezone($userTimezone)->format($format);
	}

	static function getStatusCode($str)
	{
		return Config::get('globals.status_code.'.$str);
	}
	
	static function errorResponse($response, $function_name, $line_number)
	{
		$return = array();
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
}