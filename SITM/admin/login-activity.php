<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireRole('admin');


/*
|--------------------------------------------------------------------------
| Fetch Login Audit Logs
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        l.id,
        l.user_id,
        l.email,
        l.ip_address,
        l.event_type,
        l.user_agent,
        l.created_at,

        u.name AS user_name,
        u.role AS user_role

     FROM login_audit_logs l

     LEFT JOIN users u
        ON l.user_id = u.id

     ORDER BY l.created_at DESC

     LIMIT 200"
);

$logs = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Event Badge Class
|--------------------------------------------------------------------------
*/

function eventClass(string $event): string
{
    switch ($event) {

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
    Login Activity |
    Security Incident Ticketing
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

    gap: 20px;
}


.brand {

    font-size: 18px;

    font-weight: 700;
}


.nav-links {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

    align-items: center;
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

    background: #dc2626 !important;

    color: white !important;
}


.logout:hover {

    background: #b91c1c !important;
}


/* CONTAINER */

.container {

    max-width: 1450px;

    margin: auto;

    padding: 30px;
}


/* HEADER */

.header {

    margin-bottom: 25px;
}


.header-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 8px;
}


.header h1 {

    margin: 0;

    font-size: 30px;
}


.header p {

    margin: 0;

    color: #94a3b8;
}


.back {

    display: inline-block;

    padding: 10px 15px;

    border-radius: 8px;

    background: #334155;

    color: white;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;
}


.back:hover {

    background: #475569;
}


/* CARD */

.card {

    background: #111827;

    border:
        1px solid #263244;

    border-radius: 14px;

    overflow: hidden;
}


/* TABLE */

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}


table {

    width: 100%;

    min-width: 1150px;

    border-collapse: collapse;
}


th {

    padding: 15px;

    text-align: left;

    background: #0f172a;

    color: #94a3b8;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .5px;
}


td {

    padding: 15px;

    border-top:
        1px solid #1e293b;

    color: #e2e8f0;

    font-size: 13px;

    vertical-align: top;
}


tbody tr:hover {

    background: #172033;
}


/* EVENT BADGES */

.badge {

    display: inline-block;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

    white-space: nowrap;
}


.success {

    background: #064e3b;

    color: #a7f3d0;
}


.failed {

    background: #78350f;

    color: #fde68a;
}


.locked {

    background: #7f1d1d;

    color: #fecaca;
}


.inactive {

    background: #4c1d95;

    color: #ddd6fe;
}


.normal {

    background: #334155;

    color: #cbd5e1;
}


/* USER AGENT */

.user-agent {

    max-width: 300px;

    color: #94a3b8;

    font-size: 11px;

    line-height: 1.5;

    word-break: break-word;
}


/* EMPTY */

.empty {

    padding: 60px;

    text-align: center;

    color: #64748b;
}


/* RESPONSIVE */

@media(max-width:800px) {

    .navbar {

        flex-direction: column;

        align-items: flex-start;
    }


    .container {

        padding: 18px;
    }


    .header-top {

        flex-direction: column;

        align-items: flex-start;
    }


    .header h1 {

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
            href="../auth/logout.php"
            class="logout"
        >
            Logout
        </a>


    </div>


</div>


<!-- =====================================================
     CONTENT
====================================================== -->

<div class="container">


    <div class="header">


        <div class="header-top">


            <h1>
                🔐 Login Security Activity
            </h1>


            <a
                href="dashboard.php"
                class="back"
            >
                ← Back to Dashboard
            </a>


        </div>


        <p>
            Authentication and login security events.
        </p>


    </div>


    <!-- =================================================
         LOG TABLE
    ================================================== -->

    <div class="card">


        <?php if (!$logs): ?>


            <div class="empty">

                <h3>
                    No Login Activity
                </h3>

                <p>
                    No authentication events have been recorded yet.
                </p>

            </div>


        <?php else: ?>


            <div class="table-wrapper">


                <table>


                    <thead>

                        <tr>

                            <th>
                                User
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Role
                            </th>

                            <th>
                                Event
                            </th>

                            <th>
                                IP Address
                            </th>

                            <th>
                                User Agent
                            </th>

                            <th>
                                Date / Time
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $logs
                        as $log
                    ): ?>


                        <tr>


                            <!-- USER -->

                            <td>

                                <?= e(
                                    $log['user_name']
                                    ??
                                    'Unknown User'
                                ) ?>

                            </td>


                            <!-- EMAIL -->

                            <td>

                                <?= e(
                                    $log['email']
                                ) ?>

                            </td>


                            <!-- ROLE -->

                            <td>

                                <?= e(
                                    $log['user_role']
                                    ??
                                    'Unknown'
                                ) ?>

                            </td>


                            <!-- EVENT -->

                            <td>

                                <span
                                    class="
                                        badge
                                        <?= e(
                                            eventClass(
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


                            <!-- IP -->

                            <td>

                                <?= e(
                                    $log[
                                        'ip_address'
                                    ]
                                ) ?>

                            </td>


                            <!-- USER AGENT -->

                            <td>

                                <div
                                    class="user-agent"
                                >

                                    <?= e(
                                        $log[
                                            'user_agent'
                                        ]
                                        ??
                                        'N/A'
                                    ) ?>

                                </div>

                            </td>


                            <!-- DATE -->

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