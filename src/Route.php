<?php
namespace trident;

class Route
{

    const DEF_ROUTE_NAME = 'default';

    // Matches a URI group and captures the contents
    const REGEX_GROUP = '\(((?:(?>[^()]+)|(?R))*)\)';

    // Defines the pattern of a <segment>
    const REGEX_KEY = '<([a-zA-Z0-9_]++)>';

    // What can be part of a <segment> value
    const REGEX_SEGMENT = '[^/.,;?\n]++';

    // What must be escaped in the route regex
    const REGEX_ESCAPE = '[.\\+*?[^\\]${}=!|]';

    protected static $default_protocol = 'http://';

    /**
     * @var  array   list of valid localhost entries
     */
    protected static $localhosts = array(false, '', 'local', 'localhost');

    protected static $defaultController = 'system';
    /**
     * @var  string  default action for all routes
     */
    protected static $defaultAction = 'index';

    /**
     * @var  array
     */
    protected static $routes = [];

    public static function init($data = null)
    {
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                if ('routes' === $k) {
                    foreach ($v as $r) {
                        self::set(
                            $r[0],
                            $r[1],
                            isset($r[2]) ? $r[2] : null,
                            isset($r[3]) ? $r[3] : [],
                            isset($r[4]) ? $r[4] : [],
                            isset($r[5]) ? $r[5] : []
                        );
                    }
                } else {
                    self::$$k = $v;
                }

            }
        }
        self::setRoutesFromAppConfig(Core::getAppComponents());
        self::$defaultController = Core::getDefaultAppComponent();
        self::$defaultAction = Core::getDefaultAction();
