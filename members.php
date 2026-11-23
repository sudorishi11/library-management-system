
<?php
require_once __DIR__ . '/auth/check_auth.php';
require_once 'config/database.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

// Add member
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $type = $_POST['member_type'] ?? '';

    if (
        $name === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        !in_array($type, ['student', 'faculty'], true)
    ) {
        $error = 'Please enter valid member details.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO member
                (name, email, phone, member_type)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $name,
                $email,
                $phone !== '' ? $phone : null,
                $type
            ]);

            header('Location: members.php?added=1');
            exit;

        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $error = 'This email address is already registered.';
            } else {
                $error = 'Unable to add member. Please try again.';
            }
        }
    }
}

if (isset($_GET['added'])) {
    $message = 'Member added successfully!';
}

// Search members
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT *
    FROM member
";

if ($search !== '') {
    $sql .= "
        WHERE name LIKE :search
        OR email LIKE :search
        OR phone LIKE :search
    ";
}

$sql .= " ORDER BY member_id DESC";

$stmt = $pdo->prepare($sql);

if ($search !== '') {
    $stmt->execute(['search' => "%$search%"]);
} else {
    $stmt->execute();
}

$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Members Management</title>

<style>
* { box-sizing: border-box; }

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

.sidebar h2 { margin-bottom: 35px; }

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
}

.main {
    margin-left: 230px;
    padding: 30px;
}

.panel {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

input, select {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    margin-top: 7px;
}

label { font-weight: bold; }

button {
    background: #2563eb;
    color: white;
    border: 0;
    padding: 12px 22px;
    border-radius: 6px;
    cursor: pointer;
    margin-top: 15px;
}

button:hover { background: #1d4ed8; }

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    min-width: 700px;
}

th, td {
    padding: 13px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

th { background: #f8fafc; }

.table-wrap { overflow-x: auto; }

.search-form {
    display: flex;
    gap: 10px;
}

.search-form input { flex: 1; }

.success { color: green; }
.error { color: #dc2626; }

.active-status {
    color: #15803d;
    font-weight: bold;
}

.suspended-status {
    color: #dc2626;
    font-weight: bold;
}

@media(max-width: 700px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 18px;
    }

    .form-grid { grid-template-columns: 1fr; }
}
</style>
</head>

<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">

    <h1>Members Management</h1>
    <p>Register and manage library members.</p>

    <section class="panel">

        <h2>Register New Member</h2>

        <?php if ($message): ?>
            <p class="success"><?= e($message) ?></p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="POST">

            <div class="form-grid">

                <div>
                    <label>Full Name *</label>
                    <input type="text" name="name" required>
                </div>

                <div>
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>

                <div>
                    <label>Phone</label>
                    <input type="text" name="phone">
                </div>

                <div>
                    <label>Member Type *</label>
                    <select name="member_type" required>
                        <option value="">Select Type</option>
                        <option value="student">Student</option>
                        <option value="faculty">Faculty</option>
                    </select>
                </div>

            </div>

            <button type="submit">Register Member</button>

        </form>

    </section>

    <section class="panel">

        <h2>All Members (<?= count($members) ?>)</h2>

        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                placeholder="Search by name, email or phone..."
                value="<?= e($search) ?>"
            >

            <button type="submit">Search</button>

        </form>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($members as $member): ?>

                        <tr>
                            <td><?= e($member['member_id']) ?></td>
                            <td><?= e($member['name']) ?></td>
                            <td><?= e($member['email']) ?></td>
                            <td><?= e($member['phone']) ?></td>
                            <td><?= e(ucfirst($member['member_type'])) ?></td>

                            <td class="<?= $member['status'] === 'active' ? 'active-status' : 'suspended-status' ?>">
                                <?= e(ucfirst($member['status'])) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="6">No members found.</td>
                        </tr>
                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</body>
</html>

