<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireLogin();

$incidentId = (int)($_GET['id'] ?? 0);

if ($incidentId <= 0) {
    die('Invalid incident ID.');
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole   = strtolower($_SESSION['role'] ?? '');

/*
|--------------------------------------------------------------------------
| Get Incident
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        i.*,
        c.name AS category_name,

        reporter.name AS reporter_name,
        reporter.email AS reporter_email,

        analyst.name AS analyst_name,
        analyst.email AS analyst_email

    FROM incidents i

    LEFT JOIN incident_categories c
        ON i.category_id = c.id

    LEFT JOIN users reporter
        ON i.reported_by = reporter.id

    LEFT JOIN users analyst
        ON i.assigned_to = analyst.id

    WHERE i.id = ?

    LIMIT 1
");

$stmt->execute([$incidentId]);

$incident = $stmt->fetch();

if (!$incident) {
    http_response_code(404);
    die('Incident not found.');
}

/*
|--------------------------------------------------------------------------
| Access Control
|--------------------------------------------------------------------------
*/

if ($currentRole === 'analyst') {

    if (
        (int)$incident['assigned_to'] !== $currentUserId
    ) {
        http_response_code(403);
        die('Access Denied: This incident is not assigned to you.');
    }

} elseif ($currentRole !== 'admin') {

    http_response_code(403);
    die('Access Denied.');
}

/*
|--------------------------------------------------------------------------
| Activity History
|--------------------------------------------------------------------------
*/

$activityStmt = $pdo->prepare("
    SELECT
        ia.*,
        u.name AS user_name,
        u.email AS user_email,
        u.role AS user_role

    FROM incident_activity ia

    LEFT JOIN users u
        ON ia.user_id = u.id

    WHERE ia.incident_id = ?

    ORDER BY ia.created_at ASC
");

$activityStmt->execute([$incidentId]);

$activities = $activityStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Investigation Comments
|--------------------------------------------------------------------------
*/

$commentStmt = $pdo->prepare("
    SELECT
        ic.id,
        ic.user_id,
        ic.comment,
        ic.created_at,
        ic.deleted_at,

        u.name AS user_name,
        u.email AS user_email,
        u.role AS user_role

    FROM incident_comments ic

    LEFT JOIN users u
        ON ic.user_id = u.id

    WHERE ic.incident_id = ?
    AND ic.deleted_at IS NULL

    ORDER BY ic.created_at DESC
");

$commentStmt->execute([$incidentId]);

$comments = $commentStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Evidence
|--------------------------------------------------------------------------
*/

$evidenceStmt = $pdo->prepare("
    SELECT
        ie.id,
        ie.original_name,
        ie.stored_name,
        ie.mime_type,
        ie.file_size,
        ie.file_hash,
        ie.created_at,

        u.name AS uploader_name,
        u.email AS uploader_email,
        u.role AS uploader_role

    FROM incident_evidence ie

    LEFT JOIN users u
        ON ie.uploaded_by = u.id

    WHERE ie.incident_id = ?

    ORDER BY ie.created_at DESC
");

$evidenceStmt->execute([$incidentId]);

$evidenceFiles = $evidenceStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Success Messages
|--------------------------------------------------------------------------
*/

$successMessage = '';

if (isset($_GET['created'])) {

    $successMessage =
        'Incident ' .
        $incident['ticket_number'] .
        ' was created successfully.';

} elseif (isset($_GET['updated'])) {

    $successMessage =
        'Incident was updated successfully.';

} elseif (isset($_GET['assigned'])) {

    $successMessage =
        'Incident assignment was updated successfully.';

} elseif (isset($_GET['status_updated'])) {

    $successMessage =
        'Incident status was updated successfully.';

} elseif (isset($_GET['comment_added'])) {

    $successMessage =
        'Investigation note was added successfully.';

} elseif (isset($_GET['comment_updated'])) {

    $successMessage =
        'Investigation note was updated successfully.';

} elseif (isset($_GET['comment_deleted'])) {

    $successMessage =
        'Investigation note was deleted successfully.';

} elseif (isset($_GET['evidence_added'])) {

    $successMessage =
        'Evidence uploaded successfully.';
}

/*
|--------------------------------------------------------------------------
| Comment Errors
|--------------------------------------------------------------------------
*/

$commentError = '';

if (isset($_GET['comment_error'])) {

    switch ($_GET['comment_error']) {

        case 'empty':
            $commentError =
                'Investigation note cannot be empty.';
            break;

        case 'length':
            $commentError =
                'Investigation note cannot exceed 5000 characters.';
            break;

        case 'invalid':
            $commentError =
                'Invalid investigation note request.';
            break;

        case 'not_found':
            $commentError =
                'Investigation note was not found.';
            break;

        case 'unauthorized':
            $commentError =
                'You are not authorized to modify this note.';
            break;

        case 'save_failed':
            $commentError =
                'Unable to update investigation note.';
            break;

        case 'delete_failed':
            $commentError =
                'Unable to delete investigation note.';
            break;

        default:
            $commentError =
                'Unable to process investigation note.';
    }
}

/*
|--------------------------------------------------------------------------
| Evidence Errors
|--------------------------------------------------------------------------
*/

$evidenceError = '';

if (isset($_GET['evidence_error'])) {

    switch ($_GET['evidence_error']) {

        case 'no_file':
            $evidenceError =
                'Please select an evidence file.';
            break;

        case 'upload':
            $evidenceError =
                'File upload failed.';
            break;

        case 'size':
            $evidenceError =
                'File size must be between 1 byte and 10 MB.';
            break;

        case 'type':
            $evidenceError =
                'This file extension is not allowed.';
            break;

        case 'mime':
            $evidenceError =
                'File content type is not allowed.';
            break;

        case 'hash':
            $evidenceError =
                'Unable to calculate file integrity hash.';
            break;

        case 'save':
            $evidenceError =
                'Unable to save the uploaded file.';
            break;

        case 'database':
            $evidenceError =
                'Unable to save evidence information to database.';
            break;

        default:
            $evidenceError =
                'Evidence upload failed.';
    }
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

$statusLabels = [
    'open'        => 'Open',
    'in_progress' => 'In Progress',
    'resolved'    => 'Resolved',
    'closed'      => 'Closed'
];

$statusClass = [
    'open'        => 'open',
    'in_progress' => 'in-progress',
    'resolved'    => 'resolved',
    'closed'      => 'closed'
];

$severityClass = [
    'low'      => 'low',
    'medium'   => 'medium',
    'high'     => 'high',
    'critical' => 'critical'
];

function formatFileSize(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = [
        'B',
        'KB',
        'MB',
        'GB'
    ];

    $index = 0;

    while (
        $bytes >= 1024 &&
        $index < count($units) - 1
    ) {
        $bytes /= 1024;
        $index++;
    }

    return number_format(
        $bytes,
        $index === 0 ? 0 : 2
    ) . ' ' . $units[$index];
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    <?= e($incident['ticket_number']) ?>
    | Security Incident
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #0f172a;
    color: #f8fafc;
}

/* NAVBAR */

.navbar {
    background: #111827;
    border-bottom: 1px solid #263244;
    padding: 18px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.brand {
    font-size: 18px;
    font-weight: 700;
}

.navbar a {
    color: #cbd5e1;
    text-decoration: none;
    margin-left: 18px;
}

.navbar a:hover {
    color: white;
}

/* CONTAINER */

.container {
    max-width: 1250px;
    margin: auto;
    padding: 30px;
}

/* MESSAGES */

.success,
.error {
    padding: 15px 18px;
    border-radius: 10px;
    margin-bottom: 22px;
    font-weight: 600;
}

.success {
    background: #052e16;
    border: 1px solid #166534;
    color: #86efac;
}

.error {
    background: #450a0a;
    border: 1px solid #7f1d1d;
    color: #fecaca;
}

/* INCIDENT HEADER */

.incident-header {
    background: #111827;
    border: 1px solid #263244;
    border-radius: 15px;
    padding: 28px;
    margin-bottom: 20px;
}

.ticket {
    color: #38bdf8;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 8px;
}

.incident-header h1 {
    margin: 0 0 18px;
    font-size: 30px;
}

.badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.badge {
    display: inline-block;
    padding: 7px 13px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.category {
    background: #312e81;
    color: #c7d2fe;
}

.low {
    background: #064e3b;
    color: #a7f3d0;
}

.medium {
    background: #78350f;
    color: #fde68a;
}

.high {
    background: #7f1d1d;
    color: #fecaca;
}

.critical {
    background: #450a0a;
    color: #fca5a5;
}

.open {
    background: #172554;
    color: #bfdbfe;
}

.in-progress {
    background: #164e63;
    color: #a5f3fc;
}

.resolved {
    background: #14532d;
    color: #bbf7d0;
}

.closed {
    background: #374151;
    color: #d1d5db;
}

/* GRID */

.grid {
    display: grid;
    grid-template-columns:
        minmax(0, 2fr)
        minmax(320px, 1fr);
    gap: 20px;
}

/* CARD */

.card {
    background: #111827;
    border: 1px solid #263244;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
}

.card h2 {
    margin: 0 0 18px;
    font-size: 20px;
}

.card h2::after {
    content: "";
    display: block;
    width: 100%;
    height: 1px;
    background: #263244;
    margin-top: 14px;
}

/* DESCRIPTION */

.description {
    color: #cbd5e1;
    line-height: 1.7;
    white-space: pre-wrap;
}

/* DETAILS */

.detail-row {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    padding: 13px 0;
    border-bottom: 1px solid #1e293b;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    color: #94a3b8;
}

.detail-value {
    text-align: right;
    font-weight: 600;
    word-break: break-word;
}

/* COMMENT FORM */

.comment-form textarea,
.comment-modal textarea {
    width: 100%;
    min-height: 130px;
    resize: vertical;
    padding: 14px;
    background: #0f172a;
    color: #f8fafc;
    border: 1px solid #334155;
    border-radius: 9px;
    outline: none;
    font-family: Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
}

.comment-form textarea:focus,
.comment-modal textarea:focus {
    border-color: #38bdf8;
    box-shadow: 0 0 0 2px rgba(56, 189, 248, .12);
}

.comment-form-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-top: 12px;
}

.comment-form-footer span {
    color: #64748b;
    font-size: 11px;
}

.comment-button {
    border: none;
    padding: 11px 17px;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    cursor: pointer;
    font-weight: 600;
}

.comment-button:hover {
    background: #1d4ed8;
}

/* COMMENTS */

.comments-list {
    margin-top: 25px;
}

.comment-item {
    background: #0f172a;
    border: 1px solid #263244;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 12px;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 12px;
}

.comment-author {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 7px;
}

.comment-author strong {
    color: #e2e8f0;
}

.role {
    padding: 3px 8px;
    border-radius: 12px;
    background: #312e81;
    color: #c7d2fe;
    font-size: 10px;
    text-transform: uppercase;
    font-weight: 700;
}

.comment-date {
    color: #64748b;
    font-size: 11px;
}

.comment-text {
    color: #cbd5e1;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
}

/* COMMENT ACTIONS */

.comment-actions {
    margin-top: 14px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
}

.comment-edit-button,
.comment-delete-button {
    border: 1px solid #475569;
    padding: 8px 12px;
    border-radius: 7px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}

.comment-edit-button {
    background: #1e293b;
    color: #cbd5e1;
}

.comment-edit-button:hover {
    background: #334155;
    color: white;
}

.comment-delete-form {
    display: inline;
}

.comment-delete-button {
    background: #450a0a;
    border-color: #7f1d1d;
    color: #fecaca;
}

.comment-delete-button:hover {
    background: #7f1d1d;
    color: white;
}

/* EMPTY */

.comment-empty,
.empty {
    color: #64748b;
    text-align: center;
    padding: 25px;
}

/* EDIT MODAL */

.comment-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(2, 6, 23, .82);
    backdrop-filter: blur(4px);
}

.comment-modal.show {
    display: flex;
}

.comment-modal-box {
    width: 100%;
    max-width: 620px;
    background: #111827;
    border: 1px solid #334155;
    border-radius: 15px;
    padding: 24px;
}

.comment-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.comment-modal-header h3 {
    margin: 0;
}

.comment-modal-close {
    border: none;
    background: transparent;
    color: #94a3b8;
    font-size: 25px;
    cursor: pointer;
}

.comment-modal textarea {
    min-height: 180px;
}

.comment-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 15px;
}

.comment-cancel-button,
.comment-save-button {
    border: none;
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.comment-cancel-button {
    background: #334155;
    color: white;
}

.comment-save-button {
    background: #2563eb;
    color: white;
}

/* TIMELINE */

.timeline {
    position: relative;
}

.timeline::before {
    content: "";
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #334155;
}

.timeline-item {
    position: relative;
    padding-left: 38px;
    margin-bottom: 28px;
}

.timeline-dot {
    position: absolute;
    left: 3px;
    top: 4px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #38bdf8;
    border: 3px solid #0f172a;
}

.timeline-content {
    background: #0f172a;
    border: 1px solid #263244;
    border-radius: 10px;
    padding: 15px;
}

.timeline-title {
    font-weight: 700;
    margin-bottom: 7px;
}

.timeline-user {
    color: #38bdf8;
    font-size: 13px;
}

.timeline-comment {
    color: #cbd5e1;
    line-height: 1.5;
    margin-top: 10px;
    white-space: pre-wrap;
}

.timeline-time {
    color: #64748b;
    font-size: 11px;
    margin-top: 10px;
}

/* EVIDENCE */

.evidence-help {
    color: #64748b;
    font-size: 11px;
    line-height: 1.6;
    margin: 10px 0 15px;
}

input[type="file"] {
    width: 100%;
    padding: 12px;
    background: #0f172a;
    color: #cbd5e1;
    border: 1px solid #334155;
    border-radius: 8px;
}

.evidence-list {
    margin-top: 22px;
}

.evidence-item {
    background: #0f172a;
    border: 1px solid #263244;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 12px;
}

.evidence-name {
    color: #e2e8f0;
    font-weight: 700;
    word-break: break-word;
}

.evidence-meta {
    color: #64748b;
    font-size: 11px;
    margin-top: 7px;
    line-height: 1.6;
}

.evidence-hash {
    margin-top: 12px;
    padding: 10px;
    background: #111827;
    border: 1px solid #263244;
    border-radius: 7px;
    color: #94a3b8;
    font-family: monospace;
    font-size: 10px;
    word-break: break-all;
}

.evidence-actions {
    margin-top: 12px;
}

.evidence-download {
    display: inline-block;
    padding: 9px 13px;
    background: #0891b2;
    color: white;
    text-decoration: none;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;
}

/* ACTIONS */

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 11px 16px;
    border-radius: 8px;
    color: white;
    background: #2563eb;
    text-decoration: none;
    font-weight: 600;
}

.btn-purple {
    background: #7c3aed;
}

.btn-cyan {
    background: #0891b2;
}

.btn-green {
    background: #059669;
}

/* RESPONSIVE */

@media(max-width: 900px) {

    .grid {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 700px) {

    .container {
        padding: 15px;
    }

    .navbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .navbar a {
        margin-left: 0;
        margin-right: 12px;
    }

    .detail-row {
        flex-direction: column;
        gap: 5px;
    }

    .detail-value {
        text-align: left;
    }

    .comment-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .comment-form-footer {
        flex-direction: column;
        align-items: flex-start;
    }

    .comment-actions {
        justify-content: flex-start;
    }
}

</style>

</head>

<body>

<!-- NAVBAR -->

<div class="navbar">

    <div class="brand">
        🛡 Security Incident Ticketing
    </div>

    <div>

        <?php if ($currentRole === 'admin'): ?>

            <a href="../admin/dashboard.php">
                Dashboard
            </a>

            <a href="../admin/incidents.php">
                Incidents
            </a>

            <a href="../reports/incidents-report.php">
                Reports
            </a>

        <?php else: ?>

            <a href="../analyst/dashboard.php">
                Dashboard
            </a>

            <a href="../analyst/assigned-incidents.php">
                My Incidents
            </a>

        <?php endif; ?>

        <a href="../auth/logout.php">
            Logout
        </a>

    </div>

</div>

<div class="container">

    <?php if ($successMessage): ?>

        <div class="success">
            ✓ <?= e($successMessage) ?>
        </div>

    <?php endif; ?>

    <?php if ($commentError): ?>

        <div class="error">
            ⚠ <?= e($commentError) ?>
        </div>

    <?php endif; ?>

    <?php if ($evidenceError): ?>

        <div class="error">
            ⚠ <?= e($evidenceError) ?>
        </div>

    <?php endif; ?>

    <!-- INCIDENT HEADER -->

    <div class="incident-header">

        <div class="ticket">
            <?= e($incident['ticket_number']) ?>
        </div>

        <h1>
            <?= e($incident['title']) ?>
        </h1>

        <div class="badges">

            <span class="badge category">
                <?= e(
                    $incident['category_name']
                    ?? 'Uncategorized'
                ) ?>
            </span>

            <span class="badge <?= e(
                $severityClass[$incident['severity']]
                ?? 'medium'
            ) ?>">
                <?= e(
                    ucfirst($incident['severity'])
                ) ?>
            </span>

            <span class="badge <?= e(
                $statusClass[$incident['status']]
                ?? 'open'
            ) ?>">
                <?= e(
                    $statusLabels[$incident['status']]
                    ?? ucfirst($incident['status'])
                ) ?>
            </span>

        </div>

    </div>

    <div class="grid">

        <!-- LEFT COLUMN -->

        <div>

            <!-- DESCRIPTION -->

            <div class="card">

                <h2>
                    Incident Description
                </h2>

                <div class="description">
                    <?= e($incident['description']) ?>
                </div>

            </div>

            <!-- INVESTIGATION NOTES -->

            <div class="card">

                <h2>
                    📝 Investigation Notes
                </h2>

                <!-- ADD -->

                <form
                    class="comment-form"
                    method="POST"
                    action="add-comment.php"
                >

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="incident_id"
                        value="<?= e(
                            (string)$incidentId
                        ) ?>"
                    >

                    <textarea
                        name="comment"
                        maxlength="5000"
                        required
                        placeholder="Enter investigation findings, evidence observations, analysis, or resolution notes..."
                    ></textarea>

                    <div class="comment-form-footer">

                        <span>
                            Maximum 5000 characters
                        </span>

                        <button
                            type="submit"
                            class="comment-button"
                        >
                            + Add Investigation Note
                        </button>

                    </div>

                </form>

                <!-- LIST -->

                <div class="comments-list">

                    <?php if (!$comments): ?>

                        <div class="comment-empty">
                            No investigation notes yet.
                        </div>

                    <?php else: ?>

                        <?php foreach ($comments as $comment): ?>

                            <?php

                            $isOwner =
                                (int)$comment['user_id']
                                ===
                                $currentUserId;

                            $isAdmin =
                                in_array(
                                    $currentRole,
                                    ['admin', 'administrator'],
                                    true
                                );

                            $canManage =
                                $isOwner || $isAdmin;

                            ?>

                            <div class="comment-item">

                                <div class="comment-header">

                                    <div class="comment-author">

                                        <strong>
                                            <?= e(
                                                $comment['user_name']
                                                ??
                                                'Unknown User'
                                            ) ?>
                                        </strong>

                                        <span class="role">
                                            <?= e(
                                                ucfirst(
                                                    $comment['user_role']
                                                    ??
                                                    'user'
                                                )
                                            ) ?>
                                        </span>

                                    </div>

                                    <span class="comment-date">
                                        <?= e(
                                            $comment['created_at']
                                        ) ?>
                                    </span>

                                </div>

                                <div class="comment-text">
                                    <?= e(
                                        $comment['comment']
                                    ) ?>
                                </div>

                                <?php if ($canManage): ?>

                                    <div class="comment-actions">

                                        <!-- EDIT -->

                                        <button
                                            type="button"
                                            class="comment-edit-button"
                                            onclick="openEditComment(
                                                <?= (int)$comment['id'] ?>,
                                                <?= htmlspecialchars(
                                                    json_encode(
                                                        $comment['comment'],
                                                        JSON_HEX_TAG |
                                                        JSON_HEX_APOS |
                                                        JSON_HEX_AMP |
                                                        JSON_HEX_QUOT
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            )"
                                        >
                                            ✏ Edit
                                        </button>

                                        <!-- DELETE -->

                                        <form
                                            method="POST"
                                            action="delete-comment.php"
                                            class="comment-delete-form"
                                            onsubmit="return confirmDeleteComment();"
                                        >

                                            <?= csrfField() ?>

                                            <input
                                                type="hidden"
                                                name="comment_id"
                                                value="<?= (int)$comment['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="incident_id"
                                                value="<?= (int)$incidentId ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="comment-delete-button"
                                            >
                                                🗑 Delete
                                            </button>

                                        </form>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

            <!-- EVIDENCE -->

            <div class="card">

                <h2>
                    📎 Incident Evidence
                </h2>

                <form
                    method="POST"
                    action="upload-evidence.php"
                    enctype="multipart/form-data"
                >

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="incident_id"
                        value="<?= e(
                            (string)$incidentId
                        ) ?>"
                    >

                    <input
                        type="file"
                        name="evidence"
                        required
                    >

                    <div class="evidence-help">

                        Allowed:
                        JPG, PNG, GIF, PDF, TXT,
                        LOG, CSV, DOC, DOCX, XLS, XLSX

                        <br>

                        Maximum size: 10 MB

                    </div>

                    <button
                        type="submit"
                        class="comment-button"
                    >
                        📤 Upload Evidence
                    </button>

                </form>

                <div class="evidence-list">

                    <?php if (!$evidenceFiles): ?>

                        <div class="comment-empty">
                            No evidence files uploaded yet.
                        </div>

                    <?php else: ?>

                        <?php foreach (
                            $evidenceFiles
                            as $evidence
                        ): ?>

                            <div class="evidence-item">

                                <div class="evidence-name">

                                    📄
                                    <?= e(
                                        $evidence['original_name']
                                    ) ?>

                                </div>

                                <div class="evidence-meta">

                                    Size:
                                    <?= e(
                                        formatFileSize(
                                            (int)$evidence['file_size']
                                        )
                                    ) ?>

                                    <br>

                                    Type:
                                    <?= e(
                                        $evidence['mime_type']
                                    ) ?>

                                    <br>

                                    Uploaded by:
                                    <?= e(
                                        $evidence['uploader_name']
                                        ??
                                        'Unknown User'
                                    ) ?>

                                    <br>

                                    Uploaded:
                                    <?= e(
                                        $evidence['created_at']
                                    ) ?>

                                </div>

                                <div class="evidence-hash">

                                    <strong>
                                        SHA-256:
                                    </strong>

                                    <?= e(
                                        $evidence['file_hash']
                                    ) ?>

                                </div>

                                <div class="evidence-actions">

                                    <a
                                        href="download-evidence.php?id=<?= e(
                                            (string)$evidence['id']
                                        ) ?>"
                                        class="evidence-download"
                                    >
                                        ⬇ Download Evidence
                                    </a>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

            <!-- ACTIVITY -->

            <div class="card">

                <h2>
                    Activity History
                </h2>

                <?php if (!$activities): ?>

                    <div class="empty">
                        No activity has been recorded.
                    </div>

                <?php else: ?>

                    <div class="timeline">

                        <?php foreach ($activities as $activity): ?>

                            <div class="timeline-item">

                                <div class="timeline-dot"></div>

                                <div class="timeline-content">

                                    <div class="timeline-title">
                                        <?= e(
                                            $activity['action']
                                        ) ?>
                                    </div>

                                    <div class="timeline-user">

                                        👤
                                        <?= e(
                                            $activity['user_name']
                                            ??
                                            'System'
                                        ) ?>

                                    </div>

                                    <?php if (
                                        $activity['old_value'] !== null ||
                                        $activity['new_value'] !== null
                                    ): ?>

                                        <div class="timeline-comment">

                                            Previous:
                                            <?= e(
                                                $activity['old_value']
                                                ??
                                                '—'
                                            ) ?>

                                            <br>

                                            New:
                                            <?= e(
                                                $activity['new_value']
                                                ??
                                                '—'
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                    <?php if (
                                        !empty(
                                            $activity['comment']
                                        )
                                    ): ?>

                                        <div class="timeline-comment">
                                            💬
                                            <?= e(
                                                $activity['comment']
                                            ) ?>
                                        </div>

                                    <?php endif; ?>

                                    <div class="timeline-time">
                                        <?= e(
                                            $activity['created_at']
                                        ) ?>
                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

        <!-- RIGHT COLUMN -->

        <div>

            <!-- DETAILS -->

            <div class="card">

                <h2>
                    Incident Details
                </h2>

                <div class="detail-row">

                    <div class="detail-label">
                        Ticket
                    </div>

                    <div class="detail-value">
                        <?= e(
                            $incident['ticket_number']
                        ) ?>
                    </div>

                </div>

                <div class="detail-row">

                    <div class="detail-label">
                        Category
                    </div>

                    <div class="detail-value">
                        <?= e(
                            $incident['category_name']
                            ??
                            'N/A'
                        ) ?>
                    </div>

                </div>

                <div class="detail-row">

                    <div class="detail-label">
                        Severity
                    </div>

                    <div class="detail-value">
                        <?= e(
                            ucfirst(
                                $incident['severity']
                            )
                        ) ?>
                    </div>

                </div>

                <div class="detail-row">

                    <div class="detail-label">
                        Status
                    </div>

                    <div class="detail-value">
                        <?= e(
                            $statusLabels[
                                $incident['status']
                            ]
                            ??
                            ucfirst(
                                $incident['status']
                            )
                        ) ?>
                    </div>

                </div>

                <div class="detail-row">

                    <div class="detail-label">
                        Reporter
                    </div>

                    <div class="detail-value">
                        <?= e(
                            $incident['reporter_name']
                            ??
                            'N/A'
                        ) ?>
                    </div>

                </div>

                <div class="detail-row">

                    <div class="detail-label">
                        Assigned Analyst
                    </div>

                    <div class="detail-value">
                        <?= e(
                            $incident['analyst_name']
                            ??
                            'Not Assigned'
                        ) ?>
                    </div>

                </div>

                <div class="detail-row">

                    <div class="detail-label">
                        Created
                    </div>

                    <div class="detail-value">
                        <?= e(
                            $incident['created_at']
                        ) ?>
                    </div>

                </div>

            </div>

            <!-- ACTIONS -->

            <div class="card">

                <h2>
                    Actions
                </h2>

                <div class="actions">

                    <?php if ($currentRole === 'admin'): ?>

                        <a
                            class="btn btn-purple"
                            href="edit.php?id=<?= e(
                                (string)$incidentId
                            ) ?>"
                        >
                            ✏ Edit
                        </a>

                        <a
                            class="btn btn-cyan"
                            href="assign.php?id=<?= e(
                                (string)$incidentId
                            ) ?>"
                        >
                            👤 Assign
                        </a>

                    <?php endif; ?>

                    <a
                        class="btn btn-green"
                        href="update-status.php?id=<?= e(
                            (string)$incidentId
                        ) ?>"
                    >
                        🔄 Update Status
                    </a>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     EDIT COMMENT MODAL
