<?php
    session_start();
    if (isset($_SESSION['logged_in']) && isset($_SESSION['TFA']))  {
        if ($_SESSION['TFA'] == TRUE && $_SESSION['logged_in'] == FALSE) header("Location: MultiFactor.php");
        else if ($_SESSION['logged_in'] == TRUE) header("Location: user.php");
    }
?>

<html>
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration</title>
        <link rel="stylesheet" href="mystyles.css">
        <script src="https://unpkg.com/just-validate@latest/dist/just-validate.production.min.js" defer></script>
        <script src="validation.js" defer></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
    </head>
    <body>
        
        <div class="wrapper">
            <form action="/process.php" id="register" method="post"> 
                <h1>Register</h1>
                <div class="input-box">
                    <input type="text" placeholder="" id="first" name ="first">
                    <span class="floating-label">First Name</span>
                </div>
                <div class="input-box">
                    <input type="text" placeholder="" id="last" name ="last">
                    <span class="floating-label">Last Name</span>
                </div>
                <div class="input-box">
                    <input type="text" placeholder="" id="username" name ="username">
                    <span class="floating-label">Username</span>
                </div>
                <div class="input-box">
                    <input type="password" placeholder="" id="password" name="password">
                    <span class="floating-label">Password (minimum 8 characters, 1 letter, & 1 number)</span>
                    <span id='message'></span>
                </div>
                <div class="input-box">
                    <input type="password" placeholder="" id="cpassword" name="cpassword">
                    <span class="floating-label">Confirm Password</span>
                    <span id='message2'></span>
                    <script>
                        $('#password, #cpassword').on('keyup', function () {
                            if ($('#password').val() == $('#cpassword').val()) {
                                $('#message').html('Passwords Match ✓').css('color', 'green');
                                $('#message2').html('Passwords Match ✓').css('color', 'green');
                            } else {
                                $('#message').html('Passwords Do Not Match ✕').css('color', 'red');
                                $('#message2').html('Passwords Do Not Match ✕').css('color', 'red');
                            }
                        });
                    </script>
                </div>
                <div class="input-box">
                    <input type="password" maxlength="4" placeholder="" id="pin" name="pin">
                    <span class="floating-label">4-Digit Pin</span>
                    <span id='message3'></span>
                </div>
                <script>
                    $(function() {
                        $("input[name='pin']").on('input', function(e) {
                            $(this).val($(this).val().replace(/[^0-9]/g, ''));
                        });
                    });
                </script>
                <div class="input-box">
                    <input type="password" maxlength="4" placeholder="" id="cpin" name="cpin">
                    <span class="floating-label">Confirm 4-Digit Pin</span>
                    <span id='message4'></span>
                    <script>
                        $('#pin, #cpin').on('keyup', function () {
                            if ($('#pin').val() == $('#cpin').val()) {
                                $('#message3').html('Pins Match ✓').css('color', 'green');
                                $('#message4').html('Pins Match ✓').css('color', 'green');
                            } else {
                                $('#message3').html('Pins Do Not Match ✕').css('color', 'red');
                                $('#message4').html('Pins Do Not Match ✕').css('color', 'red');
                            }
                        });
                    </script>
                    <script>
                        $(function() {
                            $("input[name='cpin']").on('input', function(e) {
                                $(this).val($(this).val().replace(/[^0-9]/g, ''));
                            });
                        });
                    </script>
                </div>
                <div class="input-box">
                    <input type="email" placeholder="" id="email" name ="email">
                    <span class="floating-label">Email</span>
                </div>
                <div class="input-box">
                    <input type="password" placeholder="" id="ssn" maxlength="9" name="ssn">
                    <span class="floating-label">Social Security Number (Format: *********)</span>
                </div>
                <script>
                    $(function() {
                        $("input[name='ssn']").on('input', function(e) {
                            $(this).val($(this).val().replace(/[^0-9]/g, ''));
                        });
                    });
                </script>
                <div class="input-box">
                    <input type="text" placeholder="" id="phone" maxlength="10" name ="phone">
                    <span class="floating-label">Phone Number (Format: **********)</span>
                </div>
                <script>
                    $(function() {
                        $("input[name='phone']").on('input', function(e) {
                            $(this).val($(this).val().replace(/[^0-9]/g, ''));
                        });
                    });
                </script>
                <div class="input-box">
                    <input type="text" placeholder="" id="lstate" maxlength="2" name ="lstate">
                    <span class="floating-label">License State (ex: CA, NY, NC, etc.)</span>
                </div>
                <script>
                    $(function() {
                        $("input[name='lstate']").on('input', function(e) {
                            $(this).val($(this).val().replace(/[^A-Z]/g, ''));
                        });
                    });
                </script>
                <div class="input-box">
                    <input type="text" placeholder="" id="license" name ="license">
                    <span class="floating-label">License Number</span>
                </div>
                <div class="input-box">
                    <input type="text" placeholder="" id="address" name ="address">
                    <span class="floating-label">Address Line 1</span>
                </div>
                <div class="input-box">
                    <input type="text" placeholder="" id="address2" name ="address2">
                    <span class="floating-label" id="cus">Address Line 2 (Optional)</span>
                </div>
                <div class="input-box">
                    <input type="text" placeholder="" id="city" name ="city">
                    <span class="floating-label">City</span>
                </div>
                <script>
                    $(function() {
                        $("input[name='city']").on('input', function(e) {
                            $(this).val($(this).val().replace(/[^a-z, A-Z]/g, ''));
                        });
                    });
                </script>
                <div class="input-box">
                    <input type="select" placeholder="" id="state" maxlength="2" name ="state">
                    <span class="floating-label">State (ex: CA, NY, NC, etc.)</span>
                </div>
                <script>
                    $(function() {
                        $("input[name='state']").on('input', function(e) {
                            $(this).val($(this).val().replace(/[^A-Z]/g, ''));
                        });
                    });
                </script>
                <div class="input-box">
                    <input type="text" placeholder="" id="zip" name ="zip" minlength="5" maxlength="5">
                    <span class="floating-label">Zip Code</span>
                    <script>
                        $(function() {
                            $("input[name='zip']").on('input', function(e) {
                                $(this).val($(this).val().replace(/[^0-9]/g, ''));
                            });
                        });
                    </script>
                </div>

                <button type="submit" class="btn">Register</button>

                <div class="register-link">
                    <p>Already have an account? <a href="Login.php">Login</a></p>
                </div>
            </form>
        </div>
    </body>
</html>
