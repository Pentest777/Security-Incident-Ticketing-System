<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireLogin();

$incidentId = (int)($_GET['id'] ?? $_POST['incident_id'] ?? 0);

if ($incidentId <= 0) {
    die('Invalid incident ID.');
}

$error = '';

/*
|--------------------------------------------------------------------------
| Get Incident
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        i.*,
        c.name AS category_name,
        reporter.name AS reporter_name,
        analyst.name AS analyst_name
     FROM incidents i

     LEFT JOIN incident_categories c
        ON i.category_id = c.id

     LEFT JOIN users reporter
        ON i.reported_by = reporter.id

     LEFT JOIN users analyst
        ON i.assigned_to = analyst.id

     WHERE i.id = ?

     LIMIT 1"
);

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
|
| Admin can update any incident.
| Analyst can update only assigned incidents.
|
*/

if ($_SESSION['role'] === 'analyst') {

    if (
        (int)$incident['assigned_to']
        !==
        (int)$_SESSION['user_id']
    ) {

        http_response_code(403);

        die(
            'Access Denied: This incident is not assigned to you.'
        );
    }
}

elseif ($_SESSION['role'] !== 'admin') {

    http_response_code(403);

    die('Access Denied.');
}


/*
|--------------------------------------------------------------------------
| Allowed Statuses
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'open',
    'in_progress',
    'resolved',
    'closed'
];


/*
|--------------------------------------------------------------------------
| Status Labels
|--------------------------------------------------------------------------
*/

$statusLabels = [
    'open' => 'Open',
    'in_progress' => 'In Progress',
    'resolved' => 'Resolved',
    'closed' => 'Closed'
];


/*
|--------------------------------------------------------------------------
| Status Workflow
|--------------------------------------------------------------------------
|
| Open → In Progress
| In Progress → Resolved
| Resolved → Closed
|
| Also allow going backward for admin corrections.
|
*/

$allowedTransitions = [

    'open' => [
        'in_progress'
    ],

    'in_progress' => [
        'open',
        'resolved'
    ],

    'resolved' => [
        'in_progress',
        'closed'
    ],

    'closed' => [
        'resolved'
    ]

];


