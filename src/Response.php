<?php
namespace trident;

use trident\HTTP\httpMessageTrait;


/**
 * Response wrapper. Created as the result of any [Request] execution
 * or utility method (i.e. Redirect). Implements standard HTTP
 * response format.
 *
 * @package    Kohana
 * @category   Base
 * @author     Kohana Team
 * @copyright  (c) 2008-2014 Kohana Team
 * @license    http://kohanaframework.org/license
 * @since      3.1.0
 */
class Response extends Object
{
    use httpMessageTrait {
        sendHeader as protected sendHeaderTrait;
    }
    // HTTP status codes and messages
    public static $messages = array(
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        // Success 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found', // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        // 306 is deprecated but reserved
        307 => 'Temporary Redirect',
        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        509 => 'Bandwidth Limit Exceeded',
    );

    /**
     * @var  integer     The response http status
     */
    protected $status = 200;

    protected $cacheLifeTime;

    protected $file = [];

    /**
     * @var $request Request
     */
    protected $request;

    public function __construct(Request $request, array $options = [])
    {
        parent::__construct($options);
        $this->request = $request;
        $this->initHeaderTrait();
    }

    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Sends the response to client.
     */
    public function send()
    {
        $this->lock();

        //$this->clearOutputBuffers();

        $out = fopen("php://output", "wb");

        // Send data in 8kb blocks
        $block = 1024 * 8;

        if (!empty($this->file)) {
            $file = null;
            if (is_resource($this->file['filePath'])) {
                $file = $this->file['filePath'];
            } else {
                if (true === $this->file['use_nginx_accelerate']) {
                    $this->setHeaders(
                        [
                            ['X-Accel-Redirect', $this->file['filePath']],
                            [
                                'content-disposition',
                                $this->file['inline'].'; '.$this->file['fileName'],
                            ],
                        ]
                    );
                    // send headers
                    $this->sendHeader();
                    @fclose($out);
                    exit;
                } else {
                    if (true === $this->file['use_lighttpd_accelerate']) {
                        $this->setHeaders(
                            [
                                ['X-Sendfile', $this->file['filePath']],
                                ['X-LIGHTTPD-send-file', $this->file['filePath']],
                                [
                                    'content-disposition',
                                    $this->file['inline'].'; '.$this->file['fileName'],
                                ],
                            ]
                        );
                        // send headers
                        $this->sendHeader();
                        @fclose($out);
                        exit;
                    } else {
                        if (true === $this->file['use_apache_accelerate']) {
                            $this->setHeaders(
                                [
                                    ['X-Sendfile', $this->file['filePath']],
                                    [
                                        'content-disposition',
                                        $this->file['inline'].'; '.$this->file['fileName'],
                                    ],
                                ]
                            );
                            // send headers
                            $this->sendHeader();
                            @fclose($out);
                            exit;
                        } else {
                            //Open the file for reading
                            $file = fopen($this->file['filePath'], 'rb');
                        }
                    }
                }
            }

            //Getting detailed stats
            if (($stats = fstat($file)) === false) {
                fclose($file);
                fclose($out);
                exit;
            };
            // Calculate byte range to download.
            list($start, $end) = $this->_calculate_byte_range($stats['size']);

            // RFC 7233 section 4.4  [Page 14]
            //unsatisfied-range
            if ($this->status == 416) {
                $this->setHeaders(
                    [
                        ['content-range', 'bytes */'.$stats['size']],
                        ['accept-range', 'bytes'],
                    ]
                );
                // send headers
                $this->sendHeader();
            }

            $this->setHeaders(
                [
                    ['content-length', (string)(($end - $start) + 1)],
                    [
                        'content-disposition',
                        ($this->file['inline'].
                            '; '.$this->file['fileName'].'; size='.$stats['size'].
                            '; modification-date="'.gmdate("D, d-M-Y H:i:s T", $stats['mtime']).
                            '"; read-date="'.gmdate("D, d-M-Y H:i:s T", $stats['atime']).'"'),
                    ],
                ]
            );

            if (true === $this->file['resumable']) {
                if ($start > 0 || $end < ($stats['size'] - 1)) {
                    // Partial Content
                    $this->status = 206;
                }
                // Range of bytes being sent
                $this->setHeaders(
                    [
                        ['content-range', ('bytes '.$start.'-'.$end.'/'.$stats['size'])],
                        ['accept-range', 'bytes'],
                    ]
                );
            }

            // Manually stop execution
            ignore_user_abort(true);

            // Keep the script running forever
            set_time_limit(0);

            // write headers
            if (fwrite($out, $this->headerString()) === false) {
                exit;
            }

            fseek($file, $start);
            //send file
            while (!feof($file) && ($pos = ftell($file)) <= $end) {
                if (connection_aborted()) {
                    break;
                }

                if ($pos + $block > $end) {
                    // Don't read past the buffer.
                    $block = $end - $pos + 1;
                }
                if (fwrite($out, fread($file, $block)) === false) {
                    break;
                }
            }

            if (true === $this->file['delete']) {
                try {
                    // Attempt to remove the file
                    unlink($this->file['filePath']);
                } catch (\Exception $e) {
                    // Add this exception to the log
                    Logger::log($e->getMessage(), Logger::ERROR, 'response_file_io', true);
                    // Do NOT display the exception, it will corrupt the output!
                }
            }

            fclose($file);
        } else {
            if (!empty($this->body)) {
                $this->setHeaders('content-length', $this->contentLength());
            }

            $this->sendHeader();

            if (!empty($this->body)) {
                $sendData = str_split((string)$this->body, $block);
                foreach ($sendData as $part) {
                    if (connection_aborted()) {
                        break;
                    }
                    if (fwrite($out, $part) === false) {
                        break;
                    }
                }
            }

        }

        fpassthru($out);
        fflush($out);
        @fclose($out);
        exit(1);
    }

