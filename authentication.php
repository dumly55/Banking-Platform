<html>
    <head><title>Authentication</title></head>
    <body>
        <?php
            session_start();
            if (isset($_POST["username"]) && isset($_POST["password"])) {
                $_SESSION['Last_Location'] = "authentication.php";
                $username = $_POST["username"];
                $password = $_POST["password"];
                $conn = mysqli_connect("localhost", "root", "", "users");
                if (!$conn) {
                    die("Connection failed: " . mysqli_connect_error());
                }
                $sql = "SELECT password, email FROM registrations WHERE username = '$username'";
                $results = mysqli_query($conn, $sql);
                if ($results) {
                    $row = mysqli_fetch_assoc($results);
                    if ($row["password"] === $password) {
                        $_SESSION['logged_in'] = FALSE;
                        $_SESSION['username'] = $username;
                        $_SESSION['TFA'] = TRUE;
                        $_SESSION['email'] = $row["email"];
                        echo "<script>window.location.href='GenerateCode.php';</script>";
                    } 
                    else {
                        echo "<script>alert('Login Failed');window.location.href='Logout.php';</script>";
                    }
                } 
                else {
                    echo mysqli_error($conn);
                }
                mysqli_close($conn); 
            } 
            else if ($_SESSION['TFA_Token'] == TRUE && $_SESSION['Last_Location'] == "authentication.php") {
                $_SESSION['logged_in'] = TRUE;
                $_SESSION['TFA_Token'] = FALSE;
                $_SESSION['time'] = time();
                header("Location: update.php");
            }
            else {
                header("Location: Login.php");
            }
        ?>
    </body>
</html>
