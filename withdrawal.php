<?php
    session_start();
    if(isset($_SESSION['logged_in']) == FALSE) header("Location: Login.php");
    else if ($_SESSION['TFA'] == TRUE) header("Location: MultiFactor.php");
    else if ($_SESSION['TFA'] == FALSE && $_SESSION['logged_in'] == FALSE) header("Location: Login.php");
    else if (time() - $_SESSION['time'] > 600) header("Location: Logout.php");
    else if (isset($_POST["acc"]) && isset($_POST["pin"]) && isset($_POST["amount"])) {
        $_SESSION['time'] = time();
        $acc = $_POST["acc"];
        $input_pin = $_POST["pin"];
        $amount = $_POST["amount"];
        $username = $_SESSION['username'];
        $conn = mysqli_connect("localhost", "root", "", "users");
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        $sql = "SELECT pin FROM registrations WHERE username = '$username'";
        $result = mysqli_query($conn, $sql);
        $pin = mysqli_fetch_assoc($result);
        if ($input_pin == $pin['pin']) {
            $sql = "SELECT Balance FROM BankAccounts WHERE accountNum = '$acc'";
            $result = mysqli_query($conn, $sql);
            $balance = mysqli_fetch_assoc($result);
            if ($balance['Balance'] < $amount) echo "<script>alert('Overdraft: You do not have enough money for that withdrawal.');window.location.href='card_selection.php';</script>";
            else {
                $description = "Withdrawal";
                $old = $balance['Balance'];
                $new_amount = $balance['Balance'] - $amount;
                $sql = "UPDATE BankAccounts SET Balance = '$new_amount' WHERE accountNum = '$acc'";
                $result = mysqli_query($conn, $sql);
                date_default_timezone_set('America/Los_Angeles');
                $date = date("m/d/Y");
                $time = date("h:i:sa");
                $mtime = date("H:i:s");
                $year = (date("Y") - 1008) * pow(10, 8);
                $num = $year + rand(0, 99999999);
                $sql="SELECT * FROM Transactions WHERE transaction_num='$num'";
                $result = mysqli_query($conn, $sql);
                $duplicate = mysqli_num_rows($result);
                $count = 0;
                while ($duplicate > 0) {
                    if ($count == 1000) {
                        $year = $year + 1;
                        $count = 0;
                    }
                    $num = $year + rand(0, 99999999);
                    $sql="SELECT * FROM Transactions WHERE transaction_num='$num'";
                    $result = mysqli_query($conn, $sql);
                    $duplicate = mysqli_num_rows($result);
                    $count = $count + 1;
                }
                $sql = "INSERT INTO Transactions (transaction_num, accountNum, date_occured, time_occured, transaction_type, transaction_status, amount, location, old_balance, Description, mil_time) VALUES ('$num', '$acc', '$date', '$time', 'Withdrawal', 'Processed', '-$amount', '$acc', '$old', '$description', '$mtime')";
                $result = mysqli_query($conn, $sql);
                echo "<script>alert('Withdrawal Successful');window.location.href='card_selection.php';</script>";
            }
        }
        else echo "<script>alert('Incorrect pin.');window.location.href='card_selection.php';</script>";
        mysqli_close($conn);
    }
    else header("Location: card_selection.php");
?>
