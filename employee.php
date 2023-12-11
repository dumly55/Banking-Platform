<?php
    session_start();
    if(isset($_SESSION['e_logged_in']) == FALSE) header("Location: EmployeeLogin.php");
    else if ($_SESSION['TFA'] == TRUE) header("Location: MultiFactor.php");
    else if ($_SESSION['TFA'] == FALSE && $_SESSION['e_logged_in'] == FALSE) header("Location: EmployeeLogin.php");
    else if (isset($_SESSION['e_logged_in']) && $_SESSION['e_logged_in'] == FALSE) header("Employee_Login.php");
    else if (time() - $_SESSION['time'] > 600) header("Location: Logout.php");
    else {
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
    }
?>

<html>
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="employee.css">
        <script defer src="employee.js"></script>
        <script src= "https://code.jquery.com/jquery-3.4.1.min.js"></script>
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
        <title>Employee</title>
    </head>
    <body>
        <h1>
            <a style="color: white;" href="HomePage.html"><i class='bx bx-building-house'></i></a>
            <?php
                $sql = "SELECT * FROM Employees WHERE employee_id = '$id'";
                $results = mysqli_query($conn, $sql);
                $ename = mysqli_fetch_assoc($results);
                $name = $ename["fname"] . " " . $ename["lname"];
                $employee_name = $name;
                print "<span style=\"font-weight: bold;\">Welcome to work, " . $name . "!</span>";
            ?>
            <div class="lbtn">
                <a href="Logout.php" class="log logbutton" name="logout">Logout</a>
            </div>
            <div class="lbtn">
                <a href="Registration.php" class="log logbutton" name="register">New User</a>
            </div>
            <div class="lbtn">
                <?php
                    $sql = "SELECT * FROM deposits";
                    $results = mysqli_query($conn, $sql);
                    $dep = 0;
                    while ($row = mysqli_fetch_assoc($results)) {
                        $dep++;
                    }
                    echo "<a href=\"pending_deposits.php\" class=\"log logbutton\" name=\"deposits\">Pending Deposits ($dep)</a>";
                ?>
            </div>
            <div class="lbtn">
                <?php
                    $sql = "SELECT * FROM support WHERE status='Ongoing'";
                    $results = mysqli_query($conn, $sql);
                    $in = 0;
                    while ($row = mysqli_fetch_assoc($results)) {
                        $in++;
                    }
                    echo "<a href=\"inquiries.php\" class=\"log logbutton\" name=\"inquiries\">Inquiries ($in)</a>";
                ?>
            </div>
            <?php
                if ($_SESSION['tier'] > 1) {
                    echo "<div class=\"lbtn\">";
                    echo "<a onclick=\"togglePopupTwo()\" style=\"cursor: pointer;\" class=\"log logbutton\" name=\"Terminate\">Terminate</a>";
                    echo "</div>";
                    echo "<div class=\"lbtn\">";
                    echo "<a onclick=\"togglePopup()\" style=\"cursor: pointer;\" class=\"log logbutton\" name=\"Hire\">Hire</a>";
                    echo "</div>";
                }
            ?>

        </h1><br>
        <table>
            <thead>
                <th>Account Number</th>
                <th>Owner</th>
                <th>Balance</th>
                <th>Type</th>
            </thead>
            <tbody>
                <?php
                    $sql = "SELECT * FROM BankAccounts ORDER BY Balance DESC";
                    $result = mysqli_query($conn, $sql);
                    $num = 0;
                    while ($accounts = mysqli_fetch_assoc($result)) {
                        $user = $accounts["username"];
                        $name = "SELECT fname, lname FROM registrations WHERE username='$user'";
                        $info = mysqli_query($conn, $name);
                        $data = mysqli_fetch_assoc($info);
                        $full = $data["fname"] . " " . $data["lname"];
                        echo "<tr><td>" . $accounts["accountNum"] . "</td>";
                        echo "<td>" . $full . " (Username: " . $accounts["username"] . ")</td>";
                        echo "<td>" . "$" . $accounts["Balance"] . "</td>";
                        echo "<td>" . $accounts["type"] . "</td></tr>";
                        $num++;
                    }
                    echo "</tbody>";
                    echo "<caption>Total Bank Accounts (" . $num . ")</caption>";
                ?>
        </table>
        <div class="popup" id ="popup-1">
            <div class="overlay"></div>
            <div class="content">
                <div class="close-btn" onclick="togglePopup()">&times;</div>
                <form action="hire.php" method="post">
                        <h2 style="font-size: 20px; font-weight: bold;">Hire</h2><br>
                        <?php 
                            echo "<input name=\"manager\" value=\"" . $employee_name . "\" type=\"hidden\">"
                        ?>
                        <label> Email:
                            <input name = "email" type = "email" required>
                        </label><br>
                        <label> Tier:
                            <select name="tier" id="tier" required>
                                <?php 
                                    $tier = $_SESSION['tier'] - 1;
                                    while ($tier > 0) {
                                        echo "<option value=\"". $tier ."\">" . $tier . "</option>";
                                        $tier--;
                                    }
                                ?>
                            </select>
                        </label><br>
                        <br>
                        <button type="submit" class="btn">Hire</button>
                </form>
            </div>
        </div>
        <div class="popup" id ="popup-2">
            <div class="overlay"></div>
            <div class="content">
                <div class="close-btn" onclick="togglePopupTwo()">&times;</div>
                <form action="terminate.php" method="post">
                        <h2 style="font-size: 20px; font-weight: bold;">Terminate</h2><br>
                        <label> Select Employee:
                            <select name="employee" id="employee" required>
                                <?php 
                                    $tier = $_SESSION['tier'];
                                    if ($tier == 3) {
                                        $sql = "SELECT * FROM Employees WHERE tier < '$tier'";
                                        $result = mysqli_query($conn, $sql);
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            echo "<option value=\"" . $row["employee_id"] . "\">";
                                            if ($row['status'] == "Hired") echo $row["fname"] . " " . $row["lname"] . " (ID: " . $row["employee_id"] . ", Tier: " . $row["tier"] . ")";
                                            else if ($row['status'] == "Pending") echo " ID: " . $row["employee_id"] . " (Pending Tier: " . $row["tier"] . " Employee)";
                                            echo "</option>";
                                        }
                                    }
                                    else if ($tier == 2) {
                                        $sql = "SELECT * FROM Employees WHERE manager='$employee_name'";
                                        $result = mysqli_query($conn, $sql);
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            echo "<option value=\"" . $row["employee_id"] . "\">";
                                            if ($row['status'] == "Hired") echo $row["fname"] . " " . $row["lname"] . " (ID: " . $row["employee_id"] . ", Tier: " . $row["tier"] . ")";
                                            else if ($row['status'] == "Pending") echo " ID: " . $row["employee_id"] . " (Pending Tier: " . $row["tier"] . " Employee)";
                                            echo "</option>";
                                        }
                                    }
                                ?>
                            </select>
                        </label><br>
                        <br>
                        <button type="submit" class="btn">Terminate</button>
                </form>
            </div>
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
