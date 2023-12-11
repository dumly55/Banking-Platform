<?php
    session_start();
    if(isset($_SESSION['logged_in']) == FALSE) header("Location: Login.php");
    else if ($_SESSION['TFA'] == TRUE) header("Location: MultiFactor.php");
    else if ($_SESSION['TFA'] == FALSE && $_SESSION['logged_in'] == FALSE) header("Location: Login.php");
    else if (isset($_SESSION['e_logged_in']) && $_SESSION['e_logged_in'] == TRUE) header("Location: employee.php");
    else if (time() - $_SESSION['time'] > 600) header("Location: Logout.php");
    else {
        $_SESSION['time'] = time();
        $username = $_SESSION['username'];
        $conn = mysqli_connect("localhost", "root", "", "users");
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        $sql = "SELECT * FROM registrations WHERE username = '$username'";
        $result = mysqli_query($conn, $sql);
        $name = mysqli_fetch_assoc($result);
        $fname = $name['fname'];
        $lname = $name['lname'];
        $sql = "SELECT * FROM BankAccounts WHERE username = '$username' ORDER BY dcreated ASC, mil_time ASC";
        $result = mysqli_query($conn, $sql);
    } 
?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="card_selection.css">
        <script src="card_selection.js"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
        <title>Wallet</title>
    </head>

    <body>
        <h1>
            Choose a Card to Withdrawal Money From:
            <div class="lbtn">
                <a href="Logout.php" class="log logbutton" name="logout">Logout</a>
            </div>
            <div class="lbtn">
                <a href="deposit.php" class="log logbutton" name="deposit">Deposit</a>
            </div>
            <div class="lbtn">
                <a href="user.php" class="log logbutton" name="Home">Home</a>
            </div>
        </h1>
        <br>
        <?php
            echo "<form action=\"withdrawal.php\" method=\"post\">";
                    $num = 1;
                    while ($accounts = mysqli_fetch_assoc($result)) {
                        $accNum = chunk_split($accounts['accountNum'], 4, ' ');
                        echo "<section>
                                <div class=\"container\">
                                    <button name=\"acc\" class=\"card front\" onclick=\"togglePopup(" . $num .")\"";  
                                    if ($accounts['type'] == "Savings Account") echo "style=\"background-image: url(savings_card.jpeg);\"";
                                    else echo "style=\"background-image: url(22.jpg);\"";
                                    echo "type=\"submit\" value=\"" . $accounts["accountNum"] . "\">
                                        <header>
                                            <div class=\"logo\">
                                                <img src=\"piggy.png\" alt=\"\" srcset=\"\">
                                                <h5 id=\"mas\">Bank of the Future Card</h5>
                                            </div>
                                            <img src=\"chip.png\" alt=\"\" class=\"chip\">
                                        </header>
                                        <div class=\"card-details\">
                                            <div class=\"name-number\">
                                                <h6>Card number</h6>
                                                <h5 class=\"number\">" . $accNum . "</h5>
                                                <h5 class=\"name\">" . $fname . " " . $lname . "</h5>
                                            </div>
                                            <div class=\"VALIDDATE\">
                                                <h6 class=\"v\">Valid Thru</h6>
                                                <h6 class=\"v\">##/##/##</h6>
                                            </div>
                    
                                        </div>
                                    </button>
                    
                                    <button name=\"acc\" class=\"card back-face\" type=\"submit\" onclick=\"togglePopup(" . $num .")\""; 
                                    if ($accounts['type'] == "Savings Account") echo "style=\"background-image: url(savings_card.jpeg);\"";
                                    else echo "style=\"background-image: url(22.jpg);\"";
                                    echo "value=\"" . $accounts["accountNum"] . "\">
                                        <h6>
                                            For customer service, please call our support number.
                                        </h6>
                                        <span class=\"strip\"></span>
                    
                                        <div class=\"sign\">
                                            <i>***</i>
                                        </div>
                                        <div class=\"add\">
                                            <div>
                                                <h6 style=\"color: aliceblue;\">" . $accounts['type'] .  " Debit Card
                                                    <br> Account Number:
                                                    <br>" . $accounts['accountNum'] ."
                                                </h6>
                                            </div>
                    
                                        </div>
                                    </button>
                                </div>
                            </section><br>";
                            $num = $num + 1;
                    }
                    $num = 1;
                    $sql = "SELECT * FROM BankAccounts WHERE username = '$username' ORDER BY dcreated ASC, mil_time ASC";
                    $result = mysqli_query($conn, $sql);
                    while ($accounts = mysqli_fetch_assoc($result)) {
                        echo "<div class=\"popup\" id =\"popup-". $num . "\">
                            <div class=\"overlay\"></div>
                            <div class=\"content\">
                                <div class=\"close-btn\" onclick=\"togglePopup(" . $num . ")\">&times;</div>
                                <form action=\"withdrawal.php\" method=\"post\">
                                        <h2 style=\"color: black;\">ATM</h2>
                                        <label style=\"color: black;\" for=\"pin\">Pin</label>
                                        <input type=\"password\" name=\"pin\" minlength=\"4\"  maxlength=\"4\" id=\"pin\" required><br>
                                        <label style=\"color: black;\" for=\"amount\">Amount($):</label>
                                        <input type=\"text\" name=\"amount\" id=\"amount\" required> <br><br>
                                        <script>
                                            $(\"#amount\").on(\"keyup\", function(){
                                                var valid = /^\d+(\.\d{0,2})?$/.test(this.value),
                                                    val = this.value;
                                                
                                                if(!valid){
                                                    console.log(\"Invalid input!\");
                                                    this.value = val.substring(0, val.length - 1);
                                                }
                                            });
                                        </script>
                                        <button class=\"btn\" style=\"color: black;\" name=\"acc\" type=\"submit\" value=\"" . $accounts['accountNum'] . "\">Withdrawal</button>
                                </form>
                            </div>
                        </div>";
                        $num = $num + 1;
                    }
            echo "</form>";
            echo "<img id=\"wallet\" src=\"wallet.webp\">";
            echo "<div class=\"popup\" id =\"popup-" . $num . "\">
                <div class=\"overlay\"></div>
                <div class=\"content\">
                    <p class=\"timertext\" style=\"font-size: 1.5rem;\"> 
                        <span class=\"secs\"></span>
                        <br>
                        <button onclick=\"stay()\">Stay Logged In</button>
                        <button onclick=\"logout()\">Logout</button>
                    </p> 
                        <script type=\"text/javascript\"> 
                            var currSeconds = 0; 
                            var popUpInactive = true;

                            $(document).ready(function() { 
                                let idleInterval = 
                                    setInterval(timerIncrement, 1000); 
                                    document.addEventListener(\"wheel\", function (e) {
                                        var oldVal = parseInt(document.getElementById(\"body\").style.transform.replace(\"translateY(\",\"\").replace(\"px)\",\"\"));
                                        var variation = parseInt(e.deltaY);
                                        document.getElementById(\"body\").style.transform = \"translateY(\" + (oldVal - variation) + \"px)\";
                                        return false;
                                    }, true);
                                    $(this).on(\"mousemove keypress keyup keypress keydown touchstart click dblclick scroll wheel load unload\", function() {
                                        if(popUpInactive) resetTimer();
                                    })
                            });

                            function resetTimer() { 
                                document.querySelector(\".timertext\") 
                                    .style.display = 'none'; 

                                currSeconds = 0; 
                            } 

                            function logout() {
                                window.location.href='Logout.php';
                            }

                            function stay() {
                                popUpInactive = true;
                                resetTimer();
                                document.getElementById(\"popup-" . $num . "\").classList.toggle(\"active\");
                            }

                            function timerIncrement() { 
                                currSeconds = currSeconds + 1;
                                left = 600 - currSeconds;
                                dSeconds = left % 60;
                                Minutes = Math.trunc(left/60); 
                                if (dSeconds < 10) dSeconds = \"0\" + dSeconds;
                                if (currSeconds == 300) {
                                    popUpInactive = false;
                                    document.getElementById(\"popup-" . $num . "\").classList.toggle(\"active\");
                                }
                                if (currSeconds >= 300 && left >= 0) {
                                    document.querySelector(\".secs\") 
                                    .textContent = \"Due to inactivity, you will be logged out in \" + Minutes + \":\" + dSeconds; 
                                    document.querySelector(\".timertext\") 
                                        .style.display = 'block'; 
                                }
                                if (currSeconds == 600) logout();
                            } 
                    </script>
                </div>
            </div>"
        ?>
    </body>
</html>
