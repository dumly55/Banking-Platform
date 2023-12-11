<?php 
    session_start();
    if (isset($_POST["code"]) && isset($_SESSION['TFA']) == TRUE && $_SESSION['TFA'] == TRUE) {
        $conn = mysqli_connect("localhost", "root", "", "users");
        $code = $_POST["code"];
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        if ($_SESSION['Last_Location'] == "authentication.php" || $_SESSION['logged_in'] == TRUE) {
            $username = $_SESSION['username'];
            $sql = "SELECT TFAcode FROM registrations WHERE username = '$username'";
            $results = mysqli_query($conn, $sql);
        }
        else if ($_SESSION['Last_Location'] == "employee_authentication.php" || $_SESSION['e_logged_in'] == TRUE) {
            $id = $_SESSION['id'];
            $sql = "SELECT TFAcode FROM Employees WHERE employee_id = '$id'";
            $results = mysqli_query($conn, $sql);
        }
        else if ($_SESSION['e_forgot'] == TRUE || $_SESSION['u_forgot'] == TRUE) {
            $email = $_SESSION['email'];
            if ($_SESSION['e_forgot'] == TRUE) {
                $sql = "SELECT TFAcode FROM Employees WHERE email = '$email'";
                $results = mysqli_query($conn, $sql);
            }
            else {
                $sql = "SELECT TFAcode FROM registrations WHERE email = '$email'";
                $results = mysqli_query($conn, $sql);
            }
        }
        if ($results) {
            $row = mysqli_fetch_assoc($results);
            if ($row["TFAcode"] == $code) {
                $_SESSION['TFA'] = FALSE;
                $_SESSION['TFA_Token'] = TRUE;
                if ($_SESSION['Last_Location'] == "forgot_process.php") header("Location: reset_pass.php");
                else {
                    $location = $_SESSION['Last_Location'];
                    header("Location: $location");
                }
            } 
            else {
                echo "<script>alert('The code that you entered was invalid.');window.location.href='MultiFactor.php';</script>";
            }
        } 
        else {
            echo mysqli_error($conn);
        }
    }
    else if ($_SESSION['logged_in'] == TRUE && $_SESSION['TFA'] == FALSE) header("Location: user.php");
    else if ($_SESSION['e_logged_in'] == TRUE && $_SESSION['TFA'] == FALSE) header("Location: employee.php");
    else header("Location: HomePage.html");
?>
