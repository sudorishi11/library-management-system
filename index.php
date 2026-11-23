
<?php
require_once __DIR__ . '/auth/check_auth.php';
require_once 'config/database.php';

try {
    // Fetch dashboard statistics
    $stats = [];

    $stats['titles'] = $pdo->query(
        "SELECT COUNT(*) FROM book"
    )->fetchColumn();

    $stats['copies'] = $pdo->query(
        "SELECT COALESCE(SUM(total_copies), 0) FROM book"
    )->fetchColumn();

    $stats['available'] = $pdo->query(
        "SELECT COALESCE(SUM(available_copies), 0) FROM book"
    )->fetchColumn();

    $stats['members'] = $pdo->query(
        "SELECT COUNT(*) FROM member WHERE status = 'active'"
    )->fetchColumn();

    $stats['issued'] = $pdo->query(
        "SELECT COUNT(*) FROM issue WHERE status = 'issued'"
    )->fetchColumn();

    $stats['fines'] = $pdo->query(
        "SELECT COUNT(*) FROM fine WHERE payment_status = 'pending'"
    )->fetchColumn();

    // Fetch recent book transactions
    $recentIssues = $pdo->query("
        SELECT b.title, m.name, i.issue_date, i.due_date, i.status
        FROM issue i
        JOIN book b ON i.book_id = b.book_id
        JOIN member m ON i.member_id = m.member_id
        ORDER BY i.issue_id DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Dashboard data could not be loaded. Please check your database.");
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Library Dashboard</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .sidebar {
            width: 230px;
            height: 100vh;
            position: fixed;
            background: #172554;
            color: white;
            padding: 25px 18px;
        }

        .sidebar h2 {
            font-size: 21px;
            margin-bottom: 35px;
        }

        .sidebar a {
            display: block;
            padding: 13px;
            margin: 8px 0;
            color: #dbeafe;
            text-decoration: none;
            border-radius: 7px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #2563eb;
            color: white;
        }

        .main {
            margin-left: 230px;
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #0000000a;
        }

        .card p {
            color: #64748b;
            margin-bottom: 10px;
        }

        .card h2 {
            font-size: 30px;
            margin: 0;
            color: #2563eb;
        }

        .section {
            margin-top: 35px;
            background: white;
            padding: 25px;
            border-radius: 12px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
        }

        .status {
            color: #15803d;
            font-weight: bold;
        }

        @media(max-width: 700px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main {
                margin-left: 0;
                padding: 18px;
            }
        }
    </style>
</head>

<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main">

        <div class="header">
            <h1>Dashboard</h1>
            <p>Library Management System</p>
        </div>

        <div class="cards">

            <div class="card">
                <p>Book Titles</p>
                <h2><?= e($stats['titles']) ?></h2>
            </div>

            <div class="card">
                <p>Total Copies</p>
                <h2><?= e($stats['copies']) ?></h2>
            </div>

            <div class="card">
                <p>Available Copies</p>
                <h2><?= e($stats['available']) ?></h2>
            </div>

            <div class="card">
                <p>Active Members</p>
                <h2><?= e($stats['members']) ?></h2>
            </div>

            <div class="card">
                <p>Currently Issued</p>
                <h2><?= e($stats['issued']) ?></h2>
            </div>

            <div class="card">
                <p>Pending Fines</p>
                <h2><?= e($stats['fines']) ?></h2>
            </div>

        </div>

        <section class="section">
            <h2>Recent Book Transactions</h2>

            <table>
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Member</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($recentIssues)): ?>
                        <tr>
                            <td colspan="5">No transactions found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentIssues as $issue): ?>
                            <tr>
                                <td><?= e($issue['title']) ?></td>
                                <td><?= e($issue['name']) ?></td>
                                <td><?= e($issue['issue_date']) ?></td>
                                <td><?= e($issue['due_date']) ?></td>
                                <td class="status"><?= e($issue['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

    </main>

</body>
</html>

