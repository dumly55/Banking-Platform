<?php
    session_start();
    if(isset($_SESSION['logged_in']) == FALSE) header("Location: Login.php");
    else if (isset($_POST['account']) == FALSE) echo "<script>alert('Error: No accounts to deposit into.');window.location.href='user.php';</script>";
    else if ($_SESSION['TFA'] == TRUE && $_SESSION['logged_in'] == FALSE) header("Location: MultiFactor.php");
    else if ($_SESSION['TFA'] == FALSE && $_SESSION['logged_in'] == FALSE) header("Location: Login.php");
    else if (time() - $_SESSION['time'] > 600) header("Location: Logout.php");
    else if (isset($_FILES['frontCheck']) && isset($_FILES['backCheck']) && isset($_POST['account'])) {
        $_SESSION['time'] = time();
        $phpFileUploadErrors = array(
            0 => 'There is no error, the file uploaded with success',
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            3 => 'The uploaded file was only partially uploaded',
            4 => 'Error: No file was uploaded',
            6 => 'Missing a temporary folder',
            7 => 'Failed to write file to disk.',
            8 => 'A PHP extension stopped the file upload.',
        );

        $ext_error = false;
        // a list of extensions that we allow to be uploaded 
        $extensions = array('jpg','jpeg','gif','png');
        $file_ext1 = explode('.', $_FILES['frontCheck']['name']);
        $file_ext1 = end($file_ext1);
        $file_ext2 = explode('.', $_FILES['backCheck']['name']);
        $file_ext2 = end($file_ext2);
        if (!in_array($file_ext1, $extensions) || !in_array($file_ext2, $extensions)) {
            $ext_error = true;
        }

        if ($_FILES['frontCheck']['error']) {
            if ($_FILES['frontCheck']['error'] == '4') {
                echo "<div class='alert alert-danger'>
                <strong>Error!</strong> At least one file was not uploaded.
            </div>";
            }
            else {
            echo $phpFileUploadErrors[$_FILES['frontCheck']['error']];
            }
        }
        elseif ($_FILES['backCheck']['error']) {
            if ($_FILES['backCheck']['error'] == '4') {
                echo "<div class='alert alert-danger'>
                <strong>Error!</strong> At least one file was not uploaded.
            </div>";
            }
            else {
            echo $phpFileUploadErrors[$_FILES['backCheck']['error']];
            }
        }
        elseif ($ext_error) {
            echo "<div class='alert alert-danger'>
            <strong>Error!</strong> At least one file is an unsupported type. Please upload files of the following type only: (.jepg .jpg .png .gif)
        </div>";
        }
        else {
            $file_name_complete =  $_FILES['frontCheck']['name'];
            $extension = pathinfo($file_name_complete, PATHINFO_EXTENSION);
            $file_name = pathinfo($file_name_complete, PATHINFO_FILENAME);
            $file_temp_location =  $_FILES['frontCheck']['tmp_name'];
            $file_name_original = $file_name;
            $num = 1;
            while (file_exists("images/" . $_SESSION['username'] . "/" . "deposits/" . $file_name . "." . $extension)) {
                $file_name = (string) $file_name_original . " (" . $num . ")";
                $file_name_complete = $file_name . "." . $extension;
                $num++;
            }
            $file_target_location_front = "images/" . $_SESSION['username'] . "/" . "deposits/" . $file_name_complete;
            move_uploaded_file($file_temp_location, $file_target_location_front);
            $file_name_complete =  $_FILES['backCheck']['name'];
            $extension = pathinfo($file_name_complete, PATHINFO_EXTENSION);
            $file_name = pathinfo($file_name_complete, PATHINFO_FILENAME);
            $file_temp_location =  $_FILES['backCheck']['tmp_name'];
            $file_name_original = $file_name;
            $num = 1;
            while (file_exists("images/" . $_SESSION['username'] . "/" . "deposits/" . $file_name . "." . $extension)) {
                $file_name = (string) $file_name_original . " (" . $num . ")";
                $file_name_complete = $file_name . "." . $extension;
                $num++;
            }
            $file_target_location_back = "images/" . $_SESSION['username'] . "/" . "deposits/" . $file_name_complete;
            move_uploaded_file($file_temp_location, $file_target_location_back);
        }
        if (!$ext_error) {
            $conn = mysqli_connect("localhost", "root", "", "users");
            if (!$conn) {
                die("Connection failed: " . mysqli_connect_error());
            }
            if (isset($_POST['submit_button'])) {
                $conn = mysqli_connect("localhost", "root", "", "users");
                if (!$conn) {
                    die("Connection failed: " . mysqli_connect_error());
                }
                $accountNum = $_POST['account'];
                $amount = $_POST['amount'];
                $sql = "SELECT Balance FROM BankAccounts WHERE accountNum = '$accountNum'";
                $result = mysqli_query($conn, $sql);
                $balance = mysqli_fetch_assoc($result);
                $old_bal = $balance['Balance'];
                date_default_timezone_set('America/Los_Angeles');
                $date = date("m/d/Y");
                $time = date("h:i:sa");
                $mtime = date("H:i:s");
                $year = (date("Y") - 1011) * pow(10, 8);
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
                $sql = "INSERT INTO Transactions (transaction_num, accountNum, date_occured, time_occured, transaction_type, transaction_status, amount, location, old_balance, Description, mil_time) VALUES ('$num', '$accountNum', '$date', '$time', 'Deposit', 'Processing', '$amount', '$accountNum', '$old_bal', 'Online Deposit', '$mtime')";
                $result = mysqli_query($conn, $sql);
                $frontcheck = $file_target_location_front;
                $backcheck = $file_target_location_back;
                $sql = "INSERT INTO deposits (transaction_num, accountNum, username, amount, frontcheck, backcheck, date, time) VALUES ('$num', '$accountNum', '$username', '$amount', '$frontcheck', '$backcheck', '$date', '$mtime')";
                $results = mysqli_query($conn, $sql);
                if ($results) {
                    echo "<div class='alert alert-success'>
                    <strong>Success!</strong> Your deposit is being processed.
                </div>";
                }
                else {
                    echo mysqli_error($conn);
                }
            } 
        }
        mysqli_close($conn);
    }
    else header("Location: user.php")
