<?php
require __DIR__ . '/../src/bootstrap.php';
$me = require_employee();

if (is_post()) {
    verify_csrf();
    $status = ['resolve' => 'Resolved', 'reopen' => 'Open'][input('action')] ?? null;
    if ($status) db_exec('UPDATE support_tickets SET status = ? WHERE id = ?', [$status, input('ticket')]);
    redirect('employee-inquiries.php');
}

$tickets = ['Open' => [], 'Resolved' => []];
foreach (db_all('SELECT * FROM support_tickets ORDER BY created_at') as $ticket) {
    $tickets[$ticket['status']][] = $ticket;
}
$sections = ['Open' => ['Ongoing Inquiries', 'resolve', 'Resolve'], 'Resolved' => ['Resolved Inquiries', 'reopen', 'Reopen']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiries | Bank of the Future</title>
    <link rel="icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/employee.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <?php view('flash'); ?>
    <?php view('employee_nav', ['title' => 'Inquiries', 'current' => 'employee-inquiries.php', 'employee' => $me]); ?>
    <br>
    <?php foreach ($sections as $status => [$caption, $action, $label]): ?>
        <table class="inquiries">
            <caption><?= $caption ?> (<?= count($tickets[$status]) ?>)</caption>
            <thead>
                <tr><th>Ticket Number</th><th>Name</th><th>Email</th><th>Question</th><th>Submitted</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($tickets[$status] as $t): ?>
                    <tr>
                        <td><?= e($t['id']) ?></td>
                        <td><?= e($t['name']) ?></td>
                        <td><a href="mailto:<?= e($t['email']) ?>"><?= e($t['email']) ?></a></td>
                        <td class="question"><?= nl2br(e($t['question'])) ?></td>
                        <td><?= date('m/d/Y g:i A', strtotime($t['created_at'])) ?></td>
                        <td>
                            <form action="employee-inquiries.php" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="ticket" value="<?= e($t['id']) ?>">
                                <button name="action" type="submit" value="<?= $action ?>"><?= $label ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table><br>
    <?php endforeach; ?>
    <?php view('session_timeout'); ?>
</body>
</html>
