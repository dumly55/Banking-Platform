<?php 
    session_start();
    if(isset($_SESSION['TFA']) == FALSE || $_SESSION['TFA'] == FALSE) header("Location: HomePage.html");
    else {
        $conn = mysqli_connect("localhost", "root", "", "users");
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        $digits = 6;
        $code =  sprintf("%06d", mt_rand(0, 999999));
        $_SESSION['body'] = "Your multifactor authentication code is: $code";
        $_SESSION['subject'] = "Multi-Factor Authentication";
        if ($_SESSION['Last_Location'] == "authentication.php" || $_SESSION['logged_in'] == TRUE) {
            $username = $_SESSION['username'];
            $sql = "UPDATE registrations SET TFAcode='$code' WHERE username = '$username'";
            $results = mysqli_query($conn, $sql);
        }
        else if ($_SESSION['Last_Location'] == "employee_authentication.php" || $_SESSION['e_logged_in'] == TRUE) {
            $id = $_SESSION['id'];
            $sql = "UPDATE Employees SET TFAcode='$code' WHERE employee_id = '$id'";
            $results = mysqli_query($conn, $sql);
        }
        else if (($_SESSION['e_forgot'] == TRUE || $_SESSION['u_forgot'] == TRUE) && $_SESSION['Last_Location'] == "forgot_process.php") {
            $email = $_SESSION['email'];
            if ($_SESSION['e_forgot'] == TRUE) {
                $sql = "UPDATE Employees SET TFAcode='$code' WHERE email = '$email'";
                $results = mysqli_query($conn, $sql);
            }
            else {
                $sql = "UPDATE registrations SET TFAcode='$code' WHERE email = '$email'";
                $results = mysqli_query($conn, $sql);
            }
        }
        header("Location: send-email.php");
    }
?>
