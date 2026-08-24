<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: view.php');
    exit;
}

$incident_id = isset($_POST['incident_id']) ? (int) $_POST['incident_id'] : 0;
$comment = trim($_POST['comment'] ?? '');

if ($incident_id <= 0 || $comment === '') {
    header("Location: view.php?id=" . $incident_id . "&error=invalid");
    exit;
}

if (mb_strlen($comment) > 5000) {
    header("Location: view.php?id=" . $incident_id . "&error=too_long");
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO incident_comments
        (incident_id, user_id, comment, created_at)
        VALUES
        (:incident_id, :user_id, :comment, NOW())
    ");

    $user_id = $_SESSION['user_id'] ?? null;

    $stmt->execute([
        ':incident_id' => $incident_id,
        ':user_id'    => $user_id,
        ':comment'    => $comment
    ]);

    header("Location: view.php?id=" . $incident_id . "&comment_added=1");
    exit;

} catch (PDOException $e) {
    error_log("Incident comment error: " . $e->getMessage());

    header("Location: view.php?id=" . $incident_id . "&error=save_failed");
    exit;
}