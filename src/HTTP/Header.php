<?php
namespace trident\HTTP;

class Header
{
    // Default Accept-* quality value if none supplied
    const DEFAULT_QUALITY = 1;

    /**
     * Generates a Cache-Control HTTP header based on the supplied array.
     *
     * @link    http://www.w3.org/Protocols/rfc2616/rfc2616-sec13.html#sec13
     * @param   array $cache_control Cache-Control to render to string
     * @return  string
     */
    public static function createCacheControl(array $cache_control)
    {
        $parts = array();
        ksort($cache_control);
        foreach ($cache_control as $key => $value) {
            if (true === $value) {
                $parts[] = $key;
            } else {
                if (preg_match('#[^a-zA-Z0-9._-]#', $value)) {
                    $value = '"'.$value.'"';
                }

                $parts[] = "$key=$value";
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Parses the Cache-Control header and returning an array representation of the Cache-Control
     * header.
     *
     *     // Create the cache control header
     *     $response->headers('cache-control', 'max-age=3600, must-revalidate, public');
     *
     *     // Parse the cache control header
     *     if ($cache_control = static::parse_cache_control($response->headers('cache-control')))
     *     {
     *          // Cache-Control header was found
     *          $maxage = $cache_control['max-age'];
     *     }
     *
     * @param   array $cache_control Array of headers
     * @return  mixed
     */
    public static function parse_cache_control($cache_control)
    {
        $directives = explode(',', strtolower($cache_control));

        if ($directives === false) {
            return false;
        }

        $output = array();

        foreach ($directives as $directive) {
            if (strpos($directive, '=') !== false) {
                list($key, $value) = explode('=', trim($directive), 2);

                $output[$key] = ctype_digit($value) ? (int)$value : $value;
            } else {
                $output[trim($directive)] = true;
            }
        }

        return $output;
    }

    /**
     * Parses the `Accept-Encoding:` HTTP header and returns an array containing
     * the charsets and associated quality.
     *
     * @link    http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.3
     * @param   string $encoding charset string to parse
     * @return  array
     * @since   3.2.0
     */
    public static function parse_encoding_header($encoding = null)
    {
        // Accept everything
        if ($encoding === null) {
            return array('*' => (float)static::DEFAULT_QUALITY);
        } elseif ($encoding === '') {
            return array('identity' => (float)static::DEFAULT_QUALITY);
        } else {
            return static::accept_quality(explode(',', (string)$encoding));
        }
    }

    /**
     * Parses an Accept(-*) header and detects the quality
     *
     * @param   array $parts accept header parts
     * @return  array
     * @since   3.2.0
     */
    public static function accept_quality(array $parts)
    {
        $parsed = array();

        // Resource light iteration
        $parts_keys = array_keys($parts);
        foreach ($parts_keys as $key) {
            $value = trim(str_replace(array("\r", "\n"), '', $parts[$key]));

            $pattern = '~\b(\;\s*+)?q\s*+=\s*+([.0-9]+)~';

            // If there is no quality directive, return default
            if (!preg_match($pattern, $value, $quality)) {
                $parsed[$value] = (float)static::DEFAULT_QUALITY;
            } else {
                $quality = $quality[2];

                if ($quality[0] === '.') {
                    $quality = '0'.$quality;
                }

                // Remove the quality value from the string and apply quality
                $parsed[trim(preg_replace($pattern, '', $value, 1), '; ')] = (float)$quality;
            }
        }

        return $parsed;
    }

    /**
     * Parses the `Accept-Language:` HTTP header and returns an array containing
     * the languages and associated quality.
     *
     * @link    http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4
     * @param   string $language charset string to parse
     * @return  array
     * @since   3.2.0
     */
    public static function parse_language_header($language = null)
    {
        if ($language === null) {
            return array('*' => array('*' => (float)static::DEFAULT_QUALITY));
        }

        $language = static::accept_quality(explode(',', (string)$language));

        $parsed_language = array();

        $keys = array_keys($language);
        foreach ($keys as $key) {
            // Extract the parts
            $parts = explode('-', $key, 2);

            // Invalid content type- bail
            if (!isset($parts[1])) {
                $parsed_language[$parts[0]]['*'] = $language[$key];
            } else {
                // Set the parsed output
                $parsed_language[$parts[0]][$parts[1]] = $language[$key];
            }
        }

        return $parsed_language;
    }

    /**
     * Parses the accept header to provide the correct quality values
     * for each supplied accept type.
     *
     * @link    http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
     * @param   string $accepts accept content header string to parse
     * @return  array
     * @since   3.2.0
     */
    public static function parse_accept_header($accepts = null)
    {
        $accepts = explode(',', (string)$accepts);

        // If there is no accept, lets accept everything
        if ($accepts === null) {
            return array('*' => array('*' => (float)static::DEFAULT_QUALITY));
        }

        // Parse the accept header qualities
        $accepts = static::accept_quality($accepts);

        $parsed_accept = array();

        // This method of iteration uses less resource
        $keys = array_keys($accepts);
        foreach ($keys as $key) {
            // Extract the parts
            $parts = explode('/', $key, 2);

            // Invalid content type- bail
            if (!isset($parts[1])) {
                continue;
            }

            // Set the parsed output
            $parsed_accept[$parts[0]][$parts[1]] = $accepts[$key];
        }

        return $parsed_accept;
    }

    /**
     * Parses the `Accept-Charset:` HTTP header and returns an array containing
     * the charset and associated quality.
     *
     * @link    http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.2
     * @param   string $charset charset string to parse
     * @return  array
     * @since   3.2.0
     */
    public static function parse_charset_header($charset = null)
    {
        if ($charset === null) {
            return array('*' => (float)static::DEFAULT_QUALITY);
        }

        return static::accept_quality(explode(',', (string)$charset));
    }

    /**
     * Parses a HTTP header string into an associative array
     *
     * @param   string $header_string Header string to parse
     * @return  array
     */
    public static function getHeaderFromString($header_string)
    {
        // If the PECL HTTP extension is loaded
        if (extension_loaded('http')) {
            // Use the fast method to parse header string
            return http_parse_headers($header_string);
        }

        // Otherwise we use the slower PHP parsing
        $headers = array();

        // Match all HTTP headers
        if (preg_match_all('/(\w[^\s:]*):[ ]*([^\r\n]*(?:\r\n[ \t][^\r\n]*)*)/', $header_string, $matches)) {
            // Parse each matched header
            foreach ($matches[0] as $key => $value) {
                // If the header has not already been set
                if (!isset($headers[$matches[1][$key]])) {
                    // Apply the header directly
                    $headers[$matches[1][$key]] = $matches[2][$key];
                } // Otherwise there is an existing entry
                else {
                    // If the entry is an array
                    if (is_array($headers[$matches[1][$key]])) {
                        // Apply the new entry to the array
                        $headers[$matches[1][$key]][] = $matches[2][$key];
                    } // Otherwise create a new array with the entries
                    else {
                        $headers[$matches[1][$key]] = array(
                            $headers[$matches[1][$key]],
                            $matches[2][$key],
                        );
                    }
                }
            }
        }

        // Return the headers
        return $headers;
    }

    /**
     * Parses the the HTTP request headers and returns an array containing
     * key value pairs. This method is slow, but provides an accurate
     * representation of the HTTP request.
     *
     * @return  array
     */
    public static function getHeaderFromRequest()
    {
        // If running on apache server
        if (function_exists('apache_request_headers')) {
            // Return the much faster method
            return apache_request_headers();
        } // If the PECL HTTP tools are installed
        elseif (extension_loaded('http')) {
            // Return the much faster method
            return http_get_request_headers();
        }

        // Setup the output
        $headers = array();

        // Parse the content type
        if (!empty($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        // Parse the content length
        if (!empty($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        foreach ($_SERVER as $key => $value) {
            // If there is no HTTP header here, skip
            if (strpos($key, 'HTTP_') !== 0) {
                continue;
            }

            // This is a dirty hack to ensure HTTP_X_FOO_BAR becomes x-foo-bar
            $headers[str_replace(array('HTTP_', '_'), array('', '-'), $key)] = $value;
        }

        return $headers;
    }

    /**
     * Returns the header object as a string, including
     * the terminating new line
     *
     *     // Return the header as a string
     *     echo (string) $request->headers();
     *
     * @return  string
     */
    public function __toString()
    {
        $header = '';

        foreach ($this as $key => $value) {
            if (!empty($value)) {
                // Put the keys back the Case-Convention expected
                $key = str_replace(' ', '-', ucfirst(str_replace('-', ' ', $key)));

                if (is_array($value)) {
                    $header .= $key.': '.(implode(', ', $value))."\r\n";
                } else {
                    $header .= $key.': '.$value."\r\n";
                }
            }
        }

        return $header.(string)$this->cookie;
    }

    public function send()
    {
        foreach ($this->headers as $key => $value) {
            if (!empty($value)) {
                // Put the keys back the Case-Convention expected
                $key = str_replace(' ', '-', ucfirst(str_replace('-', ' ', $key)));

                if (is_array($value)) {
                    header($key.': '.implode(', ', $value));
                } else {
                    header($key.': '.$value);
                }
            }
        }

        $this->cookie->send();
    }

}