    public function sendHeader()
    {
        $phpSapiName = substr(php_sapi_name(), 0, 3);
        if ($phpSapiName == 'cgi' || $phpSapiName == 'fpm') {
            header("Status: $this->status ".static::$messages[$this->status]);
        } else {
            header($this->getSpecialHeaderString(), true, $this->status);
        }
        //header('X-PHP-Response-Code: '.$this->status, true, $this->status);
        $this->sendHeaderTrait();
    }

    public function getSpecialHeaderString()
    {
        return $this->protocol.' '.$this->status.' '.static::$messages[$this->status];
    }

    /**
     * Calculates the byte range to use with send_file. If HTTP_RANGE doesn't
     * exist then the complete byte range is returned
     *
     * @param  integer $size
     * @return array
     */
    protected function _calculate_byte_range($size)
    {
        // Defaults to start with when the HTTP_RANGE header doesn't exist.
        $start = 0;
        $end = $size - 1;

        if ($range = $this->_parse_byte_range()) {
            // We have a byte range from HTTP_RANGE
            $start = $range[1];

            if ($start[0] === '-') {
                // A negative value means we start from the end, so -500 would be the
                // last 500 bytes.
                $start = $size - abs($start);
            }

            if (isset($range[2])) {
                // Set the end range
                $end = $range[2];
            }
        }

        // Normalize values.
        $start = abs(intval($start));

        // Keep the the end value in bounds and normalize it.
        $end = min(abs(intval($end)), $size - 1);

        if ($end < $start) {
            $this->status = 416;
        }
        // Keep the start in bounds.
        //$start = ($end < $start) ? 0 : max($start, 0);

        return array($start, $end);
    }

    /**
     * Parse the byte ranges from the HTTP_RANGE header used for
     * resumable downloads.
     *
     * @link   http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.35
     * @return array|FALSE
     */
    protected function _parse_byte_range()
    {
        if (!isset($_SERVER['HTTP_RANGE'])) {
            return false;
        }

        // TODO, speed this up with the use of string functions.
        preg_match_all(
            '/(-?[0-9]++(?:-(?![0-9]++))?)(?:-?([0-9]++))?/',
            $_SERVER['HTTP_RANGE'],
            $matches,
            PREG_SET_ORDER
        );

        return $matches[0];
    }

