<?php
namespace trident;

use trident\HTTP\Header;
use trident\HTTP\httpMessageTrait;

/**
 * Request.
 */
class Request extends Object
{
    use httpMessageTrait;
    const GET = Server::GET;
    const POST = 'POST';
    const PUT = 'PUT';
    const PATCH = 'PATCH';
    const DELETE = 'DELETE';
    const HEAD = 'HEAD';
    const OPTIONS = 'OPTIONS';
    const TRACE = 'TRACE';
    const CONNECT = 'CONNECT';

    const CHANGE = 'POST PUT PATCH DELETE';

    const CACHE_STATUS_KEY = 'x-cache-status';
    const CACHE_STATUS_SAVED = 'SAVED';
    const CACHE_STATUS_HIT = 'HIT';
    const CACHE_STATUS_MISS = 'MISS';


    /**
     * @var  Request  main request instance
     */
    private static $initial = null;

    /**
     * @var  Request  currently executing request instance
     */
    private static $current;
    public $proxyUsed = false;
    /**
     * @var    Cache  Caching library for request caching
     */
    public $cache;
    /**
     * @var  bool  Should redirects be followed?
     */
    public $follow = false;
    /**
     * @var  array  Headers to preserve when following a redirect
     */
    public $follow_headers = array('Authorization');
    /**
     * @var  bool  Follow 302 redirect with original request method?
     *
     * [!!] HTTP/1.1 specifies that a 302 redirect should be followed using the
     * original request method. However, the vast majority of clients and servers
     * get this wrong, with 302 widely used for 'POST - 302 redirect - GET' patterns.
     * By default, Kohana's client is fully compliant with the HTTP spec. Some
     * non-compliant third party sites may require that strict_redirect is set
     * FALSE to force the client to switch to GET following a 302 response.
     */
    public $strict_redirect = true;
    /**
     * @var array  Callbacks to use when response contains given headers
     *
     * Accepts an array with HTTP response headers as keys and a PHP callback
     * function as values. These callbacks will be triggered if a response contains
     * the given header and can either issue a subsequent request or manipulate
     * the response as required.
     *
     * By default, the [Request_Client::on_header_location] callback is assigned
     * to the Location header to support automatic redirect following.
     *
     *     array(
     *         'Location' => 'Request_Client::on_header_location',
     *         'WWW-Authenticate' => function($request, $response, $client
     *     )
     */
    public $requestCallbacks = [

    ];
    public $responseCallbacks = [
        'systemSetETag' => [__CLASS__, 'setETag'],
        'systemConfirmResponse' => [__CLASS__, 'confirmResponse'],
    ];
    public $requestHeaderCallbacks = [
    ];
    public $responseHeaderCallbacks = [
        'Location' => [
            'systemRedirect' => [__CLASS__, 'on_header_location'],
        ],
        'ETag' => [
            'systemCheckETag' => [__CLASS__, 'checkETag'],
        ],
        'content-disposition' => [
            'systemIE_ssl_encrypted_downloads' => [__CLASS__, 'IE_ssl_encrypted_downloads'],
        ],
        'content-type' => [
            'systemAddContentEncoding' => [__CLASS__, 'addContentEncoding'],
        ],
        'transfer-encoding' => [
            'systemFixTransferEncodingContentLength' => [__CLASS__, 'fixTransferEncodingContentLength'],
        ],
        'cache-control' => [
            'systemAddPragmaHeader' => [__CLASS__, 'addPragmaHeader'],
        ],
    ];
    /**
     * @var int  Maximum number of requests that header callbacks can trigger before the request is aborted
     *
     * This protects the main execution from recursive callback execution (eg
     * following infinite redirects, conflicts between callbacks causing loops
     * etc). Requests will only be allowed to nest to the level set by this
     * param before execution is aborted with a Request_Client_Recursion_Exception.
     *
     */
    public $max_callback_depth = 5;
    /**
     * @var int  Tracks the callback depth of the currently executing request
     */
    public $callback_depth = 1;
    /**
     * @var  string  method: GET, POST, PUT, DELETE, HEAD, etc
     */
    protected $method = Server::GET;
    /**
     * @var  string  protocol: HTTP/1.1, FTP, CLI, etc
     */
    protected $clientIp = '0.0.0.0';
    protected $referrer;
    protected $uri;
    protected $url;
    protected $get = [];
    protected $post = [];
    protected $files = [];
    protected $params = [];
    protected $inputPoint = 'index';
    protected $secure = false;
    /**
     * @var  boolean  external request
     */
    protected $incoming = true;
    protected $request_time;
    protected $response_time;
    protected $allow_private_cache = false;
    /**
     * @var  $response Response
     */
    protected $response;
    /**
     * @var object $handler requestHandler
     */
    protected $handler;
    protected $requestHandlerResolvers = [
        'systemSoap' => [__CLASS__, 'soapResolver'],
        'systemHessian' => [__CLASS__, 'hessianResolver'],
    ];

