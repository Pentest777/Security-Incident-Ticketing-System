<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireRole('analyst');

$userId = (int)$_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$severity = trim($_GET['severity'] ?? '');


/*
|--------------------------------------------------------------------------
| Base Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        i.id,
        i.ticket_number,
        i.title,
        i.description,
        i.severity,
        i.status,
        i.created_at,
        i.updated_at,

        c.name AS category_name,

        u.name AS reporter_name

    FROM incidents i

    LEFT JOIN incident_categories c
        ON i.category_id = c.id

    LEFT JOIN users u
        ON i.reported_by = u.id

    WHERE i.assigned_to = ?
";


$params = [
    $userId
];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            i.ticket_number LIKE ?
            OR i.title LIKE ?
        )
    ";

    $params[] =
        '%' . $search . '%';

    $params[] =
        '%' . $search . '%';
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'open',
    'in_progress',
    'resolved',
    'closed'
];

if (
    in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    $sql .= "
        AND i.status = ?
    ";

    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| Severity Filter
|--------------------------------------------------------------------------
*/

$allowedSeverities = [
    'low',
    'medium',
    'high',
    'critical'
];

if (
    in_array(
        $severity,
        $allowedSeverities,
        true
    )
) {

    $sql .= "
        AND i.severity = ?
    ";

    $params[] = $severity;
}


/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        CASE i.severity
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
            ELSE 5
        END,
        i.created_at DESC
";


/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$incidents = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$total = count($incidents);

$open = 0;
$inProgress = 0;
$resolved = 0;
$critical = 0;

foreach ($incidents as $incident) {

    if (
        $incident['status']
        ===
        'open'
    ) {

        $open++;
    }


    if (
        $incident['status']
        ===
        'in_progress'
    ) {

        $inProgress++;
    }


    if (
        $incident['status']
        ===
        'resolved'
    ) {

        $resolved++;
    }


    if (
        $incident['severity']
        ===
        'critical'
    ) {

        $critical++;
    }
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
    My Assigned Incidents
</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #0f172a;

    color: #f8fafc;
}


/* =========================================================
   NAVBAR
========================================================= */

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


.nav-links {

    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}


.nav-links a {

    color: #cbd5e1;

    text-decoration: none;

    padding: 9px 11px;

    border-radius: 7px;

    font-size: 13px;
}


.nav-links a:hover {

    background: #1e293b;

    color: white;
}


.logout {

    background: #dc2626 !important;

    color: white !important;
}


/* =========================================================
   CONTAINER
========================================================= */

.container {

    max-width: 1400px;

    margin: auto;

    padding: 30px;
}


.header {

    margin-bottom: 25px;
}


.header h1 {

    margin: 0 0 6px;

    font-size: 30px;
}


.header p {

    margin: 0;

    color: #94a3b8;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 16px;

    margin-bottom: 22px;
}


.stat {

    background: #111827;

    border: 1px solid #263244;

    border-radius: 12px;

    padding: 20px;
}


.stat-label {

    color: #94a3b8;

    font-size: 11px;

    text-transform: uppercase;

    margin-bottom: 8px;
}


.stat-value {

    font-size: 30px;

    font-weight: 700;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background: #111827;

    border: 1px solid #263244;

    border-radius: 13px;

    overflow: hidden;
}


.card-header {

    padding: 18px 20px;

    border-bottom: 1px solid #263244;
}


.card-header h2 {

    margin: 0;

    font-size: 18px;
}


/* =========================================================
   FILTER
========================================================= */

.filters {

    padding: 20px;

    display: grid;

    grid-template-columns:
        2fr
        1fr
        1fr
        auto
        auto;

    gap: 12px;
}


input,
select {

    width: 100%;

    padding: 11px 12px;

    background: #0f172a;

    color: #f8fafc;

    border: 1px solid #334155;

    border-radius: 8px;

    outline: none;
}


input:focus,
select:focus {

    border-color: #38bdf8;
}


button,
.reset {

    border: none;

    padding: 11px 16px;

    border-radius: 8px;

    cursor: pointer;

    font-weight: 700;

    text-decoration: none;

    text-align: center;
}


button {

    background: #2563eb;

    color: white;
}


.reset {

    background: #334155;

    color: white;
}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}


table {

    width: 100%;

    min-width: 1050px;

    border-collapse: collapse;
}


th {

    background: #0f172a;

    color: #94a3b8;

    text-align: left;

    padding: 14px;

    font-size: 11px;

    text-transform: uppercase;
}


td {

    padding: 14px;

    border-top: 1px solid #1e293b;

    font-size: 13px;
}


tbody tr:hover {

    background: #172033;
}


.ticket {

    color: #38bdf8;

    font-weight: 700;
}


.title {

    max-width: 300px;

    color: #e2e8f0;
}


.category {

    color: #94a3b8;

    font-size: 11px;
}


.date {

    color: #94a3b8;

    font-size: 11px;
}


/* =========================================================
   BADGES
========================================================= */

.badge {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 9px;

    font-weight: 700;

    text-transform: uppercase;

    white-space: nowrap;
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


/* =========================================================
   VIEW
========================================================= */

.view-btn {

    display: inline-block;

    padding: 7px 11px;

    background: #0369a1;

    color: white;

    text-decoration: none;

    border-radius: 7px;

    font-size: 11px;

    font-weight: 700;
}


.view-btn:hover {

    background: #0284c7;
}


.empty {

    padding: 50px;

    text-align: center;

    color: #64748b;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .filters {

        grid-template-columns:
            1fr 1fr;
    }

}


