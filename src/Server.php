<?php

namespace trident;

class Server
{
    const GET = 'GET';
    const POST = 'POST';
    const PUT = 'PUT';
    const PATCH = 'PATCH';
    const DELETE = 'DELETE';
    const HEAD = 'HEAD';
    const OPTIONS = 'OPTIONS';
    const TRACE = 'TRACE';
    const CONNECT = 'CONNECT';

    /**
     * @var  string  trusted proxy server IPs
     */
    private static $trustedProxies = array('127.0.0.1', 'localhost', 'my.localhost', 'test');

    public static function getMethod()
    {
        if (isset($_SERVER['REQUEST_METHOD'])) {
            // Use the server request method
            return $_SERVER['REQUEST_METHOD'];
        }

        return static::GET;
    }

    public static function getReferrer()
    {
        return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
    }

    public static function getHost()
    {
        return isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'];
    }

    public static function getUserAgent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
    }

    public static function getClientIp()
    {
        if (isset($_SERVER['REMOTE_ADDR'])) {
            // The remote IP address
            return $_SERVER['REMOTE_ADDR'];
        } else {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])
                AND isset($_SERVER['REMOTE_ADDR'])
                AND in_array($_SERVER['REMOTE_ADDR'], static::$trustedProxies)
            ) {
                // Use the forwarded IP address, typically set when the
                // client is using a proxy server.
                // Format: "X-Forwarded-For: client1, proxy1, proxy2"
                $client_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

                return \array_shift($client_ips);
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])
                AND isset($_SERVER['REMOTE_ADDR'])
                AND in_array($_SERVER['REMOTE_ADDR'], static::$trustedProxies)
            ) {
                // Use the forwarded IP address, typically set when the
                // client is using a proxy server.
                $client_ips = explode(',', $_SERVER['HTTP_CLIENT_IP']);

                return \array_shift($client_ips);
            }
        }

        return '0.0.0.0';
    }

    public static function getInputPoint()
    {
        $inp = (!empty($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] :
            (!empty($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] :
                (!empty($_SERVER['ORIG_SCRIPT_NAME']) ? $_SERVER['ORIG_SCRIPT_NAME'] : '/index.php')
            )
        );

        return basename($inp, '.php');
    }

    public static function isSecure()
    {
        if (
            (
                !empty($_SERVER['HTTPS'])
                &&
                filter_var($_SERVER['HTTPS'], FILTER_VALIDATE_BOOLEAN)
            )

            ||
            (
                isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                &&
                in_array(strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']), array('https', 'on', '1'))
                &&
                in_array($_SERVER['REMOTE_ADDR'], static::$trustedProxies)
            )
        ) {
            return true;
        }

        return false;
    }

    public static function getURI()
    {
        if (!empty($_SERVER['PATH_INFO'])) {
            // PATH_INFO does not contain the docroot or index
            $uri = $_SERVER['PATH_INFO'];
        } else {
            // REQUEST_URI and PHP_SELF include the docroot and index

            if (isset($_SERVER['REQUEST_URI'])) {
                /**
                 * We use REQUEST_URI as the fallback value. The reason
                 * for this is we might have a malformed URL such as:
                 *
                 *  http://localhost/http://example.com/judge.php
                 *
                 * which parse_url can't handle. So rather than leave empty
                 * handed, we'll use this.
                 */
                $uri = $_SERVER['REQUEST_URI'];
                $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                if ($request_uri) {
                    // Valid URL path found, set it.
                    $uri = $request_uri;
                    unset($request_uri);
                }

            } elseif (isset($_SERVER['PHP_SELF'])) {
                $uri = $_SERVER['PHP_SELF'];
            } elseif (isset($_SERVER['REDIRECT_URL'])) {
                $uri = $_SERVER['REDIRECT_URL'];
            } else {
                // If you ever see this error, please report an issue at http://dev.kohanaphp.com/projects/kohana3/issues
                // along with any relevant information about your web server setup. Thanks!
                throw new \Exception('Unable to detect the URI using PATH_INFO, REQUEST_URI, PHP_SELF or REDIRECT_URL');
            }
        }

        return $uri;
    }
} 