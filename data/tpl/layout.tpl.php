<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title><?php echo !empty($htmlTitle) ? $htmlTitle : 'Starter Template for Bootstrap'; ?></title>

    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <!-- Bootstrap core CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>

    <link rel="stylesheet" href="/data/MessageBoxes/messageboxes.css" type="text/css"/>
    <script src="/data/MessageBoxes/jquery.messageboxes.js"></script>
    <script src="/data/js/ajax.js"></script>

    <style type="text/css" title="currentStyle">
        body {
            padding-top: 55px;
        }

        div.row {
            padding: 10px 0px;
        }
        ul.navbar-nav > li > a > span.glyphicon{
            margin-right: 5px;
        }
    </style>

    <script type="text/javascript">

        $(document).ready(function () {

            $('li', $('ul.nav')).each(function () {
                var href = $('a:first', this).prop('href');
                if (window.location.href == href) {
                    $(this).addClass('active');
                } else {
                    $(this).removeClass('active');
                }
            });
        });
    </script>

    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <!--<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>-->
    <!--<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>-->
    <![endif]-->
</head>

<body>

<div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="#">Регистрация пользователей по инвайту</a>
        </div>
        <div class="collapse navbar-collapse">
            <ul class="nav navbar-nav">
                <li class="active"><a href="<?php echo \trident\URL::base(); ?>"><span
                            class="glyphicon glyphicon-home"></span>Главная</a></li>
                <li><a href="<?php echo \trident\Route::url(
                            'page_page',
                            ['page' => 'about']
                        ); ?>">О проекте</a></li>
                <li><a href="<?php echo \trident\Route::url('user_users'); ?>">Пользователи</a></li>
                <li><a href="<?php echo \trident\Route::url('invites_invite'); ?>">Инвайты</a></li>
                
            </ul>
            <ul class="nav navbar-nav navbar-right">
                <?php
                /**
                 * @var $userModel \components\UserModel
                 */
                $userModel = \trident\DI::get('userModel');
                if ($userModel->isGuest()) {
                    echo '<li><a href="'.\trident\Route::url(
                            'user_llr',
                            ['action' => 'login']
                        ).'" title="Login">Login</a></li>';
                    echo '<li><a href="'.\trident\Route::url(
                            'user_llr',
                            ['action' => 'register']
                        ).'" title="Register">Register</a></li>';

                } else {
                    $USER = $userModel->getUser();
                    echo '<li><a href="#"><span class="glyphicon glyphicon-user"></span>'.$USER['login'].'</a></li>';
                    echo '<li><a href="'.\trident\Route::url(
                            'user_llr',
                            ['action' => 'logout']
                        ).'" title="Logout">Logout</a></li>';
                }
                ?>
            </ul>
        </div><!--/.nav-collapse -->
    </div>
</div>

<div class="container-fluid">

    <?php echo $mainContent; ?>

</div><!-- /.container -->
</body>
</html>