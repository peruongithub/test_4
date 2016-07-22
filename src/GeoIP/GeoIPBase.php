<?php
namespace trident\GeoIP;


use trident\Server;

class GeoIPBase
{
    /**
     * @return string
     * @todo \geoip_country_code_by_name() generate ErrorException, when must return false
     */
    public static function getClientRegionCode()
    {
        $clientIP = Server::getClientIp();

        if (filter_var($clientIP, FILTER_VALIDATE_IP) === false ||
            $clientIP == $_SERVER['SERVER_ADDR'] || $clientIP == '127.0.0.1'
        ) {
            return 'UA';
        }
        if (extension_loaded('geoip')) {
            $region = geoip_country_code_by_name($clientIP);

            return ($region !== false) ? $region : 'UA';
        } else {
            if (filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                list($_1, $_2, $_3, $_4) = explode('.', $clientIP);
                $intIP = (16777216 * $_1) + (65536 * $_2) + (256 * $_3) + $_4;
                $dir = 'IPv4';
            } else {
                $intIP = self::ip2long_v6($clientIP);
                $dir = 'IPv6';
            }
            $base = require(__DIR__."/geoIPCountry/$dir/base.php");
            $file = 'end';
            foreach ($base as $filename) {
                if (floatval($filename) >= $intIP) {
                    $file = $filename.'-base';
                    break;
                }
            }
            $base = require(__DIR__."/geoIPCountry/$dir/$file.php");
            foreach ($base as $filename) {
                if (floatval($filename) >= $intIP) {
                    $file = $filename;
                    break;
                }
            }

            $base = require(__DIR__."/geoIPCountry/$dir/$file.php");
            foreach ($base as $key => $regionCode) {
                if ($key >= $intIP) {
                    return $regionCode;
                }
            }
        }

        return 'UA';
    }

    public static function ip2long_v6($ip)
    {
        $ip_n = inet_pton($ip);
        $bin = '';
        for ($bit = strlen($ip_n) - 1; $bit >= 0; $bit--) {
            $bin = sprintf('%08b', ord($ip_n[$bit])).$bin;
        }

        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($bin, 2), 10);
        } elseif (function_exists('bcadd')) {
            $dec = '0';
            for ($i = 0; $i < strlen($bin); $i++) {
                $dec = bcmul($dec, '2', 0);
                $dec = bcadd($dec, $bin[$i], 0);
            }

            return $dec;
        } else {
            trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
        }

        return false;
    }

    public static function long2ip_v6($dec)
    {
        $bin = '';
        if (function_exists('gmp_init')) {
            $bin = gmp_strval(gmp_init($dec, 10), 2);
        } elseif (function_exists('bcadd')) {
            do {
                $bin = bcmod($dec, '2').$bin;
                $dec = bcdiv($dec, '2', 0);
            } while (bccomp($dec, '0'));
        } else {
            trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
        }

        $bin = str_pad($bin, 128, '0', STR_PAD_LEFT);
        $ip = array();
        for ($bit = 0; $bit <= 7; $bit++) {
            $bin_part = substr($bin, $bit * 16, 16);
            $ip[] = dechex(bindec($bin_part));
        }
        $ip = implode(':', $ip);

        return inet_ntop(inet_pton($ip));
    }
} 