/*
|--------------------------------------------------------------------------
| Update Status
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? null
        )
    ) {

        $error =
            'Invalid security token.';

    } else {

        $newStatus =
            $_POST['status'] ?? '';

        $comment =
            trim($_POST['comment'] ?? '');

        $currentStatus =
            $incident['status'];


        /*
        |--------------------------------------------------------------------------
        | Validate Status
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $newStatus,
                $allowedStatuses,
                true
            )
        ) {

            $error =
                'Invalid status selected.';

        }

        elseif (
            $newStatus === $currentStatus
        ) {

            $error =
                'Incident is already in this status.';

        }

        elseif (
            !in_array(
                $newStatus,
                $allowedTransitions[$currentStatus] ?? [],
                true
            )
        ) {

            $error =
                'Invalid status transition: ' .
                $statusLabels[$currentStatus] .
                ' → ' .
                ($statusLabels[$newStatus] ?? $newStatus);

        }

        /*
        |--------------------------------------------------------------------------
        | Require Comment for Important Changes
        |--------------------------------------------------------------------------
        */

        elseif (
            in_array(
                $newStatus,
                ['resolved', 'closed'],
                true
            )
            &&
            strlen($comment) < 5
        ) {

            $error =
                'Please provide a resolution/closure comment.';

        }

        else {

            try {

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | Update Incident
                |--------------------------------------------------------------------------
                */

                $update = $pdo->prepare(
                    "UPDATE incidents
                     SET status = ?
                     WHERE id = ?"
                );

                $update->execute([
                    $newStatus,
                    $incidentId
                ]);


                /*
                |--------------------------------------------------------------------------
                | Activity Log
                |--------------------------------------------------------------------------
                */

                $activity = $pdo->prepare(
                    "INSERT INTO incident_activity
                    (
                        incident_id,
                        user_id,
                        action,
                        old_value,
                        new_value,
                        comment
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        'Status Updated',
                        ?,
                        ?,
                        ?
                    )"
                );

                $activity->execute([
                    $incidentId,
                    $_SESSION['user_id'],
                    $currentStatus,
                    $newStatus,
                    $comment !== ''
                        ? $comment
                        : 'Incident status updated.'
                ]);


                $pdo->commit();


                /*
                |--------------------------------------------------------------------------
                | Redirect
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: view.php?id=' .
                    $incidentId .
                    '&status_updated=1'
                );

                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error =
                    'Unable to update incident status.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Determine Available Next Statuses
|--------------------------------------------------------------------------
*/

$nextStatuses =
    $allowedTransitions[
        $incident['status']
    ] ?? [];

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
    Update Status |
    <?= e($incident['ticket_number']) ?>
</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #0f172a;

    color: #f8fafc;
}


/* NAVBAR */

.navbar {

    background: #111827;

    border-bottom:
        1px solid #263244;

    padding: 18px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.navbar a {

    color: #cbd5e1;

    text-decoration: none;

    margin-left: 15px;
}

.navbar a:hover {

    color: white;
}


/* CONTAINER */

.container {

    max-width: 850px;

    margin: 40px auto;

    padding: 20px;
}


/* CARD */

.card {

    background: #111827;

    border:
        1px solid #263244;

    border-radius: 15px;

    padding: 30px;
}


/* HEADER */

.ticket {

    color: #38bdf8;

    font-weight: 700;

    margin-bottom: 8px;
}

h1 {

    margin: 0 0 10px;
}

.subtitle {

    color: #94a3b8;

    margin-bottom: 30px;
}


/* INCIDENT INFO */

.incident-info {

    background: #0f172a;

    border:
        1px solid #263244;

    border-radius: 10px;

    padding: 18px;

    margin-bottom: 25px;
}

.info-row {

    display: flex;

    justify-content: space-between;

    gap: 20px;

    padding: 11px 0;

    border-bottom:
        1px solid #1e293b;
}

.info-row:last-child {

    border-bottom: none;
}

.label {

    color: #94a3b8;
}

.value {

    text-align: right;

    font-weight: 600;
}


/* STATUS BADGES */

.badge {

    display: inline-block;

    padding: 6px 12px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 700;
}

.open {

    background: #172554;

    color: #bfdbfe;
}

.in_progress {

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


/* ERROR */

.error {

    background: #450a0a;

    border:
        1px solid #7f1d1d;

    color: #fecaca;

    padding: 13px;

    border-radius: 8px;

    margin-bottom: 20px;
}


/* FORM */

.form-group {

    margin-bottom: 20px;
}

label {

    display: block;

    margin-bottom: 8px;

    color: #cbd5e1;

    font-weight: 600;
}

select,
textarea {

    width: 100%;

    padding: 13px;

    border-radius: 8px;

    border:
        1px solid #334155;

    background: #0f172a;

    color: white;

    outline: none;

    font-size: 15px;
}

select:focus,
textarea:focus {

    border-color: #38bdf8;
}

textarea {

    min-height: 130px;

    resize: vertical;
}


/* BUTTONS */

.actions {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}

button,
.btn {

    display: inline-block;

    padding: 13px 20px;

    border: none;

    border-radius: 8px;

    background: #2563eb;

    color: white;

    text-decoration: none;

    cursor: pointer;

    font-size: 15px;

    font-weight: 600;
}

button:hover,
.btn:hover {

    background: #1d4ed8;
}

.btn-secondary {

    background: #334155;
}

.btn-secondary:hover {

    background: #475569;
}


/* NOTICE */

.notice {

    background: #172554;

    border:
        1px solid #1e40af;

    color: #bfdbfe;

    padding: 14px;

    border-radius: 9px;

    margin-bottom: 20px;

    line-height: 1.5;
}


@media(max-width:700px) {

    .navbar {

        flex-direction: column;

        gap: 15px;
    }

    .info-row {

        flex-direction: column;

        gap: 5px;
    }

    .value {

        text-align: left;
    }

}

</style>

</head>


<body>


<!-- NAVBAR -->

<div class="navbar">

    <strong>
        🛡 Security Incident Ticketing
    </strong>

    <div>

        <a
            href="view.php?id=<?= e(
                (string)$incidentId
            ) ?>"
        >
            View Incident
        </a>

        <?php if (
            $_SESSION['role'] === 'admin'
        ): ?>

            <a href="../admin/dashboard.php">
                Dashboard
            </a>

        <?php elseif (
            $_SESSION['role'] === 'analyst'
        ): ?>

            <a href="../analyst/dashboard.php">
                Dashboard
            </a>

        <?php endif; ?>

        <a href="../auth/logout.php">
            Logout
        </a>

    </div>

</div>


<div class="container">


    <div class="card">


        <div class="ticket">

            <?= e(
                $incident['ticket_number']
            ) ?>

        </div>


        <h1>
            Update Incident Status
        </h1>


        <p class="subtitle">

            Change the current status of this
            security incident.

        </p>


        <?php if ($error): ?>

            <div class="error">

                <?= e($error) ?>

            </div>

        <?php endif; ?>


        <!-- INCIDENT INFO -->

        <div class="incident-info">


            <div class="info-row">

                <div class="label">
                    Ticket
                </div>

                <div class="value">

                    <?= e(
                        $incident['ticket_number']
                    ) ?>

                </div>

            </div>


            <div class="info-row">

                <div class="label">
                    Title
                </div>

                <div class="value">

                    <?= e(
                        $incident['title']
                    ) ?>

                </div>

            </div>


            <div class="info-row">

                <div class="label">
                    Category
                </div>

                <div class="value">

                    <?= e(
                        $incident['category_name']
                        ?? 'N/A'
                    ) ?>

                </div>

            </div>


            <div class="info-row">

                <div class="label">
                    Severity
                </div>

                <div class="value">

                    <?= e(
                        ucfirst(
                            $incident['severity']
                        )
                    ) ?>

                </div>

            </div>


            <div class="info-row">

                <div class="label">
                    Current Status
                </div>

                <div class="value">

                    <span
                        class="badge <?= e(
                            $incident['status']
                        ) ?>"
                    >

                        <?= e(
                            $statusLabels[
                                $incident['status']
                            ]
                            ??
                            $incident['status']
                        ) ?>

                    </span>

                </div>

            </div>


            <div class="info-row">

                <div class="label">
                    Assigned Analyst
                </div>

                <div class="value">

                    <?= e(
                        $incident['analyst_name']
                        ?? 'Not Assigned'
                    ) ?>

                </div>

            </div>


        </div>


        <?php if (!$nextStatuses): ?>

            <div class="notice">

                This incident currently has no
                further status transition available.

            </div>

        <?php else: ?>


            <!-- FORM -->

            <form method="POST">

                 <?= csrfField() ?>
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(
                        csrfToken()
                    ) ?>"
                >


                <input
                    type="hidden"
                    name="incident_id"
                    value="<?= e(
                        (string)$incidentId
                    ) ?>"
                >


                <!-- STATUS -->

                <div class="form-group">

                    <label>
                        New Status
                    </label>

                    <select
                        name="status"
                        required
                    >

                        <option value="">
                            -- Select New Status --
                        </option>


                        <?php foreach (
                            $nextStatuses
                            as $nextStatus
                        ): ?>

                            <option
                                value="<?= e(
                                    $nextStatus
                                ) ?>"
                            >

                                <?= e(
                                    $statusLabels[
                                        $nextStatus
                                    ]
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- COMMENT -->

                <div class="form-group">

                    <label>
                        Comment
                    </label>

                    <textarea
                        name="comment"
                        placeholder="Add investigation, resolution or closure notes..."
                    ></textarea>

                </div>


                <!-- ACTIONS -->

                <div class="actions">

                    <button type="submit">
                        Update Status
                    </button>


                    <a
                        class="btn btn-secondary"
                        href="view.php?id=<?= e(
                            (string)$incidentId
                        ) ?>"
                    >
                        Cancel
                    </a>

                </div>


            </form>


        <?php endif; ?>


    </div>

</div>


</body>

</html>