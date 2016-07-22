<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup</title>

    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <!-- Bootstrap core CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>

    <link rel="stylesheet" href="/data/MessageBoxes/messageboxes.css" type="text/css"/>
    <script src="/data/MessageBoxes/jquery.messageboxes.js"></script>
    <script src="/data/js/ajax.js"></script>

    <style type="text/css" title="currentStyle">
        div.row {
            padding: 10px 0px;
        }

        body {
            padding-top: 55px;
        }

        input {
            margin: 5px 0px;
        }
    </style>

    <script src="//oss.maxcdn.com/jquery.form/3.50/jquery.form.min.js"></script>

    <script type="text/javascript">


        $(document).ready(function () {
// Функция, которая отображает следующую вкладку
            var nextTab = function () {
                var tabs = $('ul#myTab > li');
                var active = tabs.filter('.active');
                var next = active.next('li').length ? active.next('li').find('a') : tabs.filter(':first-child').find('a');
                // Используем метод Bootsrap для отображения вкладки
                next.tab('show')
            };

            $('div.tab-pane').on('shown.bs.tab', function (e) {
                $('ul#myTab li#' + (this.prop('id'))).addClass('alert-link');
            }).on('hide.bs.tab', function (e) {
                $('ul#myTab li#' + (this.prop('id'))).removeClass('alert-link');
            });

            var dbConfigForm = $('form#dbConfig');
            var check = $('input#check');
            var save = $('input#save');
            var done = $('input#done');

            check.click(function () {
                if (check.is('.btn-primary')) {
                    dbConfigForm.ajaxSubmit({
                        dataType: 'json',
                        method: 'POST',
                        resetForm: false,
                        beforeSubmit: function (arr, form) {
                        },
                        success: function (json, statusText, xhr, form) {
                            check.removeClass('btn-primary').addClass('btn-success');
                            save.removeClass('disabled').addClass('btn-primary');
                            $.showmessage({
                                type: 'success',
                                message: json.message
                            });
                        }
                    });
                }
            });

            save.click(function () {
                if (save.is('.btn-primary')) {
                    dbConfigForm.ajaxSubmit({
                        dataType: 'json',
                        method: 'PUT',
                        resetForm: false,
                        beforeSubmit: function (arr, form) {
                        },
                        success: function (json, statusText, xhr, form) {
                            save.removeClass('btn-primary').addClass('btn-success');
                            done.removeClass('disabled').addClass('btn-primary');
                            $.showmessage({
                                type: 'success',
                                message: json.message
                            });

                            setTimeout(function () {
                                nextTab();
                            }, 1000);
                        }
                    });
                }
            });

            done.click(function () {
                if (save.is('.btn-success') && $(this).not('.disabled') && $(this).not('.btn-success')) {
                    $.ajax({
                        url: '<?php echo $done; ?>',
                        method: 'GET',
                        crossDomain: false,
                        //contentType: 'application/json',
                        dataType: 'json',
                        processData: false,
                        //data: (null == ajaxData) ? '' : JSON.stringify(ajaxData),
                        success: function (data, textStatus, jqXHR) {
                            done.removeClass('btn-primary').addClass('disabled');

                            done.parent().prepend('<ul class="list-group"></ul>');

                            var showMessage = function (message, i) {
                                setTimeout(function () {
                                    var html = '<li class="list-group-item list-group-item-success">' + message + '</li>';
                                    $('ul.list-group', done.parent()).append(html);
                                }, 1000 * (i + 1));
                            };
                            var responses = data.responses || [];
                            for (var i = 0, ien = responses.length; i < ien; i++) {
                                showMessage(responses[i].message, i);
                            }

                            if (data.message) {
                                showMessage(data.message, ++i);
                            }

                            setTimeout(function () {
                                done.removeClass('disabled').addClass('btn-success');
                            }, (i + 1) * 1000);
                            setTimeout(function () {
                                $('a#home').removeClass('disabled');
                                nextTab();
                            }, ((i + 1) * 1000 + 1000));
                        }
                    });
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
<div class="container">
    <div id="rootwizard">
        <div class="nav-divider">
            <ul id="myTab" class="nav nav-pills">
                <li class="active alert-link"><a class="tabnav" data-toggle="tab" href="#tab1">Data base connection
                        setup</a></li>
                <li><a class="tabnav" data-toggle="tab" href="#tab2">
                        Create schemas and fill some data
                    </a>
                </li>
                <li><a class="tabnav" data-toggle="tab" href="#tab3">Success</a></li>
            </ul>
        </div>
        <div class="tab-content">
            <div class="tab-pane fade in active" id="tab1">
                <form id="dbConfig" class="form-signin" action="<?php echo $dbConfig; ?>" method="POST">
                    <input name="host" type="text" class="form-control" placeholder="Mysql host" required autofocus
                           value="<?php echo $host; ?>">
                    <input name="dbname" type="text" class="form-control" placeholder="Data base name" required
                           value="<?php echo $dbname; ?>">
                    <input name="name" type="text" class="form-control" placeholder="User name"
                           value="<?php echo $name; ?>">
                    <input name="password" type="password" class="form-control" placeholder="Password" required>
                    <input name="confirm_password" type="password" class="form-control" placeholder="Confirm password"
                           required>
                    <input id="check" type="button" class="btn btn-sm btn-primary" value="Check connection">
                    <input id="save" type="button" class="btn btn-sm btn-sm disabled" value="Save config">
                </form>

            </div>
            <div class="tab-pane fade" id="tab2">
                <input id="done" type="button" class="btn btn-sm btn-sm disabled" value="Done">
            </div>
            <div class="tab-pane fade" id="tab3">
                <a id="home" class="btn btn-block btn-primary disabled" href="<?php echo \trident\URL::base(); ?>">Home
                    page</a>
            </div>
        </div>
    </div>
</div><!-- /.container -->
</body>
</html>