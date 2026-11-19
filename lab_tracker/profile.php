<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminId = (int) $_SESSION['admin_id'];
$message = '';

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $labName = trim($_POST['lab_name'] ?? '');

    if ($name === '' || $email === '' || $labName === '') {
        $message = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } else {
        try {
            $statement = $pdo->prepare(
                'SELECT photo FROM admins WHERE id = :id'
            );
            $statement->execute(['id' => $adminId]);
            $currentAdmin = $statement->fetch();

            if (!$currentAdmin) {
                session_destroy();
                header('Location: login.php');
                exit;
            }

            $photoName = $currentAdmin['photo'];

            if (!empty($_FILES['photo']['name'])) {
                if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Photo upload failed.');
                }

                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                $fileType = mime_content_type($_FILES['photo']['tmp_name']);

                if (!in_array($fileType, $allowedTypes, true)) {
                    throw new RuntimeException('Photo must be JPG, PNG, or WEBP.');
                }

                if ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
                    throw new RuntimeException('Photo must be smaller than 2 MB.');
                }

                $extension = match ($fileType) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                };

                $photoName = bin2hex(random_bytes(16)) . '.' . $extension;
                $uploadDirectory = __DIR__ . '/uploads/';

                if (!is_dir($uploadDirectory)) {
                    mkdir($uploadDirectory, 0755, true);
                }

                if (
                    !move_uploaded_file(
                        $_FILES['photo']['tmp_name'],
                        $uploadDirectory . $photoName
                    )
                ) {
                    throw new RuntimeException('Could not save the photo.');
                }
            }

            $statement = $pdo->prepare(
                'UPDATE admins
                 SET name = :name, email = :email, lab_name = :lab_name, photo = :photo
                 WHERE id = :id'
            );
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'lab_name' => $labName,
                'photo' => $photoName,
                'id' => $adminId,
            ]);

            $_SESSION['admin_name'] = $name;
            $message = 'Profile updated.';
        } catch (PDOException $error) {
            $message = $error->getCode() === '23000'
                ? 'That email address is already used by another admin.'
                : 'Could not update the profile.';
        } catch (RuntimeException $error) {
            $message = $error->getMessage();
        }
    }
}

$statement = $pdo->prepare(
    'SELECT name, email, lab_name, photo, api_key FROM admins WHERE id = :id'
);
$statement->execute(['id' => $adminId]);
$admin = $statement->fetch();

if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <title>Admin Profile</title>
</head>

<body>
    <h1>Admin Profile</h1>
    <h2>ESP32 Device Key</h2>
    <p>Copy this key into your ESP32 program:</p>

    <input type="text" value="<?= htmlspecialchars($admin['api_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly
        style="width: 420px;">
    <p><a href="dashboard.php">Dashboard</a> | <a href="logout.php">Log Out</a></p>

    <?php if ($message !== ''): ?>
        <p role="status"><?= escapeHtml($message) ?></p>
    <?php endif; ?>

    <?php if (!empty($admin['photo'])): ?>
        <p>
            <img src="uploads/<?= rawurlencode($admin['photo']) ?>" alt="Admin profile photo" width="120">
        </p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label>
            Name
            <input type="text" name="name" required maxlength="100" value="<?= escapeHtml($admin['name']) ?>">
        </label>
        <br><br>

        <label>
            Email
            <input type="email" name="email" required maxlength="190" value="<?= escapeHtml($admin['email']) ?>">
        </label>
        <br><br>

        <label>
            Lab Name
            <input type="text" name="lab_name" required maxlength="150" value="<?= escapeHtml($admin['lab_name']) ?>">
        </label>
        <br><br>

        <label>
            Change Photo
            <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        </label>
        <p>Maximum photo size: 2 MB.</p>

        <button type="submit">Save Profile</button>
    </form>
</body>

</html>