    protected function headerString()
    {
        return $this->headerToString()."\r\n\r\n";
    }

    /**
     * Send file download as the response. All execution will be halted when
     * this method is called! Use TRUE for the filename to send the current
     * response as the file content. The third parameter allows the following
     * options to be set:
     *
     * Type      | Option    | Description                        | Default Value
     * ----------|-----------|------------------------------------|--------------
     * `boolean` | inline    | Display inline instead of download | `FALSE`
     * `string`  | mime_type | Manual mime type                   | Automatic
     * `boolean` | delete    | Delete the file after sending      | `FALSE`
     *
     * Download a file that already exists:
     *
     *     $request->send_file('media/packages/kohana.zip');
     *
     * Download generated content as a file:
     *
     *     $request->response($content);
     *     $request->send_file(TRUE, $filename);
     *
     * [!!] No further processing can be done after this method is called!
     *
     * @param   string $filename filename with path, or TRUE for the current response
     * @param   string $download downloaded file name
     * @param   array $options additional options
     * @return  void
     * @throws  Kohana_Exception
     * @uses    File::mime_by_ext
     * @uses    File::mime
     * @uses    Request::send_headers
     */
    public function setFile(array $options = null)
    {
        if (empty($options['filePath'])) {
            // Create a temporary file to hold the current response
            $file = fopen('php://temp', 'wb+');
            // Write the current response into the file
            fwrite($file, $this->body);
            $options['fileName'] = empty($options['fileName']) ? sha1($this->body).'.html' : $options['fileName'];
            $this->body = null;
            $options['filePath'] = $file;
            $options['delete'] = false;
        } else {
            $options['fileName'] =
                empty($options['fileName']) ?
                    pathinfo($options['fileName'], PATHINFO_BASENAME) : $options['fileName'];
            $options['delete'] = empty($options['delete']) ? false : (bool)$options['delete'];
        }

        $filenameEncoded = rawurlencode($options['fileName']);
        if (strpos($filenameEncoded, '%') === false) { // ASCII only
            $options['fileName'] = 'filename="'.$options['fileName'].'"';
        } else {
            $ua = $_SERVER["HTTP_USER_AGENT"];
            if (preg_match('/MSIE [4-8]/', $ua)) { // IE < 9 do not support RFC 6266 (RFC 2231/RFC 5987)
                $options['fileName'] = 'filename="'.$filenameEncoded.'"';
            } else { // RFC 6266 (RFC 2231/RFC 5987)
                $options['fileName'] = 'filename*=UTF-8\'\''.$filenameEncoded;
            }
        }

        $options['mimeType'] = empty($options['mimeType']) ?
            $this->getMimeType(strtolower(pathinfo($options['fileName'], PATHINFO_EXTENSION))) :
            $options['mimeType'];
        $options['inline'] = empty($options['inline']) ? 'attachment' : 'inline';

        $options['use_apache_accelerate'] = empty($options['use_apache_accelerate']) ?
            false : (
                (bool)$options['use_apache_accelerate'] &&
                function_exists('apache_get_modules') &&
                in_array('mod_xsendfile', apache_get_modules())
            );
        $options['use_nginx_accelerate'] = empty($options['use_nginx_accelerate']) ?
            false : (bool)$options['use_nginx_accelerate'];
        $options['use_lighttpd_accelerate'] = empty($options['use_lighttpd_accelerate']) ?
            false : (bool)$options['use_lighttpd_accelerate'];

        $options['resumable'] = empty($options['resumable']) ? false : (bool)$options['resumable'];
        // Set the headers for a download
        $this->setHeaders('content-type', $options['mimeType']);

        $this->file = $options;
    }

