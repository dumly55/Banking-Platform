<?php
    session_start();
    if (time() - $_SESSION['time'] > 600) header("Location: Logout.php");
    else if (isset($_POST["employee"])) {
        $_SESSION['time'] = time();
        $conn = mysqli_connect("localhost", "root", "", "users");
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        $id = $_SESSION['id'];
        $sql = "SELECT status FROM Employees WHERE employee_id='$id'";
        $result = mysqli_query($conn, $sql);
        $status = mysqli_fetch_assoc($result);
        if ($status["status"] == "Terminated") header("Location: Logout.php");
        else {
            $employee = $_POST["employee"];
            $sql = "UPDATE Employees SET status=\"Terminated\" WHERE employee_id='$employee'";
            $result = mysqli_query($conn, $sql);
            header("Location: employee.php");
        }
    }
    else header("Location: employee.php");
?>