===================================================== -->

<div
    id="editCommentModal"
    class="comment-modal"
>

    <div class="comment-modal-box">

        <div class="comment-modal-header">

            <h3>
                ✏ Edit Investigation Note
            </h3>

            <button
                type="button"
                class="comment-modal-close"
                onclick="closeEditComment()"
            >
                ×
            </button>

        </div>

        <form
            method="POST"
            action="edit-comment.php"
        >

            <?= csrfField() ?>

            <input
                type="hidden"
                name="comment_id"
                id="edit_comment_id"
            >

            <input
                type="hidden"
                name="incident_id"
                value="<?= e(
                    (string)$incidentId
                ) ?>"
            >

            <textarea
                name="comment"
                id="edit_comment_text"
                maxlength="5000"
                required
            ></textarea>

            <div class="comment-modal-footer">

                <button
                    type="button"
                    class="comment-cancel-button"
                    onclick="closeEditComment()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="comment-save-button"
                >
                    💾 Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<script>

function openEditComment(id, comment)
{
    document.getElementById(
        'edit_comment_id'
    ).value = id;

    document.getElementById(
        'edit_comment_text'
    ).value = comment;

    document.getElementById(
        'editCommentModal'
    ).classList.add('show');

    document.getElementById(
        'edit_comment_text'
    ).focus();
}

function closeEditComment()
{
    document.getElementById(
        'editCommentModal'
    ).classList.remove('show');

    document.getElementById(
        'edit_comment_id'
    ).value = '';

    document.getElementById(
        'edit_comment_text'
    ).value = '';
}

function confirmDeleteComment()
{
    return confirm(
        'Are you sure you want to delete this investigation note?'
    );
}

document.addEventListener(
    'keydown',
    function(event)
    {
        if (event.key === 'Escape') {
            closeEditComment();
        }
    }
);

document.getElementById(
    'editCommentModal'
).addEventListener(
    'click',
    function(event)
    {
        if (event.target === this) {
            closeEditComment();
        }
    }
);

</script>

</body>

</html>