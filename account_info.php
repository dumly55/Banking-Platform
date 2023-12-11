<?php
    session_start();
    if(isset($_SESSION['logged_in']) == FALSE) header("Location: Login.php");
    else if ($_SESSION['TFA'] == TRUE) header("Location: MultiFactor.php");
    else if ($_SESSION['TFA'] == FALSE && $_SESSION['logged_in'] == FALSE) header("Location: Login.php");
    else if (isset($_SESSION['e_logged_in']) && $_SESSION['e_logged_in'] == TRUE) header("Location: employee.php");
    else if (time() - $_SESSION['time'] > 600) header("Location: Logout.php");
    else if (isset($_POST["account"])) {
        $_SESSION['time'] = time();
        $conn = mysqli_connect("localhost", "root", "", "users");
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        $account = $_POST["account"];
        $sql = "SELECT * FROM BankAccounts WHERE accountNum = '$account'";
        $results = mysqli_query($conn, $sql);
        $info = mysqli_fetch_assoc($results);
    }
    else header("Location: user.php");
?>

<html>
    <head>
        <title>Account Info</title>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="account_info.css" />
    </head>
    <body>
        <h1>
            <?php
                print "Account Information";
            ?>
            <div class="lbtn">
                <a href="Logout.php" class="log logbutton" name="logout">Logout</a>
            </div>
            <div class="lbtn">
                <a href="deposit.php" class="log logbutton" name="deposit">Deposit</a>
            </div>
            <div class="lbtn">
                <a href="card_selection.php" class="log logbutton" name="ATM">ATM</a>
            </div>
            <div class="lbtn">
                <a href="user.php" class="log logbutton" name="user_page">Home</a>
            </div>
        </h1>
        <h2><?php print $info["type"] . " (" . $info["accountNum"] . ")"; ?></h2>
        <p id="balance"><?php print "Balance: $" . $info["Balance"];?></p>
        <p id="time_created"><?php print "Date Created: " . $info["dcreated"] . " | Time Created: " . $info["tcreated"];?></p>
        <div class="account">
            <p class="name">Transaction(s)</p><br>
            <?php
                $sql = "SELECT * FROM Transactions WHERE accountNum = '$account' ORDER BY date_occured DESC, mil_time DESC";
                $result = mysqli_query($conn, $sql);
                while ($transactions = mysqli_fetch_assoc($result)) {
                    $amount = strval($transactions["amount"]);
                    substr_replace($amount , "$" , 1 , 0 );
                    echo "<a class=\"acc\">";
                    echo "<span class=\"bal\">" . "$" .$amount . "</span>";
                    echo "<span class=\"description\">" . $transactions["Description"] . "</span>";
                    if ($transactions["transaction_status"] == "Processed") {
                        echo "<span class=\"status\" style=\"color:green\">" . $transactions["transaction_status"] . "</span>";
                    }
                    else if ($transactions["transaction_status"] == "Denied") {
                        echo "<span class=\"status\" style=\"color:red\">" . $transactions["transaction_status"] . "</span>";
                    }
                    else {
                        echo "<span class=\"status\" style=\"color:gray\">" . $transactions["transaction_status"] . "</span>";
                    }
                    echo "<span class=\"date\">" . "Date: " . $transactions["date_occured"] . " | Time: " . $transactions["time_occured"] . "</span>";
                    echo "<span class=\"obal\">" . "$" . $transactions["old_balance"] . "</span>";
                    echo "<span class=\"tnum\">" . "Transaction #: " . $transactions["transaction_num"] . "</span>";
                    echo "</a><br>";
                }
            ?>
            <br>
        </div>
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
