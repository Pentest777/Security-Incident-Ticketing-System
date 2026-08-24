<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireRole('admin');


/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$totalUsers = $pdo
    ->query(
        "SELECT COUNT(*)
         FROM users"
    )
    ->fetchColumn();


$totalIncidents = $pdo
    ->query(
        "SELECT COUNT(*)
         FROM incidents"
    )
    ->fetchColumn();


$openIncidents = $pdo
    ->query(
        "SELECT COUNT(*)
         FROM incidents
         WHERE status = 'open'"
    )
    ->fetchColumn();


$criticalIncidents = $pdo
    ->query(
        "SELECT COUNT(*)
         FROM incidents
         WHERE severity = 'critical'"
    )
    ->fetchColumn();


/*
|--------------------------------------------------------------------------
| Recent Login Audit Logs
|--------------------------------------------------------------------------
*/

$loginAuditStmt = $pdo->query(
    "SELECT
        id,
        email,
        ip_address,
        event_type,
        created_at

     FROM login_audit_logs

     ORDER BY created_at DESC

     LIMIT 10"
);

$loginAuditLogs =
    $loginAuditStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Event Class Helper
|--------------------------------------------------------------------------
*/

function getEventClass(string $eventType): string
{
    switch ($eventType) {

        case 'LOGIN_SUCCESS':
            return 'success';

        case 'LOGIN_FAILED':
            return 'failed';

        case 'LOGIN_LOCKED':
            return 'locked';

        case 'ACCOUNT_INACTIVE':
            return 'inactive';

        default:
            return 'normal';
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
    Admin Dashboard |
    Security Incident Ticketing
</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}


/* =========================================================
   BODY
========================================================= */

body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #0f172a;

    color: #f8fafc;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    padding: 18px 30px;

    background: #111827;

    border-bottom:
        1px solid #263244;

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

    align-items: center;

    gap: 10px;

    flex-wrap: wrap;
}


.nav-links a {

    color: #cbd5e1;

    text-decoration: none;

    padding: 8px 11px;

    border-radius: 7px;

    font-size: 13px;
}


.nav-links a:hover {

    background: #1e293b;

    color: white;
}


.logout {

    color: white !important;

    background: #dc2626 !important;

    padding: 10px 15px !important;

    border-radius: 7px;

    text-decoration: none;
}


.logout:hover {

    background: #b91c1c !important;
}


/* =========================================================
   CONTAINER
========================================================= */

.container {

    max-width: 1400px;

    margin: auto;

    padding: 30px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    margin-bottom: 25px;
}


.page-header h1 {

    margin: 0 0 8px;

    font-size: 30px;
}


.page-header p {

    margin: 0;

    color: #94a3b8;
}


/* =========================================================
   STATISTICS CARDS
========================================================= */

.cards {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 20px;

    margin-bottom: 25px;
}


.stat-card {

    background: #1e293b;

    border:
        1px solid #334155;

    padding: 25px;

    border-radius: 12px;

    transition:
        transform 0.2s ease,
        border-color 0.2s ease;
}


.stat-card:hover {

    transform: translateY(-2px);

    border-color: #475569;
}


.stat-card h3 {

    color: #94a3b8;

    font-size: 13px;

    font-weight: 600;

    margin: 0 0 10px;
}


.stat-card h2 {

    font-size: 35px;

    margin: 0;
}


.stat-blue h2 {

    color: #38bdf8;
}


.stat-orange h2 {

    color: #fbbf24;
}


.stat-red h2 {

    color: #f87171;
}


.stat-green h2 {

    color: #4ade80;
}


/* =========================================================
   MAIN CARD
========================================================= */

.card {

    background: #111827;

    border:
        1px solid #263244;

    border-radius: 14px;

    margin-bottom: 25px;

    overflow: hidden;
}


/* =========================================================
   CARD HEADER
========================================================= */

.card-header {

    padding: 20px 22px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    border-bottom:
        1px solid #263244;
}


.card-header h2 {

    margin: 0 0 5px;

    font-size: 20px;
}


.card-header p {

    margin: 0;

    color: #94a3b8;

    font-size: 13px;
}


.view-all {

    color: #38bdf8;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

    white-space: nowrap;
}


.view-all:hover {

    text-decoration: underline;
}


/* =========================================================
   LOGIN TABLE
========================================================= */

.login-table-wrapper {

    width: 100%;

    overflow-x: auto;
}


.login-table {

    width: 100%;

    min-width: 750px;

    border-collapse: collapse;
}


.login-table th {

    text-align: left;

    padding: 13px 18px;

    background: #0f172a;

    color: #94a3b8;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: 0.5px;
}


.login-table td {

    padding: 14px 18px;

    color: #e2e8f0;

    font-size: 13px;

    border-top:
        1px solid #1e293b;
}


.login-table tbody tr:hover {

    background: #172033;
}


/* =========================================================
   EVENT BADGES
========================================================= */

.event-badge {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

    white-space: nowrap;
}


.event-badge.success {

    background: #064e3b;

    color: #a7f3d0;
}


.event-badge.failed {

    background: #78350f;

    color: #fde68a;
}


.event-badge.locked {

    background: #7f1d1d;

    color: #fecaca;
}


.event-badge.inactive {

    background: #4c1d95;

    color: #ddd6fe;
}


.event-badge.normal {

    background: #334155;

    color: #cbd5e1;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding: 45px 20px;

    text-align: center;

    color: #64748b;
}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.quick-actions {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    padding: 22px;
}