/*
        $fp=fopen('routes.php', 'wb+');
        fwrite($fp, var_export(self::$routes, true));
        fclose($fp);
      */
    }

    public static function set($name, $uri, $regex = null, $defaults = [], $filters = [], $path = [])
    {
        if (!empty(self::$routes[$name])) {
            return;
        }
        $defaults = \array_replace(
            [
                'host' => false,
                'path' => implode('/', $path),
                'controller' => self::$defaultController,
                'action' => self::$defaultAction,
            ],
            $defaults
        );

        self::$routes[$name] = array(
            'uri' => $uri,
            'route_regex' => self::compile($uri, $regex),
            'defaults' => $defaults,
            'filters' => $filters,
        );
    }

    /**
     * Returns the compiled regular expression for the route. This translates
     * keys and optional groups to a proper PCRE regular expression.
     *
     *     $compiled = Route::compile(
     *        '<controller>(/<action>(/<id>))',
     *         array(
     *           'controller' => '[a-z]+',
     *           'id' => '\d+',
     *         )
     *     );
     *
     * @return  string
     * @uses    Route::REGEX_ESCAPE
     * @uses    Route::REGEX_SEGMENT
     */
    public static function compile($uri, array $regex = null)
    {
        // The URI should be considered literal except for keys and optional parts
        // Escape everything preg_quote would escape except for : ( ) < >
        $expression = preg_replace('#'.self::REGEX_ESCAPE.'#', '\\\\$0', $uri);

        if (strpos($expression, '(') !== false) {
            // Make optional parts of the URI non-capturing and optional
            $expression = str_replace(array('(', ')'), array('(?:', ')?'), $expression);
        }

        // Insert default regex for keys
        $expression = str_replace(array('<', '>'), array('(?P<', '>'.self::REGEX_SEGMENT.')'), $expression);

        if ($regex) {
            $search = $replace = array();
            foreach ($regex as $key => $value) {
                $search[] = "<$key>".self::REGEX_SEGMENT;
                $replace[] = "<$key>$value";
            }

            // Replace the default regex with the user-specified regex
            $expression = str_replace($search, $replace, $expression);
        }

        return '#^'.$expression.'$#uD';
    }

    protected static function setRoutesFromAppConfig($appConfig = null, $path = [])
    {
        if (empty($appConfig)) {
            return;
        }

        foreach ($appConfig as $id => $config) {

            if (!empty($config['routes'])) {
                $data = $config['routes'];

                foreach ($data as $r) {
                    $p = $path;
                    $p[] = $id;
                    $p[] = $r[0];
                    self::set(
                        implode('_', $p),
                        $r[1],
                        isset($r[2]) ? $r[2] : null,
                        isset($r[3]) ? $r[3] : [],
                        isset($r[4]) ? $r[4] : [],
                        $path
                    );
                }
            }
            if (!empty($config['components'])) {
                $path[] = $id;
                self::setRoutesFromAppConfig($config['components'], $path);
            }
        }
    }

    public static function end()
    {
        return array('routes' => self::$routes);
    }

    /**
     * Retrieves a named route.
     *
     *     $route = Route::get('default');
     *
     * @param   string $name route name
     * @return  Route
     * @throws  \Exception
     */
    public static function get($name)
    {
        if (!isset(self::$routes[$name])) {
            throw new \Exception('The requested route does not exist: '.$name);
        }

        return self::$routes[$name];
    }

    /**
     * Retrieves all named routes.
     *
     *     $routes = Route::all();
     *
     * @return  array  routes by name
     */
    public static function all()
    {
        return self::$routes;
    }

    /**
     * Filters to be run before route parameters are returned:
     *
     *     Route::setFilter(routeName,
     *         function(Route $route, $params, Request $request)
     *         {
     *             if ($request->method() !== HTTP_Request::POST)
     *             {
     *                 return FALSE; // This route only matches POST requests
     *             }
     *             if ($params AND $params['controller'] === 'welcome')
     *             {
     *                 $params['controller'] = 'home';
     *             }
     *
     *             return $params;
     *         }
     *     );
     *
     * To prevent a route from matching, return `FALSE`. To replace the route
     * parameters, return an array.
     *
     * [!!] Default parameters are added before filters are called!
     *
     * @throws  \InvalidArgumentException
     * @param   array $callback callback string, array, or closure
     * @return  void
     */
    public static function setFilter($name, $callback)
    {
        if (!isset(self::$routes[$name])) {
            throw new \InvalidArgumentException('Route with name "'.$name.'" not isset.');
        }
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Invalid $callback is not callable');
        }

        self::$routes[$name]['filters'][] = $callback;
    }

    /**
     * Create a URL from a route name. This is a shortcut for:
     *
     *     echo URL::site(Route::get($name)->uri($params), $protocol);
     *
     * @param   string $name route name
     * @param   array $params URI parameters
     * @param   mixed $protocol protocol string or boolean, adds protocol and domain
     * @return  string
     * @since   3.0.7
     * @uses    URL::site
     */
    public static function url($routeName, array $params = [], $protocol = 'http')
    {
        $path = self::getPath($routeName, $params);
        $protocol = ($protocol == 'http' && Request::initial()->isSecure()) ? 'https' : $protocol;

        return URL::base($protocol, null, null, null, $path, $params);
    }

    /**
     * Generates a URI for the current route based on the parameters given.
     *
     *     routeName = [
     *          uri=> <controller>/<action>(/<id>)(/<s>)
     *          defaults=>[
     *              path => ddf/fre
     *              s => 4578875
     *          ]
     *     ]
     *     Route::getPath('routeName',array(
     *         'controller' => 'users',
     *         'action'     => 'profile',
     *         'id'         => '10'
     *     ));
     *     // return: "ddf/fre/users/profile/10/4578875"
     *
     * @param   string $routeName name of route
     * @param   array $params URI parameters
     * @return  string
     * @throws  \InvalidArgumentException
     * @uses    Route::REGEX_GROUP
     * @uses    Route::REGEX_KEY
     */
    public static function getPath($routeName, &$params = [])
    {
        $defaults = self::$routes[$routeName]['defaults'];

        /**
         * Recursively compiles a portion of a URI specification by replacing
         * the specified parameters and any optional parameters that are needed.
         *
         * @param   string $portion Part of the URI specification
         * @param   boolean $required Whether or not parameters are required (initially)
         * @return  array   Tuple of the compiled portion and whether or not it contained specified parameters
         */
        $compile = function ($portion, $required) use (&$compile, &$defaults, &$params) {
            $missing = array();

            $pattern = '#(?:'.Route::REGEX_KEY.'|'.Route::REGEX_GROUP.')#';
            $result = preg_replace_callback(
                $pattern,
                function ($matches) use (&$compile, &$defaults, &$missing, &$params, &$required) {
                    if ($matches[0][0] === '<') {
                        // Parameter, unwrapped
                        $param = $matches[1];

                        if (isset($params[$param])) {
                            // This portion is required when a specified
                            // parameter does not match the default
                            $required = (
                                $required OR
                                !isset($defaults[$param]) OR
                                $params[$param] !== $defaults[$param]
                            );

                            // Add specified parameter to this result
                            $res = $params[$param];
                            unset($params[$param]);

                            return $res;
                        }

                        // Add default parameter to this result
                        if (isset($defaults[$param])) {
                            return $defaults[$param];
                        }

                        // This portion is missing a parameter
                        $missing[] = $param;
                    } else {
                        // Group, unwrapped
                        $result = $compile($matches[2], false);
                        if ($result[1]) {
                            // This portion is required when it contains a group
                            // that is required
                            $required = true;
                        }

                        return $result[0];
                    }
                },
                $portion
            );

            if ($required AND $missing) {
                throw new \InvalidArgumentException(
                    'Required route parameter not passed: :param',
                    array(':param' => reset($missing))
                );
            }

            return array($result, $required);
        };

        list($uri) = $compile(self::$routes[$routeName]['uri'], true);

        // Trim all extra slashes from the URI
        $uri = preg_replace('#//+#', '/', rtrim($uri, '/'));

        return $uri;
    }

    /**
     * Tests if the route matches a given Request. A successful match will return
     * all of the routed parameters as an array. A failed match will return
     * boolean FALSE.
     *
     *     // Params: controller = users, action = edit, id = 10
     *     $params = $route->matches(Request::factory('users/edit/10'));
     *
     * This method should almost always be used within an if/else block:
     *
     *     if ($params = $route->matches($request))
     *     {
     *         // Parse the parameters
     *     }
     *
     * @param   Request $request Request object to match
     * @return  array             on success
     * @return  FALSE             on failure
     */
    public static function matches(Request $request, $routes = null)
    {
        // Get the URI from the Request
        $uri = trim($request->getUri(), '/');

        $routes = array_reverse(empty($routes) ? self::$routes : $routes);
        foreach ($routes as $rn => $route) {
            $matches = [];

            if (!preg_match($route['route_regex'], $uri, $matches)) {
                continue;
            }

            $params = array();
            foreach ($matches as $key => $value) {
                if (is_int($key)) {
                    // Skip all unnamed keys
                    continue;
                }
                // Set the value for all matched keys
                $params[$key] = $value;
            }

            foreach ($route['defaults'] as $key => $value) {
                if (empty($params[$key])) {
                    // Set default values for any key that was not matched
                    $params[$key] = $value;
                }
            }


            if (!empty($route['filters'])) {
                foreach ($route['filters'] as $callback) {
                    // Execute the filter giving it the route, params, and request
                    $return = call_user_func($callback, $params, $request);

                    if ($return === false) {
                        // Filter has aborted the match
                        return false;
                    } elseif (is_array($return)) {
                        // Filter has modified the parameters
                        $params = $return;
                    }
                }
            }

            return [$rn, $params];
        }

        return false;
    }

    /**
     * Returns whether this route is an external route
     * to a remote controller.
     *
     * @return  boolean
     */
    public static function is_outgoing($name)
    {
        return isset(self::$routes[$name]) && !in_array(
            self::$routes[$name]['defaults']['host'],
            self::$localhosts
        );
    }

}
