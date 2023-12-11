<?php 
    session_start();
    if (isset($_SESSION['new_e_token'])) {
        if ($_SESSION['new_e_token'] == TRUE) header("Location: employee_register.php");
    }
    else if (isset($_SESSION['e_logged_in']) && $_SESSION['e_logged_in'] == TRUE) header("Location: employee.php");
    else if (isset($_SESSION['e_forgot']) || isset($_SESSION['u_forgot'])) header("Location: Logout.php");
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="app.css">
        <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.10.0/css/all.css">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
        <link rel="icon" type="image/png" href="favicon.ico"/>
        <script defer src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
        <script defer src="app.js"></script>
        <script defer src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
        <title>  Employee Login </title>
    </head>
    <body>
        <div id="wrapper">
            <form action="employee_authentication.php" id="form-login" method="post">
                <h1 class="form-heading"> Employee Login</h1>
                <div class="form-group">
                    <input type="text" name="employee_id" class="form-input" placeholder="Employee ID" maxlength="30" required>
                    <script>
                        $(function() {
                            $("input[name='employee_id']").on('input', function(e) {
                                $(this).val($(this).val().replace(/[^0-9]/g, ''));
                            });
                        });
                    </script>
                </div>
                <div class="form-group">
                    <br>
                    <input type="password" name="password"  class="form-input"  placeholder="Password" required>
                    <i class="fas fa-eye" id="togglePassword"></i>
                </div>
                
                <div class="text-center p-t-90">
                    <a class="txt1" href="employee_register_code.php">
                        &nbsp;&nbsp;&nbsp;New Employee? Register
                    </a>
                </div> 

                <div class="text-center p-t-90">
                    <a class="txt1" href="e_forgot.php">
                        Forgot Password or Employee ID
                    </a>
                </div>

                <div class="container-login100-form-btn">
                    <button class="login100-form-btn">
                        Login
                    </button>
                </div><br><br>
                <div class="col-sm-12 col-md-12 col-lg-12 hidden-xs">
                    <p class="text-right hidden-xs"><a data-toggle="collapse" data-parent="#accordion" href="#virtualkeyboard" class="text-danger">Virtual Keyboard <span class="caret"></span></a></p>
                    <div id="virtualkeyboard" class="panel-collapse collapse">
                        <div class="panel-body">
                            <ul class="keyboard">
                            <li class="char">1</li>
                            <li class="char">2</li>
                            <li class="char">3</li>
                            <li class="char">4</li>
                            <li class="char">5</li>
                            <li class="char">6</li>
                            <li class="char">7</li>
                            <li class="char">8</li>
                            <li class="char">9</li>
                            <li class="char">0</li>
                            <li class="char">a</li>
                            <li class="char">b</li>
                            <li class="char">c</li>
                            <li class="char">d</li>
                            <li class="char">e</li>
                            <li class="char">f</li>
                            <li class="char">g</li>
                            <li class="char">h</li>
                            <li class="char">i</li>
                            <li class="char">j</li>    
                            <li class="char">k</li>
                            <li class="char">l</li>
                            <li class="char">m</li>
                            <li class="char">n</li>
                            <li class="char">o</li>
                            <li class="char">p</li>
                            <li class="char">q</li>
                            <li class="char">r</li>
                            <li class="char">s</li>
                            <li class="char">t</li>  
                            <li class="char">u</li>
                            <li class="char">v</li>
                            <li class="char">w</li>
                            <li class="char">x</li>
                            <li class="char">y</li>
                            <li class="char">z</li>
                            <li class="char">^</li>
                            <li class="char">-</li>
                            <li class="char">_</li>
                            <li class="char at">@</li>
                            <li class="char">+</li>
                            <li class="char">&</li>
                            <li class="char">*</li>
                            <li class="char">%</li>
                            <li class="char">$</li>
                            <li class="char">#</li>
                            <li class="char">!</li> 
                            <li class="backspace last">CLR</li>
                            <li class="capslock">CAPS</li>
                            </ul>
                        </div>
                    </div>
                </div>    
            </form>
        </div>          
        <footer>
            <p><a href="HomePage.html">Go back to the Homepage</a></p>
        </footer>
    </body>
</html>
