<?php
namespace trident;


class Triad extends Component
{
    const ALLOW_METHODS = 'HEAD, GET, POST, PUT, PATCH, DELETE, CONNECT';
    protected $id;
    protected $parent;
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    protected $route;
    protected $routeName;

    protected $defaultAction;
    protected $defaultConfigForActions = [
        'httpMethods' => 'GET, POST',
        'filters' => [
            'confirmHTTPMethod' => [__CLASS__, 'confirmHTTPMethod'],
        ],
        'cache' => null,
        'cacheExpire' => 0,
        'cacheDependency' => null,
        'render' => '$::render',
    ];
    protected $actions = [

    ];
    protected $disallowedActions = [];
    protected $runWithReflectionActions = [];

    public function __construct(array $options = [])
    {
        parent::__construct($options);

        if (empty($this->parent) || !($this->parent instanceof Triad)) {
            $this->parent = $this;
        }

        $this->disallowedActions = array_diff(
            get_class_methods(__CLASS__),
            ['index']
        );

        $classActions = $this->normalizeActionConfig(
            array_diff(get_class_methods($this->className()), $this->disallowedActions)
        );
        $thisAction = $this->normalizeActionConfig($this->actions);
        $this->actions = array_replace_recursive($classActions, $thisAction);

        $this->defaultAction = ($this->defaultAction == null) ?
            Core::getDefaultAction() : $this->defaultAction;

    }

    protected function normalizeActionConfig(array $config)
    {
        $actions = [];
        if (!empty($config)) {
            foreach ($config as $k => $v) {
                if (is_int($k)) {
                    $actions[$v] = [];
                } else {
                    $actions[$k] = $v;
                }
            }
        }

        return $actions;
    }

    public static function confirmHTTPMethod($action, array $config, Triad $triad)
    {
        $httpMethod = $triad->request->method();
        $methods = strtoupper($config['httpMethods']);
        $pos = strpos($methods, $httpMethod);
        if ($pos === false && DEBUG) {
            throw new \RuntimeException(
                'action allowed for calling by http methods: '.$methods.
                '. But calling by http method:'.$httpMethod
            );
        } elseif ($pos === false) {
            $triad->response->status(405);

            return false;
        }

        return true;
    }

    public static function onlyAjaxAllowed($action, array $config, Triad $triad)
    {
        return $triad->request->is_ajax();
    }

    public function id()
    {
        return $this->id;
    }

    public function parent()
    {
        return $this->parent;
    }

    public function index()
    {

    }

    public function getResponseCacheExpire()
    {
        return $this->responseCacheExpire;
    }

    public function hasAction($action)
    {
        return isset($this->actions[$action]);
    }

    public function getDefaultAction()
    {
        return $this->defaultAction;
    }

    public function setDefaultAction($action)
    {
        if ($this->methodExists($action)) {
            $this->defaultAction = $action;

            return true;
        }

        return false;
    }

    public function getAllowedActions()
    {
        return $this->actions;
    }

