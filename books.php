
<?php
require_once __DIR__ . '/auth/check_auth.php';
require_once 'config/database.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

// Add a new book
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isbn = trim($_POST['isbn'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $publisher = trim($_POST['publisher'] ?? '');
    $year = $_POST['publish_year'] ?? '';
    $category = (int)($_POST['category_id'] ?? 0);
    $copies = (int)($_POST['total_copies'] ?? 0);

    if ($title === '' || $author === '' || $category <= 0 || $copies < 1) {
        $error = 'Please fill all required fields correctly.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO book
                (isbn, title, author, publisher, publish_year,
                 category_id, total_copies, available_copies)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $isbn !== '' ? $isbn : null,
                $title,
                $author,
                $publisher !== '' ? $publisher : null,
                $year !== '' ? (int)$year : null,
                $category,
                $copies,
                $copies
            ]);

            header('Location: books.php?added=1');
            exit;

        } catch (PDOException $ex) {
            $error = 'Could not add book. Check ISBN uniqueness and category.';
        }
    }
}

if (isset($_GET['added'])) {
    $message = 'Book added successfully!';
}

// Fetch categories
$categories = $pdo->query("
    SELECT category_id, category_name
    FROM category
    ORDER BY category_name
")->fetchAll(PDO::FETCH_ASSOC);

// Search books
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT b.*, c.category_name
    FROM book b
    JOIN category c ON b.category_id = c.category_id
";

if ($search !== '') {
    $sql .= "
        WHERE b.title LIKE :search
        OR b.author LIKE :search
        OR b.isbn LIKE :search
    ";
}

$sql .= " ORDER BY b.book_id DESC";

$stmt = $pdo->prepare($sql);

if ($search !== '') {
    $stmt->execute(['search' => "%$search%"]);
} else {
    $stmt->execute();
}

$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Books</title>

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
    min-width: 750px;
}

th, td {
    padding: 13px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

th { background: #f8fafc; }

.table-wrap { overflow-x: auto; }

.success { color: green; }
.error { color: #dc2626; }

.search-form {
    display: flex;
    gap: 10px;
}

.search-form input { flex: 1; }

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

    <h1>Books Management</h1>
    <p>Manage your library collection.</p>

    <section class="panel">
        <h2>Add New Book</h2>

        <?php if ($message): ?>
            <p class="success"><?= e($message) ?></p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <div class="form-grid">

                <div>
                    <label>Book Title *</label>
                    <input type="text" name="title" required>
                </div>

                <div>
                    <label>Author *</label>
                    <input type="text" name="author" required>
                </div>

                <div>
                    <label>ISBN</label>
                    <input type="text" name="isbn">
                </div>

                <div>
                    <label>Publisher</label>
                    <input type="text" name="publisher">
                </div>

                <div>
                    <label>Publication Year</label>
                    <input type="number" name="publish_year"
                           min="0" max="9999">
                </div>

                <div>
                    <label>Category *</label>
                    <select name="category_id" required>
                        <option value="">Select Category</option>

                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat['category_id']) ?>">
                                <?= e($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div>
                    <label>Total Copies *</label>
                    <input type="number" name="total_copies"
                           min="1" value="1" required>
                </div>

            </div>

            <button type="submit">Add Book</button>
        </form>
    </section>

    <section class="panel">

        <h2>All Books (<?= count($books) ?>)</h2>

        <form method="GET" class="search-form">
            <input type="text" name="search"
                   placeholder="Search by title, author or ISBN..."
                   value="<?= e($search) ?>">

            <button type="submit">Search</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Total</th>
                        <th>Available</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td><?= e($book['book_id']) ?></td>
                            <td><?= e($book['title']) ?></td>
                            <td><?= e($book['author']) ?></td>
                            <td><?= e($book['category_name']) ?></td>
                            <td><?= e($book['total_copies']) ?></td>
                            <td><?= e($book['available_copies']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($books)): ?>
                        <tr>
                            <td colspan="6">No books found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>

</main>

</body>
</html>

