<?php 
    session_start();
    if(isset($_SESSION['TFA']) == FALSE || $_SESSION['TFA'] == FALSE) {
        if (isset($_SESSION['e_logged_in'])) header("Location: EmployeeLogin.php");
        else if (isset($_SESSION['logged_in'])) header("Location: Login.php");
        else header("Location: HomePage.html");
    }
?>

<html>
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Multi-Factor Authentication</title>
        <link rel="stylesheet" href="mystyles.css">
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
    </head>
    <body>
    <div class="wrapper">
            <form action="MultiFactorProcess.php" method="post"> 
                <h1>Multi-Factor Authentication</h1>
                <div class="input-box">
                    <input type="text" placeholder="" name ="code" maxlength="6" minlength="6" required>
                    <i class='bx bx-dialpad'></i>
                    <span class="floating-label">Please Enter the 6-Digit Code Sent to Your Email</span>
                </div>
                <script>
                    $(function() {
                        $("input[name='code']").on('input', function(e) {
                            $(this).val($(this).val().replace(/[^0-9]/g, ''));
                        });
                    });
                </script>
                <button type="submit" class="btn">Submit</button>

                <div class="register-link">
                    <p>Didn't receive a code? <a href="GenerateCode.php">Send it again.</a></p>
                </div>
                <div class="register-link">
                    <p><a href="back.php">Back</a></p>
                </div>
            </form>
        </div>
    </body>
</html>
