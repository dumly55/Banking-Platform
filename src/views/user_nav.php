<?php
// Customer nav buttons. Floated right, so the first link renders rightmost.
$links = ['logout.php' => 'Logout', 'deposit.php' => 'Deposit', 'atm.php' => 'ATM', 'dashboard.php' => 'Home'];
foreach ($links as $href => $label):
    if ($href === ($current ?? '')) continue; ?>
    <div class="lbtn"><a href="<?= $href ?>" class="log logbutton"><?= $label ?></a></div>
<?php endforeach; ?>