    public function __construct(array $params = [])
    {
        if (self::$initial === null) {
            $method = Server::getMethod();
            $userAgent = Server::getUserAgent();
            $params = \array_replace_recursive(
                [
                    'protocol' => static::$defaultProtocol,
                    'method' => $method,
                    'referrer' => Server::getReferrer(),
                    'userAgent' => $userAgent,
                    'clientIp' => Server::getClientIp(),
                    'inputPoint' => Server::getInputPoint(),
                    'get' => $_GET,
                    'post' => $_POST,
                    'params' => \array_replace_recursive($_GET, $_POST),
                    'files' => $_FILES,
                    'cookies' => $_COOKIE,
                    'cookieDomain' => Server::getHost(),
                    'body' => '',
                    'uri' => URL::detect_uri(),
                    'url' => URL::base(static::$defaultProtocol, null, null, null, substr(URL::detect_uri(), 1), $_GET),
                    //.substr(URL::detect_uri(),1),
                    'headers' => Header::getHeaderFromRequest(),
                    'secure' => Server::isSecure(),
                ],
                $params
            );
            if ($method !== Server::GET) {
                $input = fopen("php://input", "rb");
                $params['body'] = stream_get_contents($input);
                fclose($input);
            }
        }
        parent::__construct($params);
        if (self::$initial === null) {
            self::$initial = $this;
        }
        /*
        if(!empty($this->cache) && !($this->cache instanceof Cache)){
            throw new \InvalidArgumentException('cache argument mus by instanceof trident\\caching\\Cache');
        }
        */

        $this->setURI($this->uri);

        $this->response = new Response($this, []);


        $this->initHeaderTrait();

        $this->extractParams();
    }

    public function setURI($uri = null){
        if (!empty($uri)) {
            // Cleanse query parameters from URI (faster that parse_url())
            $split_uri = explode('?', $this->uri);
            $this->uri = array_shift($split_uri);
            if (mb_strpos($this->uri, '://') === false) {
                // Remove trailing slashes from the URI
                $this->uri = trim($this->uri, '/');
            } else {
                // Set the security setting if required
                if (mb_strpos($this->uri, 'https://') === 0) {
                    $this->secure = true;
                }

                // Set external state
                $this->incoming = false;
            }
        }
    }

    public function extractParams()
    {
        $contentParams = [];

        if (!empty($this->body)) {
            if ($this->getHeaders('Content-Type') == 'application/json') {
                $contentParams = \json_decode($this->body, true);
                //$m = '$_'.$this->method;
                //$$m = [];
            } else {
                if (function_exists('mb_parse_str')) {
                    mb_parse_str($this->body, $contentParams);
                } else {
                    parse_str($this->body, $contentParams);
                }
                //UTF8::parse_str($this->body);
            }
        }
        if (empty($contentParams) || !is_array($contentParams)) {
            $contentParams = [];
        }
        $this->params = \array_replace_recursive($this->params, $contentParams);
    }

    /**
     * Return the currently executing request. This is changed to the current
     * request when [static::execute] is called and restored when the request
     * is completed.
     *
     *     $request = Request::current();
     *
     * @return  Request
     * @since   3.0.5
     */
    public static function current()
    {
        return static::$current;
    }

    /**
     * Returns the first request encountered by this framework. This will should
     * only be set once during the first [Request::factory] invocation.
     *
     *     // Get the first request
     *     $request = Request::initial();
     *
     *     // Test whether the current request is the first request
     *     if (Request::initial() === Request::current())
     *          // Do something useful
     *
     * @return  Request
     * @since   3.1.0
     */
    public static function initial()
    {
        return static::$initial;
    }

