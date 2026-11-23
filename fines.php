
<?php
require_once __DIR__ . '/auth/check_auth.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Mark fine as paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_fine'])) {
    $fine_id = (int)($_POST['fine_id'] ?? 0);

    try {
        if ($fine_id <= 0) {
            throw new Exception("Invalid fine selected.");
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT fine_id, payment_status
            FROM fine
            WHERE fine_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$fine_id]);
        $fine = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fine) {
            throw new Exception("Fine record not found.");
        }

        if ($fine['payment_status'] === 'paid') {
            throw new Exception("This fine has already been paid.");
        }

        $stmt = $pdo->prepare("
            UPDATE fine
            SET payment_status = 'paid',
                payment_date = CURDATE()
            WHERE fine_id = ?
        ");
        $stmt->execute([$fine_id]);

        $pdo->commit();

        header("Location: fines.php?success=1");
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

if (isset($_GET['success'])) {
    $message = "Fine payment successfully recorded!";
}

// Fine statistics
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total_fines,
        COALESCE(SUM(CASE
            WHEN payment_status = 'pending' THEN amount
            ELSE 0
        END), 0) AS pending_amount,
        COALESCE(SUM(CASE
            WHEN payment_status = 'paid' THEN amount
            ELSE 0
        END), 0) AS collected_amount,
        SUM(payment_status = 'pending') AS pending_count
    FROM fine
")->fetch(PDO::FETCH_ASSOC);

// Fine records
$fines = $pdo->query("
    SELECT
        f.fine_id,
        f.issue_id,
        f.amount,
        f.payment_status,
        f.payment_date,
        f.created_at,
        b.title,
        m.name AS member_name,
        m.email,
        i.issue_date,
        i.due_date,
        i.return_date,
        DATEDIFF(i.return_date, i.due_date) AS late_days
    FROM fine f
    JOIN issue i ON f.issue_id = i.issue_id
    JOIN book b ON i.book_id = b.book_id
    JOIN member m ON i.member_id = m.member_id
    ORDER BY f.fine_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Fine Management | Library System</title>

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
    font-size: 27px;
}

.panel {
    margin-bottom: 24px;
}

.panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
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

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
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
}

.muted {
    color: #8790a2;
    font-size: 12px;
}

.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.pending {
    background: #fef3c7;
    color: #92400e;
}

.paid {
    background: #dcfce7;
    color: #15803d;
}

.pay-btn {
    background: #315fe8;
    color: white;
    border: 0;
    border-radius: 7px;
    padding: 9px 14px;
    font-weight: 600;
    cursor: pointer;
}

.pay-btn:hover {
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
        font-size: 21px;
    }
}
</style>
</head>

<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1>Fine Management</h1>
            <p>Track overdue fines and manage payment collection.</p>
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
            <small>Total Fine Records</small>
            <h2><?= (int)$stats['total_fines'] ?></h2>
        </div>

        <div class="stat-card">
            <small>Pending Payments</small>
            <h2><?= (int)$stats['pending_count'] ?></h2>
        </div>

        <div class="stat-card">
            <small>Pending Amount</small>
            <h2>₹<?= number_format((float)$stats['pending_amount'], 2) ?></h2>
        </div>

        <div class="stat-card">
            <small>Total Collected</small>
            <h2>₹<?= number_format((float)$stats['collected_amount'], 2) ?></h2>
        </div>

    </div>

    <!-- Fine Records -->
    <div class="panel">

        <div class="panel-heading">
            <h2>Fine Records</h2>

            <input
                type="search"
                id="fineSearch"
                class="search-box"
                placeholder="Search member or book..."
            >
        </div>

        <div class="table-wrap">

            <table id="fineTable">

                <thead>
                    <tr>
                        <th>Fine ID</th>
                        <th>Book</th>
                        <th>Member</th>
                        <th>Late Days</th>
                        <th>Fine Amount</th>
                        <th>Payment Status</th>
                        <th>Payment Date</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($fines as $fine): ?>

                    <tr>
                        <td>#<?= (int)$fine['fine_id'] ?></td>

                        <td>
                            <strong><?= e($fine['title']) ?></strong>
                            <span class="muted">
                                Issue #<?= (int)$fine['issue_id'] ?>
                            </span>
                        </td>

                        <td>
                            <strong><?= e($fine['member_name']) ?></strong>
                            <span class="muted"><?= e($fine['email']) ?></span>
                        </td>

                        <td><?= max(0, (int)$fine['late_days']) ?></td>

                        <td>
                            <strong>
                                ₹<?= number_format((float)$fine['amount'], 2) ?>
                            </strong>
                        </td>

                        <td>
                            <?php if ($fine['payment_status'] === 'pending'): ?>
                                <span class="status pending">Pending</span>
                            <?php else: ?>
                                <span class="status paid">Paid</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= $fine['payment_date']
                                ? e($fine['payment_date'])
                                : '-' ?>
                        </td>

                        <td>
                            <?php if ($fine['payment_status'] === 'pending'): ?>

                                <form method="POST"
                                      onsubmit="return confirm('Confirm payment received?');">

                                    <input
                                        type="hidden"
                                        name="fine_id"
                                        value="<?= (int)$fine['fine_id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="pay_fine"
                                        class="pay-btn"
                                    >
                                        Mark Paid
                                    </button>

                                </form>

                            <?php else: ?>

                                <span class="muted">Completed</span>

                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (!$fines): ?>
                    <tr>
                        <td colspan="8" class="empty">
                            No fine records found. Late returns will appear here.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>

        </div>
    </div>

</div>

<script>
document.getElementById('fineSearch').addEventListener('input', function() {

    const query = this.value.toLowerCase();

    document.querySelectorAll('#fineTable tbody tr').forEach(row => {

        row.style.display =
            row.innerText.toLowerCase().includes(query) ? '' : 'none';

    });

});
</script>

</body>
</html>

