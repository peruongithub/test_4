<?php
namespace components;


use trident\DI;
use trident\Triad;
use trident\URL;

class UserFilter
{
    public static function onlyForLogged($action, array $config, Triad $triad)
    {
        /**
         * @var $model UserModel
         */
        $model = DI::get('userModel');
        if ($model->isGuest()) {
            $triad->request->redirect(URL::base());

            return false;
        }

        return true;
    }
}