<?php
  session_start();
  if ($_SESSION['e_logged_in'] == TRUE) header("Location: employee.php");
  else if ($_SESSION['logged_in'] == TRUE) header("Location: user.php");
  else if (isset($_SESSION['u_forgot']) == FALSE && isset($_SESSION['e_forgot']) == FALSE) header("Location: Homepage.html");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="forgot.css">
<link rel="icon" type="image/png" href="lock.ico"/>
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
                  <h2 class="text-center"><b>Forgot Password?</b></h2>
                  <p>Please enter your email address to reset your password or recover your username.</p>
                  <div class="panel-body">

                    <form id="forgot-form" action="forgot_process.php" role="form" autocomplete="off" class="form" method="post">
    
                      <div class="form-group">
                        <div class="input-group">
                          <span class="input-group-addon"><i class="glyphicon glyphicon-envelope color-blue"></i></span>
                          <input id="email" name="email" placeholder="email address" class="form-control"  type="email" required>
                        </div>
                      </div>
                      <div class="form-group">
                        <button name="pass" class="btn btn-lg btn-primary btn-block" value="1" type="submit">Reset Password</button>
                      </div>
                      <div class="form-group">
                        <button name="user" class="btn btn-lg btn-primary btn-block" value="1" type="submit">Forgot Username</button>
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
