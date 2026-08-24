<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireLogin();

$error = '';

/*
|--------------------------------------------------------------------------
| Generate Ticket Number
|--------------------------------------------------------------------------
*/

function generateTicketNumber(PDO $pdo): string
{
    $year = date('Y');

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) 
         FROM incidents 
         WHERE YEAR(created_at) = ?"
    );

    $stmt->execute([$year]);

    $count = (int)$stmt->fetchColumn() + 1;

    return 'INC-' . $year . '-' . str_pad(
        (string)$count,
        4,
        '0',
        STR_PAD_LEFT
    );
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
| Create Incident
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        $error = 'Invalid security token.';

    } else {

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $severity = $_POST['severity'] ?? 'low';

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $allowedSeverity = [
            'low',
            'medium',
            'high',
            'critical'
        ];

        if ($title === '') {

            $error = 'Incident title is required.';

        } elseif (strlen($title) < 5) {

            $error = 'Incident title must contain at least 5 characters.';

        } elseif ($description === '') {

            $error = 'Incident description is required.';

        } elseif (strlen($description) < 10) {

            $error = 'Incident description must contain at least 10 characters.';

        } elseif ($categoryId <= 0) {

            $error = 'Please select an incident category.';

        } elseif (!in_array($severity, $allowedSeverity, true)) {

            $error = 'Invalid severity selected.';

        } else {

            try {

                $pdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | Generate Ticket
                |--------------------------------------------------------------------------
                */

                $ticketNumber = generateTicketNumber($pdo);

                /*
                |--------------------------------------------------------------------------
                | Insert Incident
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare(
                    "INSERT INTO incidents
                    (
                        ticket_number,
                        title,
                        description,
                        category_id,
                        severity,
                        status,
                        reported_by
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, 'open', ?
                    )"
                );

                $stmt->execute([
                    $ticketNumber,
                    $title,
                    $description,
                    $categoryId,
                    $severity,
                    $_SESSION['user_id']
                ]);

                $incidentId = (int)$pdo->lastInsertId();

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
                        new_value,
                        comment
                    )
                    VALUES
                    (
                        ?, ?, 'Incident Created', ?, ?
                    )"
                );

                $activity->execute([
                    $incidentId,
                    $_SESSION['user_id'],
                    $severity,
                    'Incident ticket created.'
                ]);

                $pdo->commit();

                /*
                |--------------------------------------------------------------------------
                | Redirect
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: view.php?id=' . $incidentId .
                    '&created=1'
                );

                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = 'Unable to create incident.';
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

<title>Create Incident</title>

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

    padding: 18px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.navbar a {
    color: white;

    text-decoration: none;

    margin-left: 15px;
}

.container {
    max-width: 900px;

    margin: 40px auto;

    padding: 20px;
}

.card {
    background: #111827;

    border: 1px solid #263244;

    border-radius: 15px;

    padding: 30px;

    box-shadow:
        0 20px 50px rgba(0,0,0,.35);
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

    border: 1px solid #334155;

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

button {
    width: 100%;

    padding: 14px;

    border: none;

    border-radius: 8px;

    background: #2563eb;

    color: white;

    font-size: 16px;

    cursor: pointer;
}

button:hover {
    background: #1d4ed8;
}

.error {
    background: #450a0a;

    border: 1px solid #7f1d1d;

    color: #fecaca;

    padding: 13px;

    border-radius: 8px;

    margin-bottom: 20px;
}

.info {
    background: #172554;

    border: 1px solid #1e40af;

    color: #bfdbfe;

    padding: 13px;

    border-radius: 8px;

    margin-bottom: 25px;
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
        Security Incident Ticketing System
    </strong>

    <div>

        <?php if ($_SESSION['role'] === 'admin'): ?>

            <a href="../admin/dashboard.php">
                Dashboard
            </a>

        <?php elseif ($_SESSION['role'] === 'analyst'): ?>

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

        <h1>
            Create Security Incident
        </h1>

        <p class="subtitle">
            Report a new cybersecurity incident.
        </p>


        <?php if ($error): ?>

            <div class="error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <div class="info">

            Reporter:
            <strong>
                <?= e($_SESSION['name']) ?>
            </strong>

            |
            
            Role:
            <strong>
                <?= e(ucfirst($_SESSION['role'])) ?>
            </strong>

        </div>


        <form method="POST">
             <?= csrfField() ?>
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >


            <!-- TITLE -->

            <div class="form-group">

                <label>
                    Incident Title
                </label>

                <input
                    type="text"
                    name="title"
                    placeholder="Example: Suspicious Login Detected"
                    maxlength="255"
                    required
                    value="<?= e($_POST['title'] ?? '') ?>"
                >

            </div>


            <!-- DESCRIPTION -->

            <div class="form-group">

                <label>
                    Incident Description
                </label>

                <textarea
                    name="description"
                    placeholder="Describe what happened, when it happened, affected systems, suspicious activity, etc."
                    required
                ><?= e($_POST['description'] ?? '') ?></textarea>

            </div>


            <div class="row">


                <!-- CATEGORY -->

                <div class="form-group">

                    <label>
                        Incident Category
                    </label>

                    <select
                        name="category_id"
                        required
                    >

                        <option value="">
                            -- Select Category --
                        </option>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= e((string)$category['id']) ?>"
                                <?= (
                                    isset($_POST['category_id'])
                                    &&
                                    (int)$_POST['category_id']
                                    === (int)$category['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e($category['name']) ?>

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

                        <option value="low">
                            Low
                        </option>

                        <option value="medium">
                            Medium
                        </option>

                        <option value="high">
                            High
                        </option>

                        <option value="critical">
                            Critical
                        </option>

                    </select>

                </div>

            </div>


            <button type="submit">
                Create Incident Ticket
            </button>

        </form>

    </div>

</div>


</body>

</html>