.action {

    display: block;

    padding: 17px;

    border:
        1px solid #334155;

    border-radius: 10px;

    background: #0f172a;

    color: #e2e8f0;

    text-decoration: none;

    transition:
        background 0.2s ease,
        border-color 0.2s ease;
}


.action:hover {

    background: #172033;

    border-color: #475569;
}


.action-title {

    font-weight: 700;

    margin-bottom: 5px;
}


.action-description {

    color: #64748b;

    font-size: 12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px) {

    .cards {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .quick-actions {

        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media(max-width:700px) {

    .navbar {

        flex-direction: column;

        align-items: flex-start;
    }

    .container {

        padding: 18px;
    }

    .cards {

        grid-template-columns: 1fr;
    }

    .quick-actions {

        grid-template-columns: 1fr;
    }

    .page-header h1 {

        font-size: 24px;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     NAVBAR
====================================================== -->

<div class="navbar">


    <div class="brand">

        🛡 Security Incident Ticketing

    </div>


    <div class="nav-links">


        <a href="dashboard.php">
            Dashboard
        </a>


        <a href="users.php">
            Users
        </a>


        <a href="incidents.php">
            Incidents
        </a>


        <a href="../reports/incidents-report.php">
            Reports
        </a>


        <a
            class="logout"
            href="../auth/logout.php"
        >
            Logout
        </a>


    </div>

</div>


<!-- =====================================================
     MAIN CONTAINER
====================================================== -->

<div class="container">


    <!-- PAGE HEADER -->

    <div class="page-header">

        <h1>
            Admin Dashboard
        </h1>

        <p>

            Welcome,

            <strong>
                <?= e(
                    $_SESSION['name']
                ) ?>
            </strong>

            — Security Incident Management

        </p>

    </div>


    <!-- =================================================
         STATISTICS
    ================================================== -->

    <div class="cards">


        <!-- USERS -->

        <div class="stat-card stat-blue">

            <h3>
                TOTAL USERS
            </h3>

            <h2>
                <?= e(
                    (string)$totalUsers
                ) ?>
            </h2>

        </div>


        <!-- INCIDENTS -->

        <div class="stat-card stat-blue">

            <h3>
                TOTAL INCIDENTS
            </h3>

            <h2>
                <?= e(
                    (string)$totalIncidents
                ) ?>
            </h2>

        </div>


        <!-- OPEN -->

        <div class="stat-card stat-orange">

            <h3>
                OPEN INCIDENTS
            </h3>

            <h2>
                <?= e(
                    (string)$openIncidents
                ) ?>
            </h2>

        </div>


        <!-- CRITICAL -->

        <div class="stat-card stat-red">

            <h3>
                CRITICAL INCIDENTS
            </h3>

            <h2>
                <?= e(
                    (string)$criticalIncidents
                ) ?>
            </h2>

        </div>


    </div>


    <!-- =================================================
         QUICK ACTIONS
    ================================================== -->

    <div class="card">


        <div class="card-header">

            <div>

                <h2>
                    ⚡ Quick Actions
                </h2>

                <p>
                    Frequently used administration functions.
                </p>

            </div>

        </div>


        <div class="quick-actions">


            <a
                class="action"
                href="users.php"
            >

                <div class="action-title">
                    👥 Manage Users
                </div>

                <div class="action-description">
                    View and manage system users.
                </div>

            </a>


            <a
                class="action"
                href="incidents.php"
            >

                <div class="action-title">
                    🎫 Manage Incidents
                </div>

                <div class="action-description">
                    View and manage security incidents.
                </div>

            </a>


            <a
                class="action"
                href="../incidents/create.php"
            >

                <div class="action-title">
                    ➕ Create Incident
                </div>

                <div class="action-description">
                    Create a new security incident.
                </div>

            </a>


            <a
                class="action"
                href="../reports/incidents-report.php"
            >

                <div class="action-title">
                    📊 Incident Reports
                </div>

                <div class="action-description">
                    Generate and export reports.
                </div>

            </a>


        </div>

    </div>


    <!-- =================================================
         SECURITY LOGIN ACTIVITY
    ================================================== -->

    <div class="card">


        <div class="card-header">


            <div>

                <h2>
                    🔐 Security Login Activity
                </h2>

                <p>
                    Latest authentication and security events.
                </p>

            </div>


            <a
                href="login-activity.php"
                class="view-all"
            >
                View All →
            </a>


        </div>


        <?php if (!$loginAuditLogs): ?>


            <div class="empty">

                No login activity has been recorded yet.

            </div>


        <?php else: ?>


            <div class="login-table-wrapper">


                <table class="login-table">


                    <thead>

                        <tr>

                            <th>
                                Email
                            </th>

                            <th>
                                Event
                            </th>

                            <th>
                                IP Address
                            </th>

                            <th>
                                Date / Time
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $loginAuditLogs
                        as $log
                    ): ?>


                        <tr>


                            <td>

                                <?= e(
                                    $log['email']
                                ) ?>

                            </td>


                            <td>


                                <span
                                    class="
                                        event-badge
                                        <?= e(
                                            getEventClass(
                                                $log[
                                                    'event_type'
                                                ]
                                            )
                                        ) ?>
                                    "
                                >

                                    <?= e(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $log[
                                                'event_type'
                                            ]
                                        )
                                    ) ?>

                                </span>


                            </td>


                            <td>

                                <?= e(
                                    $log[
                                        'ip_address'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $log[
                                        'created_at'
                                    ]
                                ) ?>

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