<?php
    session_start();
    if (time() - $_SESSION['time'] > 600) header("Location: Logout.php");
    else if (isset($_POST["ticket"]) && isset($_POST["action"])) {
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
            $ticket = $_POST["ticket"];
            $action = $_POST["action"];
            if ($action == "resolve") {
                $sql = "UPDATE support SET status=\"Resolved\" WHERE ticket='$ticket'";
                $result = mysqli_query($conn, $sql);
            }
            else if ($action == "reopen") {
                $sql = "UPDATE support SET status=\"Ongoing\" WHERE ticket='$ticket'";
                $result = mysqli_query($conn, $sql);
            }
            header("Location: inquiries.php");
        }
    }
    else header("Location: employee.php");
?>