    public static function hessianResolver(Request $request)
    {
        $ct = $request->getHeaders('Content-Type');
        if ($ct == 'application/hessian-binary' || $ct == 'application/binary') {
            return ['className' => 'trident\request\handlers\hessianRequestHandler'];
        }

        return false;
    }

    public static function soapResolver(Request $request)
    {
        if (!empty($request->getHeaders('SOAPAction')) ||
            (stripos($request->body, '<?xml') !== false && stripos($request->body, ':Envelope') !== false)
        ) {
            return ['className' => 'trident\request\handlers\soapRequestHandler'];
        }

        return false;
    }

    /**
     * The default handler for following redirects, triggered by the presence of
     * a Location header in the response.
     *
     * The client's follow property must be set TRUE and the HTTP response status
     * one of 201, 301, 302, 303 or 307 for the redirect to be followed.
     *
     * @param string
     * @param string /array $headerValue
     * @param Request $request
     * @return Request
     */
    public static function on_header_location($key, $headerValue, Request $request)
    {
        if ($key !== 'location') {
            return;
        }
        // Do we need to follow a Location header ?
        if ($request->follow && in_array(
                $status = $request->response->status(),
                array(201, 301, 302, 303, 307)
            )
        ) {
            $requestURL = $request->url();
            $requestRefererURL = $request->response->getHeaders('Referer');
            $followURL = $headerValue;
            //ignore recursion redirect
            if (
                $followURL !== $requestRefererURL ||
                $followURL !== $requestURL
            ) {
                // Figure out which method to use for the follow request
                switch ($status) {
                    default:
                    case 301:
                    case 307:
                        $follow_method = $request->method();
                        break;
                    case 201:
                    case 303:
                        $follow_method = Request::GET;
                        break;
                    case 302:
                        // Cater for sites with broken HTTP redirect implementations
                        if ($request->strict_redirect) {
                            $follow_method = $request->method();
                        } else {
                            $follow_method = Request::GET;
                        }
                        break;
                }
                // Prepare the additional request, copying any follow_headers
                //that were present on the original request
                $orig_headers = $request->getHeaders();
                $follow_headers = array_intersect_assoc(
                    $orig_headers,
                    array_fill_keys($request->follow_headers, true)
                );
                $follow_request = Request::factory($followURL);
                $follow_request->method($follow_method);
                $follow_request->setHeaders($follow_headers);
                $follow_request->setHeaders('Referer', $request->url());
                if ($follow_method !== Request::GET) {
                    $follow_request->body($request->body());
                }

                return $follow_request;
            }
        }

        return null;
    }

    /**
     * @return  string
     */
    public function url()
    {
        return $this->url;
    }

    /**
     * Gets or sets the HTTP method.
     * @param   string $method Method to use for this request
     * @return  mixed
     */
    public function method($method = null)
    {
        if ($method === null) {
            // Act as a getter
            return $this->method;
        }

        // Act as a setter
        $this->method = strtoupper($method);

        return $this;
    }

    public static function checkETag($key, $headerValue, Request $request)
    {
        if ($key !== 'etag') {
            return;
        }
        $I_ETag = $request->getHeaders('ETag');
        if ($I_ETag == $headerValue) {
            $request->response->status(304);
            $request->response->body('');
        }
    }

    public static function IE_ssl_encrypted_downloads($key, $headerValue, Request $request)
    {
        if ($key !== 'content-disposition') {
            return;
        }
        /**
         * Check if we need to remove Cache-Control for ssl encrypted downloads when using IE < 9
         * @link http://support.microsoft.com/kb/323308
         */

        if (preg_match('/MSIE (.*?);/i', $request->getUserAgent(), $match) == 1) {
            if (true === $request->isSecure()) {
                // http://support.microsoft.com/kb/316431
                $request->response->setHeaders(
                    [
                        ['Cache-Control', 'public'],
                        ['pragma', 'public'],
                    ]
                );
            }
            if (intval(preg_replace("/(MSIE )(.*?);/", "$2", $match[0])) < 9) {
                if (false !== stripos($headerValue, 'attachment')) {
                    $request->response->setHeaders('Cache-Control', null);
                }
            } else {
                // http://ajaxian.com/archives/ie-8-security
                $request->response->setHeaders('x-content-type-options', 'nosniff');
            }
        }
    }