    /**
     * Removes all existing output buffers.
     */
    public function clearOutputBuffers()
    {
        // the following manual level counting is to deal with zlib.output_compression set to On
        for ($level = ob_get_level(); $level > 0; --$level) {
            if (!@ob_end_clean()) {
                ob_clean();
            }
        }
    }

    /**
     * Returns the Response as an HTTP string.
     *
     * The string representation of the Response is the same as the
     * one that will be sent to the client only if the prepare() method
     * has been called before.
     *
     * @return string The Response as an HTTP string
     *
     * @see prepare()
     */
    public function __toString()
    {
        $output = $this->headerToString()."\r\n";
        if (!empty($this->body)) {
            $output .= (string)$this->body."\r\n";
        }


        return $output;
    }

    public function getCacheLifetime()
    {
        return $this->cacheLifeTime;
    }

    public function setCacheLifetime($lifeTime)
    {
        $this->cacheLifeTime = (int)$lifeTime;
    }

    /**
     * Generate ETag
     * Generates an ETag from the response ready to be returned
     *
     * @throws Request_Exception
     * @return String Generated ETag
     */
    public function generate_etag()
    {
        return '"'.sha1($this->body).'"';
    }

    /**
     * Sets or gets the HTTP status from this response.
     *
     *      // Set the HTTP status to 404 Not Found
     *      $response = Response::factory()
     *              ->status(404);
     *
     *      // Get the current status
     *      $status = $response->status();
     *
     * @param   integer $status Status to set to this response
     * @return  mixed
     */
    public function status($status = null)
    {
        if ($status === null) {
            return $this->status;
        } elseif (array_key_exists($status, static::$messages)) {
            if ($this->isLocked) {
                return $this;
            }
            $this->status = (int)$status;

            return $this;
        } else {
            throw new \UnexpectedValueException(
                __METHOD__.' unknown status value : :value',
                array(':value' => $status)
            );
        }
    }

    // http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html

    /**
     * Is response invalid?
     *
     * @return Boolean
     *
     * @api
     */
    public function isInvalid()
    {
        return $this->status < 100 || $this->status >= 600;
    }

    /**
     * Is response informative?
     *
     * @return Boolean
     *
     * @api
     */
    public function isInformational()
    {
        return $this->status >= 100 && $this->status < 200;
    }

    /**
     * Is response successful?
     *
     * @return Boolean
     *
     * @api
     */
    public function isSuccessful()
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Is the response a redirect?
     *
     * @return Boolean
     *
     * @api
     */
    public function isRedirection()
    {
        return $this->status >= 300 && $this->status < 400;
    }

    /**
     * Is there a client error?
     *
     * @return Boolean
     *
     * @api
     */
    public function isClientError()
    {
        return $this->status >= 400 && $this->status < 500;
    }

    /**
     * Was there a server side error?
     *
     * @return Boolean
     *
     * @api
     */
    public function isServerError()
    {
        return $this->status >= 500 && $this->status < 600;
    }

    /**
     * Is the response OK?
     *
     * @return Boolean
     *
     * @api
     */
    public function isOk()
    {
        return 200 === $this->status;
    }

    /**
     * Is the response forbidden?
     *
     * @return Boolean
     *
     * @api
     */
    public function isForbidden()
    {
        return 403 === $this->status;
    }

    /**
     * Is the response a not found error?
     *
     * @return Boolean
     *
     * @api
     */
    public function isNotFound()
    {
        return 404 === $this->status;
    }

    /**
     * Is the response a redirect of some form?
     *
     * @param string $location
     *
     * @return Boolean
     *
     * @api
     */
    public function isRedirect($location = null)
    {
        return in_array(
            $this->status,
            array(201, 301, 302, 303, 307, 308)
        ) && (null === $location ?: $location == $this->getHeaders('Location'));
    }

    /**
     * Is the response empty?
     *
     * @return Boolean
     *
     * @api
     */
    public function isEmpty()
    {
        return in_array($this->status, array(201, 204, 304));
    }

}