?>
<!DOCTYPE html>
<html lang="en">  
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>depositprocess</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="depositprocess.css" />
</head>
<body>
    <br>
    <form action="deposit.php" style="display: inline-block;">
        <button type="submit">Deposit another check</button>
    </form>
    <form action="user.php" style="display: inline-block;">
        <button type="submit">Back to Homepage</button>
    </form>
    <div class="popup" id ="popup-3">
        <div class="overlay"></div>
        <div class="content">
            <p class="timertext" style="font-size: 1.5rem;"> 
                <span class="secs"></span>
                <br>
                <button onclick="stay()">Stay Logged In</button>
                <button onclick="logout()">Logout</button>
            </p> 
                <script type="text/javascript"> 
                    var currSeconds = 0; 
                    var popUpInactive = true;

                    $(document).ready(function() { 
                        let idleInterval = 
                            setInterval(timerIncrement, 1000); 
                            document.addEventListener("wheel", function (e) {
                                var oldVal = parseInt(document.getElementById("body").style.transform.replace("translateY(","").replace("px)",""));
                                var variation = parseInt(e.deltaY);
                                document.getElementById("body").style.transform = "translateY(" + (oldVal - variation) + "px)";
                                return false;
                            }, true);
                            $(this).on("mousemove keypress keyup keypress keydown touchstart click dblclick scroll wheel load unload", function() {
                                if(popUpInactive) resetTimer();
                            })
                    });

                    function resetTimer() { 
                        document.querySelector(".timertext") 
                            .style.display = 'none'; 

                        currSeconds = 0; 
                    } 

                    function logout() {
                        window.location.href='Logout.php';
                    }

                    function stay() {
                        popUpInactive = true;
                        resetTimer();
                        document.getElementById("popup-3").classList.toggle("active");
                    }

                    function timerIncrement() { 
                        currSeconds = currSeconds + 1;
                        left = 600 - currSeconds;
                        dSeconds = left % 60;
                        Minutes = Math.trunc(left/60); 
                        if (dSeconds < 10) dSeconds = "0" + dSeconds;
                        if (currSeconds == 300) {
                            popUpInactive = false;
                            document.getElementById("popup-3").classList.toggle("active");
                        }
                        if (currSeconds >= 300 && left >= 0) {
                            document.querySelector(".secs") 
                            .textContent = "Due to inactivity, you will be logged out in " + Minutes + ":" + dSeconds; 
                            document.querySelector(".timertext") 
                                .style.display = 'block'; 
                        }
                        if (currSeconds == 600) logout();
                    } 
            </script>
        </div>
    </div>
</body>
</html>
