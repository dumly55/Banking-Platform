<?php foreach (take_flashes() as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>" role="alert" onclick="this.remove()"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