    public function getUserAgent()
    {
        return $this->userAgent;
    }

    public function isSecure($secure = null)
    {
        if ($secure === null) {
            return $this->secure;
        }

        // Act as a setter
        $this->secure = (bool)$secure;

        return $this;
    }

    public static function addContentEncoding($key, $headerValue, Request $request)
    {
        if ($key !== 'content-type') {
            return;
        }
        if (0 === strpos($headerValue, 'text/') && false === strpos($headerValue, 'charset')) {
            // add the charset
            $request->response->setHeaders('Content-Type', $headerValue.'; charset='.Core::ENCODING);
        }
    }

    public static function fixTransferEncodingContentLength($key, $headerValue, Request $request)
    {
        if ($key !== 'transfer-encoding') {
            return;
        }
        $request->response->setHeaders('Content-Length', null);
    }

    public static function addPragmaHeader($key, $headerValue, Request $request)
    {
        if ($key !== 'cache-control') {
            return;
        }
        if (strpos($headerValue, 'no-cache')) {
            $request->response->setHeaders(
                [
                    ['pragma', 'no-cache'],
                    ['expires', -1],
                ]
            );
        }
    }

    public static function setETag(Request $request)
    {
        $method = $request->method();
        if ($method == Server::GET || Server::HEAD) {
            $cc = $request->response->getHeaders('cache-control');
            $request->response->setHeaders(
                [
                    ['ETag', $request->response->generate_etag()],
                    ['cache-control', !empty($cc) ? $cc.', must-revalidate' : 'must-revalidate'],
                ]
            );
        }

    }

    /**
     * Parses an accept header and returns an array (type => quality) of the
     * accepted types, ordered by quality.
     *
     *     $accept = Request::_parse_accept($header, $defaults);
     *
     * @param   string $header Header to parse
     * @param   array $accepts Default values
     * @return  array
     */
    protected static function _parse_accept($header, array $accepts = null)
    {
        if (!empty($header)) {
            // Get all of the types
            $types = explode(',', $header);

            foreach ($types as $type) {
                // Split the type into parts
                $parts = explode(';', $type);

                // Make the type only the MIME
                $type = trim(array_shift($parts));

                // Default quality is 1.0
                $quality = 1.0;

                foreach ($parts as $part) {
                    // Prevent undefined $value notice below
                    if (strpos($part, '=') === false) {
                        continue;
                    }

                    // Separate the key and value
                    list ($key, $value) = explode('=', trim($part));

                    if ($key === 'q') {
                        // There is a quality for this type
                        $quality = (float)trim($value);
                    }
                }

                // Add the accept type and quality
                $accepts[$type] = $quality;
            }
        }

        // Make sure that accepts is an array
        $accepts = (array)$accepts;

        // Order by quality
        arsort($accepts);

        return $accepts;
    }

    public function isDestructive()
    {
        return strpos(static::CHANGE, $this->method);
    }

//handler

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Returns the response as the string representation of a request.
     *
     *     echo $request;
     *
     * @return  string
     */
    public function __toString()
    {
        // Resolve the POST fields
        if ($post = $this->post()) {
            $this->method = Server::POST;
            $this->body = http_build_query($post, null, '&');
            $this->setHeaders(
                [
                    ['content-type', 'application/x-www-form-urlencoded; charset='.Core::ENCODING],
                    ['content-length', (string)$this->contentLength()],
                ]
            );
        }
        $output = $this->headerToString()."\r\n\r\n";
        $output .= $this->body."\r\n\r\n";

        return $output;
    }

    public function post($key = null, $value = null, $default = null)
    {
        return $this->get_set_data($key, $value, $default, $this->post);
    }

    /**
     * @param null $key
     * @param null $value
     * @param null $default
     * @param $data
     * @return mixed
     */
    protected function get_set_data($key = null, $value = null, $default = null, &$data)
    {
        if ($key === null) {
            // Act as a getter, all query strings
            return $data;
        } elseif ($value === null) {
            $ret = Arr::getData($data, $key, $default);
            if (!empty($default)) {
                settype($ret, gettype($default));
            }

            return $ret;
        }

        // Act as a setter, single query string
        Arr::setData($data, $key, $value);

        return null;
    }

