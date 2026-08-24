<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: view.php');
    exit;
}

$commentId = (int)($_POST['comment_id'] ?? 0);
$incidentId = (int)($_POST['incident_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($commentId <= 0 || $incidentId <= 0) {
    header(
        "Location: view.php?id={$incidentId}&comment_error=invalid"
    );
    exit;
}

if ($comment === '') {
    header(
        "Location: view.php?id={$incidentId}&comment_error=empty"
    );
    exit;
}

if (mb_strlen($comment) > 5000) {
    header(
        "Location: view.php?id={$incidentId}&comment_error=length"
    );
    exit;
}

$currentUserId =
    (int)($_SESSION['user_id'] ?? 0);

$currentRole =
    strtolower($_SESSION['role'] ?? '');

try {

    /*
    |--------------------------------------------------------------------------
    | Find Comment
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            incident_id,
            user_id,
            comment

        FROM incident_comments

        WHERE id = ?
        AND incident_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $commentId,
        $incidentId
    ]);

    $existing = $stmt->fetch();

    if (!$existing) {

        header(
            "Location: view.php?id={$incidentId}&comment_error=not_found"
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Permission
    |--------------------------------------------------------------------------
    */

    $isOwner =
        (int)$existing['user_id']
        ===
        $currentUserId;

    $isAdmin =
        in_array(
            $currentRole,
            ['admin', 'administrator'],
            true
        );

    if (!$isOwner && !$isAdmin) {

        http_response_code(403);

        die(
            'Access Denied: You cannot edit this investigation note.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE incident_comments

        SET comment = ?

        WHERE id = ?
        AND incident_id = ?
    ");

    $update->execute([
        $comment,
        $commentId,
        $incidentId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        "Location: view.php?id={$incidentId}&comment_updated=1"
    );

    exit;

} catch (PDOException $e) {

    error_log(
        'Edit Comment Error: ' .
        $e->getMessage()
    );

    header(
        "Location: view.php?id={$incidentId}&comment_error=save_failed"
    );

    exit;
}