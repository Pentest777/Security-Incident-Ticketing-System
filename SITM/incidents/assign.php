<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireRole('admin');

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
        u.name AS reporter_name,
        analyst.name AS analyst_name
     FROM incidents i
     LEFT JOIN incident_categories c
        ON i.category_id = c.id
     LEFT JOIN users u
        ON i.reported_by = u.id
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
| Get Analysts
|--------------------------------------------------------------------------
*/

$analystStmt = $pdo->query(
    "SELECT id, name, email
     FROM users
     WHERE role = 'analyst'
       AND status = 'active'
     ORDER BY name ASC"
);

$analysts = $analystStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Assign Analyst
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        $error = 'Invalid security token.';

    } else {

        $analystId = (int)($_POST['analyst_id'] ?? 0);

        /*
        |--------------------------------------------------------------------------
        | Allow Unassign
        |--------------------------------------------------------------------------
        */

        if ($analystId === 0) {

            try {

                $pdo->beginTransaction();

                $oldAnalyst = $incident['analyst_name']
                    ?? 'Not Assigned';

                $update = $pdo->prepare(
                    "UPDATE incidents
                     SET assigned_to = NULL
                     WHERE id = ?"
                );

                $update->execute([$incidentId]);

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
                        'Analyst Unassigned',
                        ?,
                        NULL,
                        ?
                    )"
                );

                $activity->execute([
                    $incidentId,
                    $_SESSION['user_id'],
                    $oldAnalyst,
                    'Incident analyst assignment removed.'
                ]);

                $pdo->commit();

                header(
                    'Location: view.php?id=' .
                    $incidentId .
                    '&assigned=1'
                );

                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = 'Unable to unassign analyst.';
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | Verify Analyst
            |--------------------------------------------------------------------------
            */

            $check = $pdo->prepare(
                "SELECT id, name, email
                 FROM users
                 WHERE id = ?
                   AND role = 'analyst'
                   AND status = 'active'
                 LIMIT 1"
            );

            $check->execute([$analystId]);

            $analyst = $check->fetch();

            if (!$analyst) {

                $error =
                    'Selected analyst is invalid or inactive.';

            } else {

                try {

                    $pdo->beginTransaction();

                    $oldAnalyst =
                        $incident['analyst_name']
                        ?? 'Not Assigned';

                    /*
                    |--------------------------------------------------------------------------
                    | Update Assignment
                    |--------------------------------------------------------------------------
                    */

                    $update = $pdo->prepare(
                        "UPDATE incidents
                         SET assigned_to = ?
                         WHERE id = ?"
                    );

                    $update->execute([
                        $analystId,
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
                            'Analyst Assigned',
                            ?,
                            ?,
                            ?
                        )"
                    );

                    $activity->execute([
                        $incidentId,
                        $_SESSION['user_id'],
                        $oldAnalyst,
                        $analyst['name'],
                        'Incident assigned to analyst.'
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Automatically Move Open → In Progress
                    |--------------------------------------------------------------------------
                    */

                    if ($incident['status'] === 'open') {

                        $statusUpdate = $pdo->prepare(
                            "UPDATE incidents
                             SET status = 'in_progress'
                             WHERE id = ?"
                        );

                        $statusUpdate->execute([
                            $incidentId
                        ]);

                        $statusActivity = $pdo->prepare(
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
                                'open',
                                'in_progress',
                                ?
                            )"
                        );

                        $statusActivity->execute([
                            $incidentId,
                            $_SESSION['user_id'],
                            'Incident moved to In Progress after analyst assignment.'
                        ]);
                    }

                    $pdo->commit();

                    header(
                        'Location: view.php?id=' .
                        $incidentId .
                        '&assigned=1'
                    );

                    exit;

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $error =
                        'Unable to assign analyst.';
                }
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Assign Incident
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;

    font-family: Arial, sans-serif;

    background: #0f172a;

    color: white;
}

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

.container {
    max-width: 850px;

    margin: 40px auto;

    padding: 20px;
}

.card {
    background: #111827;

    border:
        1px solid #263244;

    border-radius: 15px;

    padding: 30px;
}

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

    padding: 10px 0;

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
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;

    margin-bottom: 8px;

    color: #cbd5e1;

    font-weight: 600;
}

select {
    width: 100%;

    padding: 14px;

    border-radius: 8px;

    border:
        1px solid #334155;

    background: #0f172a;

    color: white;

    font-size: 15px;
}

select:focus {
    outline: none;

    border-color: #38bdf8;
}

button,
.btn {
    display: inline-block;

    padding: 13px 20px;

    border: none;

    border-radius: 8px;

    color: white;

    background: #2563eb;

    text-decoration: none;

    cursor: pointer;

    font-size: 15px;
}

button:hover,
.btn:hover {
    background: #1d4ed8;
}

.btn-danger {
    background: #dc2626;
}

.btn-danger:hover {
    background: #b91c1c;
}

.btn-secondary {
    background: #334155;
}

.btn-secondary:hover {
    background: #475569;
}

.actions {
    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}

.error {
    background: #450a0a;

    border:
        1px solid #7f1d1d;

    color: #fecaca;

    padding: 13px;

    border-radius: 8px;

    margin-bottom: 20px;
}

.current {
    background: #172554;

    border:
        1px solid #1e40af;

    color: #bfdbfe;

    padding: 13px;

    border-radius: 8px;

    margin-bottom: 20px;
}

@media(max-width: 700px) {

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

        <a href="view.php?id=<?= e(
            (string)$incidentId
        ) ?>">
            View Incident
        </a>

        <a href="../admin/dashboard.php">
            Dashboard
        </a>

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
            Assign Incident
        </h1>

        <p class="subtitle">

            Assign this security incident
            to an available analyst.

        </p>


        <?php if ($error): ?>

            <div class="error">

                <?= e($error) ?>

            </div>

        <?php endif; ?>


        <!-- INCIDENT INFORMATION -->

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

                    <?= e(
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                $incident['status']
                            )
                        )
                    ) ?>

                </div>

            </div>


            <div class="info-row">

                <div class="label">
                    Reporter
                </div>

                <div class="value">

                    <?= e(
                        $incident['reporter_name']
                        ?? 'N/A'
                    ) ?>

                </div>

            </div>

        </div>


        <!-- CURRENT ASSIGNMENT -->

        <div class="current">

            Current Analyst:

            <strong>

                <?= e(
                    $incident['analyst_name']
                    ?? 'Not Assigned'
                ) ?>

            </strong>

        </div>


        <!-- ASSIGN FORM -->

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


            <div class="form-group">

                <label>
                    Select Analyst
                </label>

                <select
                    name="analyst_id"
                    required
                >

                    <option value="">
                        -- Select Analyst --
                    </option>


                    <?php foreach (
                        $analysts
                        as $analyst
                    ): ?>

                        <option
                            value="<?= e(
                                (string)$analyst['id']
                            ) ?>"
                            <?= (
                                (int)$incident['assigned_to']
                                ===
                                (int)$analyst['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e(
                                $analyst['name']
                            ) ?>

                            -
                            <?= e(
                                $analyst['email']
                            ) ?>

                        </option>

                    <?php endforeach; ?>


                </select>

            </div>


            <div class="actions">

                <button type="submit">
                    Assign Analyst
                </button>

                <?php if (
                    !empty(
                        $incident['assigned_to']
                    )
                ): ?>

                    <button
                        type="submit"
                        name="analyst_id"
                        value="0"
                        class="btn-danger"
                        onclick="return confirm(
                            'Are you sure you want to unassign this analyst?'
                        );"
                    >
                        Unassign
                    </button>

                <?php endif; ?>


                <a
                    href="view.php?id=<?= e(
                        (string)$incidentId
                    ) ?>"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>


        <?php if (!$analysts): ?>

            <div
                class="error"
                style="margin-top:20px;"
            >

                No active Analyst accounts are
                currently available.

                <br><br>

                Go to
                <strong>
                    Admin → Users
                </strong>

                and create an Analyst account.

            </div>

        <?php endif; ?>

    </div>

</div>


</body>

</html>