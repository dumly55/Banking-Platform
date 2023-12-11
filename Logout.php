<?php
    session_start();
    if ($_SESSION["e_logged_in"] == TRUE || $_SESSION['e_forgot'] == TRUE || $_SESSION['Last_Location'] == "employee_authentication.php") $location = "EmployeeLogin.php";
    else if ($_SESSION["logged_in"] == TRUE || $_SESSION['u_forgot'] == TRUE || $_SESSION['Last_Location'] == "authentication.php") $location = "Login.php";
    else $location = "HomePage.html";
    unset($_SESSION['logged_in'], $_SESSION['TFA'], $_SESSION['username'], $_SESSION['Last_Location'], $_SESSION['account'], $_SESSION['recipient'], $_SESSION['nbal'], $_SESSION['amount'], $_SESSION['old_sender'], $_SESSION['e_logged_in'], $_SESSION['new_e_token'], $_SESSION['id'], $_SESSION['time'], $_SESSION['tier'], $_SESSION['email'], $_SESSION['u_forgot'], $_SESSION['e_forgot'], $_SESSION['body'], $_SESSION['subject']);
    session_destroy();
    header("Location: $location");
    exit;
?>
