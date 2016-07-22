<?php
/**
 * Created by PhpStorm.
 * User: peru
 * Date: 01.12.14
 * Time: 20:07
 */

namespace trident\HTTP;

use trident\Request;
use trident\Response;

/**
 * Редирект с задержкой можно сделать так:
 * HeaderInterface->setHeader('Refresh', '3; URL=http://www.tigir.com/php.htm');
 *
 */
trait HeaderTrait
{
    use CookieTrait;
    /**
     * @var     array    Accept: (content) types
     */
    protected $_accept_content;

    /**
     * @var     array    Accept-Charset: parsed header
     */
    protected $_accept_charset;

    /**
     * @var     array    Accept-Encoding: parsed header
     */
    protected $_accept_encoding;

    /**
     * @var     array    Accept-Language: parsed header
     */
    protected $_accept_language;
    protected $headers = [];

    public function headerToString()
    {
        $header = $this->getSpecialHeaderString()."\r\n";
        foreach ($this->headers as $key => $value) {
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

        return $header.$this->cookieToString();
    }

    /**
     * @return string
     */
    public function getSpecialHeaderString()
    {
        throw new \RuntimeException('This object must override method "getSpecialHeaderString()".');
    }

    public function sendHeader()
    {
        if ($this->isUsedOutputHandlers()) {
            unset($this->headers['content-length']);
        }
        header($this->getSpecialHeaderString(), true);
        $this->setHeaders($this->cookieHeaderKey, $this->strCookie());
        foreach ($this->headers as $key => $value) {
            if (!empty($value)) {
                // Put the keys back the Case-Convention expected
                $key = str_replace(' ', '-', ucfirst(str_replace('-', ' ', $key)));

                if (is_array($value)) {
                    header($key.': '.implode(', ', $value), true);
                } else {
                    header($key.': '.$value, true);
                }
            }
        }

        //$this->sendCookie();

    }

    public function isUsedOutputHandlers()
    {
        $mapper = function ($entry) {
            return ($entry === 'default output handler') ? false : 1;
        };
        $list = array_values(array_filter(array_map($mapper, ob_list_handlers())));

        return !empty($list);
    }

    public function processHeaders()
    {
        if ($this->isUsedOutputHandlers()) {
            unset($this->headers['content-length']);
        }
        //print_r(headers_list());
        header($this->getSpecialHeaderString(), true);
        if ($this instanceof Response && function_exists('http_response_code')) {
            http_response_code($this->status());
        }
        foreach ($this->getHeaders() as $key => $value) {
            if (!empty($value)) {
                // Put the keys back the Case-Convention expected
                $key = str_replace(' ', '-', ucfirst(str_replace('-', ' ', $key)));

                if (is_array($value)) {
                    header($key.': '.implode(', ', $value), true);
                } else {
                    header($key.': '.$value, true);
                }
            }
        }

        $this->sendCookie();
    }

    public function getHeaders($name = null)
    {
        if ($name === null) {
            $headers = $this->headers;
            if (!empty($headers['cache-control'])) {
                $headers['cache-control'] = $this->getCacheControlHeader();
            }

            return $headers;
        }
        $name = strtolower($name);
        if ($name === 'cache-control') {
            if (empty($this->headers['cache-control'])) {
                return null;
            }

            return $this->getCacheControlHeader();
        } elseif (array_key_exists($name, $this->headers)) {
            return $this->headers[$name];
        } else {
            return null;
        }
    }

    public function setHeaders($name, $value = null)
    {
        /*if($this->isLocked){
            return $this;
        }*/
        if (is_array($name)) {
            foreach ($name as $headerSet) {
                $key = strtolower($headerSet[0]);
                if (empty($headerSet[1])) {
                    unset($this->headers[$key]);
                } else {
                    if ($key === 'cache-control') {
                        $this->setCacheControlHeader($value);
                    } else {
                        $this->headers[$key] = $value;
                    }
                }
            }

            return $this;
        } else {
            $name = strtolower($name);
        }
        if (empty($value)) {
            unset($this->headers[$name]);
        } else {
            if ($name === 'cache-control') {
                $this->setCacheControlHeader($value);
            } else {
                $this->headers[$name] = $value;
            }
        }

        return $this;
    }

    protected function getCacheControlHeader()
    {
        if (empty($this->headers['cache-control'])) {
            return '';
        }
        $parts = array();
        ksort($this->headers['cache-control']);
        foreach ($this->headers['cache-control'] as $key => $value) {
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

    public function addCacheControlDirective($key, $value = true)
    {
        if (empty($this->headers['cache-control'])) {
            $this->headers['cache-control'] = [];
        }
        $this->headers['cache-control'][$key] = $value;

        return $this;
    }

    public function getCacheControlDirective($key)
    {
        return $this->hasCacheControlDirective($key) ? $this->headers['cache-control'][$key] : null;
    }

    public function hasCacheControlDirective($key)
    {
        return !empty($this->headers['cache-control']) && array_key_exists($key, $this->headers['cache-control']);
    }

    public function removeCacheControlDirective($key)
    {
        if (!empty($this->headers['cache-control'])) {
            unset($this->headers['cache-control'][$key]);
        }
    }

    /**
     * Parses a HTTP Message header line and applies it to this HTTP_Header
     *
     *     $header = $response->headers();
     *     $header->parse_header_string(NULL, 'content-type: application/json');
     *
     * @param   string $header_line the line from the header to parse
     * @return  int
     * @since   3.2.0
     */
    public function parseHeaderString($header_line)
    {
        if (preg_match_all('/(\w[^\s:]*):[ ]*([^\r\n]*(?:\r\n[ \t][^\r\n]*)*)/', $header_line, $matches)) {

            foreach ($matches[0] as $key => $value) {
                if ($matches[1][$key] == $this->cookieHeaderKey) {
                    $this->setCookieFromString($matches[2][$key]);
                } else {
                    $this->setHeaders($matches[1][$key], $matches[2][$key]);
                }
            }
        }

        return strlen($header_line);
    }

    public function getMimeType($ex)
    {
        $mimeTypes = include('../helpers/mimeTypes.php');
        $mime = (isset($mimeTypes[$ex])) ? $mimeTypes[$ex] : 'application/octet-stream';
        unset($ex, $mimeTypes);

        return $mime;
    }

    /**
     * Returns the preferred response content type based on the accept header
     * quality settings. If items have the same quality value, the first item
     * found in the array supplied as `$types` will be returned.
     *
     *     // Get the preferred acceptable content type
     *     // Accept: text/html, application/json; q=.8, text/*
     *     $result = $header->preferred_accept(array(
     *         'text/html'
     *         'text/rtf',
     *         'application/json'
     *     )); // $result = 'application/json'
     *
     *     $result = $header->preferred_accept(array(
     *         'text/rtf',
     *         'application/xml'
     *     ), TRUE); // $result = FALSE (none matched explicitly)
     *
     *
     * @param   array $types the content types to examine
     * @param   boolean $explicit only allow explicit references, no wildcards
     * @return  string  name of the preferred content type
     * @since   3.2.0
     */
    public function preferred_accept(array $types, $explicit = false)
    {
        $preferred = false;
        $ceiling = 0;

        foreach ($types as $type) {
            $quality = $this->accepts_at_quality($type, $explicit);

            if ($quality > $ceiling) {
                $preferred = $type;
                $ceiling = $quality;
            }
        }

        return $preferred;
    }

    /**
     * Returns the accept quality of a submitted mime type based on the
     * request `Accept:` header. If the `$explicit` argument is `TRUE`,
     * only precise matches will be returned, excluding all wildcard (`*`)
     * directives.
     *
     *     // Accept: application/xml; application/json; q=.5; text/html; q=.2, text/*
     *     // Accept quality for application/json
     *
     *     // $quality = 0.5
     *     $quality = $request->headers()->accepts_at_quality('application/json');
     *
     *     // $quality_explicit = FALSE
     *     $quality_explicit = $request->headers()->accepts_at_quality('text/plain', TRUE);
     *
     * @param   string $type
     * @param   boolean $explicit explicit check, excludes `*`
     * @return  mixed
     * @since   3.2.0
     */
    public function accepts_at_quality($type, $explicit = false)
    {
        // Parse Accept header if required
        if ($this->_accept_content === null) {
            if ($this->header->offsetExists('Accept')) {
                $accept = $this->header->offsetGet('Accept');
            } else {
                $accept = '*/*';
            }

            $this->_accept_content = Header::parse_accept_header($accept);
        }

        // If not a real mime, try and find it in config
        if (strpos($type, '/') === false) {
            $mime = Kohana::$config->load('mimes.'.$type);

            if ($mime === null) {
                return false;
            }

            $quality = false;

            foreach ($mime as $_type) {
                $quality_check = $this->accepts_at_quality($_type, $explicit);
                $quality = ($quality_check > $quality) ? $quality_check : $quality;
            }

            return $quality;
        }

        $parts = explode('/', $type, 2);

        if (isset($this->_accept_content[$parts[0]][$parts[1]])) {
            return $this->_accept_content[$parts[0]][$parts[1]];
        } elseif ($explicit === true) {
            return false;
        } else {
            if (isset($this->_accept_content[$parts[0]]['*'])) {
                return $this->_accept_content[$parts[0]]['*'];
            } elseif (isset($this->_accept_content['*']['*'])) {
                return $this->_accept_content['*']['*'];
            } else {
                return false;
            }
        }
    }

    /**
     * Returns the preferred charset from the supplied array `$charsets` based
     * on the `Accept-Charset` header directive.
     *
     *      // Accept-Charset: utf-8, utf-16; q=.8, iso-8859-1; q=.5
     *      $charset = $header->preferred_charset(array(
     *          'utf-10', 'ascii', 'utf-16', 'utf-8'
     *      )); // $charset = 'utf-8'
     *
     * @param   array $charsets charsets to test
     * @return  mixed   preferred charset or `FALSE`
     * @since   3.2.0
     */
    public function preferred_charset(array $charsets)
    {
        $preferred = false;
        $ceiling = 0;

        foreach ($charsets as $charset) {
            $quality = $this->accepts_charset_at_quality($charset);

            if ($quality > $ceiling) {
                $preferred = $charset;
                $ceiling = $quality;
            }
        }

        return $preferred;
    }

    /**
     * Returns the quality of the supplied `$charset` argument. This method
     * will automatically parse the `Accept-Charset` header if present and
     * return the associated resolved quality value.
     *
     *      // Accept-Charset: utf-8, utf-16; q=.8, iso-8859-1; q=.5
     *      $quality = $header->accepts_charset_at_quality('utf-8');
     *            // $quality = (float) 1
     *
     * @param   string $charset charset to examine
     * @return  float   the quality of the charset
     * @since   3.2.0
     */
    public function accepts_charset_at_quality($charset)
    {
        if ($this->_accept_charset === null) {
            if ($this->header->offsetExists('Accept-Charset')) {
                $charset_header = strtolower($this->$this->header('Accept-Charset'));
                $this->_accept_charset = Header::parse_charset_header($charset_header);
            } else {
                $this->_accept_charset = Header::parse_charset_header(null);
            }
        }

        $charset = strtolower($charset);

        if (isset($this->_accept_charset[$charset])) {
            return $this->_accept_charset[$charset];
        } elseif (isset($this->_accept_charset['*'])) {
            return $this->_accept_charset['*'];
        } elseif ($charset === 'iso-8859-1') {
            return (float)1;
        }

        return (float)0;
    }

    /**
     * Returns the preferred message encoding type based on quality, and can
     * optionally ignore wildcard references. If two or more encodings have the
     * same quality, the first listed in `$encodings` will be returned.
     *
     *     // Accept-Encoding: compress, gzip, *; q.5
     *     $encoding = $header->preferred_encoding(array(
     *          'gzip', 'bzip', 'blowfish'
     *     ));
     *     // $encoding = 'gzip';
     *
     * @param   array $encodings encodings to test against
     * @param   boolean $explicit explicit check, if `TRUE` wildcards are excluded
     * @return  mixed
     * @since   3.2.0
     */
    public function preferred_encoding(array $encodings, $explicit = false)
    {
        $ceiling = 0;
        $preferred = false;

        foreach ($encodings as $encoding) {
            $quality = $this->accepts_encoding_at_quality($encoding, $explicit);

            if ($quality > $ceiling) {
                $ceiling = $quality;
                $preferred = $encoding;
            }
        }

        return $preferred;
    }

    /**
     * Returns the quality of the `$encoding` type passed to it. Encoding
     * is usually compression such as `gzip`, but could be some other
     * message encoding algorithm. This method allows explicit checks to be
     * done ignoring wildcards.
     *
     *      // Accept-Encoding: compress, gzip, *; q=.5
     *      $encoding = $header->accepts_encoding_at_quality('gzip');
     *      // $encoding = (float) 1.0s
     *
     * @param   string $encoding encoding type to interrogate
     * @param   boolean $explicit explicit check, ignoring wildcards and `identity`
     * @return  float
     * @since   3.2.0
     */
    public function accepts_encoding_at_quality($encoding, $explicit = false)
    {
        if ($this->_accept_encoding === null) {
            if ($this->header->offsetExists('Accept-Encoding')) {
                $encoding_header = $this->header->offsetGet('Accept-Encoding');
            } else {
                $encoding_header = null;
            }

            $this->_accept_encoding = Header::parse_encoding_header($encoding_header);
        }

        // Normalize the encoding
        $encoding = strtolower($encoding);

        if (isset($this->_accept_encoding[$encoding])) {
            return $this->_accept_encoding[$encoding];
        }

        if ($explicit === false) {
            if (isset($this->_accept_encoding['*'])) {
                return $this->_accept_encoding['*'];
            } elseif ($encoding === 'identity') {
                return (float)Header::DEFAULT_QUALITY;
            }
        }

        return (float)0;
    }

    /**
     * Returns the preferred language from the supplied array `$languages` based
     * on the `Accept-Language` header directive.
     *
     *      // Accept-Language: en-us, en-gb; q=.7, en; q=.5
     *      $lang = $header->preferred_language(array(
     *          'en-gb', 'en-au', 'fr', 'es'
     *      )); // $lang = 'en-gb'
     *
     * @param   array $languages
     * @param   boolean $explicit
     * @return  mixed
     * @since   3.2.0
     */
    public function preferred_language(array $languages, $explicit = false)
    {
        $ceiling = 0;
        $preferred = false;

        foreach ($languages as $language) {
            $quality = $this->accepts_language_at_quality($language, $explicit);

            if ($quality > $ceiling) {
                $ceiling = $quality;
                $preferred = $language;
            }
        }

        return $preferred;
    }

    /**
     * Returns the quality of `$language` supplied, optionally ignoring
     * wildcards if `$explicit` is set to a non-`FALSE` value. If the quality
     * is not found, `0.0` is returned.
     *
     *     // Accept-Language: en-us, en-gb; q=.7, en; q=.5
     *     $lang = $header->accepts_language_at_quality('en-gb');
     *     // $lang = (float) 0.7
     *
     *     $lang2 = $header->accepts_language_at_quality('en-au');
     *     // $lang2 = (float) 0.5
     *
     *     $lang3 = $header->accepts_language_at_quality('en-au', TRUE);
     *     // $lang3 = (float) 0.0
     *
     * @param   string $language language to interrogate
     * @param   boolean $explicit explicit interrogation, `TRUE` ignores wildcards
     * @return  float
     * @since   3.2.0
     */
    public function accepts_language_at_quality($language, $explicit = false)
    {
        if ($this->_accept_language === null) {
            if ($this->header->offsetExists('Accept-Language')) {
                $language_header = strtolower($this->header->offsetGet('Accept-Language'));
            } else {
                $language_header = null;
            }

            $this->_accept_language = Header::parse_language_header($language_header);
        }

        // Normalize the language
        $language_parts = explode('-', strtolower($language), 2);

        if (isset($this->_accept_language[$language_parts[0]])) {
            if (isset($language_parts[1])) {
                if (isset($this->_accept_language[$language_parts[0]][$language_parts[1]])) {
                    return $this->_accept_language[$language_parts[0]][$language_parts[1]];
                } elseif ($explicit === false AND isset($this->_accept_language[$language_parts[0]]['*'])) {
                    return $this->_accept_language[$language_parts[0]]['*'];
                }
            } elseif (isset($this->_accept_language[$language_parts[0]]['*'])) {
                return $this->_accept_language[$language_parts[0]]['*'];
            }
        }

        if ($explicit === false AND isset($this->_accept_language['*'])) {
            return $this->_accept_language['*'];
        }

        return (float)0;
    }

    protected function initHeaderTrait()
    {
        if ($this instanceof Request && empty($this->headers)) {
            $this->headers = Header::getHeaderFromRequest($this->headers);
        } elseif (is_string($this->headers)) {
            $this->headers = Header::getHeaderFromString($this->headers);
        }
        $this->headers = array_change_key_case($this->headers, CASE_LOWER);
        if (!empty($this->headers['cache-control'])) {
            $this->setCacheControlHeader($this->headers['cache-control']);
        }
        $this->initCookieTrait();
    }

    protected function setCacheControlHeader($header)
    {
        if (!isset($this->headers['cache-control']) || !is_array($this->headers['cache-control'])) {
            $this->headers['cache-control'] = [];
        }
        $this->headers['cache-control'] = array_replace(
            $this->headers['cache-control'],
            $this->parseCacheControl($header)
        );

        return $this;
    }

    /**
     * Parses a Cache-Control HTTP header.
     *
     * @param string $header The value of the Cache-Control HTTP header
     *
     * @return array An array representing the attribute values
     */
    protected function parseCacheControl($header)
    {
        $cacheControl = array();
        preg_match_all('#([a-zA-Z][a-zA-Z_-]*)\s*(?:=(?:"([^"]*)"|([^ \t",;]*)))?#', $header, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $cacheControl[strtolower($match[1])] = isset($match[3]) ? $match[3] : (isset($match[2]) ? $match[2] : true);
        }

        return $cacheControl;
    }


} 