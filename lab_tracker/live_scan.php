<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
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
    <title>Live Card Scan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            color: #172033;
        }

        .scan-card {
            max-width: 620px;
            margin: 50px auto;
            padding: 28px;
            border-radius: 16px;
            background: #f2f6ff;
            text-align: center;
        }

        .uid {
            font-size: 30px;
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        .timer {
            margin: 18px 0;
            font-size: 54px;
            font-weight: bold;
        }

        .status {
            color: #345;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/includes/menu.php'; ?>

    <main class="scan-card">
        <h1>Live Card Scan</h1>
        <p class="status" id="message">Waiting for a card scan.</p>

        <h2>Latest Card Number</h2>
        <p class="uid" id="cardUid">—</p>
        <p id="cardInfo">No card scanned yet.</p>

        <h2>Student Scan Timer</h2>
        <p class="timer" id="timer">00:00</p>
        <p id="studentInfo">No active scan session.</p>
    </main>

    <script>
        const messageElement = document.getElementById('message');
        const cardUidElement = document.getElementById('cardUid');
        const cardInfoElement = document.getElementById('cardInfo');
        const timerElement = document.getElementById('timer');
        const studentInfoElement = document.getElementById('studentInfo');

        async function refreshScanStatus() {
            try {
                const response = await fetch('api/get_live.php', {
                    cache: 'no-store'
                });
                const data = await response.json();

                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'Could not load scan status.');
                }

                if (data.latest_scan) {
                    cardUidElement.textContent = data.latest_scan.card_uid;
                    cardInfoElement.textContent =
                        data.latest_scan.card_type + ' card · ' +
                        data.latest_scan.scanned_at;
                } else {
                    cardUidElement.textContent = '—';
                    cardInfoElement.textContent = 'No card scanned yet.';
                }

                if (data.active_session) {
                    const seconds = Math.max(0, Number(data.seconds_left));
                    const minutesText = String(Math.floor(seconds / 60)).padStart(2, '0');
                    const secondsText = String(seconds % 60).padStart(2, '0');

                    timerElement.textContent = minutesText + ':' + secondsText;
                    studentInfoElement.textContent =
                        'Student: ' + (data.student || 'Unknown');
                    messageElement.textContent =
                        'Scan the equipment card before the timer expires.';
                } else {
                    timerElement.textContent = '00:00';
                    studentInfoElement.textContent = 'No active scan session.';
                    messageElement.textContent = 'Waiting for a student card scan.';
                }
            } catch (error) {
                messageElement.textContent = error.message;
            }
        }

        refreshScanStatus();
        setInterval(refreshScanStatus, 2000);
    </script>
</body>

</html>