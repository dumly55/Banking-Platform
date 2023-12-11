<?php
    session_start();
    if ($_SESSION['e_logged_in'] == TRUE) header("Location: employee.php");
    else if ($_SESSION['logged_in'] == TRUE) header("Location: user.php");
    else {
        $_SESSION['e_forgot'] = true;
        header("Location: forgot_pass.php");
    }
?>