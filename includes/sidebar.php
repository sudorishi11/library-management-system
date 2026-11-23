
<?php
$currentPage = basename($_SERVER['PHP_SELF']);

$menuItems = [
    'index.php'   => 'Dashboard',
    'books.php'   => 'Books',
    'members.php' => 'Members',
    'issues.php'  => 'Issue Books',
    'returns.php' => 'Returns',
    'fines.php'   => 'Fines'
];
?>

<div class="sidebar">
    <h2>Library System</h2>

    <nav>
        <?php foreach ($menuItems as $page => $label): ?>
            <a
                href="<?= htmlspecialchars($page) ?>"
                class="<?= $currentPage === $page ? 'active' : '' ?>"
            >
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
<a href="auth/logout.php" class="logout-link">
    Logout
</a>
</div>

