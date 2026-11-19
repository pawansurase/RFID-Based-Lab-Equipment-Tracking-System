<?php
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

function sendJson(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(405, ['ok' => false, 'message' => 'Use POST.']);
}

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (strlen($apiKey) !== 64) {
    sendJson(401, ['ok' => false, 'message' => 'Missing or invalid device key.']);
}

$statement = $pdo->prepare('SELECT id FROM admins WHERE api_key = :api_key');
$statement->execute(['api_key' => $apiKey]);
$admin = $statement->fetch();

if (!$admin) {
    sendJson(401, ['ok' => false, 'message' => 'Device key not recognized.']);
}

$body = json_decode(file_get_contents('php://input'), true);
$cardUid = strtoupper(trim((string) ($body['uid'] ?? '')));

if ($cardUid === '' || strlen($cardUid) > 50) {
    sendJson(400, ['ok' => false, 'message' => 'Send a valid card UID.']);
}

$adminId = (int) $admin['id'];

$studentStatement = $pdo->prepare(
    'SELECT id, name FROM students
     WHERE admin_id = :admin_id AND UPPER(card_uid) = :card_uid'
);
$studentStatement->execute([
    'admin_id' => $adminId,
    'card_uid' => $cardUid,
]);
$student = $studentStatement->fetch();

if ($student) {
    $statement = $pdo->prepare(
        'INSERT INTO scan_events (admin_id, card_uid, card_type)
         VALUES (:admin_id, :card_uid, "student")'
    );
    $statement->execute([
        'admin_id' => $adminId,
        'card_uid' => $cardUid,
    ]);

    $statement = $pdo->prepare(
        'INSERT INTO scan_sessions (admin_id, student_id, expires_at)
         VALUES (:admin_id, :student_id, DATE_ADD(NOW(), INTERVAL 90 SECOND))
         ON DUPLICATE KEY UPDATE
             student_id = VALUES(student_id),
             expires_at = VALUES(expires_at)'
    );
    $statement->execute([
        'admin_id' => $adminId,
        'student_id' => $student['id'],
    ]);

    $statement = $pdo->prepare(
        'SELECT UNIX_TIMESTAMP(expires_at) AS expires_at
         FROM scan_sessions WHERE admin_id = :admin_id'
    );
    $statement->execute(['admin_id' => $adminId]);
    $session = $statement->fetch();

    sendJson(200, [
        'ok' => true,
        'type' => 'student',
        'message' => 'Student scanned. Scan equipment within 90 seconds.',
        'student' => $student['name'],
        'expires_at' => (int) $session['expires_at'],
    ]);
}

$equipmentStatement = $pdo->prepare(
    'SELECT id, name, status, issued_to_student_id
     FROM equipment
     WHERE admin_id = :admin_id AND UPPER(card_uid) = :card_uid'
);
$equipmentStatement->execute([
    'admin_id' => $adminId,
    'card_uid' => $cardUid,
]);
$equipment = $equipmentStatement->fetch();

$statement = $pdo->prepare(
    'INSERT INTO scan_events (admin_id, card_uid, card_type)
     VALUES (:admin_id, :card_uid, :card_type)'
);
$statement->execute([
    'admin_id' => $adminId,
    'card_uid' => $cardUid,
    'card_type' => $equipment ? 'equipment' : 'unknown',
]);

if (!$equipment) {
    sendJson(404, ['ok' => false, 'message' => 'Card is not registered.']);
}

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare(
        'SELECT student_id
         FROM scan_sessions
         WHERE admin_id = :admin_id AND expires_at > NOW()
         FOR UPDATE'
    );
    $statement->execute(['admin_id' => $adminId]);
    $activeSession = $statement->fetch();

    if (!$activeSession) {
        $pdo->commit();
        sendJson(409, [
            'ok' => false,
            'message' => 'No active student scan. Scan the student card first.',
        ]);
    }

    $studentId = (int) $activeSession['student_id'];
    $action = '';

    if ($equipment['status'] === 'available') {
        $statement = $pdo->prepare(
            "UPDATE equipment
             SET status = 'issued', issued_to_student_id = :student_id
             WHERE id = :id AND admin_id = :admin_id"
        );
        $statement->execute([
            'student_id' => $studentId,
            'id' => $equipment['id'],
            'admin_id' => $adminId,
        ]);
        $action = 'issued';
    } elseif ((int) $equipment['issued_to_student_id'] === $studentId) {
        $statement = $pdo->prepare(
            "UPDATE equipment
             SET status = 'available', issued_to_student_id = NULL
             WHERE id = :id AND admin_id = :admin_id"
        );
        $statement->execute([
            'id' => $equipment['id'],
            'admin_id' => $adminId,
        ]);
        $action = 'returned';
    } else {
        $pdo->commit();
        sendJson(409, [
            'ok' => false,
            'message' => 'This equipment is issued to another student.',
        ]);
    }

    $statement = $pdo->prepare(
        'INSERT INTO transactions (admin_id, student_id, equipment_id, action)
         VALUES (:admin_id, :student_id, :equipment_id, :action)'
    );
    $statement->execute([
        'admin_id' => $adminId,
        'student_id' => $studentId,
        'equipment_id' => $equipment['id'],
        'action' => $action,
    ]);

    $statement = $pdo->prepare(
        'DELETE FROM scan_sessions WHERE admin_id = :admin_id'
    );
    $statement->execute(['admin_id' => $adminId]);

    $pdo->commit();

    sendJson(200, [
        'ok' => true,
        'type' => $action,
        'message' => $action === 'issued'
            ? 'Equipment issued successfully.'
            : 'Equipment returned successfully.',
        'equipment' => $equipment['name'],
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendJson(500, ['ok' => false, 'message' => 'Could not save the scan.']);
}