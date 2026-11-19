<?php
session_start();
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please log in.']);
    exit;
}

$adminId = (int) $_SESSION['admin_id'];

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare(
        'SELECT student_id, expires_at,
                TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS seconds_left
         FROM scan_sessions
         WHERE admin_id = :admin_id
         FOR UPDATE'
    );
    $statement->execute(['admin_id' => $adminId]);
    $scanSession = $statement->fetch();

    $active = false;
    $secondsLeft = 0;
    $studentName = null;

    if ($scanSession && (int) $scanSession['seconds_left'] > 0) {
        $active = true;
        $secondsLeft = (int) $scanSession['seconds_left'];

        $statement = $pdo->prepare(
            'SELECT name FROM students WHERE id = :student_id'
        );
        $statement->execute(['student_id' => $scanSession['student_id']]);
        $studentName = $statement->fetchColumn() ?: null;
    } elseif ($scanSession) {
        $statement = $pdo->prepare(
            "INSERT INTO transactions (admin_id, student_id, action)
             VALUES (:admin_id, :student_id, 'timeout')"
        );
        $statement->execute([
            'admin_id' => $adminId,
            'student_id' => $scanSession['student_id'],
        ]);

        $statement = $pdo->prepare(
            'DELETE FROM scan_sessions WHERE admin_id = :admin_id'
        );
        $statement->execute(['admin_id' => $adminId]);
    }

    $pdo->commit();

    $statement = $pdo->prepare(
        'SELECT card_uid, card_type, scanned_at
         FROM scan_events
         WHERE admin_id = :admin_id
         ORDER BY id DESC
         LIMIT 1'
    );
    $statement->execute(['admin_id' => $adminId]);
    $latestScan = $statement->fetch() ?: null;

    echo json_encode([
        'ok' => true,
        'latest_scan' => $latestScan,
        'active_session' => $active,
        'student' => $studentName,
        'seconds_left' => $secondsLeft,
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Could not load scan status.']);
}