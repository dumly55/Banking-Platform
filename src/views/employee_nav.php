<?php
// Employee header nav with live counts. Floated right, so the first link renders rightmost.
$pendingDeposits = db_value("SELECT COUNT(*) FROM transactions WHERE type = 'Deposit' AND status = 'Pending'");
$openTickets = db_value("SELECT COUNT(*) FROM support_tickets WHERE status = 'Open'");
$links = [
    'logout.php'              => 'Logout',
    'employee-inquiries.php'  => "Inquiries ($openTickets)",
    'employee-deposits.php'   => "Pending Deposits ($pendingDeposits)",
    'employee.php'            => 'Home',
];
?>
<h1>
    <a class="home-icon" href="index.html" title="Public site"><i class='bx bx-building-house'></i></a>
    <span class="page-title"><?= e($title) ?></span>
    <?php foreach ($links as $href => $label):
        if ($href === $current) continue; ?>
        <div class="lbtn"><a href="<?= $href ?>" class="log logbutton"><?= e($label) ?></a></div>
    <?php endforeach; ?>
    <?php if (($employee['tier'] ?? 0) > 1 && $current === 'employee.php'): ?>
        <div class="lbtn"><a onclick="togglePopup('popup-terminate')" class="log logbutton" style="cursor:pointer">Terminate</a></div>
        <div class="lbtn"><a onclick="togglePopup('popup-hire')" class="log logbutton" style="cursor:pointer">Hire</a></div>
    <?php endif; ?>
</h1>
