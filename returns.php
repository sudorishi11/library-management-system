
<?php
require_once __DIR__ . '/auth/check_auth.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Process book return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_book'])) {

    $issue_id = (int)($_POST['issue_id'] ?? 0);

    try {
        if ($issue_id <= 0) {
            throw new Exception("Invalid issue selected.");
        }

        $pdo->beginTransaction();

        // Lock issue record to prevent duplicate returns
        $stmt = $pdo->prepare("
            SELECT
                issue_id,
                due_date,
                status,
                DATEDIFF(CURDATE(), due_date) AS late_days
            FROM issue
            WHERE issue_id = ?
            FOR UPDATE
        ");

        $stmt->execute([$issue_id]);
        $issue = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$issue) {
            throw new Exception("Issue record not found.");
        }

        if ($issue['status'] !== 'issued') {
            throw new Exception("This book has already been returned.");
        }

        // Calculate fine: ₹2 per late day
        $late_days = max(0, (int)$issue['late_days']);
        $fine_amount = $late_days * 2;

        // Update issue status.
        // after_book_return trigger automatically restores book stock.
        $stmt = $pdo->prepare("
            UPDATE issue
            SET status = 'returned',
                return_date = CURDATE()
            WHERE issue_id = ?
        ");

        $stmt->execute([$issue_id]);

        // Create fine record only when there is a late return
        if ($fine_amount > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO fine
                (issue_id, amount, payment_status)
                VALUES (?, ?, 'pending')
            ");

            $stmt->execute([$issue_id, $fine_amount]);
        }

        $pdo->commit();

        if ($fine_amount > 0) {
            header("Location: returns.php?success=1&fine=" . $fine_amount . "&days=" . $late_days);
        } else {
            header("Location: returns.php?success=2");
        }
        exit;

    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Database error: " . $ex->getMessage();

    } catch (Exception $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $ex->getMessage();
    }
}

// Success messages
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Book returned successfully! Fine: ₹" .
        (int)($_GET['fine'] ?? 0) .
        " for " . (int)($_GET['days'] ?? 0) . " late days.";
} elseif (isset($_GET['success']) && $_GET['success'] == 2) {
    $message = "Book returned successfully! No fine applicable.";
}

// Dashboard statistics
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total_active,
        SUM(due_date < CURDATE()) AS overdue,
        SUM(due_date = CURDATE()) AS due_today
    FROM issue
    WHERE status = 'issued'
")->fetch(PDO::FETCH_ASSOC);

