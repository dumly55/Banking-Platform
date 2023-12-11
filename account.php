<?php 
        session_start();
        if(isset($_SESSION['logged_in']) == FALSE || $_SESSION['logged_in'] == FALSE) header("Location: Login.php");
        else if (time() - $_SESSION['time'] > 600) header("Location: Logout.php");
        else {
            $_SESSION['time'] = time();
            $username = $_SESSION['username'];
            if (isset($_POST["acc"])) {
                if ($_POST["acc"]) {
                    $acc = $_POST["acc"];
                    $check = "Checking Account";
                    $save = "Savings Account";
                    date_default_timezone_set('America/Los_Angeles');
                    $date = date("m/d/Y");
                    $time = date("h:i:sa");
                    $mtime = date("H:i:s");
                    $conn = mysqli_connect("localhost", "root", "", "users");
                    if (!$conn) {
                        die("Connection failed: " . mysqli_connect_error());
                    }
                    if ($acc == $check) {
                        $ir = 0.0005;
                        $year = (date("Y") - 1004) * pow(10, 12);
                        $num = $year + rand(0, 999999999999);
                        $sql="SELECT * FROM BankAccounts WHERE accountNum='$num'";
                        $result = mysqli_query($conn, $sql);
                        $duplicate = mysqli_num_rows($result);
                        $count = 0;
                        while ($duplicate > 0) {
                            if ($count == 1000) {
                                $year = $year + 1;
                                $count = 0;
                            }
                            $num = $year + rand(0, 999999999999);
                            $sql="SELECT * FROM BankAccounts WHERE accountNum='$num'";
                            $result = mysqli_query($conn, $sql);
                            $duplicate = mysqli_num_rows($result);
                            $count = $count + 1;
                        }
                        $sql = "INSERT INTO BankAccounts (accountNum, username, Balance, type, interest, dcreated, tcreated, mil_time, last_update, last_increment) VALUES ('$num', '$username', '0', 'Checking Account', '$ir', '$date', '$time', '$mtime', '$date', '$date')";
                        $results = mysqli_query($conn, $sql);
                    }
                    else if ($acc == $save) {
                        $ir = 0.0057;
                        $year = (date("Y") - 1017) * pow(10, 12);
                        $num = $year + rand(0, 999999999999);
                        $sql="SELECT * FROM BankAccounts WHERE accountNum='$num'";
                        $result = mysqli_query($conn, $sql);
                        $duplicate = mysqli_num_rows($result);
                        $count = 0;
                        while ($duplicate > 0) {
                            if ($count == 1000) {
                                $year = $year + 1;
                                $count = 0;
                            }
                            $num = $year + rand(0, 999999999999);
                            $sql="SELECT * FROM BankAccounts WHERE accountNum='$num'";
                            $result = mysqli_query($conn, $sql);
                            $duplicate = mysqli_num_rows($result);
                            $count = $count + 1;
                        }
                        $sql = "INSERT INTO BankAccounts (accountNum, username, Balance, type, interest, dcreated, tcreated, mil_time, last_update, last_increment) VALUES ('$num', '$username', '0', 'Savings Account', '$ir', '$date', '$time', '$mtime', '$date', '$date')";
                        $results = mysqli_query($conn, $sql);
                    }
                    mysqli_close($conn);
                    header("Location: user.php");
                }
                else header("Location: user.php");
            }
            else header("Location: user.php");
        } 
?>
