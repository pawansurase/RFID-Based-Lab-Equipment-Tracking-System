<?php
session_start();
require 'config.php';

$message = '';

if (isset($_GET['registered'])) {
    $message = 'Account created. Please log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $password === '') {
        $message = 'Please enter your name and password.';
    } else {
        $statement = $pdo->prepare(
            'SELECT id, name, password_hash FROM admins WHERE name = :name'
        );
        $statement->execute(['name' => $name]);
        $admins = $statement->fetchAll();

        $matchedAdmin = null;

        foreach ($admins as $admin) {
            if (password_verify($password, $admin['password_hash'])) {
                $matchedAdmin = $admin;
                break;
            }
        }

        if ($matchedAdmin !== null) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $matchedAdmin['id'];
            $_SESSION['admin_name'] = $matchedAdmin['name'];

            header('Location: dashboard.php');
            exit;
        }

        $message = 'Incorrect name or password.';
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <title>Admin Log In</title>
</head>

<body>
    <h1>Admin Log In</h1>

    <?php if ($message !== ''): ?>
        <p role="alert">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="post">
        <label>
            Name
            <input type="text" name="name" required maxlength="100">
        </label>
        <br><br>

        <label>
            Password
            <input type="password" name="password" required>
        </label>
        <br><br>

        <button type="submit">Log In</button>
    </form>

    <p>Do not have an account? <a href="signup.php">Sign Up</a></p>
</body>

</html>