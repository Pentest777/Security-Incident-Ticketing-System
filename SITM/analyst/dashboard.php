<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireRole('analyst');

$userId = (int)$_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Assigned Incident Statistics
|--------------------------------------------------------------------------
*/

$totalAssigned = (int)$pdo->prepare(
    "SELECT COUNT(*)
     FROM incidents
     WHERE assigned_to = ?"
)->execute([$userId])
    ? 0
    : 0;


/*
|--------------------------------------------------------------------------
| Get Counts
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM incidents
     WHERE assigned_to = ?"
);

$stmt->execute([$userId]);

$totalAssigned = (int)$stmt->fetchColumn();


$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM incidents
     WHERE assigned_to = ?
     AND status = 'open'"
);

$stmt->execute([$userId]);

$openIncidents = (int)$stmt->fetchColumn();


$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM incidents
     WHERE assigned_to = ?
     AND status = 'in_progress'"
);

$stmt->execute([$userId]);

$inProgressIncidents = (int)$stmt->fetchColumn();


$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM incidents
     WHERE assigned_to = ?
     AND status = 'resolved'"
);

$stmt->execute([$userId]);

$resolvedIncidents = (int)$stmt->fetchColumn();


$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM incidents
     WHERE assigned_to = ?
     AND severity = 'critical'
     AND status NOT IN ('resolved', 'closed')"
);

$stmt->execute([$userId]);

$criticalIncidents = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Recent Assigned Incidents
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        i.id,
        i.ticket_number,
        i.title,
        i.severity,
        i.status,
        i.created_at,
        c.name AS category_name

     FROM incidents i

     LEFT JOIN incident_categories c
        ON i.category_id = c.id

     WHERE i.assigned_to = ?

     ORDER BY i.created_at DESC

     LIMIT 10"
);

$stmt->execute([$userId]);

$recentIncidents =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Severity Statistics
|--------------------------------------------------------------------------
*/

$severityStats = [
    'low' => 0,
    'medium' => 0,
    'high' => 0,
    'critical' => 0
];


$stmt = $pdo->prepare(
    "SELECT
        severity,
        COUNT(*) AS total

     FROM incidents

     WHERE assigned_to = ?

     GROUP BY severity"
);

$stmt->execute([$userId]);

