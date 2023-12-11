<?php 
    session_start();
    if (isset($_SESSION["new_e_token"]) && isset($_SESSION["id"])) {
        if ($_SESSION["new_e_token"] == FALSE) header("Location: EmployeeLogin.php");
    }
    else header("Location: EmployeeLogin.php");
?>

<html>
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="app.css">
        <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.10.0/css/all.css">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
        <script src="https://unpkg.com/just-validate@latest/dist/just-validate.production.min.js" defer></script>
        <script src="evalidation.js" defer></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
        <title>Employee Registration</title>
    </head>
    <body>
        <div id="wrapper">
            <form action="employee_process.php" method="post" id="form-login">
                <h1 class="form-heading">Employee Registration</h1>
                <div class="form-group">
                    <input type="text" id="first" name="fname"  class="form-input" placeholder="First Name" required>
                </div><br>
                <div class="form-group">
                    <input type="text" id="last" name="lname"  class="form-input" placeholder="Last Name" required>
                </div><br>
                <div class="form-group">
                    <input type="password" id="password" name="password"  class="form-input" placeholder="Password" required>
                    <span id='message'></span>
                </div><br>
                <div class="form-group">
                    <input type="password" id="cpassword" name="cpassword"  class="form-input" placeholder="Confirm Password" required>
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
                </div><br>
                <div class="form-group">
                    <input type="text" minlength="10" maxlength="10" id="phone" name="phone"  class="form-input" placeholder="Phone Number (Format: ##########)" required>
                    <script>
                        $(function() {
                            $("input[name='phone']").on('input', function(e) {
                                $(this).val($(this).val().replace(/[^0-9]/g, ''));
                            });
                        });
                    </script>
                </div><br>
                <div class="form-group">
                    <input type="text" id="address" name="address"  class="form-input" placeholder="Address Line 1" required>
                </div><br>
                <div class="form-group">
                    <input type="text" name="address2"  class="form-input" placeholder="Address Line 2 (Optional)">
                </div><br>
                <div class="form-group">
                    <input type="text" id="city" name="city"  class="form-input" placeholder="City" required>
                </div><br>
                <div class="form-group">
                    <input type="text" id="state" name="state"  class="form-input" placeholder="State (ex: CA, NY, NC, etc.)" required>
                    <script>
                        $(function() {
                            $("input[name='state']").on('input', function(e) {
                                $(this).val($(this).val().replace(/[^A-Z]/g, ''));
                            });
                        });
                    </script>
                </div><br>
                <div class="form-group">
                    <input type="text" minlength="5" maxlength="5" id="zip" name="zip"  class="form-input" placeholder="Zip Code" required>
                    <script>
                        $(function() {
                            $("input[name='zip']").on('input', function(e) {
                                $(this).val($(this).val().replace(/[^0-9]/g, ''));
                            });
                        });
                    </script>
                </div><br>
                <div class="form-group">
                    <input type="text" id="lstate" name="lstate"  class="form-input" placeholder="License State (ex: CA, NY, NC, etc.)" required>
                    <script>
                        $(function() {
                            $("input[name='lstate']").on('input', function(e) {
                                $(this).val($(this).val().replace(/[^A-Z]/g, ''));
                            });
                        });
                    </script>
                </div><br>
                <div class="form-group">
                    <input type="text" id="license" name="license"  class="form-input" placeholder="License" required>
                </div><br>
                <div class="form-group">
                    <input type="password" id="ssn" name="ssn"  class="form-input" minlength="9" maxlength="9" placeholder="SSN (Format: #########)" required>
                    <script>
                        $(function() {
                            $("input[name='ssn']").on('input', function(e) {
                                $(this).val($(this).val().replace(/[^0-9]/g, ''));
                            });
                        });
                    </script>
                </div><br>
                <div class="container-login100-form-btn">
                    <button type="submit" class="login100-form-btn">
                        Register
                    </button>
                </div>
                <div class="text-center p-t-90">
                    <a class="txt1" href="back.php">
                        &nbsp;&nbsp;&nbsp;Back to Login
                    </a>
                </div> 
            </form>
        </div>  
    </body>
</html>
