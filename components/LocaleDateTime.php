<?php
namespace components;


use trident\GeoIP\GeoIPBase;

class LocaleDateTime
{
    protected static $country;
    protected static $timeZone;

    public static function init(array $options)
    {
        static::$country = GeoIPBase::getClientRegionCode();
    }

    public static function getTimeZone()
    {
        if (!empty(static::$timeZone)) {
            return static::$timeZone;
        }
        $territoryData = require('./src/territories/'.static::$country.'.php');

        return static::$timeZone = new \DateTimeZone($territoryData['primaryTimeZone']);
    }
}