    //response header callbacks

    public function getSpecialHeaderString()
    {
        return $this->method.' '.$this->url().' '.$this->protocol;
    }

    public function send()
    {
        $this->execute();
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws requestException
     */
    public function execute()
    {
        // Store the currently active request
        $previous = static::$current;
        // Change the current request to this request
        static::$current = $this;

        $this->executeRequestHeaderCallbacks();
        $this->lock();
        // Prevent too much recursion of header callback requests
        if ($this->callback_depth > $this->max_callback_depth) {
            throw new requestException(
                'Could not execute request to '.$this->uri.' - too many recursions after '.
                ($this->callback_depth - 1).' requests'
            );
        }

        if ($this->cache instanceof Cache) {
            $this->executeCache();
        } else {
            $this->response = $this->getHandler()->execute_request($this, $this->response);
        }

        $this->executeResponseHeaderCallbacks();
        //$this->executeResponseCallbacks();
        //$this->response->lock();
        // Restore the previous request
        static::$current = $previous;
        $this->confirmResponse($this);

        return $this->response;
    }

    protected function executeRequestHeaderCallbacks()
    {
        if (empty($this->requestHeaderCallbacks)) {
            return;
        }
        $response = $orig_response = $this->response;
        // Execute response callbacks
        foreach ($this->requestHeaderCallbacks as $header => $callbacks) {
            $headerValue = $this->getHeaders($header);
            if ($headerValue !== null && !empty($callbacks)) {
                foreach ($callbacks as $id => $callback) {
                    if (is_callable($callback)) {
                        $cb_result = call_user_func($callback, strtolower($header), $headerValue, $this);

                        if ($cb_result instanceof Request) {
                            $this->assignRequestProperties($cb_result);
                            // Execute the request
                            $response = $cb_result->execute();
                        } elseif ($cb_result instanceof Response) {
                            // Assign the returned response
                            $response = $cb_result;
                            break;
                        }
                    }
                }
            }
        }
        $this->response = $response;
    }

    public function assignRequestProperties(Request $request)
    {
        $request->cache = $this->cache;
        $request->follow = $this->follow;
        $request->follow_headers = $this->follow_headers;
        $request->header_callbacks = $this->header_callbacks;
        $request->max_callback_depth = $this->max_callback_depth;
        $request->callback_depth = $this->callback_depth + 1;
        $request->callback_params($this->callback_params());
    }

    /**
     * Getter/Setter for the callback_params array, which allows additional
     * application-specific parameters to be shared with callbacks.
     *
     * As with other Kohana setter/getters, usage is:
     *
     *     // Set full array
     *     $client->callback_params(array('foo'=>'bar'));
     *
     *     // Set single key
     *     $client->callback_params('foo','bar');
     *
     *     // Get full array
     *     $params = $client->callback_params();
     *
     *     // Get single key
     *     $foo = $client->callback_params('foo');
     *
     * @param string|array $param
     * @param mixed $value
     * @return Request_Client|mixed
     */
    public function callback_params($param = null, $value = null)
    {
        // Getter for full array
        if ($param === null) {
            return $this->callback_params;
        }

        // Setter for full array
        if (is_array($param)) {
            $this->callback_params = $param;

            return $this;
        } // Getter for single value
        elseif ($value === null) {
            return Arr::get($this->callback_params, $param);
        } // Setter for single value
        else {
            $this->callback_params[$param] = $value;

            return $this;
        }

    }

    //response callbacks

    protected function executeCache()
    {
        // If this is a destructive request, by-pass cache completely
        if (strpos(static::CHANGE, $this->method) !== false) {
            // Kill existing caches for this request
            $this->cache->delete($this);


            $this->response = $this->getHandler()->execute_request($this, $this->response);
            // Ensure client respects destructive action
            if ($this->proxyUsed) {
                $cc = Header::create_cache_control(['no-cache', 'must-revalidate', 'proxy-revalidate']);
            } else {
                $cc = Header::create_cache_control(['no-cache', 'must-revalidate']);
            }
            $this->response->setHeaders(
                [
                    ['Pragma', 'no-cache'],
                    ['cache-control', $cc],
                ]
            );

            return;
        }

        $requestHeaders = $this->getHeaders();
        if (!empty($requestHeaders['cache-control'])) {
            $requestHeaders['cache-control'] = Header::parse_cache_control($requestHeaders['cache-control']);
        }

        $useCache = $this->useCache($requestHeaders);

        if ($useCache === false && Core::is_debug()) {
            $this->cache->delete($this);
            $this->response = $this->getHandler()->execute_request($this, $this->response);
            $this->response->setHeaders(
                [
                    ['Pragma', 'no-cache'],
                    ['cache-control', Header::create_cache_control(['no-cache', 'must-revalidate'])],
                ]
            );

            return;
        }

        // Try and return cached version
        if (($response = $this->cache->get($this)) instanceof Response) {
            if ($useCache === false) {
                $response->setHeaders(
                    [
                        ['Pragma', 'no-cache'],
                        ['cache-control', Header::create_cache_control(['no-cache', 'must-revalidate'])],
                    ]
                );
            }
            $response->setHeaders(static::CACHE_STATUS_KEY, static::CACHE_STATUS_HIT);
            $this->response = $response;

            return;
        } else {
            $this->response->setHeaders(static::CACHE_STATUS_KEY, static::CACHE_STATUS_MISS);
        }

        // Start request time
        $this->request_time = time();
        // Execute the request with the Request client
        $this->response = $this->getHandler()->execute_request($this, $this->response);
        // Stop response time
        $this->response_time = time() - $this->request_time;

        if ($useCache === false) {
            $this->response->setHeaders(
                [
                    ['Pragma', 'no-cache'],
                    ['cache-control', Header::create_cache_control(['no-cache', 'must-revalidate'])],
                ]
            );
        }


        $ttl = $this->response->getCacheLifetime();//real cache life time
        if (empty($ttl)) {
            $responseHeaders = $this->response->getHeaders();
            if (!empty($responseHeaders['cache-control'])) {
                $responseHeaders['cache-control'] =
                    Header::parse_cache_control($responseHeaders['cache-control']);
            }
            $ttl = $this->getCacheLifetime($responseHeaders);//cache life time from headers for clients
        }


        if ($ttl > 0 && $this->cache->set($this, $this->response, (int)$ttl)) {
            $this->response->setHeaders(static::CACHE_STATUS_KEY, static::CACHE_STATUS_SAVED);
        } else {
            $this->response->setHeaders(
                [
                    ['Pragma', 'no-cache'],
                    ['cache-control', Header::create_cache_control(['no-cache', 'must-revalidate'])],
                ]
            );
        }
    }

    /**
     * @return object requestHandler
     */
    public function getHandler()
    {
        if (is_object($this->handler) && $this->handler instanceof requestHandler) {
            return $this->handler;
        }
        if (($handlerConfig = $this->resolveHandler()) !== false) {
            $this->handler = is_array($this->handler) ? $this->handler : [];
            $this->handler = \array_replace_recursive($this->handler, $handlerConfig);
            unset($handlerConfig);
        }
        if ($this->incoming) {
            $this->handler['instanceof'] = 'trident\incomingRequestHandler';
            $this->handler['className'] = !empty($this->handler['className']) ?
                $this->handler['className'] : 'trident\incomingRequestHandler';
        } else {
            $this->handler['instanceof'] = 'trident\outgoingRequestHandler';
            $this->handler['className'] = !empty($this->handler['className']) ?
                $this->handler['className'] : 'trident\curlRequestHandler';
        }
        $this->handler = DI::build($this->handler);

        return $this->handler;
    }

    /**
     * @return array/bool
     * @throws requestException
     */
    private function resolveHandler()
    {
        if (!empty($this->requestHandlerResolvers)) {
            foreach ($this->requestHandlerResolvers as $resolverName => $callback) {
                $ret = null;
                if (is_callable($callback)) {
                    $ret = $callback($this);
                }

                if (is_array($ret)) {
                    return $ret;
                }
            }
        }

        return false;
    }

    public function useCache(array $requestHeaders)
    {
        if (!empty($requestHeaders['cache-control'])) {
            $cache_control = &$requestHeaders['cache-control'];

            if (array_intersect($cache_control, array('no-cache', 'no-store'))) {
                return false;
            }
            // Check that max-age has been set and if it is valid for caching
            if (isset($cache_control['max-age']) && $cache_control['max-age'] < 1) {
                return false;
            }
        } else {
            if (!empty($requestHeaders['expires']) && (strtotime($requestHeaders['expires']) <= time())) {
                return false;
            }
        }
        if (!empty($requestHeaders['pragma']) &&
            (
                $requestHeaders['pragma'] == 'no-cache' ||
                (is_array($requestHeaders['pragma']) && in_array('no-cache', $requestHeaders['pragma']))
            )
        ) {
            return false;
        }

        return true;
    }

    /**
     * Calculates the total Time To Live based on the specification
     * RFC 2616 cache lifetime rules.
     *
     * @param   array $responseHeaders
     * @return  integer  TTL value or false if the response should not be cached
     */
    public function getCacheLifetime(array $responseHeaders)
    {
        // Calculate apparent age
        if (!empty($responseHeaders['date'])) {
            $apparent_age = max(0, $this->response_time - strtotime($responseHeaders['date']));
        } else {
            $apparent_age = $this->response_time;
        }

        // Calculate corrected received age
        if (!empty($responseHeaders['age'])) {
            $corrected_received_age = max($apparent_age, intval($responseHeaders['age']));
        } else {
            $corrected_received_age = $apparent_age;
        }

        // Current age
        $current_age = time() + $corrected_received_age - $this->request_time;

        if (!empty($responseHeaders['expires'])) {
            return strtotime($responseHeaders['expires']) - $current_age;
        }

        // Prepare the cache freshness lifetime
        $ttl = 0;

        // Cache control overrides
        if (!empty($responseHeaders['cache-control'])) {
            $cache_control = &$responseHeaders['cache-control'];

            if (!$this->allow_private_cache && in_array('private', $cache_control)) {
                //The s-maxage directive is always ignored by a private cache.
                unset($cache_control['s-maxage']);
            }
            if (isset($cache_control['s-maxage'])) {
                /**If a response includes an s-maxage directive, then for a shared cache
                 * (but not for a private cache), the maximum age specified by this directive
                 * overrides the maximum age specified by either the max-age directive
                 * or the Expires header.
                 */
                $cache_control['max-age'] = $cache_control['s-maxage'];
            }

            if (isset($cache_control['max-age'])) {
                $ttl = $cache_control['max-age'];
            }

            if (isset($cache_control['max-stale']) AND !isset($cache_control['must-revalidate'])) {
                $ttl = $current_age + $cache_control['max-stale'];
            }
        }

        return $ttl;
    }

    protected function executeResponseHeaderCallbacks()
    {
        if (empty($this->responseHeaderCallbacks)) {
            return;
        }
        $response = $orig_response = $this->response;
        // Execute response callbacks
        foreach ($this->responseHeaderCallbacks as $header => $callbacks) {
            $headerValue = $response->getHeaders($header);
            if ($headerValue !== null && !empty($callbacks)) {
                foreach ($callbacks as $id => $callback) {
                    if (is_callable($callback)) {
                        $cb_result = call_user_func($callback, strtolower($header), $headerValue, $this);

                        if ($cb_result instanceof Request) {
                            $this->assignRequestProperties($cb_result);
                            // Execute the request
                            $response = $cb_result->execute();
                        } elseif ($cb_result instanceof Response) {
                            // Assign the returned response
                            $response = $cb_result;
                        }

                        // If the callback has created a new response, do not process any further
                        if ($response !== $orig_response) {
                            break;
                        }
                    }
                }
            }
        }
        $this->response = $response;
    }

    public static function confirmResponse(Request $request)
    {
        $requestMethod = $request->method();
        $responseStatus = $request->response->status();
        $responseHeaders = $request->response->getHeaders();
        // Figure out which method to use for the follow request
        switch ($responseStatus) {
            case 100:
            case 101:
            case 204:
                $request->response->body('');
                break;
            case 304:
                // remove headers that MUST NOT by included with 304 Not Modified responses
                $request->response->setHeaders(
                    [
                        ['Allow', null],
                        ['Content-Encoding', null],
                        ['Content-Language', null],
                        ['Content-Length', null],
                        ['Content-MD5', null],
                        ['Content-Type', null],
                        ['Last-Modified', null],
                    ]
                );
                $request->response->body('');
                break;
        }
        switch ($requestMethod) {
            case Server::HEAD:
                // cf. RFC2616 14.13
                $request->response->body('');
                break;
        }

        $request->response->setHeaders('X-requested-url', $request->url());

        // Fix Content-Type
        if (empty($responseHeaders['content-type'])) {
            $request->response->setHeaders('Content-Type', 'text/html; charset='.Core::ENCODING);
        }

        /*if (empty($responseHeaders['content-length'])) {
            $request->response->setHeaders('content-length', (string)$request->response->contentLength());
        }*/

        if (Core::expose()) {
            $request->response->setHeaders('X-Powered-By', Core::poweredBy());
        }
        //Section 4.2 of [RFC7234] [Page 12]
        if (!empty($responseHeaders['content-range']) && !($responseStatus == 206 || $responseStatus == 416)) {
            $request->response->setHeaders('Content-Range', null);
        }
    }

    /**
     * Gets the request's scheme.
     *
     * @return string
     *
     * @api
     */
    public function getScheme()
    {
        return $this->secure ? 'https' : 'http';
    }

    /**
     * Gets the Etags.
     *
     * @return array The entity tags
     */
    public function getETags()
    {
        return preg_split('/\s*,\s*/', $this->getHeaders('if_none_match'), null, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Returns whether this request is the initial request Kohana received.
     * Can be used to test for sub requests.
     *
     *     if ( ! $request->is_initial())
     *         // This is a sub request
     *
     * @return  boolean
     */
    public function is_initial()
    {
        return ($this === static::$initial);
    }

    /**
     * Readonly access to the [Request::$_external] property.
     *
     *     if ( ! $request->is_external())
     *          // This is an internal request
     *
     * @return  boolean
     */
    public function is_incoming()
    {
        return $this->incoming;
    }

    /**
     * Returns whether this is an ajax request (as used by JS frameworks)
     *
     * @return  boolean
     */
    public function is_ajax()
    {
        return 'xmlhttprequest' == strtolower((string)$this->getHeaders('X-Requested-With'));
    }

    public function is_SOAP()
    {
        return (!empty($this->getHeaders('SOAPAction')) ||
            (stripos($this->body, '<?xml') !== false && stripos($this->body, ':Envelope') !== false)
        );
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function query($key = null, $value = null, $default = null)
    {
        return $this->get_set_data($key, $value, $default, $this->get);
    }

    public function param($key = null, $default = null)
    {
        return $this->get_set_data($key, null, $default, $this->params);
    }

    public function setParams(array $params = [])
    {
        $this->params = $params;
    }

    /**
     * Sets and gets the referrer from the request.
     *
     * @param   string $referrer
     * @return  mixed
     */
    public function referrer($referrer = null)//?
    {
        if ($referrer === null) {
            // Act as a getter
            return $this->referrer;
        }

        // Act as a setter
        $this->referrer = (string)$referrer;

        return $this;
    }

    /**
     * Issues a HTTP redirect.
     *
     * @param  string $uri URI to redirect to
     * @param  int $code HTTP Status code to use for the redirect
     * @throws HTTP_Exception
     */
    public function redirect($uri, $code = 302)
    {
        if (!empty($uri)) {

            $this->response->body('');
            $this->response->status($code);
            $this->response->setHeaders('Location', $uri);
        }

    }

    /**
     * Processes an array of key value pairs and encodes
     * the values to meet RFC 3986
     *
     * @param   array $params Params
     * @return  string
     */
    public function www_form_urlencode(array $params = array())
    {
        if (!$params) {
            $params = $this->params;
        }

        $encoded = array();

        foreach ($params as $key => $value) {
            $encoded[] = $key.'='.rawurlencode($value);
        }

        return implode('&', $encoded);
    }

    protected function headerString()
    {
        return $this->headerToString()."\r\n\r\n";
    }

    protected function executeResponseCallbacks()
    {
        if (empty($this->responseCallbacks)) {
            return;
        }
        foreach ($this->responseCallbacks as $id => $callback) {
            if (is_callable($callback)) {
                call_user_func($callback, $this);
            }
        }
    }

}
