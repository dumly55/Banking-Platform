<?php
    session_start();
    require "vendor/autoload.php";
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    if (isset($_SESSION['body']) && isset($_SESSION['subject'])) {
        if (isset($_POST["email"])) $email = $_POST["email"];
        else $email = $_SESSION['email'];
        $body = $_SESSION['body'];
        $subject = $_SESSION['subject'];
        $name = "Bank of the Future";
    
        $mail = new PHPMailer(true);
    
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    
        $mail->isSMTP();
        $mail->SMTPAuth = true;
    
        $mail->Host = "smtp.gmail.com";
        $mail->SMTPSecure = "tls";
        $mail->Port = "587";
    
        $mail->Username = "onlinebankingwebsite@gmail.com";
        $mail->Password = "jmanactbghairvty";
    
        $mail->setFrom($email, $name);
        $mail->addAddress($email);
        $mail->Subject = $subject;
        $mail->Body = $body;
    
        $mail->send();

        unset($_SESSION['body'], $_SESSION['subject']);
    
        if ($subject == "Multi-Factor Authentication") header("Location: MultiFactor.php");
        else if ($_SESSION['logged_in'] == TRUE) header("Location: user.php");
        else if ($_SESSION['e_logged_in'] == TRUE) header("Location: employee.php");
        else if ($_SESSION['e_forgot'] == TRUE || $_SESSION['u_forgot'] == TRUE) header("Location: Logout.php");
        else header("Location: HomePage.html");
    }
    else if ($_SESSION['logged_in'] == TRUE) header("Location: user.php");
    else if ($_SESSION['e_logged_in'] == TRUE) header("Location: employee.php");
    else header("Location: HomePage.html");
?>
