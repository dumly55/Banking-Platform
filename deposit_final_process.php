<?php 
    session_start();
    if (time() - $_SESSION['time'] > 600) header("Location: Logout.php");
    else if (isset($_POST["account_num"]) && isset($_POST["decision"]) && isset($_POST["transaction_num"]) && isset($_POST["amount"])) {
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
            $account = $_POST["account_num"];
            $decision = $_POST["decision"];
            $transaction = $_POST["transaction_num"];
            date_default_timezone_set('America/Los_Angeles');
            $date = date("m/d/Y");
            $time = date("h:i:sa");
            $mtime = date("H:i:s");
            $amount = $_POST["amount"];
            $sql = "SELECT Balance FROM BankAccounts WHERE accountNum='$account'";
            $result = mysqli_query($conn, $sql);
            $info = mysqli_fetch_assoc($result);
            $obal = $info["Balance"];
            if ($decision == "approve") {
                $bal = $obal + $amount;
                $sql = "UPDATE Transactions SET date_occured='$date', time_occured='$time', transaction_status='Processed', old_balance='$obal', mil_time='$mtime' WHERE transaction_num='$transaction'";
                $result = mysqli_query($conn, $sql);
                $sql = "UPDATE BankAccounts SET Balance='$bal' WHERE accountNum='$account'";
                $result = mysqli_query($conn, $sql);
                $sql = "DELETE FROM deposits WHERE transaction_num='$transaction'";
                $result = mysqli_query($conn, $sql);
            }
            else if ($decision == "deny") {
                $sql = "UPDATE Transactions SET date_occured='$date', time_occured='$time', transaction_status='Denied', mil_time='$mtime', old_balance='$obal' WHERE transaction_num='$transaction'";
                $result = mysqli_query($conn, $sql);
                $sql = "DELETE FROM deposits WHERE transaction_num='$transaction'";
                $result = mysqli_query($conn, $sql);
            }
            header("Location: pending_deposits.php");
        }
    }
    else header("Location: employee.php");
?>