$returned_today = $pdo->query("
    SELECT COUNT(*)
    FROM issue
    WHERE status = 'returned'
      AND return_date = CURDATE()
")->fetchColumn();

$pending_fines = $pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM fine
    WHERE payment_status = 'pending'
")->fetchColumn();

// Currently issued books
$active_issues = $pdo->query("
    SELECT
        i.issue_id,
        b.title,
        b.author,
        m.name AS member_name,
        m.email,
        i.issue_date,
        i.due_date,
        DATEDIFF(CURDATE(), i.due_date) AS late_days
    FROM issue i
    JOIN book b ON i.book_id = b.book_id
    JOIN member m ON i.member_id = m.member_id
    WHERE i.status = 'issued'
    ORDER BY i.due_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Recent return history
$return_history = $pdo->query("
    SELECT
        i.issue_id,
        b.title,
        m.name AS member_name,
        i.issue_date,
        i.due_date,
        i.return_date,
        COALESCE(f.amount, 0) AS fine_amount,
        COALESCE(f.payment_status, 'none') AS payment_status,
        DATEDIFF(i.return_date, i.due_date) AS late_days
    FROM issue i
    JOIN book b ON i.book_id = b.book_id
    JOIN member m ON i.member_id = m.member_id
    LEFT JOIN fine f ON i.issue_id = f.issue_id
    WHERE i.status = 'returned'
    ORDER BY i.return_date DESC, i.issue_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Book Returns | Library Management</title>

<link rel="stylesheet" href="assets/style.css">

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f4f6fb;
    color: #172033;
}

/* Sidebar */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background: #172554;
    padding: 28px 18px;
    z-index: 100;
    display: flex;
    flex-direction: column;
}

.sidebar h2 {
    color: white;
    font-size: 22px;
    margin: 0 0 35px;
    padding: 0 10px;
}

.sidebar a {
    display: block;
    color: #cbd5e1;
    text-decoration: none;
    padding: 13px 15px;
    border-radius: 8px;
    margin-bottom: 7px;
    font-size: 14px;
    transition: .2s;
}

.sidebar a:hover {
    background: #263b70;
    color: white;
}

.sidebar a.active {
    background: #315fe8;
    color: white;
    font-weight: 600;
}

/* Main content */
.main-content {
    margin-left: 250px;
    padding: 30px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.page-header h1 {
    margin: 0 0 7px;
    font-size: 27px;
}

.page-header p {
    margin: 0;
    color: #778198;
}

/* Statistics */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card, .panel {
    background: white;
    border-radius: 13px;
    padding: 22px;
    box-shadow: 0 3px 15px rgba(25,40,70,.04);
}

.stat-card small {
    color: #7b8497;
    font-size: 13px;
}

.stat-card h2 {
    margin: 12px 0 0;
    font-size: 29px;
}

/* Panels */
.panel {
    margin-bottom: 24px;
}

.panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.panel-heading h2 {
    font-size: 19px;
    margin: 0;
}

.search-box {
    max-width: 280px;
    width: 100%;
    padding: 11px;
    border: 1px solid #e0e5ef;
    border-radius: 8px;
    outline: none;
}

.search-box:focus {
    border-color: #4778ed;
}

/* Tables */
.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th, td {
    text-align: left;
    padding: 14px 12px;
    border-bottom: 1px solid #edf0f5;
    font-size: 13px;
}

th {
    color: #778198;
    background: #f8f9fc;
}

td strong {
    display: block;
    font-size: 13px;
}

.muted {
    color: #8790a2;
    font-size: 12px;
}

/* Badges */
.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.issued {
    background: #dbeafe;
    color: #1d4ed8;
}

.overdue {
    background: #fee2e2;
    color: #b91c1c;
}

.returned {
    background: #dcfce7;
    color: #15803d;
}

.pending {
    background: #fef3c7;
    color: #92400e;
}

.no-fine {
    background: #dcfce7;
    color: #15803d;
}

/* Buttons */
.return-btn {
    background: #315fe8;
    color: white;
    border: 0;
    border-radius: 7px;
    padding: 9px 14px;
    font-weight: 600;
    cursor: pointer;
}

.return-btn:hover {
    background: #244bc2;
}

.notice {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 18px;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.failure {
    background: #fee2e2;
    color: #991b1b;
}

.empty {
    text-align: center;
    padding: 25px;
    color: #8790a2;
}

@media(max-width: 1000px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 700px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        padding: 15px;
    }

    .sidebar h2 {
        margin-bottom: 12px;
    }

    .sidebar a {
        display: inline-block;
        margin: 3px;
    }

    .main-content {
        margin-left: 0;
        padding: 18px;
    }

    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .stat-card {
        padding: 15px;
    }

    .stat-card h2 {
        font-size: 23px;
    }
}
</style>
</head>

<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1>Book Returns</h1>
            <p>Process returns and manage overdue books and fines.</p>
        </div>
        <span class="muted"><?= date('d M Y') ?></span>
    </div>

    <?php if ($message): ?>
        <div class="notice success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice failure"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">

        <div class="stat-card">
            <small>Currently Issued</small>
            <h2><?= (int)$stats['total_active'] ?></h2>
        </div>

        <div class="stat-card">
            <small>Overdue Books</small>
            <h2><?= (int)$stats['overdue'] ?></h2>
        </div>

        <div class="stat-card">
            <small>Returned Today</small>
            <h2><?= (int)$returned_today ?></h2>
        </div>

        <div class="stat-card">
            <small>Pending Fine Amount</small>
            <h2>₹<?= number_format((float)$pending_fines, 2) ?></h2>
        </div>

    </div>

    <!-- Active Issues -->
    <div class="panel">

        <div class="panel-heading">
            <h2>Books Awaiting Return</h2>

            <input
                type="search"
                id="activeSearch"
                class="search-box"
                placeholder="Search book or member..."
            >
        </div>

        <div class="table-wrap">
            <table id="activeTable">

                <thead>
                    <tr>
                        <th>Issue / Book</th>
                        <th>Member</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Fine</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($active_issues as $issue): ?>

                    <?php
                    $late_days = max(0, (int)$issue['late_days']);
                    $fine = $late_days * 2;
                    ?>

                    <tr>
                        <td>
                            <strong><?= e($issue['title']) ?></strong>
                            <span class="muted">
                                Issue #<?= (int)$issue['issue_id'] ?>
                            </span>
                        </td>

                        <td>
                            <strong><?= e($issue['member_name']) ?></strong>
                            <span class="muted"><?= e($issue['email']) ?></span>
                        </td>

                        <td><?= e($issue['issue_date']) ?></td>

                        <td><?= e($issue['due_date']) ?></td>

                        <td>
                            <?php if ($late_days > 0): ?>
                                <strong>₹<?= number_format($fine, 2) ?></strong>
                                <span class="muted">
                                    <?= $late_days ?> days late
                                </span>
                            <?php else: ?>
                                <span class="status no-fine">No fine</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <form method="POST"
                                  onsubmit="return confirm('Confirm return of this book?');">

                                <input
                                    type="hidden"
                                    name="issue_id"
                                    value="<?= (int)$issue['issue_id'] ?>"
                                >

                                <button
                                    type="submit"
                                    name="return_book"
                                    class="return-btn"
                                >
                                    Return Book
                                </button>

                            </form>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (!$active_issues): ?>
                    <tr>
                        <td colspan="6" class="empty">
                            No books are currently awaiting return.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

    <!-- Return History -->
    <div class="panel">

        <div class="panel-heading">
            <h2>Return History</h2>

            <input
                type="search"
                id="historySearch"
                class="search-box"
                placeholder="Search return history..."
            >
        </div>

        <div class="table-wrap">
            <table id="historyTable">

                <thead>
                    <tr>
                        <th>Book / Issue ID</th>
                        <th>Member</th>
                        <th>Issue Date</th>
                        <th>Return Date</th>
                        <th>Fine</th>
                        <th>Fine Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($return_history as $record): ?>

                    <tr>
                        <td>
                            <strong><?= e($record['title']) ?></strong>
                            <span class="muted">
                                Issue #<?= (int)$record['issue_id'] ?>
                            </span>
                        </td>

                        <td><?= e($record['member_name']) ?></td>

                        <td><?= e($record['issue_date']) ?></td>

                        <td><?= e($record['return_date']) ?></td>

                        <td>
                            ₹<?= number_format((float)$record['fine_amount'], 2) ?>
                        </td>

                        <td>
                            <?php if ((float)$record['fine_amount'] <= 0): ?>
                                <span class="status no-fine">No Fine</span>
                            <?php elseif ($record['payment_status'] === 'pending'): ?>
                                <span class="status pending">Pending</span>
                            <?php else: ?>
                                <span class="status returned">Paid</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (!$return_history): ?>
                    <tr>
                        <td colspan="6" class="empty">
                            No returned books yet.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
// Search active issues
document.getElementById('activeSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();

    document.querySelectorAll('#activeTable tbody tr').forEach(row => {
        row.style.display =
            row.innerText.toLowerCase().includes(query) ? '' : 'none';
    });
});

// Search return history
document.getElementById('historySearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();

    document.querySelectorAll('#historyTable tbody tr').forEach(row => {
        row.style.display =
            row.innerText.toLowerCase().includes(query) ? '' : 'none';
    });
});
</script>

</body>
</html>

