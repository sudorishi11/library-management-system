
<?php
require_once __DIR__ . '/auth/check_auth.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Issue a book
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_id = (int)($_POST['book_id'] ?? 0);
    $member_id = (int)($_POST['member_id'] ?? 0);
    $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
    $due_date = $_POST['due_date'] ?? '';

    try {
        if (!$book_id || !$member_id || !$issue_date || !$due_date) {
            throw new Exception('Please complete all fields.');
        }

        if ($due_date < $issue_date) {
            throw new Exception('Due date cannot be before issue date.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT available_copies
            FROM book WHERE book_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$book || (int)$book['available_copies'] <= 0) {
            throw new Exception('This book is currently unavailable.');
        }

        $stmt = $pdo->prepare("
            SELECT member_id FROM member
            WHERE member_id = ? AND status = 'active'
        ");
        $stmt->execute([$member_id]);

        if (!$stmt->fetch()) {
            throw new Exception('Member not found or inactive.');
        }

        $staff_id = $pdo->query("
            SELECT staff_id FROM staff ORDER BY staff_id LIMIT 1
        ")->fetchColumn();

        if (!$staff_id) {
            throw new Exception('Please add a staff record first.');
        }

        // The after_book_issue trigger decreases available_copies.
        $stmt = $pdo->prepare("
            INSERT INTO issue
            (book_id, member_id, staff_id, issue_date, due_date, status)
            VALUES (?, ?, ?, ?, ?, 'issued')
        ");
        $stmt->execute([
            $book_id, $member_id, $staff_id, $issue_date, $due_date
        ]);

        $pdo->commit();
        header('Location: issues.php?success=1');
        exit;

    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'Database error: ' . $ex->getMessage();
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $ex->getMessage();
    }
}

if (isset($_GET['success'])) {
    $message = 'Book issued successfully!';
}

// Dashboard statistics
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total_issued,
        SUM(issue_date = CURDATE()) AS issued_today,
        SUM(due_date < CURDATE() AND status = 'issued') AS overdue,
        SUM(due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
            AND status = 'issued') AS due_soon
    FROM issue
    WHERE status = 'issued'
")->fetch(PDO::FETCH_ASSOC);

// Available books and active members
$books = $pdo->query("
    SELECT book_id, title, author, available_copies
    FROM book
    WHERE available_copies > 0
    ORDER BY title
")->fetchAll(PDO::FETCH_ASSOC);

$members = $pdo->query("
    SELECT member_id, name, email, member_type
    FROM member
    WHERE status = 'active'
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Issue records
$issues = $pdo->query("
    SELECT
        i.issue_id, b.title, b.author,
        m.name AS member_name, m.email,
        i.issue_date, i.due_date, i.return_date, i.status,
        CASE
            WHEN i.status = 'returned' THEN 'Returned'
            WHEN i.due_date < CURDATE() THEN 'Overdue'
            WHEN i.due_date = CURDATE() THEN 'Due Today'
            ELSE 'Issued'
        END AS display_status,
        DATEDIFF(i.due_date, CURDATE()) AS days_left
    FROM issue i
    JOIN book b ON i.book_id = b.book_id
    JOIN member m ON i.member_id = m.member_id
    ORDER BY i.issue_id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Circulation | Library Management</title>
<link rel="stylesheet" href="assets/style.css">

<style>
* { box-sizing: border-box; }
*
body {
    margin: 0;
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f4f6fb;
    color: #172033;
}

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
}


body {
    background: #f4f6fb;
    color: #172033;
}

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

.page-header h1 { margin: 0 0 7px; font-size: 27px; }
.page-header p { margin: 0; color: #778198; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card, .panel {
    background: #fff;
    border-radius: 13px;
    padding: 22px;
    box-shadow: 0 3px 15px rgba(25,40,70,.04);
}

.stat-card small { color: #7b8497; font-size: 13px; }
.stat-card h2 { margin: 12px 0 0; font-size: 29px; }

.panel { margin-bottom: 24px; }
.panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.panel-heading h2 { font-size: 19px; margin: 0; }

.issue-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.field label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 8px;
}

.field select, .field input, .search-box {
    width: 100%;
    padding: 12px;
    border: 1px solid #e0e5ef;
    border-radius: 8px;
    background: #fff;
    outline: none;
    color: #172033;
}

.field select:focus, .field input:focus, .search-box:focus {
    border-color: #4778ed;
}

.full { grid-column: 1 / -1; }

.primary-btn {
    border: 0;
    border-radius: 8px;
    background: #315fe8;
    color: white;
    padding: 12px 22px;
    cursor: pointer;
    font-weight: 600;
}
.primary-btn:hover { background: #244bc2; }

.notice {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 18px;
}
.success { background: #dcfce7; color: #166534; }
.failure { background: #fee2e2; color: #991b1b; }

.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 750px; }
th, td {
    text-align: left;
    padding: 14px 12px;
    border-bottom: 1px solid #edf0f5;
    font-size: 13px;
}
th { color: #778198; background: #f8f9fc; }
td strong { display: block; font-size: 13px; }
.muted { color: #8790a2; font-size: 12px; }

.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}
.issued { background: #dbeafe; color: #1d4ed8; }
.overdue { background: #fee2e2; color: #b91c1c; }
.returned { background: #dcfce7; color: #15803d; }
.due { background: #fef3c7; color: #92400e; }

.filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.filter-btn {
    padding: 8px 13px;
    border: 1px solid #e1e6ef;
    background: white;
    border-radius: 7px;
    cursor: pointer;
}
.filter-btn.active { background: #315fe8; color: white; border-color: #315fe8; }

@media(max-width: 1000px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media(max-width: 700px) {
    .main-content { margin-left: 0; padding: 18px; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 15px; }
    .stat-card h2 { font-size: 23px; }
    .issue-grid { grid-template-columns: 1fr; }
    .full { grid-column: auto; }
}
</style>
</head>

<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1>Book Circulation</h1>
            <p>Manage book lending, active issues and due dates.</p>
        </div>
        <span class="muted"><?= date('d M Y') ?></span>
    </div>

    <?php if ($message): ?>
        <div class="notice success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice failure"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <small>Currently Issued</small>
            <h2><?= (int)$stats['total_issued'] ?></h2>
        </div>
        <div class="stat-card">
            <small>Issued Today</small>
            <h2><?= (int)$stats['issued_today'] ?></h2>
        </div>
        <div class="stat-card">
            <small>Overdue Books</small>
            <h2><?= (int)$stats['overdue'] ?></h2>
        </div>
        <div class="stat-card">
            <small>Due Within 3 Days</small>
            <h2><?= (int)$stats['due_soon'] ?></h2>
        </div>
    </div>

    <div class="panel">
        <div class="panel-heading">
            <h2>Issue a New Book</h2>
            <span class="muted">New transaction</span>
        </div>

        <form method="POST">
            <div class="issue-grid">

                <div class="field">
                    <label>Choose Book</label>
                    <select name="book_id" required>
                        <option value="">Select available book</option>
                        <?php foreach ($books as $book): ?>
                            <option value="<?= (int)$book['book_id'] ?>">
                                <?= e($book['title']) ?> —
                                <?= e($book['author']) ?> 
                                (<?= (int)$book['available_copies'] ?> available)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Choose Member</label>
                    <select name="member_id" required>
                        <option value="">Select active member</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?= (int)$member['member_id'] ?>">
                                <?= e($member['name']) ?> — <?= e($member['member_type']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Issue Date</label>
                    <input type="date" name="issue_date"
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="field">
                    <label>Due Date</label>
                    <input type="date" name="due_date"
                           value="<?= date('Y-m-d', strtotime('+14 days')) ?>"
                           min="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="full">
                    <button class="primary-btn" type="submit">
                        + Confirm Book Issue
                    </button>
                </div>

            </div>
        </form>
    </div>

    <div class="panel">
        <div class="panel-heading">
            <h2>Issue Records</h2>
            <input type="search" id="searchInput" class="search-box"
                   placeholder="Search book or member..." style="max-width:260px;">
        </div>

        <div class="filters">
            <button class="filter-btn active" data-filter="all">All</button>
            <button class="filter-btn" data-filter="issued">Issued</button>
            <button class="filter-btn" data-filter="overdue">Overdue</button>
            <button class="filter-btn" data-filter="returned">Returned</button>
        </div>

        <div class="table-wrap" style="margin-top:18px;">
            <table id="issueTable">
                <thead>
                    <tr>
                        <th>Book / Issue ID</th>
                        <th>Member</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Remaining</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($issues as $issue):
                    $status = strtolower($issue['display_status']);
                    $filterStatus = $issue['status'] === 'returned'
                        ? 'returned'
                        : ($status === 'overdue' ? 'overdue' : 'issued');

                    $badgeClass = $filterStatus === 'overdue'
                        ? 'overdue'
                        : ($filterStatus === 'returned'
                            ? 'returned'
                            : ($status === 'due today' ? 'due' : 'issued'));
                ?>
                    <tr data-status="<?= e($filterStatus) ?>">
                        <td>
                            <strong><?= e($issue['title']) ?></strong>
                            <span class="muted">Issue #<?= (int)$issue['issue_id'] ?></span>
                        </td>
                        <td>
                            <strong><?= e($issue['member_name']) ?></strong>
                            <span class="muted"><?= e($issue['email']) ?></span>
                        </td>
                        <td><?= e($issue['issue_date']) ?></td>
                        <td><?= e($issue['due_date']) ?></td>
                        <td>
                            <?php if ($issue['status'] === 'returned'): ?>
                                <span class="muted">Completed</span>
                            <?php elseif ((int)$issue['days_left'] < 0): ?>
                                <strong><?= abs((int)$issue['days_left']) ?> days late</strong>
                            <?php elseif ((int)$issue['days_left'] === 0): ?>
                                <strong>Due today</strong>
                            <?php else: ?>
                                <?= (int)$issue['days_left'] ?> days left
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status <?= $badgeClass ?>">
                                <?= e($issue['display_status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$issues): ?>
                    <tr><td colspan="6">No issue records available.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
const searchInput = document.getElementById('searchInput');
const filterButtons = document.querySelectorAll('.filter-btn');
const rows = document.querySelectorAll('#issueTable tbody tr[data-status]');

let activeFilter = 'all';

function filterIssues() {
    const query = searchInput.value.toLowerCase();

    rows.forEach(row => {
        const matchesText = row.innerText.toLowerCase().includes(query);
        const matchesStatus =
            activeFilter === 'all' || row.dataset.status === activeFilter;

        row.style.display = matchesText && matchesStatus ? '' : 'none';
    });
}

searchInput.addEventListener('input', filterIssues);

filterButtons.forEach(button => {
    button.addEventListener('click', () => {
        filterButtons.forEach(item => item.classList.remove('active'));
        button.classList.add('active');
        activeFilter = button.dataset.filter;
        filterIssues();
    });
});
</script>

</body>
</html>

