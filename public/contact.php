<?php
require __DIR__ . '/../src/bootstrap.php';

if (is_post()) {
    verify_csrf();
    $name = input('name');
    $email = input('email');
    $question = input('question');

    if ($name === '' || mb_strlen($name) > 100) back_with('contact.php', 'Please enter your name.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) back_with('contact.php', 'Please enter a valid email address.');
    if ($question === '' || mb_strlen($question) > 500) back_with('contact.php', 'Your question must be 1-500 characters.');

    $ticket = unique_number('support_tickets', 'id', 10);
    db_exec('INSERT INTO support_tickets (id, name, email, question) VALUES (?, ?, ?, ?)', [$ticket, $name, $email, $question]);

    send_mail(
        $email,
        "Ticket #$ticket (Bank of the Future Support Team)",
        "Ticket #$ticket\n\nThank you for contacting us. We will get back to you as soon as we can.\n\n"
        . "Sincerely,\nBank of the Future Team\n\nName: $name\nEmail: $email\n\nQuestion:\n$question"
    );
    back_with('contact.php', "Ticket #$ticket submitted. A confirmation was sent to your email.", 'success');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Bank of the Future</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/contact.css">
    <link rel="stylesheet" href="assets/css/common.css">
</head>
<body>
    <?php view('flash'); ?>
    <h4 class="title">Bank of the Future</h4>
    <header>
        <nav>
            <ul>
                <li><a href="index.html">Home</a></li>
                <li><a href="about.html">About Us</a></li>
                <li><a href="services.html">Services</a></li>
                <li><a href="login.php">Customer Login</a></li>
                <li><a href="employee-login.php">Employee Login</a></li>
            </ul>
        </nav>
    </header>

    <form method="post" action="contact.php">
        <?= csrf_field() ?>
        <label for="name">Name:</label>
        <input type="text" name="name" id="name" maxlength="100" required>

        <label for="email">Email:</label>
        <input type="email" name="email" id="email" required>

        <label for="question">Question:</label>
        <textarea name="question" id="question" maxlength="500" required
                  oninput="document.getElementById('charNum').textContent = 'Remaining Characters: ' + (500 - this.value.length)"></textarea>
        <div id="charNum">Remaining Characters: 500</div>
        <input type="submit" value="Submit">
    </form>

    <footer>
        <p>&copy; Bank of the Future</p>
    </footer>
</body>
</html>
