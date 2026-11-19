<?php
session_start();
require 'config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $labName = trim($_POST['lab_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $photoName = null;

    if ($name === '' || $email === '' || $labName === '' || $password === '') {
        $message = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
    } else {
        try {
            if (!empty($_FILES['photo']['name'])) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                $fileType = mime_content_type($_FILES['photo']['tmp_name']);

                if (!in_array($fileType, $allowedTypes, true)) {
                    throw new RuntimeException('Photo must be JPG, PNG, or WEBP.');
                }

                if ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
                    throw new RuntimeException('Photo must be smaller than 2 MB.');
                }

                $uploadDirectory = __DIR__ . '/uploads/';
                if (!is_dir($uploadDirectory)) {
                    mkdir($uploadDirectory, 0755, true);
                }

                $extension = match ($fileType) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                };

                $photoName = bin2hex(random_bytes(16)) . '.' . $extension;
                $destination = $uploadDirectory . $photoName;

                if (!move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                    throw new RuntimeException('Could not save the photo. Check the uploads folder.');
                }
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $apiKey = bin2hex(random_bytes(32));

            $statement = $pdo->prepare(
                'INSERT INTO admins (name, email, photo, lab_name, password_hash, api_key)
                 VALUES (:name, :email, :photo, :lab_name, :password_hash, :api_key)'
            );

            $statement->execute([
                'name' => $name,
                'email' => $email,
                'photo' => $photoName,
                'lab_name' => $labName,
                'password_hash' => $passwordHash,
                'api_key' => $apiKey,
            ]);

            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $pdo->lastInsertId();
            $_SESSION['admin_name'] = $name;

            header('Location: dashboard.php');
            exit;
        } catch (PDOException $error) {
            $message = $error->getCode() === '23000'
                ? 'This email is already registered.'
                : 'Could not create the account. Please try again.';
        } catch (RuntimeException $error) {
            $message = $error->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <title>Admin Sign Up</title>
</head>

<body>
    <h1>Create Admin Account</h1>

    <?php if ($message !== ''): ?>
        <p role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label>
            Name
            <input type="text" name="name" required maxlength="100">
        </label>
        <br><br>

        <label>
            Email
            <input type="email" name="email" required maxlength="190">
        </label>
        <br><br>

        <label>
            Photo
            <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        </label>
        <br><br>

        <label>
            Lab Name
            <input type="text" name="lab_name" required maxlength="150">
        </label>
        <br><br>

        <label>
            Password
            <input type="password" name="password" required minlength="8">
        </label>
        <br><br>

        <label>
            Confirm Password
            <input type="password" name="confirm_password" required minlength="8">
        </label>
        <br><br>

        <button type="submit">Sign Up</button>
    </form>

    <p>Already have an account? <a href="login.php">Log In</a></p>
</body>

</html>