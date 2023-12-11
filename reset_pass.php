<?php
  session_start();
  if ($_SESSION['e_logged_in'] == TRUE) header("Location: employee.php");
  else if ($_SESSION['logged_in'] == TRUE) header("Location: user.php");
  else if ($_SESSION['TFA_Token'] == FALSE && $_SESSION['Last_Location'] != "forgot_process.php") header("Location: Logout.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="forgot.css">
<link rel="icon" type="image/png" href="lock.ico"/>
<script src="https://unpkg.com/just-validate@latest/dist/just-validate.production.min.js" defer></script>
<script src="reset_pass.js" defer></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>

<title>  Forgot Password </title>

</head>
<body>

<div class="form-gap"></div>
<div class="container">
	<div class="row">
		<div class="col-md-4 col-md-offset-4">
            <div class="panel panel-default">
              <div class="panel-body">
                <div class="text-center">
                  <h3><i class="fa fa-lock fa-4x"style="color: gray;"></i></h3>
                  <h2 class="text-center"><b>Reset Password</b></h2>
                  <p>Please enter your new password:</p>
                  <div class="panel-body">

                    <form id="forgot-form" action="forgot_process.php" role="form" autocomplete="off" class="form" method="post">
    
                      <div class="form-group">
                        <div class="input-group" style="margin: auto;">
                          <input style="border-radius: 2px;width: 275px;" id="password" name="password" placeholder="New Password" class="form-control" type="password" required>
                          <span id='message'></span>
                        </div>
                        <div class="input-group custom" style="margin: auto;">
                          <input style="border-radius: 2px;width: 275px;" id="cpassword" name="cpassword" placeholder="Confirm New Password" class="form-control"  type="password" required>
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
                      </div>
                      <div class="form-group">
                        <button name="pass" class="btn btn-lg btn-primary btn-block" value="1" type="submit">Reset Password</button>
                      </div>
                      <div class="form-group">
                        <a href="Logout.php" class="btn btn-lg btn-secondary btn-block">Back to Login</a>
                      </div>
                    </form>
    
                  </div>
                </div>
              </div>
            </div>
          </div>
	</div>
</div>

</body>
