<?php 

namespace App\Modules\ThirdParty\Helpers;

use SimpleXMLElement;
use Log;

/**
 * Hepler Class to manipulate XML to Other data types 
 * and vice versa.
 * 
 * @version   1.0
 * @author    Raheel Masood <raheel@hubwire.com>
 * @author    Yuki <yuki@hubwire.com>
 */

class XmlUtils{

	/**
	 * Array to XML
	 * 
	 * @param  array $mp_array 
	 * @param  string $xmlTag
	 * @return string
	 * @version 1.0
	 * @author Yuki <yuki@hubwire.com>
	 */
	public static function arrayToXml($mp_array, $xmlTag)
	{
		$xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><".$xmlTag."></".$xmlTag.">");
		self::arrayToXmlNodes($mp_array, $xml);
		$xml_str = $xml->asXML();
		return $xml_str;
	}

	/**
	 * Convert Object to Array
	 * 
	 * @param  mixed $item 
	 * @return mixed
	 * @version 1.0
	 * @author Cyndel <cyndel@hubwire.com> 
	 */
	public static function objectToArray( $object )
    {
        if( !is_object( $object ) && !is_array( $object ) )
        {
            return $object;
        }
        if( is_object( $object ) )
        {
            $object = get_object_vars( $object );
        }
        return array_map( 'self::objectToArray', $object );
    }

	/**
	 * Callback for arrayToXml
	 * 
	 * @param  array $array_info
	 * @param  string &$xml_info
	 * @return object
	 * @version 1.0
	 * @author Yuki <yuki@hubwire.com>
	 */
	private static function arrayToXmlNodes($array_info, &$xml_info)
	{
		$array_info = self::objectToArray($array_info);
		
		try
		{
			foreach($array_info as $key => $value) 
			{
				if(is_array($value)) 
				{
					// check the children
					for (reset($value); is_int(key($value)); next($value));
					$onlyIntKeys = is_null(key($value));
					if($onlyIntKeys && !empty($value))
					{
						//to handle array without array keys
						foreach($value as $subValue)
						{
							if(is_array($subValue))
							{
								//eg. array( image => array(array('name'=> 'image1', 'url'=>'http://image1.jpg'),array('name'=> 'image2', 'url'=>'http://image2.jpg'))
								//result  <image><name>image1</name><url>http://image1.jpg</url></image><image><name>image3</name><url>http://image2.jpg</url></image>
								$subnode = $xml_info->addChild("$key");
								self::arrayToXmlNodes($subValue, $subnode);
							}
		                	else 
		                	{
		                		//eg. array( image => array('image1_url','image2_url') = result  <image>image1_url</image><image>image2_url</image>
		                		$xml_info->addChild("$key",htmlspecialchars("$subValue"));
							}
						}
					}
					else 
					{
						$subnode = $xml_info->addChild("$key");
		                self::arrayToXmlNodes($value, $subnode);
					}
		        }
		        else 
		        {
		        	//insert node
		        	$xml_info->addChild("$key",htmlspecialchars("$value"));
		        }
		    }
		}
		catch(Exception $ex)
		{
			dd($array_info);
		}
	}

	/**
	 * Replace empty XML
	 * 
	 * @param  string $val 
	 * @return null|string
	 * @author Yuki <yuki@hubwire.com>
	 */
	public static function replaceEmptyXML($val)
	{
		$check = (string)$val;
		if($check == "0") return $val; // exclude 0 from the check
		return (empty($check)) ? null : $val;
	}

	/**
	 * XML to Array
	 * 
	 * @param  object $xml
	 * @return array
	 * @version 1.0
	 * @author Mahadir <mahadir@hubwire.com>
	 */
	public static function xmlToArray($xml)
	{
		$arr = array();
	   	$json = json_encode((array)($xml));
	    $arr = json_decode($json, TRUE);
	    array_walk($arr, array('self','checkArray'));
	    return $arr;
	}

	/**
	 * Check Array
	 * 
	 * @param  mixed &$item 
	 * @param  string $key
	 * @return mixed
	 * @version 1.0
	 * @author Yuki <yuki@hubwire.com> 
	 */
	public static function checkArray(&$item, $key)
	{
		if(is_array($item)){
		    if(!empty($item))
		    	array_walk($item,array('self','checkArray'));
		    else
		    	$item = '';
		 }
	}
}