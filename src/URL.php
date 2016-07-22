<?php
namespace trident;

/**
 * URL class.
 */
class URL
{

    public static function base(
        $protocol = 'http',
        $domain = null,
        $port = null,
        $inputPoint = null,
        $path = null,
        array $query = []
    ) {
        if (empty($protocol) || !in_array($protocol, ['http', 'https', 'ftp'])) {
            // Use the configured default protocol
            $protocol = 'http';
        }
        if (empty($domain)) {
            $domain = self::urlencode(Server::getHost());
        }
        if (empty($port)) {
            $port = '';
        } else {
            $port = ':'.$port;
        }
        $inputPoint = empty($inputPoint) ? Server::getInputPoint() : $inputPoint;
        if (Core::hideInputPoint() && $inputPoint == Core::inputPointToHide()) {
            $inputPoint = '';
        } else {
            $inputPoint = '/'.$inputPoint.'.php';
        }
        if (empty($path)) {
            $path = '/';
        } else {
            $path = '/'.self::urlencode($path);
        }
        if (!empty($query)) {
            $query = '?'.http_build_query($query, '', '&');
        } else {
            $query = '';
        }

        return $protocol.'://'.$domain.$port.$inputPoint.$path.$query;
    }

    public static function urlencode($string = '')
    {
        if (preg_match('/[^\x00-\x7F]/S', $string)) {
            // Encode all non-ASCII characters, as per RFC 1738
            $string = preg_replace_callback(
                '~([^/]+)~',
                function ($matches) {
                    return rawurlencode($matches[0]);
                },
                $string
            );
        }

        // Concat the URL
        return $string;
    }

    /**
     * Automatically detects the URI of the main request using PATH_INFO,
     * REQUEST_URI, PHP_SELF or REDIRECT_URL.
     *
     *     $uri = static::detect_uri();
     *
     * @return  string  URI of the main request
     * @throws  Kohana_Exception
     * @since   3.0.8
     */
    public static function detect_uri()
    {
        $uri = Server::getURI();
        // Decode the request URI
        $uri = rawurldecode($uri);

        $inputPointToHide = Core::inputPointToHide();
        if (Core::hideInputPoint() AND strpos($uri, $inputPointToHide) === 0) {
            // Remove the index file from the URI
            $uri = (string)substr($uri, strlen($inputPointToHide));
        }

        return $uri;
    }

    /**
     * UTF-8 aware parse_url() replacement.
     * @param   string $url
     * @return array
     */
    public static function mb_parse_url($url, $component = -1)
    {
        $enc_url = preg_replace_callback(
            '%[^:/@?&=#]+%usD',
            function ($matches) {
                return urlencode($matches[0]);
            },
            $url
        );

        $parts = parse_url($enc_url);

        if ($parts === false) {
            throw new \InvalidArgumentException('Malformed URL: '.$url);
        }

        foreach ($parts as $name => $value) {
            $parts[$name] = urldecode($value);
        }


        switch ($component):
            case -1:
                return $parts;
                break;
            case 'PHP_URL_SCHEME':
                return $parts['scheme'];
                break;
            case ' PHP_URL_HOST':
                return $parts['host'];
                break;
            case 'PHP_URL_PORT':
                return $parts['port'];
                break;
            case 'PHP_URL_USER':
                return $parts['user'];
                break;
            case 'PHP_URL_PASS':
                return $parts['pass'];
                break;
            case 'PHP_URL_PATH':
                return $parts['path'];
                break;
            case 'PHP_URL_QUERY':
                return $parts['query'];
                break;
            case 'PHP_URL_FRAGMENT':
                return $parts['fragment'];
                break;
        endswitch;
    }
}
