
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {

        $sql = "SELECT staff_id, name, username, password_hash, role
                FROM staff
                WHERE username = :username
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);

        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($staff && password_verify($password, $staff['password_hash'])) {

            session_regenerate_id(true);

            $_SESSION['staff_id'] = $staff['staff_id'];
            $_SESSION['name'] = $staff['name'];
            $_SESSION['username'] = $staff['username'];
            $_SESSION['role'] = $staff['role'];

            header('Location: ../index.php');
            exit;

        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login | Library Management</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-box {
            background: white;
            width: 380px;
            max-width: 92%;
            padding: 35px;
            border-radius: 14px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.08);
        }

        h2 {
            color: #172554;
            text-align: center;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 28px;
        }

        label {
            display: block;
            margin: 15px 0 7px;
            font-weight: 600;
            color: #334155;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            background: #172554;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #1e40af;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 11px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }
    </style>
</head>

<body>

<div class="login-box">

    <h2>Library Management</h2>
    <p class="subtitle">Staff Login Portal</p>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Username</label>
        <input
            type="text"
            name="username"
            placeholder="Enter username"
            required
        >

        <label>Password</label>
        <input
            type="password"
            name="password"
            placeholder="Enter password"
            required
        >

        <button type="submit">Login</button>

    </form>

</div>

</body>
</html>

