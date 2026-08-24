<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireRole('admin');


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search   = trim($_GET['search'] ?? '');
$status   = trim($_GET['status'] ?? '');
$severity = trim($_GET['severity'] ?? '');
$category = (int)($_GET['category'] ?? 0);

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');


/*
|--------------------------------------------------------------------------
| Categories
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

    WHERE 1 = 1
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
        )
    ";

    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}


/*
|--------------------------------------------------------------------------
| Status
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
| Severity
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
| Category
|--------------------------------------------------------------------------
*/

if ($category > 0) {

    $sql .= "
        AND i.category_id = ?
    ";

    $params[] = $category;
}


/*
|--------------------------------------------------------------------------
| Date From
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $sql .= "
        AND DATE(i.created_at) >= ?
    ";

    $params[] = $dateFrom;
}


/*
|--------------------------------------------------------------------------
| Date To
|--------------------------------------------------------------------------
*/

if ($dateTo !== '') {

    $sql .= "
        AND DATE(i.created_at) <= ?
    ";

    $params[] = $dateTo;
}


/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY i.created_at DESC
";


/*
|--------------------------------------------------------------------------
| Execute Query
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

$totalResults = count($incidents);

$criticalCount = 0;
$openCount = 0;
$resolvedCount = 0;

foreach ($incidents as $row) {

    if ($row['severity'] === 'critical') {
        $criticalCount++;
    }

    if ($row['status'] === 'open') {
        $openCount++;
    }

    if ($row['status'] === 'resolved') {
        $resolvedCount++;
    }
}


/*
|--------------------------------------------------------------------------
| Export Query
|--------------------------------------------------------------------------
*/

$exportQuery = http_build_query([
    'search'   => $search,
    'status'   => $status,
    'severity' => $severity,
    'category' => $category,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo
]);

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
    Incident Reports
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


.navbar a {

    color: #cbd5e1;

    text-decoration: none;

    margin-left: 18px;

    font-size: 13px;
}


.navbar a:hover {

    color: white;
}


.logout {

    background: #dc2626;

    color: white !important;

    padding: 9px 13px;

    border-radius: 7px;
}


/* =========================================================
   CONTAINER
========================================================= */

.container {

    max-width: 1450px;

    margin: auto;

    padding: 30px;
}


/* =========================================================
   HEADER
========================================================= */

.header {

    margin-bottom: 25px;
}


.header h1 {

    margin: 0 0 7px;

    font-size: 30px;
}


.header p {

    margin: 0;

    color: #94a3b8;
}


/* =========================================================
   STATS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 22px;
}


.stat {

    background: #111827;

    border: 1px solid #263244;

    border-radius: 12px;

    padding: 20px;
}


.stat-title {

    color: #94a3b8;

    font-size: 12px;

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

    border-radius: 14px;

    margin-bottom: 22px;

    overflow: hidden;
}


.card-header {

    padding: 20px;

    border-bottom: 1px solid #263244;
}


.card-header h2 {

    margin: 0;

    font-size: 19px;
}


/* =========================================================
   FILTERS
========================================================= */

