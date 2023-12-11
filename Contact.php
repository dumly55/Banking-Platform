
<html>
<head>
    <link rel="stylesheet" href="EmployeeProfile.css">
    <title>Contact Us - Bank of the Future</title>
</head>
<body>
    <h4 class="title">Bank of the Future</h4>
    <header>
        <nav>
            <ul>
                <li><a href="HomePage.html">Home</a></li>
                <li><a href="About Us.html">About Us</a></li>
                <li><a href="TypesOfAccts.html">Services</a></li>
                <li><a href="EmployeeLogin.php">Employee Login</a></li>
            </ul>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        </nav>
    </header>

    <form method="post" action="contact_process.php">
        <label for="name">Name:</label>
        <input type="text" name="name" id="name" required><br>

        <label for="email">Email:</label>
        <input type="email" name="email" id="email" required><br>

        <label for="question">Question:</label>
        <textarea name="question" id="field" onkeyup="countChar(this)" maxlength="500" required></textarea><br>
        <div style="text-align: right; font-size: 12px; color: gray;" id="charNum"></div>
        <script>
            function countChar(val) {
                var len = val.value.length;
                if (len >= 500) {
                    val.value = val.value.substring(0, 500);
                } else {
                    var num = 500 - len;
                    var cleft = "Remaining Characters: " + num;
                    $('#charNum').text(cleft);
                }
            };
        </script>
        <input type="submit">
    </form>
   




    <footer>
        <p style="text-align: center;">&copy; Bank of the Future</p>
    </footer>
</body>
</html>





    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        h4.title {
            background-color: #003a70;
            color: #fff;
            text-align: center;
            padding: 10px;
        }

        header {
            background-color: #003a70;
            color: #fff;
            text-align: center;
            padding: 10px;
        }

        form {
            max-width: 400px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }

        label {
            display: block;
            margin-bottom: 5px;
        }

        input[type="text"],
        input[type="email"],
        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }

        input[type="submit"] {
            background-color: #003a70;
            color: orange;
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
    </style>