foreach (
    $stmt->fetchAll()
    as $row
) {

    if (
        isset(
            $severityStats[
                $row['severity']
            ]
        )
    ) {

        $severityStats[
            $row['severity']
        ] = (int)$row['total'];
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
    Analyst Dashboard
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
        repeat(5, 1fr);

    gap: 16px;

    margin-bottom: 25px;
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
   GRID
========================================================= */

.grid {

    display: grid;

    grid-template-columns:
        2fr 1fr;

    gap: 20px;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background: #111827;

    border: 1px solid #263244;

    border-radius: 13px;

    margin-bottom: 20px;

    overflow: hidden;
}


.card-header {

    padding: 18px 20px;

    border-bottom: 1px solid #263244;

    display: flex;

    justify-content: space-between;

    align-items: center;
}


.card-header h2 {

    margin: 0;

    font-size: 18px;
}


.card-body {

    padding: 20px;
}


/* =========================================================
   INCIDENT
========================================================= */

.incident {

    padding: 15px 0;

    border-bottom: 1px solid #1e293b;

    display: grid;

    grid-template-columns:
        120px
        1fr
        90px
        110px
        70px;

    gap: 12px;

    align-items: center;
}


.incident:last-child {

    border-bottom: none;
}


.ticket {

    color: #38bdf8;

    font-weight: 700;

    font-size: 12px;
}


.title {

    font-size: 13px;

    color: #e2e8f0;
}


.category {

    color: #64748b;

    font-size: 11px;

    margin-top: 4px;
}


/* =========================================================
   BADGES
========================================================= */

.badge {

    display: inline-block;

    padding: 5px 8px;

    border-radius: 20px;

    font-size: 9px;

    font-weight: 700;

    text-transform: uppercase;
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
   LINKS
========================================================= */

.view-link {

    color: #38bdf8;

    text-decoration: none;

    font-size: 11px;

    font-weight: 700;
}


/* =========================================================
   BREAKDOWN
========================================================= */

.breakdown {

    margin-bottom: 18px;
}


.breakdown-head {

    display: flex;

    justify-content: space-between;

    margin-bottom: 7px;

    font-size: 12px;
}


.progress {

    height: 8px;

    background: #1e293b;

    border-radius: 20px;

    overflow: hidden;
}


.progress-bar {

    height: 100%;

    background: #38bdf8;

    border-radius: 20px;
}


.empty {

    text-align: center;

    color: #64748b;

    padding: 30px;
}


/* =========================================================
   QUICK ACTION
========================================================= */

.action {

    display: block;

    background: #0f172a;

    border: 1px solid #263244;

    border-radius: 10px;

    padding: 16px;

    color: white;

    text-decoration: none;
}


.action:hover {

    border-color: #38bdf8;
}


.action-title {

    font-weight: 700;

    font-size: 13px;
}


.action-text {

    color: #64748b;

    font-size: 10px;

    margin-top: 5px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .grid {

        grid-template-columns: 1fr;
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


    .incident {

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
            Analyst Dashboard
        </h1>

        <p>
            Your assigned security incidents and investigation workload.
        </p>

    </div>


    <!-- =================================================
         STATISTICS
    ================================================== -->

    <div class="stats">


        <div class="stat">

            <div class="stat-label">
                Assigned
            </div>

            <div class="stat-value">

                <?= e(
                    (string)$totalAssigned
                ) ?>

            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Open
            </div>

            <div class="stat-value">

                <?= e(
                    (string)$openIncidents
                ) ?>

            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                In Progress
            </div>

            <div class="stat-value">

                <?= e(
                    (string)$inProgressIncidents
                ) ?>

            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Resolved
            </div>

            <div class="stat-value">

                <?= e(
                    (string)$resolvedIncidents
                ) ?>

            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Critical
            </div>

            <div class="stat-value">

                <?= e(
                    (string)$criticalIncidents
                ) ?>

            </div>

        </div>


    </div>


    <!-- =================================================
         CONTENT
    ================================================== -->

    <div class="grid">


        <!-- LEFT -->

        <div>


            <div class="card">


                <div class="card-header">

                    <h2>
                        🚨 My Assigned Incidents
                    </h2>

                    <a
                        href="assigned-incidents.php"
                        class="view-link"
                    >
                        View All
                    </a>

                </div>


                <div class="card-body">


                    <?php if (!$recentIncidents): ?>


                        <div class="empty">

                            No incidents are currently assigned to you.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $recentIncidents
                            as $incident
                        ): ?>


                            <div class="incident">


                                <div>

                                    <div class="ticket">

                                        <?= e(
                                            $incident[
                                                'ticket_number'
                                            ]
                                        ) ?>

                                    </div>

                                </div>


                                <div>

                                    <div class="title">

                                        <?= e(
                                            $incident[
                                                'title'
                                            ]
                                        ) ?>

                                    </div>

                                    <div class="category">

                                        <?= e(
                                            $incident[
                                                'category_name'
                                            ]
                                            ??
                                            'Uncategorized'
                                        ) ?>

                                    </div>

                                </div>


                                <div>

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

                                </div>


                                <div>

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

                                </div>


                                <div>

                                    <a
                                        href="../incidents/view.php?id=<?= e(
                                            (string)$incident['id']
                                        ) ?>"
                                        class="view-link"
                                    >
                                        View
                                    </a>

                                </div>


                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>


            </div>


        </div>


        <!-- RIGHT -->

        <div>


            <!-- SEVERITY -->

            <div class="card">


                <div class="card-header">

                    <h2>
                        ⚠ My Severity
                    </h2>

                </div>


                <div class="card-body">


                    <?php

                    $total =
                        max(
                            1,
                            $totalAssigned
                        );

                    ?>


                    <?php foreach (
                        $severityStats
                        as $name => $count
                    ): ?>


                        <?php

                        $percentage =
                            (
                                $count /
                                $total
                            )
                            * 100;

                        ?>


                        <div class="breakdown">


                            <div class="breakdown-head">

                                <span>
                                    <?= e(
                                        ucfirst($name)
                                    ) ?>
                                </span>

                                <strong>
                                    <?= e(
                                        (string)$count
                                    ) ?>
                                </strong>

                            </div>


                            <div class="progress">

                                <div
                                    class="progress-bar"
                                    style="width: <?= e(
                                        (string)$percentage
                                    ) ?>%;"
                                ></div>

                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            </div>


            <!-- QUICK ACTION -->

            <div class="card">


                <div class="card-header">

                    <h2>
                        ⚡ Quick Action
                    </h2>

                </div>


                <div class="card-body">


                    <a
                        href="assigned-incidents.php"
                        class="action"
                    >

                        <div class="action-title">

                            📋 View Assigned Incidents

                        </div>

                        <div class="action-text">

                            Review and investigate your assigned cases.

                        </div>

                    </a>


                </div>


            </div>


        </div>


    </div>


</div>


</body>

</html>