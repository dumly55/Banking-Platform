<?php 
    session_start();
    if ($_SESSION['logged_in'] == TRUE || $_SESSION['e_logged_in'] == TRUE) {
        $conn = mysqli_connect("localhost", "root", "", "users");
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        date_default_timezone_set('America/Los_Angeles');
        $date = date("m/d/Y");
        $sql = "SELECT * FROM BankAccounts WHERE last_update < '$date'";
        $results = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($results)) {
            $account = $row["accountNum"];
            $balance = $row["Balance"];
            $li = $row["last_increment"];
            $interest = $row["interest"];
            while (strtotime($li)<strtotime('-1 year')) {
                $balance = $balance + $balance * $interest;
                $li = date('m/d/Y', strtotime('+1 year', strtotime($li)));
            }
            $balance = round($balance, 2);
            $sql = "UPDATE BankAccounts SET Balance='$balance', last_update='$date', last_increment='$li' WHERE accountNum='$account'";
            $result = mysqli_query($conn, $sql);
        }
        if (isset($_POST["account"])) {
            $account = $_POST["account"];
            echo "<form id=\"acc\" action=\"account_info.php\" method=\"post\"><input type=\"hidden\" name=\"account\" value=\"$account\"></form>";
            echo    "<script type=\"text/javascript\"> 
                        window.onload=function(){
                            document.forms['acc'].submit();
                        }
                    </script>";
        }
        else if ($_SESSION['logged_in'] == TRUE) header("Location: user.php");
        else if ($_SESSION['e_logged_in'] == TRUE) header("Location: employee.php");
    }
    else header("Location: HomePage.html");
?>