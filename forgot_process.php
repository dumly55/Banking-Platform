<?php 
    session_start();
    if ($_SESSION['e_logged_in'] == TRUE) header("Location: employee.php");
    else if ($_SESSION['logged_in'] == TRUE) header("Location: user.php");
    else if (isset($_POST["email"]) && (isset($_SESSION['e_forgot']) || isset($_SESSION['u_forgot']))) {
        $_SESSION['email'] = $_POST["email"];
        $email = $_POST["email"];
        $conn = mysqli_connect("localhost", "root", "", "users");
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        if ($_SESSION['e_forgot'] == TRUE && isset($_POST["user"])) {
            $sql = "SELECT employee_id FROM Employees WHERE email='$email'";
            $result = mysqli_query($conn, $sql);
            $info = mysqli_fetch_assoc($result);
            $_SESSION['body'] = "Your employee id is: " . $info["employee_id"];
            $_SESSION['subject'] = "Forgot Employee ID";
            echo "<script>alert('An email with your Employee ID in it will be sent if that email is registered.');window.location.href='send-email.php';</script>";
        }
        else if ($_SESSION['u_forgot'] == TRUE && isset($_POST["user"])) {
            $sql = "SELECT username FROM registrations WHERE email='$email'";
            $result = mysqli_query($conn, $sql);
            $info = mysqli_fetch_assoc($result);
            $_SESSION['body'] = "Your username is: " . $info["username"];
            $_SESSION['subject'] = "Forgot Username";
            echo "<script>alert('An email with your username in it will be sent if that email is registered.');window.location.href='send-email.php';</script>";
        }
        else if (($_SESSION['e_forgot'] == TRUE || $_SESSION['u_forgot'] == TRUE) && isset($_POST["pass"])) {
            $_SESSION['Last_Location'] = "forgot_process.php";
            $_SESSION['TFA'] = TRUE;
            header("Location: GenerateCode.php");
        }
    }
    else if (($_SESSION['e_forgot'] == TRUE || $_SESSION['u_forgot'] == TRUE) && $_SESSION['TFA_Token'] && $_POST["password"]) {
        $conn = mysqli_connect("localhost", "root", "", "users");
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        $pass = $_POST["password"];
        $email = $_SESSION['email'];
        if ($_SESSION['e_forgot'] == TRUE) {
            $sql = "UPDATE Employees SET password='$pass' WHERE email='$email'";
            $result = mysqli_query($conn, $sql);
        }
        else if ($_SESSION['u_forgot'] == TRUE) {
            $sql = "UPDATE registrations SET password='$pass' WHERE email='$email'";
            $result = mysqli_query($conn, $sql);
        }
        echo "<script>alert('Password Reset Successful');window.location.href='Logout.php';</script>";
    }
    else header("Location: HomePage.html");
?>