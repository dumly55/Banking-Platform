<?php
    session_start();
    if(isset($_SESSION['logged_in']) == FALSE) header("Location: Login.php");
    else if (time() - $_SESSION['time'] > 600) header("Location: Logout.php");
    else if (isset($_POST['account']) && isset($_POST['recipient']) && isset($_POST['amount'])) {
        $_SESSION['time'] = time();
        if ($_POST['account'] && $_POST['recipient'] && $_POST['amount']) {
            $_SESSION['Last_Location'] = "transfer.php";
            $username = $_SESSION['username'];
            $conn = mysqli_connect("localhost", "root", "", "users");
            if (!$conn) {
                die("Connection failed: " . mysqli_connect_error());
            }
            $account = $_POST['account'];
            $recipient = $_POST['recipient'];
            $sql="SELECT * FROM BankAccounts WHERE AccountNum='$recipient'";
            $result = mysqli_query($conn, $sql);
            $existence = mysqli_num_rows($result);
            if ($existence == 0) echo "<script>alert('Recipient account does not exist.');window.location.href='user.php';</script>";
            else {
                $amount = $_POST['amount'];
                $sql = "SELECT Balance FROM BankAccounts WHERE accountNum = '$account'";
                $result = mysqli_query($conn, $sql);
                $bal = mysqli_fetch_assoc($result);
                $old_sender = $bal['Balance'];
                $bal['Balance'] = $bal['Balance'] - $amount;
                $nbal = $bal['Balance'];
                $sql = "SELECT username FROM BankAccounts WHERE accountNum = '$recipient'";
                $result = mysqli_query($conn, $sql);
                $recipientUser = mysqli_fetch_assoc($result);
                $otherUser = $recipientUser['username'];
                if ($nbal < 0) {
                    mysqli_close($conn);
                    echo "<script>alert('Overdraft: Inadequate Balance in Sender Account to Perform Transfer');window.location.href='user.php';</script>";
                } 
                else if ($account == $recipient) {
                    mysqli_close($conn);
                    echo "<script>alert('You cannot transfer money to and from the same account.');window.location.href='user.php';</script>";
                } 
                else if ($username == $otherUser) {
                    $sql = "UPDATE BankAccounts SET Balance = '$nbal' WHERE accountNum = '$account'";
                    $result = mysqli_query($conn, $sql);
                    $sql = "SELECT Balance FROM BankAccounts WHERE accountNum = '$recipient'";
                    $result = mysqli_query($conn, $sql);
                    $bal = mysqli_fetch_assoc($result);
                    $old_recipient = $bal['Balance'];
                    $bal['Balance'] = $bal['Balance'] + $amount;
                    $nbal = $bal['Balance'];
                    $sql = "UPDATE BankAccounts SET Balance = '$nbal' WHERE accountNum = '$recipient'";
                    $result = mysqli_query($conn, $sql);
                    $description_sender = "Transfer to Account Number: " . $account;
                    $description_recipient = "Transfer from Account Number: " . $recipient;
                    date_default_timezone_set('America/Los_Angeles');
                    $date = date("m/d/Y");
                    $time = date("h:i:sa");
                    $mtime = date("H:i:s");
                    $year = (date("Y") - 1001) * pow(10, 8);
                    $num_sender = $year + rand(0, 99999999);
                    $sql="SELECT * FROM Transactions WHERE transaction_num='$num_sender'";
                    $result = mysqli_query($conn, $sql);
                    $duplicate = mysqli_num_rows($result);
                    $count = 0;
                    while ($duplicate > 0) {
                        if ($count == 1000) {
                            $year = $year + 1;
                            $count = 0;
                        }
                        $num_sender = $year + rand(0, 99999999);
                        $sql="SELECT * FROM Transactions WHERE transaction_num='$num_sender'";
                        $result = mysqli_query($conn, $sql);
                        $duplicate = mysqli_num_rows($result);
                        $count = $count + 1;
                    }
                    $num_recipient = $year + rand(0, 99999999);
                    $sql="SELECT * FROM Transactions WHERE transaction_num='$num_recipient'";
                    $result = mysqli_query($conn, $sql);
                    $duplicate = mysqli_num_rows($result);
                    $count = 0;
                    while ($duplicate > 0) {
                        if ($count == 1000) {
                            $year = $year + 1;
                            $count = 0;
                        }
                        $num_recipient = $year + rand(0, 99999999);
                        $sql="SELECT * FROM Transactions WHERE transaction_num='$num_recipient'";
                        $result = mysqli_query($conn, $sql);
                        $duplicate = mysqli_num_rows($result);
                        $count = $count + 1;
                    }
                    $sql = "INSERT INTO Transactions (transaction_num, accountNum, date_occured, time_occured, transaction_type, transaction_status, amount, location, old_balance, Description, mil_time, corresponding_transaction) VALUES ('$num_sender', '$account', '$date', '$time', 'Transfer', 'Processed', '-$amount', '$recipient', '$old_sender', '$description_sender', '$mtime', '$num_recipient')";
                    $result = mysqli_query($conn, $sql);
                    $sql = "INSERT INTO Transactions (transaction_num, accountNum, date_occured, time_occured, transaction_type, transaction_status, amount, location, old_balance, Description, mil_time, corresponding_transaction) VALUES ('$num_recipient', '$recipient', '$date', '$time', 'Transferee', 'Processed', '$amount', '$account', '$old_recipient', '$description_recipient', '$mtime', '$num_sender')";
                    $result = mysqli_query($conn, $sql);
                    mysqli_close($conn);
                    header("Location: user.php");
                }
                else {
                    $_SESSION['account'] = $account;
                    $_SESSION['recipient'] = $recipient;
                    $_SESSION['nbal'] = $nbal;
                    $_SESSION['amount'] = $amount;
                    $_SESSION['old_sender'] = $old_sender;
                    $_SESSION['TFA'] = TRUE;
                    header("Location: GenerateCode.php");
                }
            }
        }
        else header("Location: user.php");
    }
    else if ($_SESSION['TFA_Token'] == TRUE && $_SESSION['Last_Location'] == "transfer.php") {
        $account = $_SESSION['account'];
        $recipient = $_SESSION['recipient'];
        $nbal = $_SESSION['nbal'];
        $amount = $_SESSION['amount'];
        $old_sender = $_SESSION['old_sender'];
        $_SESSION['TFA'] = FALSE;
        $_SESSION['TFA_Token'] = FALSE;
        $description_sender = "Transfer to Account Number: " . $account;
        $description_recipient = "Transfer from Account Number: " . $recipient;
        $conn = mysqli_connect("localhost", "root", "", "users");
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        $sql = "UPDATE BankAccounts SET Balance = '$nbal' WHERE accountNum = '$account'";
        $result = mysqli_query($conn, $sql);
        $sql = "SELECT Balance FROM BankAccounts WHERE accountNum = '$recipient'";
        $result = mysqli_query($conn, $sql);
        $bal = mysqli_fetch_assoc($result);
        $old_recipient = $bal['Balance'];
        $bal['Balance'] = $bal['Balance'] + $amount;
        $nbal = $bal['Balance'];
        $sql = "UPDATE BankAccounts SET Balance = '$nbal' WHERE accountNum = '$recipient'";
        $result = mysqli_query($conn, $sql);
        date_default_timezone_set('America/Los_Angeles');
        $date = date("m/d/Y");
        $time = date("h:i:sa");
        $mtime = date("H:i:s");
        $year = (date("Y") - 1001) * pow(10, 8);
        $num_sender = $year + rand(0, 99999999);
        $sql="SELECT * FROM Transactions WHERE transaction_num='$num_sender'";
        $result = mysqli_query($conn, $sql);
        $duplicate = mysqli_num_rows($result);
        $count = 0;
        while ($duplicate > 0) {
            if ($count == 1000) {
                $year = $year + 1;
                $count = 0;
            }
            $num_sender = $year + rand(0, 99999999);
            $sql="SELECT * FROM Transactions WHERE transaction_num='$num_sender'";
            $result = mysqli_query($conn, $sql);
            $duplicate = mysqli_num_rows($result);
            $count = $count + 1;
        }
        $num_recipient = $year + rand(0, 99999999);
        $sql="SELECT * FROM Transactions WHERE transaction_num='$num_recipient'";
        $result = mysqli_query($conn, $sql);
        $duplicate = mysqli_num_rows($result);
        $count = 0;
        while ($duplicate > 0) {
            if ($count == 1000) {
                $year = $year + 1;
                $count = 0;
            }
            $num_recipient = $year + rand(0, 99999999);
            $sql="SELECT * FROM Transactions WHERE transaction_num='$num_recipient'";
            $result = mysqli_query($conn, $sql);
            $duplicate = mysqli_num_rows($result);
            $count = $count + 1;
        }
        $sql = "INSERT INTO Transactions (transaction_num, accountNum, date_occured, time_occured, transaction_type, transaction_status, amount, location, old_balance, Description, mil_time, corresponding_transaction) VALUES ('$num_sender', '$account', '$date', '$time', 'Transfer', 'Processed', '-$amount', '$recipient', '$old_sender', '$description_sender', '$mtime', '$num_recipient')";
        $result = mysqli_query($conn, $sql);
        $sql = "INSERT INTO Transactions (transaction_num, accountNum, date_occured, time_occured, transaction_type, transaction_status, amount, location, old_balance, Description, mil_time, corresponding_transaction) VALUES ('$num_recipient', '$recipient', '$date', '$time', 'Transferee', 'Processed', '$amount', '$account', '$old_recipient', '$description_recipient', '$mtime', '$num_sender')";
        $result = mysqli_query($conn, $sql);
        mysqli_close($conn);
        header("Location: user.php");
    }
    else header("Location: user.php");
?>
