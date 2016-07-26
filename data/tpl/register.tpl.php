<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Registration</title>

    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="/data/js/ajax.js"></script>
    <!-- Bootstrap core CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>

    <link rel="stylesheet" href="/data/MessageBoxes/messageboxes.css" type="text/css"/>
    <script src="/data/MessageBoxes/jquery.messageboxes.js"></script>

    <!-- bootstrap-datetimepicker -->
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.14.1/moment.min.js"></script>
    <script type="text/javascript"
            src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.37/js/bootstrap-datetimepicker.min.js"></script>
    <link rel="stylesheet"
          href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.37/css/bootstrap-datetimepicker.min.css"/>


    <!-- bootstrap-validator -->
    <link rel="stylesheet"
          href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-validator/0.5.3/css/bootstrapValidator.min.css">
    <script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-validator/0.5.3/js/bootstrapValidator.min.js"></script>

    <!-- maskedinput -->
    <script src="/data/js/jquery.maskedinput.min.js"></script>

    <style type="text/css" title="currentStyle">


        .container {
            width: 800px;
        }
        #login-form > .form-signin-heading {
            text-align: center;
        }
    </style>
</head>

<body>
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div id="login-form">
                <h2 class="form-signin-heading">Авторизация на сайте</h2>

                <form id="register_form" class="form-signin form-horizontal" action="<?php echo $url; ?>"
                      method="<?php echo $method; ?>" data-bv-feedbackicons-valid="glyphicon glyphicon-ok"
                      data-bv-feedbackicons-invalid="glyphicon glyphicon-remove"
                      data-bv-feedbackicons-validating="glyphicon glyphicon-refresh" data-bv-live="enabled">
                    <!-- login -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="login">Логин</label>
                        <div class="inputGroupContainer col-sm-6">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                                <input name="login" type="text" class="form-control" placeholder="Логин" required
                                       autofocus
                                       value="<?php echo $login; ?>">
                            </div>
                        </div>
                        <span class="col-sm-4 help-inline" id="login_errors"></span>
                    </div>
                    <!-- phone -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="phone">Телефон</label>
                        <div class="inputGroupContainer col-sm-6">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-phone"></i></span>
                                <input name="phone"
                                       type="tel"
                                       class="form-control"
                                       placeholder="+38 (093) 937-99-92"
                                       required title="Телефон"
                                       pattern="(((\+\d)?([1-9])?)?\s?(\(?(\d{3})\)?){1}\s?([\-\s\d]{7,9}){1})"
                                >
                            </div>
                        </div>
                        <span class="col-sm-4 help-inline" id="phone_errors"></span>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="country">Страна</label>
                        <div class="inputGroupContainer col-sm-6">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-list"></i></span>
                                <select name="country" class="form-control selectpicker">
                                    <?php
                                    foreach ($country_list as $row) {
                                        echo '<option value="' . $row['id'] . '" name="' . $row['code'] . '">' . $row['name'] . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <span class="col-sm-4 help-inline" id="country_errors"></span>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="city">Город</label>
                        <div class="inputGroupContainer col-sm-6">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-list"></i></span>
                                <select name="city" class="form-control selectpicker">

                                </select>
                            </div>
                        </div>
                        <span class="col-sm-4 help-inline" id="city_errors"></span>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="password">Пароль</label>
                        <div class="inputGroupContainer col-sm-6">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-sunglasses"></i></span>
                                <input name="password" type="password" class="form-control" placeholder="Password"
                                       required>
                            </div>
                        </div>
                        <span class="col-sm-4 help-inline" id="password_errors"></span>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="confirm_password">Пароль еще раз</label>
                        <div class="inputGroupContainer col-sm-6">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-sunglasses"></i></span>
                                <input name="confirm_password" type="password" class="form-control"
                                       placeholder="Confirm password"
                                       required>
                            </div>
                        </div>
                        <span class="col-sm-4 help-inline" id="confirm_errors"></span>
                    </div>
                    <!-- invite -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="invite">Код инвайта</label>
                        <div class="inputGroupContainer col-sm-6">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                                <input name="invite" type="text" class="form-control" placeholder="Инвайт"
                                       value="" required>
                            </div>
                        </div>
                        <span class="col-sm-4 help-inline" id="invite_errors"></span>
                    </div>

                    <div class="col-sm-3 col-sm-offset-2">
                        <input id="registerSubmit" type="button"
                               class="btn btn-lg btn-primary"
                               value="Регистрация">
                    </div>
                    <div class="col-sm-3">
                        <input type="reset"
                               class="btn btn-lg btn-default"
                               value="Очистить">
                    </div>

                </form>

            </div>
            <script type="text/javascript">
                $(document).ready(function () {


                    $('input[name=phone]').mask('+38 (999) 999-99-99', {
                        placeholder: '_',
                        completed: function () {
                            $('#register_form')
                                .data('bootstrapValidator')
                                .updateStatus('phone', 'VALID', null);
                        }
                    });

                    $('input[type=reset]').click(function () {
                        $('#register_form')
                            .data('bootstrapValidator')
                            .resetForm();
                    });

                    $('#registerSubmit').click(function () {
                        $.ajax({
                            url: $('#register_form').attr('action'),
                            method: 'POST',
                            dataType: 'json',
                            processData: false,
                            data: $('#register_form').serialize(),
                            success: function (data, textStatus, jqXHR) {
                                $('#registerSubmit').addClass('disabled');
                                $.showmessage({
                                    type: 'success',
                                    message: data.message
                                });
                            }
                        });
                    });

                    var city = $('select[name=city]');
                    $('select[name=country]').change(function () {
                        var id = $('select[name=country] option:selected').val();

                        $.ajax({
                            url: '<?php echo $cityData; ?>?country=' + id + '',
                            method: 'GET',
                            dataType: 'json',
                            success: function (data, textStatus, jqXHR) {
                                $('option', city).remove();
                                city.val('');
                                for (var i = 0; i < data.length; i++) {
                                    city
                                        .append($("<option></option>")
                                            .attr("value", data[i].id)
                                            .text(data[i].name));
                                }


                            }
                        });
                    });

                    $('#register_form').bootstrapValidator({
                        // To use feedback icons, ensure that you use Bootstrap v3.1.0 or later
                        feedbackIcons: {
                            valid: 'glyphicon glyphicon-ok',
                            invalid: 'glyphicon glyphicon-remove',
                            validating: 'glyphicon glyphicon-refresh'
                        },
                        fields: {
                            login: {
                                container: '#login_errors',
                                validators: {
                                    remote: {
                                        message: 'The username is not available',
                                        url: '<?php echo $checkLogin; ?>'
                                    }
                                }
                            },
                            phone: {
                                container: '#phone_errors',
                                validators: {
                                    remote: {
                                        url: '<?php echo $checkPhone; ?>'
                                    }
                                }
                            },
                            invite: {
                                container: '#invite_errors',
                                validators: {
                                    remote: {
                                        url: '<?php echo $checkInvite; ?>'
                                    }
                                }
                            },
                            password: {
                                container: '#password_errors',
                                validators: {
                                    remote: {
                                        url: '<?php echo $checkPassword; ?>'
                                    }
                                }
                            },
                            confirm_password: {
                                container: '#confirm_errors',
                                validators: {
                                    identical: {
                                        field: 'password',
                                        message: 'The password and its confirm are not the same'
                                    }
                                }
                            },
                            country: {
                                container: '#country_errors',
                                validators: {
                                    notEmpty: {
                                        message: 'Please select your country'
                                    },
                                    remote: {
                                        url: '<?php echo $checkCountry; ?>'
                                    }
                                }
                            },
                            city: {
                                container: '#city_errors',
                                validators: {
                                    notEmpty: {
                                        message: 'Please select your country'
                                    },
                                    remote: {
                                        url: '<?php echo $checkCity; ?>'
                                    }
                                }
                            }
                        }
                    });

                });
            </script>
        </div>
    </div>
</div>
</body>
</html>