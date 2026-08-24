<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: view.php');
    exit;
}

$commentId  = (int)($_POST['comment_id'] ?? 0);
$incidentId = (int)($_POST['incident_id'] ?? 0);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole   = strtolower($_SESSION['role'] ?? '');

if ($commentId <= 0 || $incidentId <= 0) {
    header(
        "Location: view.php?id={$incidentId}&comment_error=invalid"
    );
    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | Get Comment
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
        AND deleted_at IS NULL
        LIMIT 1
    ");

    $stmt->execute([
        $commentId,
        $incidentId
    ]);

    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$comment) {

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
        (int)$comment['user_id'] === $currentUserId;

    $isAdmin =
        in_array(
            $currentRole,
            ['admin', 'administrator'],
            true
        );

    if (!$isOwner && !$isAdmin) {

        http_response_code(403);

        die(
            'Access Denied: You cannot delete this investigation note.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Soft Delete
    |--------------------------------------------------------------------------
    */

    $deleteStmt = $pdo->prepare("
        UPDATE incident_comments

        SET
            deleted_at = NOW(),
            deleted_by = ?

        WHERE id = ?
        AND incident_id = ?
        AND deleted_at IS NULL
    ");

    $deleteStmt->execute([
        $currentUserId,
        $commentId,
        $incidentId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */

    $activityStmt = $pdo->prepare("
        INSERT INTO incident_activity
        (
            incident_id,
            user_id,
            action,
            comment,
            created_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            NOW()
        )
    ");

    $activityStmt->execute([
        $incidentId,
        $currentUserId,
        'Investigation Note Deleted',
        'Investigation note was deleted.'
    ]);

    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        "Location: view.php?id={$incidentId}&comment_deleted=1"
    );

    exit;

} catch (PDOException $e) {

    error_log(
        'Delete Investigation Note Error: ' .
        $e->getMessage()
    );

    header(
        "Location: view.php?id={$incidentId}&comment_error=delete_failed"
    );

    exit;
}