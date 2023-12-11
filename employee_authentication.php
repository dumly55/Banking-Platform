<?php 
    session_start();
    if (isset($_POST["employee_id"]) && isset($_POST["password"])) {
        $_SESSION['Last_Location'] = "employee_authentication.php";
        $id = $_POST["employee_id"];
        $password = $_POST["password"];
        $conn = mysqli_connect("localhost", "root", "", "users");
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        $sql = "SELECT password, status, email, tier FROM Employees WHERE employee_id = '$id'";
        $results = mysqli_query($conn, $sql);
        if ($results) {
            $row = mysqli_fetch_assoc($results);
            if ($row["password"] === $password && $row["status"] == "Hired") {
                $_SESSION['e_logged_in'] = FALSE;
                $_SESSION['id'] = $id;
                $_SESSION['TFA'] = TRUE;
                $_SESSION['tier'] = $row["tier"];
                $_SESSION['email'] = $row["email"];
                echo "<script>window.location.href='GenerateCode.php';</script>";
            } 
            else if ($row["status"] == "Terminated") {
                echo "<script>alert('Sorry. You are no longer with the company.');window.location.href='Logout.php';</script>";
            }
            else {
                echo "<script>alert('Login Failed');window.location.href='Logout.php';</script>";
            }
        } 
        else {
            echo mysqli_error($conn);
        }
        mysqli_close($conn); 
    }
    else if ($_SESSION['TFA_Token'] == TRUE && $_SESSION['Last_Location'] == "employee_authentication.php") {
        $_SESSION['e_logged_in'] = TRUE;
        $_SESSION['TFA_Token'] = FALSE;
        $_SESSION['time'] = time();
        header("Location: update.php");
    }
    else {
        header("Location: EmployeeLogin.php");
    }
?>
