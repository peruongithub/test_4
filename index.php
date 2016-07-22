<?php
/**
 * Perederko Ruslan <perederko.ruslan@gmail.com>
 */

require_once('vendor/autoload.php');
define('ROOT', realpath(__DIR__));
$initClassData = [
    'trident\\DI' => [
        'session' => [
            'className' => 'components\\Session',
            'argument' => [
                'cookieExpire' => (new DateTime('now'))->modify('+1 week'),
            ],
        ],
        'db' => [
            'className' => 'PDO',
            'arguments' => require('./components/pdoConfig.php'),
        ],
        'render' => [
            'className' => 'trident\\phpRender',
        ],
        'setupModel' => [
            'className' => 'components\\SetupModel',
        ],
        'countryModel' => [
            'className' => 'components\\CountryModel',
            'arguments' => ['$::db' => 'db'],
        ],
        'cityModel' => [
            'className' => 'components\\CityModel',
            'arguments' => ['$::db' => 'db'],
        ],
        'inviteModel' => [
            'className' => 'components\\InviteModel',
            'arguments' => ['$::db' => 'db'],
        ],
        'userModel' => [
            'className' => 'components\\UserModel',
            'arguments' => [
                '$::session' => 'session',
                '$::db' => 'db',
                '$::countryModel' => 'countryModel',
            ],
        ]
    ],
    'trident\\Route' => [
        'default_protocol' => 'http://',
        'localhosts' => [false, '', 'local', 'localhost'],
        'routes' => [
            [\trident\Route::DEF_ROUTE_NAME, '<controller>(/<action>)(/<s>)'],
        ],
    ],
    'components\\LocaleDateTime' => [],
];

$coreProperties = [
    'context' => 'production',
    'appComponents' => [
        'main' => [
            'className' => 'components\\MainTriad',
        ],
        'user' => [
            'className' => 'components\\UserTriad',
            'argument' => [
                '$::model' => 'userModel',
                'actions' => [
                    'login' => [
                        'template' => './data/tpl/login.tpl.php',
                    ],
                ],
            ],
            'routes' => [
                ['llr', '<action>', ['action' => '(login|logout|register)'], ['controller' => 'user']],
                ['users', 'users', null, ['controller' => 'user', 'action' => 'index']]
            ],

        ],
        'invites' => [
            'className' => 'components\\InviteTriad',
            'argument' => [
                '$::model' => 'inviteModel'
            ],
            'routes' => [
                ['invite', 'invites', null, ['controller' => 'invites', 'action' => 'index']]
            ]
        ],
        'page' => [
            'className' => 'components\\PagesTriad',
            'routes' => [
                ['page', 'page/<page>', ['page' => '[A-Za-z0-9_-]+'], ['controller' => 'page', 'action' => 'index']]
            ]
        ]
    ],
    'defaultAppComponent' => 'main',
    'defaultAction' => 'index',
    'hideInputPoint' => true,
];

\trident\Core::init($coreProperties, $initClassData);

$request = (new \trident\Request());

$response = $request->execute();


$status = $response->status();
$isAjax = $request->is_ajax();
$tpl = null;
switch ($status) {
    case 400:
        $tpl = './data/tpl/errors/error400.php';
        break;
    case 403:
        $tpl = './data/tpl/errors/error403.php';
        break;
    case 404:
        $tpl = './data/tpl/errors/error404.php';
        break;
    case 500:
        $tpl = './data/tpl/errors/error500.php';
        break;
    case 503:
        $tpl = './data/tpl/errors/error503.php';
        break;
}


if (null !== $tpl && !$isAjax) {
    /**
     * @var $render \trident\phpRender
     */
    $render = \trident\DI::get('render');
    $response->body($render->fetch($tpl));
}
$response->send();
