<?php
    session_start();
    if (time() - $_SESSION['time'] > 600) header("Location: Logout.php");
    else if (isset($_POST["manager"]) && isset($_POST["email"]) && isset($_POST["tier"])) {
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
            $manager = $_POST["manager"];
            $email = $_POST["email"];
            $tier = $_POST["tier"];
            $sql = "SELECT email FROM Employees WHERE email='$email'";
            $result = mysqli_query($conn, $sql);
            $dup = mysqli_num_rows($result);
            if ($dup > 0) {
                $sql = "SELECT status, full_license FROM Employees WHERE email='$email'";
                $result = mysqli_query($conn, $sql);
                $status = mysqli_fetch_assoc($result);
                if ($status["status"] == "Hired") echo "<script>alert('That person is already working at Bank of the Future.');window.location.href='employee.php';</script>";
                else if ($status["status"] == "Terminated") {
                    $sql = "SELECT employee_id FROM Employees WHERE email='$email'";
                    $result = mysqli_query($conn, $sql);
                    $info = mysqli_fetch_assoc($result);
                    $id = $info["employee_id"];
                    if ($status["full_license"] == NULL) {
                        $sql = "UPDATE Employees SET status=\"Pending\" WHERE email='$email'";
                        $result = mysqli_query($conn, $sql);
                    }
                    else {
                        $sql = "UPDATE Employees SET status=\"Hired\" WHERE email='$email'";
                        $result = mysqli_query($conn, $sql);
                    }
                    $_SESSION['subject'] = "Job Offer";
                    $_SESSION['body'] = "Congratulations on becoming a team member once again at Bank of the Future. We are excited to have you back. If you have registered before, please use your old login. If not, please use your employee id ($id) to register.";
                    echo "<form method = \"post\" action=\"send-email.php\" id=\"mail\"><input name=\"email\" value=\"$email\" type=\"hidden\"></form>";
                    echo "<script type=\"text/javascript\"> 
                            window.onload=function(){
                                document.forms['mail'].submit();
                            }
                          </script>";
                }
            }
            else {
                date_default_timezone_set('America/Los_Angeles');
                $year = (date("Y") - 1734) * pow(10, 6);
                $nid = $year + rand(0, 999999);
                $sql = "SELECT * FROM Employees WHERE employee_id='$nid'";
                $result = mysqli_query($conn, $sql);
                $duplicate = mysqli_num_rows($result);
                $count = 0;
                while ($duplicate > 0) {
                    if ($count == 1000) {
                        $year = $year + 1;
                        $count = 0;
                    }
                    $nid = $year + rand(0, 999999);
                    $sql = "SELECT * FROM Employees WHERE employee_id='$nid'";
                    $result = mysqli_query($conn, $sql);
                    $duplicate = mysqli_num_rows($result);
                    $count = $count + 1;
                }
                $sql = "INSERT INTO Employees (employee_id, email, status, tier, manager) VALUES ('$nid', '$email', 'Pending', '$tier', '$manager')";
                $result = mysqli_query($conn, $sql);
                $_SESSION['subject'] = "Job Offer";
                $_SESSION['body'] = "Congratulations on becoming a team member at Bank of the Future. We are excited to have you. Here is your employee id: $nid. Use it at localhost/employee_register_code.php to make your employee account.";
                echo "<form method = \"post\" action=\"send-email.php\" id=\"mail\"><input name=\"email\" value=\"$email\" type=\"hidden\"></form>";
                echo "<script type=\"text/javascript\"> 
                        window.onload=function(){
                            document.forms['mail'].submit();
                        }
                      </script>";
            }
        }
    }
    else header("Location: employee.php");
?>