    public function setAllowedAction($action, $params = [])
    {
        if ($this->methodExists($action)) {
            $this->actions[$action] = $params;

            return true;
        }

        return false;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    public function setResponse(Response $response)
    {
        $this->response = $response;
    }

    public function executeAction($action, $raw = false)
    {
        $config = $this->getActionConfig($action);
        $asses = $this->confirmActionAccess($action, $config);
        if ($asses === false) {
            return '';
        }

        $return = $this->run($action, $config);

        if ($raw == false) {
            if ($return instanceof Response) {
                return $return;
            } elseif ($this->request->is_ajax()) {
                $return = \json_encode($return);
                $this->response->setHeaders('Content-Type', 'application/json');
            } elseif (($render = $this->getRender($config)) instanceof phpRender) {

                $template = $this->getTemplate($config);
                if (is_string($template)) {
                    /**
                     * @var $render phpRender
                     */
                    $return = $render->fetch($template, $return, $this);
                }
            }
        }

        return $return;
    }

    protected function getActionConfig($action)
    {
        if (!empty($action) && false !== ($pos = strrpos($action, ':'))) {
            $action = substr($action, $pos + 1);
        }
        if (!isset($this->actions[$action])) {
            return false;
        }

        return \array_replace_recursive($this->defaultConfigForActions, $this->actions[$action]);
    }

    protected function confirmActionAccess($action, $config)
    {
        $is_debug = DEBUG;
        if ($config === false) {
            if ($is_debug) {
                throw new \RuntimeException('not defined action');
            }

            return false;
        } elseif (in_array($action, $this->disallowedActions)) {
            if ($is_debug) {
                throw new \RuntimeException('not allowed action');
            }

            return false;
        } elseif (isset($this->actions[$action]['disabled']) && $this->actions[$action]['disabled']) {
            if ($is_debug) {
                throw new \RuntimeException('action disabled');
            }

            return false;
        } elseif (!$this->methodExists($action)) {
            if ($is_debug) {
                throw new \RuntimeException('not allowed action "'.$action.'"');
            }

            return false;
        }
        $return = true;
        if (!empty($config['filters'])) {
            foreach ($config['filters'] as $filterName => $callback) {
                $callback[0] = null === $callback[0] ? $this : $callback[0];
                if (!is_callable($callback)) {
                    continue;
                }
                $return = call_user_func($callback, $action, $config, $this) && $return;
                if ($return == false) {
                    break;
                }
            }
        }

        return $return;
    }

    protected function run($action, $config)
    {
        if (!empty($config['actions'])) {
            $return = [];
            $rawSub = $this->request->is_ajax() ? true : false;
            foreach ($config['actions'] as $id => $callConfig) {
                list($componentName, $componentAction) = $callConfig;

                if ($componentName == 'this' || empty($componentName)) {
                    $component = $this;
                } else {
                    $component = Core::getAppComponentFromRoute($componentName, $this->request, $this->response);
                }
                $return[$id] = $component->executeAction($componentAction, $rawSub);
            }

            $return = $this->$action($return);
        } else {
            if (in_array($action, $this->runWithReflectionActions)) {
                $return = $this->runWithReflection($action);
            } else {
                $return = $this->$action();
            }
        }

        return $return;
    }

    protected function runWithReflection($method)
    {
        $object = $this->getObject($method);
        $Reflection = new    \ReflectionMethod($object, $method);
        if ($Reflection->getNumberOfParameters() > 0) {
            $ps = array();
            $Parameters = $Reflection->getParameters();
            foreach ($Parameters as $i => $param) {
                $name = $param->getName();
                //$ref= $param->isPassedByReference();
                $default = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                $value = $this->request->param($name, $default);
                if ($param->isArray() && !is_array($value)) {
                    throw new \InvalidArgumentException('argument '.$name.' must by array');
                }
                $ps[] = $value;
            }

            //call_user_func_array(array($object,$method),$ps);
            return $Reflection->invokeArgs($object, $ps);
        } else {
            return $object->$method();
        }
    }

    /**
     * @param array $config
     * @return string
     */
    public function getRender(array $config)
    {
        $render = false;
        if (!empty($config['render'])) {
            $render = $config['render'];
            if (is_array($render)) {
                $render = DI::build($render);
            } elseif (DI::is_object_str($render)) {
                $render = DI::build(substr($render, DI::OML));
            } elseif (DI::is_service_str($render)) {
                $render = DI::get(substr($render, DI::SML));
            }
        }
        if (!$render) {
            throw new\RuntimeException('Render not defined');
        }

        return $render;
    }

    /**
     * @param array $config
     * @return string
     */
    public function getTemplate(array $config)
    {
        if (!empty($config['template'])) {
            return $config['template'];
        }

        return false;
    }

    public final function get($varName)
    {
        if (isset($this->$varName) && $this->$varName !== null) {
            return $this->$varName;
        } else {
            if ($this->parent !== $this) {
                return $this->parent->get($varName);
            }
        }

        return null;
    }

    public function setRouteName($routeName)
    {
        $this->routeName = $routeName;
    }

    public function createUrl($action = null, $routeName = null, $path = null, $query = [], $protocol = 'http')
    {
        if (!empty($action) && false !== ($pos = strrpos($action, ':'))) {
            $action = substr($action, $pos + 1);
        }

        $routeName = null === $routeName ? $this->routeName : $routeName;

        if (null !== $routeName) {
            $params = $query;
            if (!empty($action)) {
                $params['action'] = $action;
            }
            if (!empty($path)) {
                $p = strrpos($path, '/');
                $params['path'] = substr($path, 0, $p);
                $params['controller'] = substr($path, $p);
            }
            $path = Route::getPath($routeName, $params);
            unset($params['path'], $params['controller'], $params['action']);
            $query = $params;
        } else {
            $path = empty($path) ? $this->getRout() : $path;
            $action = ($action === null) ? '' : '/'.$action;
            $path = $path.$action;
        }

        $protocol = ($protocol == 'http' && $this->request->isSecure()) ? 'https' : $protocol;

        return URL::base($protocol, null, null, null, $path, $query);

    }

    public function getRout()
    {
        return empty($this->route) ? $this->id : $this->route;
    }

}
