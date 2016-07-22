<?php

namespace components;


use trident\DI;
use trident\phpRender;
use trident\Request;
use trident\Route;
use trident\URL;

class UserTriad extends MainTriad
{
    /**
     * @var $session phpRender
     */
    protected $render;

    protected $actions = [
        'index' => [
            'httpMethods' => 'GET',
        ],
        'login' => [
            'httpMethods' => 'GET, POST',
            'template' => './data/tpl/login.tpl.php',
        ],
        'register' => [
            'httpMethods' => 'GET, POST',
            'template' => './data/tpl/register.tpl.php',
        ],
        'checkLogin' => [
            'filters' => ['onlyAjaxAllowed' => [__CLASS__, 'onlyAjaxAllowed']],
        ],
        'checkPassword' => [
            'filters' => ['onlyAjaxAllowed' => [__CLASS__, 'onlyAjaxAllowed']],
        ],
        'checkPhone' => [
            'filters' => ['onlyAjaxAllowed' => [__CLASS__, 'onlyAjaxAllowed']],
        ],
        'cities' => [
            'filters' => ['onlyAjaxAllowed' => [__CLASS__, 'onlyAjaxAllowed']],
        ]
    ];
    protected $runWithReflectionActions = [];

    /**
     * @var $model UserModel
     */
    protected $model;

    public function index()
    {
        if ($this->request->is_ajax()) {
            return $this->model->simple($this->request);
        }

        return ['mainContent' => DI::get('render')->fetch('./data/tpl/users.tpl.php', $this->model->getDataTableDefaults(), $this)];
    }

    public function login()
    {
        $data = [
            'uri' => $this->createUrl(__METHOD__),
            'method' => Request::POST,
            'errors' => '',
        ];

        if (Request::POST === $this->request->method()) {
            //'set'
            $result = $this->model->login($this->request);
            if (Status::SUCCESS !== $result->getStatus()) {
                $data['errors'] = $result->getMessage();

                return $data;
            }

            $this->request->redirect(URL::base());

            return '';
        }

        //'get'

        return $data;
    }

    public function logout()
    {
        $this->model->logout();

        $this->request->redirect(URL::base());
    }

    public function register()
    {
        $data = $this->model->getDefaults();

        $data['url'] = $this->createUrl(__METHOD__);
        $data['method'] = Request::POST;
        $countryModel = DI::get('countryModel');
        $data['country_list'] = $countryModel->getCountryData($this->request, CountryModel::SELECT_ALL);


        $data['checkLogin'] = $this->createUrl('checkLogin', Route::DEF_ROUTE_NAME, $this->getRout());
        $data['checkPassword'] = $this->createUrl('checkPassword', Route::DEF_ROUTE_NAME, $this->getRout());
        $data['checkPhone'] = $this->createUrl('checkPhone', Route::DEF_ROUTE_NAME, $this->getRout());
        $data['checkCountry'] = $this->createUrl('checkCountry', Route::DEF_ROUTE_NAME, $this->getRout());
        $data['checkCity'] = $this->createUrl('checkCity', Route::DEF_ROUTE_NAME, $this->getRout());
        $data['cityData'] = $this->createUrl('cities', Route::DEF_ROUTE_NAME, $this->getRout());
        $data['checkInvite'] = $this->createUrl('checkInvite', Route::DEF_ROUTE_NAME, 'invites');

        if (Request::POST === $this->request->method()) {
            //'set'
            $result = $this->model->register($this->request);
            if($result->getStatus() === Status::ERROR ||$result->getStatus() === Status::FAILURE){
                $this->response->status(400);
            }
            return $result->toArray();
        }

        //'get'

        return $data;
    }

    public function cities(){
        /**
         * @var $cityModel CityModel
         */
        $cityModel = DI::get('cityModel');
        $data = $cityModel->getCityData($this->request, CityModel::SELECT_ALL);
        if ($data instanceof ModelResponse) {
            if($data->getStatus() === Status::ERROR ||$data->getStatus() === Status::FAILURE){
                $this->response->status(400);
            }
            $data = $data->toArray();
        }

        return $data;
    }

    /**
     * @return array
     */
    public function checkLogin()
    {
        $valid = $this->model->validateLogin($this->request, null);
        $message = '';
        if ($valid instanceof ModelResponse) {
            if($valid->getStatus() === Status::ERROR ||$valid->getStatus() === Status::FAILURE){
                //$this->response->status(400);
            }
            $message = $valid->getMessage();
            $valid = false;
        }

        return ['valid' => $valid, 'message' => $message];
    }

    /**
     * @return array
     */
    public function checkPhone()
    {
        $valid = $this->model->validatePhone($this->request, null);
        $message = '';
        if ($valid instanceof ModelResponse) {
            if($valid->getStatus() === Status::ERROR ||$valid->getStatus() === Status::FAILURE){
                //$this->response->status(400);
            }
            $message = $valid->getMessage();
            $valid = false;
        }

        return ['valid' => $valid, 'message' => $message];
    }

    /**
     * @return array
     */
    public function checkCountry()
    {
        /**
         * @var $countryModel CountryModel
         */
        $countryModel = DI::get('countryModel');
        $valid = $countryModel->validateCountry($this->request, null);
        $message = '';
        if ($valid instanceof ModelResponse) {
            if($valid->getStatus() === Status::ERROR ||$valid->getStatus() === Status::FAILURE){
                //$this->response->status(400);
            }
            $message = $valid->getMessage();
            $valid = false;
        }

        return ['valid' => $valid, 'message' => $message];
    }

    /**
     * @return array
     */
    public function checkCity()
    {
        /**
         * @var $cityModel CityModel
         */
        $cityModel = DI::get('cityModel');
        $valid = $cityModel->validateCity($this->request, null);
        $message = '';
        if ($valid instanceof ModelResponse) {
            if($valid->getStatus() === Status::ERROR ||$valid->getStatus() === Status::FAILURE){
                //$this->response->status(400);
            }
            $message = $valid->getMessage();
            $valid = false;
        }

        return ['valid' => $valid, 'message' => $message];
    }

    /**
     * @return array
     */
    public function checkPassword()
    {
        $password = $this->request->param('password', 'defaultPassword');
        $valid = $this->model->validatePassword($password);
        $message = '';
        if ($valid instanceof ModelResponse) {
            if($valid->getStatus() === Status::ERROR ||$valid->getStatus() === Status::FAILURE){
                //$this->response->status(400);
            }
            $message = $valid->getMessage();
            $valid = false;
        }

        return ['valid' => $valid, 'message' => $message];
    }
}