@media(max-width:700px) {

    .navbar {

        flex-direction: column;

        align-items: flex-start;
    }


    .container {

        padding: 15px;
    }


    .stats {

        grid-template-columns: 1fr;
    }


    .filters {

        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     NAVBAR
===================================================== -->

<div class="navbar">


    <div class="brand">

        🛡 Security Incident Ticketing

    </div>


    <div class="nav-links">

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="assigned-incidents.php">
            My Incidents
        </a>

        <a href="../auth/logout.php" class="logout">
            Logout
        </a>

    </div>


</div>


<!-- =====================================================
     MAIN
===================================================== -->

<div class="container">


    <div class="header">

        <h1>
            My Assigned Incidents
        </h1>

        <p>
            View and investigate incidents assigned to you.
        </p>

    </div>


    <!-- =================================================
         STATISTICS
    ================================================== -->

    <div class="stats">


        <div class="stat">

            <div class="stat-label">
                Total
            </div>

            <div class="stat-value">

                <?= e(
                    (string)$total
                ) ?>

            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Open
            </div>

            <div class="stat-value">

                <?= e(
                    (string)$open
                ) ?>

            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                In Progress
            </div>

            <div class="stat-value">

                <?= e(
                    (string)$inProgress
                ) ?>

            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Critical
            </div>

            <div class="stat-value">

                <?= e(
                    (string)$critical
                ) ?>

            </div>

        </div>


    </div>


    <!-- =================================================
         FILTER CARD
    ================================================== -->

    <div class="card">


        <div class="card-header">

            <h2>
                🔎 Filter Assigned Incidents
            </h2>

        </div>


        <form
            method="GET"
            action="assigned-incidents.php"
            class="filters"
        >


            <input
                type="text"
                name="search"
                placeholder="Search ticket or title..."
                value="<?= e($search) ?>"
            >


            <select name="status">

                <option value="">
                    All Status
                </option>

                <option
                    value="open"
                    <?= $status === 'open'
                        ? 'selected'
                        : '' ?>
                >
                    Open
                </option>

                <option
                    value="in_progress"
                    <?= $status === 'in_progress'
                        ? 'selected'
                        : '' ?>
                >
                    In Progress
                </option>

                <option
                    value="resolved"
                    <?= $status === 'resolved'
                        ? 'selected'
                        : '' ?>
                >
                    Resolved
                </option>

                <option
                    value="closed"
                    <?= $status === 'closed'
                        ? 'selected'
                        : '' ?>
                >
                    Closed
                </option>

            </select>


            <select name="severity">

                <option value="">
                    All Severity
                </option>

                <option
                    value="low"
                    <?= $severity === 'low'
                        ? 'selected'
                        : '' ?>
                >
                    Low
                </option>

                <option
                    value="medium"
                    <?= $severity === 'medium'
                        ? 'selected'
                        : '' ?>
                >
                    Medium
                </option>

                <option
                    value="high"
                    <?= $severity === 'high'
                        ? 'selected'
                        : '' ?>
                >
                    High
                </option>

                <option
                    value="critical"
                    <?= $severity === 'critical'
                        ? 'selected'
                        : '' ?>
                >
                    Critical
                </option>

            </select>


            <button type="submit">
                Filter
            </button>


            <a
                href="assigned-incidents.php"
                class="reset"
            >
                Reset
            </a>


        </form>


    </div>


    <br>


    <!-- =================================================
         INCIDENT TABLE
    ================================================== -->

    <div class="card">


        <div class="card-header">

            <h2>
                Assigned Incident List
            </h2>

        </div>


        <?php if (!$incidents): ?>


            <div class="empty">

                No assigned incidents found.

            </div>


        <?php else: ?>


            <div class="table-wrapper">


                <table>


                    <thead>

                        <tr>

                            <th>
                                Ticket
                            </th>

                            <th>
                                Title
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Severity
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Reporter
                            </th>

                            <th>
                                Created
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $incidents
                        as $incident
                    ): ?>


                        <tr>


                            <td>

                                <span class="ticket">

                                    <?= e(
                                        $incident[
                                            'ticket_number'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <div class="title">

                                    <?= e(
                                        $incident[
                                            'title'
                                        ]
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <span class="category">

                                    <?= e(
                                        $incident[
                                            'category_name'
                                        ]
                                        ??
                                        'N/A'
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="badge <?= e(
                                        $incident[
                                            'severity'
                                        ]
                                    ) ?>"
                                >

                                    <?= e(
                                        ucfirst(
                                            $incident[
                                                'severity'
                                            ]
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="badge <?= e(
                                        $incident[
                                            'status'
                                        ]
                                    ) ?>"
                                >

                                    <?= e(
                                        ucfirst(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $incident[
                                                    'status'
                                                ]
                                            )
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= e(
                                    $incident[
                                        'reporter_name'
                                    ]
                                    ??
                                    'N/A'
                                ) ?>

                            </td>


                            <td>

                                <span class="date">

                                    <?= e(
                                        $incident[
                                            'created_at'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <a
                                    href="../incidents/view.php?id=<?= e(
                                        (string)$incident['id']
                                    ) ?>"
                                    class="view-btn"
                                >
                                    View
                                </a>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>