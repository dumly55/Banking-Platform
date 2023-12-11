<?php 
    session_start();
    if (isset($_SESSION["new_e_token"])) {
        if ($_SESSION["new_e_token"] == TRUE) header("Location: employee_register.php");
    }
?>

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
        <title>  New Employee </title>
    </head>
    <body>
        <div id="wrapper">
            <form action="employee_code_process.php" method="post" id="form-login">
                <h1 class="form-heading">New Employee</h1>
                <div class="form-group">
                    <input type="text" name="employee_id"  class="form-input" placeholder="Employee ID" maxlength="30" required>
                    <script>
                        $(function() {
                            $("input[name='employee_id']").on('input', function(e) {
                                $(this).val($(this).val().replace(/[^0-9]/g, ''));
                            });
                        });
                    </script>
                </div>
                <div class="text-center p-t-90">
                    <a class="txt1" href="EmployeeLogin.php">
                        &nbsp;&nbsp;&nbsp;Back to Login
                    </a>
                </div> 
                <div class="container-login100-form-btn">
                    <button type="submit" class="login100-form-btn">
                        Submit
                    </button>
                </div><br><br>
            </form>
        </div>   
    </body>
</html>