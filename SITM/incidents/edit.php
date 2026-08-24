<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireRole('admin');

$incidentId = (int)($_GET['id'] ?? $_POST['incident_id'] ?? 0);

if ($incidentId <= 0) {
    die('Invalid incident ID.');
}

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| Get Incident
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT *
     FROM incidents
     WHERE id = ?
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
| Get Categories
|--------------------------------------------------------------------------
*/

$categoryStmt = $pdo->query(
    "SELECT id, name
     FROM incident_categories
     ORDER BY name ASC"
);

$categories = $categoryStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Update Incident
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        $error = 'Invalid security token.';

    } else {

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $severity = $_POST['severity'] ?? '';

        $allowedSeverity = [
            'low',
            'medium',
            'high',
            'critical'
        ];

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($title === '') {

            $error = 'Incident title is required.';

        } elseif (strlen($title) < 5) {

            $error =
                'Incident title must contain at least 5 characters.';

        } elseif ($description === '') {

            $error =
                'Incident description is required.';

        } elseif (strlen($description) < 10) {

            $error =
                'Incident description must contain at least 10 characters.';

        } elseif ($categoryId <= 0) {

            $error =
                'Please select an incident category.';

        } elseif (!in_array(
            $severity,
            $allowedSeverity,
            true
        )) {

            $error =
                'Invalid severity selected.';

        } else {

            try {

                $pdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | Track Changes
                |--------------------------------------------------------------------------
                */

                $changes = [];

                if (
                    $incident['title']
                    !== $title
                ) {

                    $changes[] = [
                        'field' => 'Title',
                        'old' => $incident['title'],
                        'new' => $title
                    ];
                }

                if (
                    $incident['description']
                    !== $description
                ) {

                    $changes[] = [
                        'field' => 'Description',
                        'old' => $incident['description'],
                        'new' => $description
                    ];
                }

                if (
                    (int)$incident['category_id']
                    !== $categoryId
                ) {

                    $changes[] = [
                        'field' => 'Category',
                        'old' => (string)$incident['category_id'],
                        'new' => (string)$categoryId
                    ];
                }

                if (
                    $incident['severity']
                    !== $severity
                ) {

                    $changes[] = [
                        'field' => 'Severity',
                        'old' => $incident['severity'],
                        'new' => $severity
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Update
                |--------------------------------------------------------------------------
                */

                $update = $pdo->prepare(
                    "UPDATE incidents
                     SET
                        title = ?,
                        description = ?,
                        category_id = ?,
                        severity = ?
                     WHERE id = ?"
                );

                $update->execute([
                    $title,
                    $description,
                    $categoryId,
                    $severity,
                    $incidentId
                ]);

                /*
                |--------------------------------------------------------------------------
                | Activity Log
                |--------------------------------------------------------------------------
                */

                foreach ($changes as $change) {

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
                            ?,
                            ?,
                            ?,
                            ?
                        )"
                    );

                    $activity->execute([
                        $incidentId,
                        $_SESSION['user_id'],
                        'Incident Updated - ' .
                            $change['field'],
                        $change['old'],
                        $change['new'],
                        $change['field'] .
                            ' was updated.'
                    ]);
                }

                $pdo->commit();

                header(
                    'Location: view.php?id=' .
                    $incidentId .
                    '&updated=1'
                );

                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error =
                    'Unable to update incident.';
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
    Edit <?= e($incident['ticket_number']) ?>
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
    max-width: 900px;

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

    font-weight: bold;

    margin-bottom: 8px;
}

h1 {
    margin-top: 0;
}

.subtitle {
    color: #94a3b8;

    margin-bottom: 30px;
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

input,
textarea,
select {
    width: 100%;

    padding: 13px;

    border-radius: 8px;

    border:
        1px solid #334155;

    background: #0f172a;

    color: white;

    outline: none;
}

input:focus,
textarea:focus,
select:focus {
    border-color: #38bdf8;
}

textarea {
    min-height: 180px;

    resize: vertical;
}

.row {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;
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

.btn-secondary {
    background: #334155;
}

.btn-secondary:hover {
    background: #475569;
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

.info {
    background: #172554;

    border:
        1px solid #1e40af;

    color: #bfdbfe;

    padding: 13px;

    border-radius: 8px;

    margin-bottom: 25px;
}

.actions {
    display: flex;

    gap: 10px;
}

@media(max-width: 700px) {

    .row {
        grid-template-columns: 1fr;
    }

    .navbar {
        flex-direction: column;

        gap: 15px;
    }

}

</style>

</head>

<body>


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

        <a href="../auth/logout.php">
            Logout
        </a>

    </div>

</div>


<div class="container">

    <div class="card">

        <div class="ticket">
            <?= e($incident['ticket_number']) ?>
        </div>

        <h1>
            Edit Security Incident
        </h1>

        <p class="subtitle">
            Update the incident information.
        </p>


        <?php if ($error): ?>

            <div class="error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <div class="info">

            Ticket:
            <strong>
                <?= e($incident['ticket_number']) ?>
            </strong>

            &nbsp; | &nbsp;

            Current Status:
            <strong>
                <?= e(
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $incident['status']
                        )
                    )
                ) ?>
            </strong>

        </div>


        <form method="POST">
             <?= csrfField() ?>
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <input
                type="hidden"
                name="incident_id"
                value="<?= e(
                    (string)$incidentId
                ) ?>"
            >


            <!-- TITLE -->

            <div class="form-group">

                <label>
                    Incident Title
                </label>

                <input
                    type="text"
                    name="title"
                    maxlength="255"
                    required
                    value="<?= e(
                        $incident['title']
                    ) ?>"
                >

            </div>


            <!-- DESCRIPTION -->

            <div class="form-group">

                <label>
                    Incident Description
                </label>

                <textarea
                    name="description"
                    required
                ><?= e(
                    $incident['description']
                ) ?></textarea>

            </div>


            <div class="row">


                <!-- CATEGORY -->

                <div class="form-group">

                    <label>
                        Category
                    </label>

                    <select
                        name="category_id"
                        required
                    >

                        <?php foreach (
                            $categories
                            as $category
                        ): ?>

                            <option
                                value="<?= e(
                                    (string)$category['id']
                                ) ?>"
                                <?= (
                                    (int)$incident[
                                        'category_id'
                                    ]
                                    ===
                                    (int)$category['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $category['name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- SEVERITY -->

                <div class="form-group">

                    <label>
                        Severity
                    </label>

                    <select
                        name="severity"
                        required
                    >

                        <option
                            value="low"
                            <?= $incident['severity']
                                === 'low'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Low
                        </option>

                        <option
                            value="medium"
                            <?= $incident['severity']
                                === 'medium'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Medium
                        </option>

                        <option
                            value="high"
                            <?= $incident['severity']
                                === 'high'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            High
                        </option>

                        <option
                            value="critical"
                            <?= $incident['severity']
                                === 'critical'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Critical
                        </option>

                    </select>

                </div>

            </div>


            <!-- BUTTONS -->

            <div class="actions">

                <button type="submit">
                    Save Changes
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

    </div>

</div>

</body>

</html>