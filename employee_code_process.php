<?php 
    if (isset($_POST["employee_id"])) {
        $id = $_POST["employee_id"];
        $conn = mysqli_connect("localhost", "root", "", "users");
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        $sql = "SELECT status FROM Employees WHERE employee_id='$id'";
        $result = mysqli_query($conn, $sql);
        $info = mysqli_fetch_assoc($result);
        if ($info["status"] == "Pending") {
            session_start();
            $_SESSION['id'] = $id;
            $_SESSION['new_e_token'] = TRUE;
            header("Location: employee_register.php");
        }
        else {
            echo "<script>alert('Invalid Employee ID');window.location.href='employee_register_code.php';</script>";
        }
        mysqli_close($conn);
    }
    else header("Location: EmployeeLogin.php");
?>