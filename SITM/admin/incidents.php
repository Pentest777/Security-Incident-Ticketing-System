<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireRole('admin');

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$severity = $_GET['severity'] ?? 'all';

$allowedStatuses = [
    'all',
    'open',
    'in_progress',
    'resolved',
    'closed'
];

$allowedSeverities = [
    'all',
    'low',
    'medium',
    'high',
    'critical'
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}

if (!in_array($severity, $allowedSeverities, true)) {
    $severity = 'all';
}


/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        i.id,
        i.ticket_number,
        i.title,
        i.severity,
        i.status,
        i.created_at,
        i.updated_at,

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

    WHERE 1=1
";

$params = [];


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
            OR reporter.name LIKE ?
            OR analyst.name LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($status !== 'all') {

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

if ($severity !== 'all') {

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
        END,
        i.updated_at DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$incidents = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Total Counts
|--------------------------------------------------------------------------
*/

$totalIncidents = (int)$pdo
    ->query("SELECT COUNT(*) FROM incidents")
    ->fetchColumn();

$openIncidents = (int)$pdo
    ->query(
        "SELECT COUNT(*)
         FROM incidents
         WHERE status = 'open'"
    )
    ->fetchColumn();

$inProgress = (int)$pdo
    ->query(
        "SELECT COUNT(*)
         FROM incidents
         WHERE status = 'in_progress'"
    )
    ->fetchColumn();

$resolved = (int)$pdo
    ->query(
        "SELECT COUNT(*)
         FROM incidents
         WHERE status = 'resolved'"
    )
    ->fetchColumn();

$critical = (int)$pdo
    ->query(
        "SELECT COUNT(*)
         FROM incidents
         WHERE severity = 'critical'
         AND status != 'closed'"
    )
    ->fetchColumn();

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
    Incident Management
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

    max-width: 1500px;

    margin: auto;

    padding: 30px;
}


/* HEADER */

.header {

    margin-bottom: 25px;
}

.header h1 {

    margin-bottom: 8px;
}

.header p {

    color: #94a3b8;
}


/* STAT CARDS */

.stats {

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 15px;

    margin-bottom: 25px;
}

.stat {

    background: #111827;

    border:
        1px solid #263244;

    border-radius: 12px;

    padding: 18px;
}

.stat-label {

    color: #94a3b8;

    font-size: 13px;

    margin-bottom: 8px;
}

.stat-value {

    font-size: 28px;

    font-weight: 700;
}

.critical-value {

    color: #f87171;
}


/* FILTER CARD */

.filter-card {

    background: #111827;

    border:
        1px solid #263244;

    border-radius: 12px;

    padding: 20px;

    margin-bottom: 20px;
}

.filters {

    display: grid;

    grid-template-columns:
        2fr 1fr 1fr auto;

    gap: 12px;

    align-items: end;
}

.form-group label {

    display: block;

    color: #94a3b8;

    font-size: 13px;

    margin-bottom: 7px;
}

input,
select {

    width: 100%;

    padding: 11px 13px;

    border:
        1px solid #334155;

    border-radius: 8px;

    background: #0f172a;

    color: white;

    outline: none;
}

input:focus,
select:focus {

    border-color: #38bdf8;
}

button,
.btn {

    display: inline-block;

    padding: 11px 16px;

    border: none;

    border-radius: 8px;

    background: #2563eb;

    color: white;

    text-decoration: none;

    cursor: pointer;

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


/* TABLE */

.table-card {

    background: #111827;

    border:
        1px solid #263244;

    border-radius: 12px;

    overflow: hidden;
}

.table-wrapper {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1150px;
}

th,
td {

    padding: 14px;

    text-align: left;

    border-bottom:
        1px solid #1e293b;
}

th {

    color: #94a3b8;

    font-size: 12px;

    text-transform: uppercase;
}

td {

    color: #e2e8f0;
}

.ticket {

    color: #38bdf8;

    font-weight: 700;
}

.title {

    font-weight: 600;
}

.small {

    display: block;

    color: #64748b;

    font-size: 11px;

    margin-top: 4px;
}


/* BADGES */

.badge {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 700;
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


/* ACTIONS */

.actions {

    display: flex;

    gap: 6px;

    flex-wrap: wrap;
}

.action {

    padding: 7px 10px;

    border-radius: 6px;

    color: white;

    text-decoration: none;

    background: #2563eb;

    font-size: 11px;
}

.action:hover {

    background: #1d4ed8;
}

.action-edit {

    background: #7c3aed;
}

.action-assign {

    background: #0891b2;
}

.action-status {

    background: #059669;
}


/* EMPTY */

.empty {

    padding: 50px;

    text-align: center;

    color: #94a3b8;
}


/* RESPONSIVE */

@media(max-width:1000px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .filters {

        grid-template-columns: 1fr;
    }

}

@media(max-width:700px) {

    .container {

        padding: 15px;
    }

    .stats {

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


<!-- NAVBAR -->

<div class="navbar">

    <div class="brand">

        🛡 Security Incident Ticketing

    </div>


    <div>

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="users.php">
            Users
        </a>

        <a href="incidents.php">
            Incidents
        </a>

        <a href="../incidents/create.php">
            + New Incident
        </a>

        <a href="../auth/logout.php">
            Logout
        </a>

    </div>

</div>


<div class="container">


    <div class="header">

        <h1>
            Incident Management
        </h1>

        <p>
            Manage, classify, assign and track all
            security incidents.
        </p>

    </div>


    <!-- STATISTICS -->

    <div class="stats">


        <div class="stat">

            <div class="stat-label">
                Total Incidents
            </div>

            <div class="stat-value">
                <?= $totalIncidents ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Open
            </div>

            <div class="stat-value">
                <?= $openIncidents ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                In Progress
            </div>

            <div class="stat-value">
                <?= $inProgress ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Resolved
            </div>

            <div class="stat-value">
                <?= $resolved ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Critical Active
            </div>

            <div
                class="stat-value critical-value"
            >
                <?= $critical ?>
            </div>

        </div>


    </div>


    <!-- FILTER -->

    <div class="filter-card">

        <form method="GET">

            <div class="filters">


                <div class="form-group">

                    <label>
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        placeholder="Ticket, title, reporter, analyst..."
                        value="<?= e($search) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option
                            value="all"
                            <?= $status === 'all'
                                ? 'selected'
                                : '' ?>
                        >
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

                </div>


                <div class="form-group">

                    <label>
                        Severity
                    </label>

                    <select name="severity">

                        <option
                            value="all"
                            <?= $severity === 'all'
                                ? 'selected'
                                : '' ?>
                        >
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

                </div>


                <div>

                    <button type="submit">
                        Apply Filters
                    </button>

                    <a
                        href="incidents.php"
                        class="btn btn-secondary"
                    >
                        Reset
                    </a>

                </div>


            </div>

        </form>

    </div>


    <!-- INCIDENT TABLE -->

    <div class="table-card">


        <?php if (!$incidents): ?>

            <div class="empty">

                No incidents found.

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
                                Incident
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Reporter
                            </th>

                            <th>
                                Analyst
                            </th>

                            <th>
                                Severity
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Updated
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $incidents
                        as $incident
                    ): ?>


                        <tr>


                            <td class="ticket">

                                <?= e(
                                    $incident[
                                        'ticket_number'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <div class="title">

                                    <?= e(
                                        $incident['title']
                                    ) ?>

                                </div>

                                <span class="small">

                                    Created:
                                    <?= e(
                                        $incident['created_at']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= e(
                                    $incident[
                                        'category_name'
                                    ]
                                    ?? 'N/A'
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $incident[
                                        'reporter_name'
                                    ]
                                    ?? 'N/A'
                                ) ?>

                            </td>


                            <td>

                                <?php if (
                                    $incident[
                                        'analyst_name'
                                    ]
                                ): ?>

                                    <?= e(
                                        $incident[
                                            'analyst_name'
                                        ]
                                    ) ?>

                                <?php else: ?>

                                    <span
                                        style="color:#f59e0b;"
                                    >
                                        Not Assigned
                                    </span>

                                <?php endif; ?>

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
                                        ucwords(
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
                                        'updated_at'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <div class="actions">


                                    <a
                                        class="action"
                                        href="../incidents/view.php?id=<?= e(
                                            (string)$incident['id']
                                        ) ?>"
                                    >
                                        View
                                    </a>


                                    <a
                                        class="action action-edit"
                                        href="../incidents/edit.php?id=<?= e(
                                            (string)$incident['id']
                                        ) ?>"
                                    >
                                        Edit
                                    </a>


                                    <a
                                        class="action action-assign"
                                        href="../incidents/assign.php?id=<?= e(
                                            (string)$incident['id']
                                        ) ?>"
                                    >
                                        Assign
                                    </a>


                                    <a
                                        class="action action-status"
                                        href="../incidents/update-status.php?id=<?= e(
                                            (string)$incident['id']
                                        ) ?>"
                                    >
                                        Status
                                    </a>


                                </div>

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