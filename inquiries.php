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
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="inquiries.css">
        <script src= "https://code.jquery.com/jquery-3.4.1.min.js"></script>
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
        <title>Inquiries</title>
    </head>
    <body>
        <h1>
            <a style="color: white;" href="HomePage.html"><i class='bx bx-building-house'></i></a>
            <?php
                print "<span style=\"font-weight: bold;\">Inquiries</span>";
            ?>
            <div class="lbtn">
                <a href="Logout.php" class="log logbutton" name="logout">Logout</a>
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
                <a href="employee.php" class="log logbutton" name="home">Home</a>
            </div>
        </h1><br>
        <table>
            <thead>
                <th>Ticket Number</th>
                <th>Name</th>
                <th>Email</th>
                <th>Question</th>
                <th>Date</th>
                <th>Time</th>
                <th>Action</th>
            </thead>
            <tbody>
                <?php
                    $sql = "SELECT * FROM support WHERE status='Ongoing' ORDER BY date ASC, mil_time ASC";
                    $result = mysqli_query($conn, $sql);
                    $num = 0;
                    while ($inquiries = mysqli_fetch_assoc($result)) {
                        echo "<form action=\"inquiry_process.php\" method=\"post\"><input type=\"hidden\" name=\"ticket\" value=\"" . $inquiries["ticket"] . "\">";
                        echo "<tr><td>" . $inquiries["ticket"] . "</td>";
                        echo "<td>" . $inquiries["name"] . "</td>";
                        echo "<td>" . $inquiries["email"] . "</td>";
                        echo "<td>" . $inquiries["question"] . "</td>";
                        echo "<td>" . $inquiries["date"] . "</td>";
                        echo "<td>" . $inquiries["time"] . "</td>";
                        echo "<td><button name=\"action\" type=\"submit\" value=\"resolve\">Resolve</button></form>";
                        $num++;
                    }
                    echo "</tbody>";
                    echo "<caption>Ongoing Inquiries (" . $num . ")</caption>";
                ?>
        </table><br>
        <table>
            <thead>
            <th>Ticket Number</th>
                <th>Name</th>
                <th>Email</th>
                <th>Question</th>
                <th>Date</th>
                <th>Time</th>
                <th>Action</th>
            </thead>
            <tbody>
                <?php
                    $sql = "SELECT * FROM support WHERE status='Resolved' ORDER BY date ASC, mil_time ASC";
                    $result = mysqli_query($conn, $sql);
                    $num = 0;
                    while ($inquiries = mysqli_fetch_assoc($result)) {
                        echo "<form action=\"inquiry_process.php\" method=\"post\"><input type=\"hidden\" name=\"ticket\" value=\"" . $inquiries["ticket"] . "\">";
                        echo "<tr><td>" . $inquiries["ticket"] . "</td>";
                        echo "<td>" . $inquiries["name"] . "</td>";
                        echo "<td>" . $inquiries["email"] . "</td>";
                        echo "<td>" . $inquiries["question"] . "</td>";
                        echo "<td>" . $inquiries["date"] . "</td>";
                        echo "<td>" . $inquiries["time"] . "</td>";
                        echo "<td><button name=\"action\" type=\"submit\" value=\"reopen\">Reopen</button></form>";
                        $num++;
                    }
                    echo "</tbody>";
                    echo "<caption>Resolved Inquiries (" . $num . ")</caption>";
                ?>
        </table>
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
