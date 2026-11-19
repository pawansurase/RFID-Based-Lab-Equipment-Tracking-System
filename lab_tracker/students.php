<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminId = (int) $_SESSION['admin_id'];
$message = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $message = 'Please refresh the page and try again.';
    } elseif (isset($_POST['add_student'])) {
        $name = trim($_POST['name'] ?? '');
        $studentNumber = trim($_POST['student_number'] ?? '');
        $cardUid = strtoupper(trim($_POST['card_uid'] ?? ''));

        if ($name === '' || $studentNumber === '' || $cardUid === '') {
            $message = 'Please fill in all student fields.';
        } else {
            try {
                $statement = $pdo->prepare(
                    'INSERT INTO students (admin_id, name, student_number, card_uid)
                     VALUES (:admin_id, :name, :student_number, :card_uid)'
                );
                $statement->execute([
                    'admin_id' => $adminId,
                    'name' => $name,
                    'student_number' => $studentNumber,
                    'card_uid' => $cardUid,
                ]);
                $message = 'Student added.';
            } catch (PDOException $error) {
                $message = $error->getCode() === '23000'
                    ? 'That student number or card is already in use in your lab.'
                    : 'Could not add the student.';
            }
        }
    } elseif (isset($_POST['delete_student'])) {
        $studentId = (int) ($_POST['student_id'] ?? 0);

        $statement = $pdo->prepare(
            'DELETE FROM students WHERE id = :id AND admin_id = :admin_id'
        );
        $statement->execute([
            'id' => $studentId,
            'admin_id' => $adminId,
        ]);

        $message = $statement->rowCount() > 0
            ? 'Student deleted.'
            : 'Student was not found.';
    }
}

$statement = $pdo->prepare(
    'SELECT id, name, student_number, card_uid, created_at
     FROM students
     WHERE admin_id = :admin_id
     ORDER BY name'
);
$statement->execute(['admin_id' => $adminId]);
$students = $statement->fetchAll();

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <title>Students</title>
</head>

<body>
    <?php include __DIR__ . '/includes/menu.php'; ?>
    <h1>Students</h1>
    <p><a href="dashboard.php">Dashboard</a> | <a href="logout.php">Log Out</a></p>

    <?php if ($message !== ''): ?>
        <p role="status"><?= escapeHtml($message) ?></p>
    <?php endif; ?>

    <h2>Add Student</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['csrf_token']) ?>">

        <label>
            Student Name
            <input type="text" name="name" maxlength="100" required>
        </label>
        <br><br>

        <label>
            Student Number
            <input type="text" name="student_number" maxlength="50" required>
        </label>
        <br><br>

        <label>
            RFID Card UID
            <input type="text" name="card_uid" maxlength="50" required>
        </label>
        <br><br>

        <button type="submit" name="add_student">Add Student</button>
    </form>

    <h2>Student List</h2>

    <?php if ($students === []): ?>
        <p>No students added yet.</p>
    <?php else: ?>
        <table border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Student Number</th>
                    <th>Card UID</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= escapeHtml($student['name']) ?></td>
                        <td><?= escapeHtml($student['student_number']) ?></td>
                        <td><?= escapeHtml($student['card_uid']) ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
                                <button type="submit" name="delete_student" onclick="return confirm('Delete this student?')">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>

</html>