<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <!-- Bootstrap core CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
    <!--[if lt IE 9]>
    <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->

    <style type="text/css" title="currentStyle">
        input {
            margin: 5px 0px;
        }

        .container {
            width: 400px;
        }
    </style>

</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div id="login-form">
                <h1>Авторизация на сайте</h1>
                <fieldset>
                    <form action="<?php echo $uri ?>" method="<?php echo $method ?>">
                        <input name="login" type="text" class="form-control" placeholder="Login or email address"
                               required autofocus>
                        <input name="password" type="password" class="form-control" placeholder="Password" required>
                        <input type="submit" class="btn btn-lg btn-primary btn-block" value="Sign in">
                        <footer class="clearfix">
                            <label for="remember" class="checkbox-inline"><input id="remember" name="remember"
                                                                                 type="checkbox">Remember me</label>
                        </footer>

                    </form>
                </fieldset>
            </div>
            <h1><?php echo $errors ?></h1>
        </div>
    </div>
</div>
</body>
</html>