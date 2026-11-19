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
    } elseif (isset($_POST['add_equipment'])) {
        $name = trim($_POST['name'] ?? '');
        $assetNumber = trim($_POST['asset_number'] ?? '');
        $cardUid = strtoupper(trim($_POST['card_uid'] ?? ''));

        if ($name === '' || $assetNumber === '' || $cardUid === '') {
            $message = 'Please fill in all equipment fields.';
        } else {
            try {
                $statement = $pdo->prepare(
                    'INSERT INTO equipment (admin_id, name, asset_number, card_uid)
                     VALUES (:admin_id, :name, :asset_number, :card_uid)'
                );
                $statement->execute([
                    'admin_id' => $adminId,
                    'name' => $name,
                    'asset_number' => $assetNumber,
                    'card_uid' => $cardUid,
                ]);
                $message = 'Equipment added.';
            } catch (PDOException $error) {
                $message = $error->getCode() === '23000'
                    ? 'That asset number or card is already in use in your lab.'
                    : 'Could not add the equipment.';
            }
        }
    } elseif (isset($_POST['delete_equipment'])) {
        $equipmentId = (int) ($_POST['equipment_id'] ?? 0);

        $statement = $pdo->prepare(
            'DELETE FROM equipment WHERE id = :id AND admin_id = :admin_id'
        );
        $statement->execute([
            'id' => $equipmentId,
            'admin_id' => $adminId,
        ]);

        $message = $statement->rowCount() > 0
            ? 'Equipment deleted.'
            : 'Equipment was not found.';
    }
}

$statement = $pdo->prepare(
    'SELECT id, name, asset_number, card_uid, status
     FROM equipment
     WHERE admin_id = :admin_id
     ORDER BY name'
);
$statement->execute(['admin_id' => $adminId]);
$equipmentList = $statement->fetchAll();

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
    <title>Equipment</title>
</head>

<body>
    <?php include __DIR__ . '/includes/menu.php'; ?>
    <h1>Equipment</h1>
    <p><a href="dashboard.php">Dashboard</a> | <a href="logout.php">Log Out</a></p>

    <?php if ($message !== ''): ?>
        <p role="status"><?= escapeHtml($message) ?></p>
    <?php endif; ?>

    <h2>Add Equipment</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['csrf_token']) ?>">

        <label>
            Equipment Name
            <input type="text" name="name" maxlength="150" required>
        </label>
        <br><br>

        <label>
            Asset Number
            <input type="text" name="asset_number" maxlength="50" required>
        </label>
        <br><br>

        <label>
            RFID Card UID
            <input type="text" name="card_uid" maxlength="50" required>
        </label>
        <br><br>

        <button type="submit" name="add_equipment">Add Equipment</button>
    </form>

    <h2>Equipment List</h2>

    <?php if ($equipmentList === []): ?>
        <p>No equipment added yet.</p>
    <?php else: ?>
        <table border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Asset Number</th>
                    <th>Card UID</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipmentList as $item): ?>
                    <tr>
                        <td><?= escapeHtml($item['name']) ?></td>
                        <td><?= escapeHtml($item['asset_number']) ?></td>
                        <td><?= escapeHtml($item['card_uid']) ?></td>
                        <td><?= escapeHtml($item['status']) ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="equipment_id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" name="delete_equipment"
                                    onclick="return confirm('Delete this equipment?')">
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