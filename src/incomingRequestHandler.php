<?php
namespace trident;

/**
 * Request Client for internal execution
 */
class incomingRequestHandler
{
    public $routeName;
    public $route;
    /**
     * @var  Triad
     */
    public $controller;

    /**
     * @var  string  action to be executed in the controller
     */
    public $action;

    /**
     * Processes the request, executing the controller action that handles this
     * request, determined by the [Route].
     *
     *     $request->execute();
     *
     * @param   Request $request
     * @param   Response $response
     * @return  Response
     * @throws  \RuntimeException
     */
    public function execute_request(Request $request, Response $response)
    {

        $processed = Route::matches($request);
        if ($processed) {
            // Store the matching route
            $this->routeName = $processed[0];
            $params = $processed[1];

            $route = '';
            $route .= !empty($params['path']) ? $params['path'] : '';
            $route .= !empty($params['controller']) ? '/'.$params['controller'] : '';
            //$route .= !empty($params['action'])?'/'.$params['action']:'';
            $this->action = !empty($params['action']) ? $params['action'] : null;

            unset($params['controller'], $params['action'], $params['path']);
            if (empty($params) || !is_array($params)) {
                $params = [];
            }
            $request->setParams(\array_replace_recursive($request->param(), $params));
            $this->routing($request, $response, $route);
            $this->controller->setRouteName($this->routeName);

        } else {
            $this->routing($request, $response);
        }

        $this->action = empty($this->action) ? $this->controller->getDefaultAction() : $this->action;

        $out = $this->controller->executeAction($this->action, false);
        if (is_string($out)) {
            $response->body($out);
        } elseif ($out instanceof Response) {
            $response = $out;
        } else {
            $response->body($out);
        }

        // Return the response
        return $response;
    }


    protected function routing(Request $request, Response $response, $route = null)
    {
        $route = trim($route, '/');
        if (empty($route)) {
            $route = $request->query('r');
        }

        try {
            $this->controller = Core::getAppComponentFromRoute($route, $request, $response);
        } catch (\Exception $e) {
            $this->controller = DI::build(
                [
                    'className' => 'trident\\RedirectTriad',
                    'argument' => [
                        'request' => $request,
                        'response' => $response,
                    ],
                ]
            );
            $this->action = 'redirect';
        }


        if (!$this->controller instanceof Triad) {
            throw new \RuntimeException('shit happens: Core::getAppComponentFromRoute("'.$route.'") not return Triad');
        }
        if (empty($this->action)) {
            $this->action = $request->query('a', null, $this->controller->getDefaultAction());
        }
    }

}

class RedirectTriad extends Triad
{
    public function redirect()
    {
        $this->request->redirect(URL::base());

        return;
    }
}
