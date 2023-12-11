<?php
    session_start();
    if (isset($_SESSION['TFA']) == TRUE) {
        if ($_SESSION['TFA'] == TRUE) {
            $_SESSION['TFA'] = FALSE;
            $location = $_SESSION['Last_Location'];
            header("Location: $location");
        }
    }
    else if ($_SESSION['logged_in'] == TRUE) {
        if (time() - $_SESSION['time'] > 600) header("Location: Logout.php");
        else {
            $_SESSION['time'] = time();
            header("Location: user.php");
        }
    } 
    else if ($_SESSION['e_logged_in'] == TRUE) {
        if (time() - $_SESSION['time'] > 600) header("Location: Logout.php");
        else {
            $_SESSION['time'] = time();
            header("Location: employee.php");
        }
    }
    else if (isset($_SESSION["new_e_token"]) && $_SESSION["new_e_token"] == TRUE) {
        $_SESSION["new_e_token"] = FALSE;
        header("Location: employee_register.php");
    }
    else header("Location: Login.php");
?>
