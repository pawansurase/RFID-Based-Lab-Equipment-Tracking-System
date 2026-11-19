<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminId = (int) $_SESSION['admin_id'];

$statement = $pdo->prepare(
    "SELECT
        SUM(CASE WHEN action = 'issued' THEN 1 ELSE 0 END) AS issued_total,
        SUM(CASE WHEN action = 'returned' THEN 1 ELSE 0 END) AS returned_total
     FROM transactions
     WHERE admin_id = :admin_id"
);
$statement->execute(['admin_id' => $adminId]);
$totals = $statement->fetch();

$statement = $pdo->prepare(
    'SELECT
        t.action,
        t.event_time,
        s.name AS student_name,
        e.name AS equipment_name,
        s.student_number,
        e.asset_number
     FROM transactions AS t
     LEFT JOIN students AS s ON s.id = t.student_id
     LEFT JOIN equipment AS e ON e.id = t.equipment_id
     WHERE t.admin_id = :admin_id
     ORDER BY t.event_time DESC
     LIMIT 20'
);
$statement->execute(['admin_id' => $adminId]);
$transactions = $statement->fetchAll();

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
    <title>Lab Equipment Dashboard</title>
</head>

<body>
    <?php include __DIR__ . '/includes/menu.php'; ?>

    <h1>Lab Equipment Dashboard</h1>
    <p>Welcome, <?= escapeHtml($_SESSION['admin_name']) ?></p>

    <h2>Transaction Summary</h2>
    <p>Equipment Issued: <?= (int) ($totals['issued_total'] ?? 0) ?></p>
    <p>Equipment Returned: <?= (int) ($totals['returned_total'] ?? 0) ?></p>

    <h2>Recent Activity</h2>

    <?php if ($transactions === []): ?>
        <p>No transactions recorded yet.</p>
    <?php else: ?>
        <table border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Equipment</th>
                    <th>Status</th>
                    <th>Date and Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td>
                            <?= escapeHtml($transaction['student_name'] ?? 'Deleted student') ?>
                            <?php if (!empty($transaction['student_number'])): ?>
                                (<?= escapeHtml($transaction['student_number']) ?>)
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= escapeHtml($transaction['equipment_name'] ?? 'Deleted equipment') ?>
                            <?php if (!empty($transaction['asset_number'])): ?>
                                (<?= escapeHtml($transaction['asset_number']) ?>)
                            <?php endif; ?>
                        </td>
                        <td><?= escapeHtml(ucfirst($transaction['action'])) ?></td>
                        <td><?= escapeHtml($transaction['event_time']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>

</html>