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
            width: 400px;
        }
    </style>
</head>

<body>
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div id="login-form">
                <h2 class="form-signin-heading">Авторизация на сайте</h2>

                <form id="register_form" class="form-signin" action="<?php echo $url; ?>"
                      method="<?php echo $method; ?>" data-bv-feedbackicons-valid="glyphicon glyphicon-ok"
                      data-bv-feedbackicons-invalid="glyphicon glyphicon-remove"
                      data-bv-feedbackicons-validating="glyphicon glyphicon-refresh" data-bv-live="enabled">
                    <!-- login -->
                    <div class="form-group">
                        <div class="inputGroupContainer">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                                <input name="login" type="text" class="form-control" placeholder="Логин" required
                                       autofocus
                                       value="<?php echo $login; ?>">
                            </div>
                        </div>
                    </div>
                    <!-- phone -->
                    <div class="form-group">
                        <div class="inputGroupContainer">
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
                    </div>

                    <div class="form-group">
                        <div class="inputGroupContainer">
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
                    </div>

                    <div class="form-group">
                        <div class="inputGroupContainer">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-list"></i></span>
                                <select name="city" class="form-control selectpicker">

                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="inputGroupContainer">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-sunglasses"></i></span>
                                <input name="password" type="password" class="form-control" placeholder="Password"
                                       required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="inputGroupContainer">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-sunglasses"></i></span>
                                <input name="confirm_password" type="password" class="form-control"
                                       placeholder="Confirm password"
                                       required>
                            </div>
                        </div>
                    </div>
                    <!-- invite -->
                    <div class="form-group">
                        <div class="inputGroupContainer">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                                <input name="invite" type="text" class="form-control" placeholder="Инвайт"
                                       value="" required>
                            </div>
                        </div>
                    </div>
                    <input id="registerSubmit" type="button" class="btn btn-lg btn-primary" value="Регистрация"
                           >
                    <input type="reset" class="btn btn-lg btn-default " value="Очистить">

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
                                validators: {
                                    remote: {
                                        message: 'The username is not available',
                                        url: '<?php echo $checkLogin; ?>'
                                    }
                                }
                            },
                            phone: {
                                validators: {
                                    remote: {
                                        url: '<?php echo $checkPhone; ?>'
                                    }
                                }
                            },
                            invite: {
                                validators: {
                                    remote: {
                                        url: '<?php echo $checkInvite; ?>'
                                    }
                                }
                            },
                            password: {
                                validators: {
                                    remote: {
                                        url: '<?php echo $checkPassword; ?>'
                                    }
                                }
                            },
                            confirm_password: {
                                validators: {
                                    identical: {
                                        field: 'password',
                                        message: 'The password and its confirm are not the same'
                                    }
                                }
                            },
                            country: {
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