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

$statement = $pdo->prepare(
    'SELECT password_hash, photo FROM admins WHERE id = :id'
);
$statement->execute(['id' => $adminId]);
$admin = $statement->fetch();

if (!$admin) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmText = trim($_POST['confirm_text'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $message = 'Please refresh the page and try again.';
    } elseif ($confirmText !== 'DELETE') {
        $message = 'Type DELETE exactly to confirm.';
    } elseif (!password_verify($password, $admin['password_hash'])) {
        $message = 'Password is incorrect.';
    } else {
        $deleteStatement = $pdo->prepare(
            'DELETE FROM admins WHERE id = :id'
        );
        $deleteStatement->execute(['id' => $adminId]);

        if (!empty($admin['photo'])) {
            $photoPath = __DIR__ . '/uploads/' . basename($admin['photo']);
            if (is_file($photoPath)) {
                unlink($photoPath);
            }
        }

        $_SESSION = [];
        session_destroy();

        header('Location: signup.php?account_deleted=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <title>Delete Admin Account</title>
</head>

<body>
    <?php include __DIR__ . '/includes/menu.php'; ?>

    <h1>Permanent Delete</h1>
    <p>This permanently deletes your account, students, equipment, and transaction history.</p>
    <p>This action cannot be undone.</p>

    <?php if ($message !== ''): ?>
        <p role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <label>
            Account Password
            <input type="password" name="password" required>
        </label>
        <br><br>

        <label>
            Type DELETE to confirm
            <input type="text" name="confirm_text" required>
        </label>
        <br><br>

        <button type="submit">Permanently Delete Account</button>
    </form>

    <p><a href="dashboard.php">Cancel and return to dashboard</a></p>
</body>

</html>