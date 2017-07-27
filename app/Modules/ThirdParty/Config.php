<?php

namespace App\Modules\ThirdParty;

/**
 * The ThirdParty\Config class is specifically used to get 
 * marketplace config values from thirdparty/config folrder.
 * 
 * @version   1.0
 * @author    Raheel Masood <raheel@hubwire.com>
 */

class Config
{
    /**
     * Works same as Laravel default Config.
     * 
     * @param  string $key Key to fetch the value from config files. Nested level array keys should be provided in (.) Dot notation
     *  For example:
        <code>
          Config::get('options.site_id');
          This will be read as $value = $lazada['options']['site_id'];
        </code>
     * @return [type]      [description]
     */
    public static function get($key)
    {
        $value = self::parse($key);
        return $value;
    }

    /**
     * Parse the key to pull out file name and returns the array from the included file
     * 
     * @param  string $key 
     * @return mixed      
     */
    private static function parse($key)
    {
        $parts = explode('.', $key);
        $file_name = array_shift($parts).".php";
        $configurations = require "config/$file_name";
        return self::iterate($parts, $configurations);
    }

    /**
     * Iterate over the keys and go recurvise on configuration array to find the user provided key
     * 
     * @param  array $parts          
     * @param  array $configurations
     * @return mixed
     */
    private static function iterate($parts, $configurations)
    {
         foreach ($parts as $config_key) 
         {
            $configurations = isset($configurations[$config_key]) ? $configurations[$config_key] : null;
         }
         
         return $configurations;
    }

}