<?php 
    session_start();
    if (isset($_POST["fname"]) && isset($_POST["lname"]) && isset($_POST["password"]) && isset($_POST["ssn"]) && isset($_POST["phone"]) && isset($_POST["lstate"]) && isset($_POST["license"]) && isset($_POST["address"]) && isset($_POST["city"]) && isset($_POST["state"]) && isset($_POST["zip"]) && isset($_SESSION["id"])) {
        $fname = $_POST["fname"];
        $lname = $_POST["lname"];
        $password = $_POST["password"];
        $ssn = $_POST["ssn"];
        $phone = $_POST["phone"];
        $lstate = $_POST["lstate"];
        $license = $_POST["license"];
        $address = $_POST["address"];
        $address2 = $_POST["address2"];
        $city = $_POST["city"];
        $state = $_POST["state"];
        $zip = $_POST["zip"];
        $id = $_SESSION["id"];
        $full_license = $lstate . $license;
        $conn = mysqli_connect("localhost", "root", "", "users");
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        $sql = "SELECT status FROM Employees WHERE employee_id='$id'";
        $result = mysqli_query($conn, $sql);
        $info = mysqli_fetch_assoc($result);
        if ($info["status"] == "Pending") {
            $sql= "SELECT * FROM Employees WHERE ssn='$ssn'";
            $result = mysqli_query($conn, $sql);
            $count = mysqli_num_rows($result);
            if($count>0) {
                echo "<script>alert('Registration unsuccessful.');window.location.href='employee_register.php';</script>";
            }
            $sql= "SELECT * FROM Employees WHERE phone='$phone'";
            $result = mysqli_query($conn, $sql);
            $count = mysqli_num_rows($result);
            if($count>0) {
                echo "<script>alert('Registration unsuccessful.');window.location.href='employee_register.php';</script>";
            }
            $sql= "SELECT * FROM Employees WHERE full_license='$full_license'";
            $result = mysqli_query($conn, $sql);
            $count = mysqli_num_rows($result);
            if($count>0) {
                echo "<script>alert('Registration unsuccessful.');window.location.href='employee_register.php';</script>";
            }
            $sql = "UPDATE Employees SET password='$password', status='Hired', address='$address', address2='$address2', phone='$phone', state='$state', license='$license', ssn='$ssn', lstate='$lstate', city='$city', zip='$zip', fname='$fname', lname='$lname', full_license='$full_license' WHERE employee_id='$id'";
            $result = mysqli_query($conn, $sql);
            $_SESSION["new_e_token"] = FALSE;
            echo "<script>alert('Registration Successful');window.location.href='EmployeeLogin.php';</script>";
        }
        else {
            $_SESSION["new_e_token"] = FALSE;
            echo "<script>alert('Invalid Registration');window.location.href='EmployeeLogin.php';</script>";
        }
    }
    else header("Location: employee_register.php");
?>