.filters {

    padding: 20px;

    display: grid;

    grid-template-columns:
        2fr
        1fr
        1fr
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


.filter-btn,
.reset-btn,
.export-btn {

    display: inline-block;

    border: none;

    padding: 11px 15px;

    border-radius: 8px;

    cursor: pointer;

    font-weight: 700;

    text-decoration: none;

    white-space: nowrap;
}


.filter-btn {

    background: #2563eb;

    color: white;
}


.reset-btn {

    background: #334155;

    color: white;
}


.export-wrapper {

    padding: 0 20px 20px;
}


.export-btn {

    background: #059669;

    color: white;
}


.filter-btn:hover {

    background: #1d4ed8;
}


.reset-btn:hover {

    background: #475569;
}


.export-btn:hover {

    background: #047857;
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

    text-align: left;

    padding: 14px 16px;

    background: #0f172a;

    color: #94a3b8;

    font-size: 11px;

    text-transform: uppercase;
}


td {

    padding: 14px 16px;

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


/* =========================================================
   BADGES
========================================================= */

.badge {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;
}


.badge-critical {

    background: #450a0a;

    color: #fca5a5;
}


.badge-high {

    background: #7f1d1d;

    color: #fecaca;
}


.badge-medium {

    background: #78350f;

    color: #fde68a;
}


.badge-low {

    background: #064e3b;

    color: #a7f3d0;
}


.badge-open {

    background: #172554;

    color: #bfdbfe;
}


.badge-progress {

    background: #164e63;

    color: #a5f3fc;
}


.badge-resolved {

    background: #14532d;

    color: #bbf7d0;
}


.badge-closed {

    background: #374151;

    color: #d1d5db;
}


.view-btn {

    color: #38bdf8;

    text-decoration: none;

    font-weight: 700;
}


.empty {

    padding: 50px;

    text-align: center;

    color: #64748b;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .filters {

        grid-template-columns:
            repeat(3, 1fr);
    }

}


@media(max-width:800px) {

    .navbar {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }


    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .filters {

        grid-template-columns: 1fr;
    }


    .container {

        padding: 18px;
    }

}


@media(max-width:500px) {

    .stats {

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


    <div>

        <a href="../admin/dashboard.php">
            Dashboard
        </a>

        <a href="../admin/users.php">
            Users
        </a>

        <a href="../admin/incidents.php">
            Incidents
        </a>

        <a href="incidents-report.php">
            Reports
        </a>

        <a
            href="../auth/logout.php"
            class="logout"
        >
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
            📊 Incident Reports
        </h1>

        <p>
            Search, filter and analyse security incidents.
        </p>

    </div>


    <!-- =================================================
         STATISTICS
    ================================================== -->

    <div class="stats">


        <div class="stat">

            <div class="stat-title">
                TOTAL RESULTS
            </div>

            <div class="stat-value">

                <?= e(
                    (string)$totalResults
                ) ?>

            </div>

        </div>


        <div class="stat">

            <div class="stat-title">
                CRITICAL
            </div>

            <div class="stat-value">

                <?= e(
                    (string)$criticalCount
                ) ?>

            </div>

        </div>


        <div class="stat">

            <div class="stat-title">
                OPEN
            </div>

            <div class="stat-value">

                <?= e(
                    (string)$openCount
                ) ?>

            </div>

        </div>


        <div class="stat">

            <div class="stat-title">
                RESOLVED
            </div>

            <div class="stat-value">

                <?= e(
                    (string)$resolvedCount
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
                🔎 Report Filters
            </h2>

        </div>


        <form
            method="GET"
            action="incidents-report.php"
            class="filters"
        >


            <input
                type="text"
                name="search"
                placeholder="Ticket number or title..."
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


            <select name="category">

                <option value="0">
                    All Categories
                </option>


                <?php foreach (
                    $categories
                    as $cat
                ): ?>

                    <option
                        value="<?= e(
                            (string)$cat['id']
                        ) ?>"
                        <?= (
                            $category == $cat['id']
                        )
                            ? 'selected'
                            : '' ?>
                    >

                        <?= e(
                            $cat['name']
                        ) ?>

                    </option>

                <?php endforeach; ?>


            </select>


            <input
                type="date"
                name="date_from"
                value="<?= e($dateFrom) ?>"
            >


            <input
                type="date"
                name="date_to"
                value="<?= e($dateTo) ?>"
            >


            <button
                type="submit"
                class="filter-btn"
            >
                Filter
            </button>


            <a
                href="incidents-report.php"
                class="reset-btn"
            >
                Reset
            </a>


        </form>


        <div class="export-wrapper">

            <a
                href="export.php?<?= e($exportQuery) ?>"
                class="export-btn"
            >
                📥 Export CSV
            </a>

        </div>


    </div>


    <!-- =================================================
         INCIDENT TABLE
    ================================================== -->

    <div class="card">


        <div class="card-header">

            <h2>
                Security Incidents
            </h2>

        </div>


        <?php if (!$incidents): ?>


            <div class="empty">

                No incidents found for the selected filters.

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
                                Assigned Analyst
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
                        as $row
                    ): ?>


                        <?php

                        $severityName =
                            $row['severity'];

                        $statusName =
                            $row['status'];

                        if (
                            $statusName
                            ===
                            'in_progress'
                        ) {

                            $statusBadge =
                                'progress';

                        } else {

                            $statusBadge =
                                $statusName;
                        }

                        ?>


                        <tr>


                            <td>

                                <span class="ticket">

                                    <?= e(
                                        $row[
                                            'ticket_number'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= e(
                                    $row['title']
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $row[
                                        'category_name'
                                    ]
                                    ??
                                    'N/A'
                                ) ?>

                            </td>


                            <td>

                                <span
                                    class="badge badge-<?=
                                        e(
                                            $severityName
                                        )
                                    ?>"
                                >

                                    <?= e(
                                        ucfirst(
                                            $severityName
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="badge badge-<?=
                                        e(
                                            $statusBadge
                                        )
                                    ?>"
                                >

                                    <?= e(
                                        ucfirst(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $statusName
                                            )
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= e(
                                    $row[
                                        'reporter_name'
                                    ]
                                    ??
                                    'N/A'
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $row[
                                        'analyst_name'
                                    ]
                                    ??
                                    'Not Assigned'
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $row[
                                        'created_at'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <a
                                    href="../incidents/view.php?id=<?= e(
                                        (string)$row['id']
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