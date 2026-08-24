<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireRole('admin');


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? null
        )
    ) {

        http_response_code(403);

        die('Invalid security token.');
    }


    /*
    |--------------------------------------------------------------------------
    | Action
    |--------------------------------------------------------------------------
    */

    $action =
        $_POST['action'] ?? '';

    $userId =
        (int)($_POST['user_id'] ?? 0);


    if ($userId <= 0) {

        die('Invalid user ID.');
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent Admin From Modifying Own Account
    |--------------------------------------------------------------------------
    */

    if (
        $userId
        ===
        (int)$_SESSION['user_id']
    ) {

        die(
            'You cannot modify your own account from this page.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Activate / Deactivate
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'activate'
        ||
        $action === 'deactivate'
    ) {

        $newStatus =
            $action === 'activate'
            ? 'active'
            : 'inactive';


        $stmt = $pdo->prepare(
            "UPDATE users

             SET status = ?

             WHERE id = ?

             LIMIT 1"
        );


        $stmt->execute([
            $newStatus,
            $userId
        ]);


        header(
            'Location: users.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Change Role
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'change_role'
    ) {

        $newRole =
            $_POST['role'] ?? '';


        $allowedRoles = [
            'admin',
            'analyst'
        ];


        if (
            !in_array(
                $newRole,
                $allowedRoles,
                true
            )
        ) {

            die('Invalid role.');
        }


        $stmt = $pdo->prepare(
            "UPDATE users

             SET role = ?

             WHERE id = ?

             LIMIT 1"
        );


        $stmt->execute([
            $newRole,
            $userId
        ]);


        header(
            'Location: users.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Reset Password
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'reset_password'
    ) {

        $password =
            $_POST['password'] ?? '';


        if (
            strlen($password) < 8
        ) {

            die(
                'Password must contain at least 8 characters.'
            );
        }


        $passwordHash =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );


        $stmt = $pdo->prepare(
            "UPDATE users

             SET password = ?

             WHERE id = ?

             LIMIT 1"
        );


        $stmt->execute([
            $passwordHash,
            $userId
        ]);


        header(
            'Location: users.php'
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search =
    trim(
        $_GET['search'] ?? ''
    );


$role =
    trim(
        $_GET['role'] ?? ''
    );


$status =
    trim(
        $_GET['status'] ?? ''
    );


/*
|--------------------------------------------------------------------------
| Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        name,
        email,
        role,
        status,
        created_at

    FROM users

    WHERE 1 = 1
";


$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if (
    $search !== ''
) {

    $sql .= "
        AND (
            name LIKE ?
            OR email LIKE ?
        )
    ";


    $params[] =
        '%' . $search . '%';


    $params[] =
        '%' . $search . '%';
}


/*
|--------------------------------------------------------------------------
| Role
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $role,
        [
            'admin',
            'analyst'
        ],
        true
    )
) {

    $sql .= "
        AND role = ?
    ";


    $params[] =
        $role;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $status,
        [
            'active',
            'inactive'
        ],
        true
    )
) {

    $sql .= "
        AND status = ?
    ";


    $params[] =
        $status;
}


/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY created_at DESC
";


$stmt =
    $pdo->prepare($sql);


$stmt->execute($params);


$users =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalUsers =
    count($users);


$activeUsers = 0;

$inactiveUsers = 0;

$adminUsers = 0;

$analystUsers = 0;


foreach (
    $users
    as $user
) {

    if (
        $user['status']
        ===
        'active'
    ) {

        $activeUsers++;
    }


    if (
        $user['status']
        ===
        'inactive'
    ) {

        $inactiveUsers++;
    }


    if (
        $user['role']
        ===
        'admin'
    ) {

        $adminUsers++;
    }


    if (
        $user['role']
        ===
        'analyst'
    ) {

        $analystUsers++;
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
    User Management
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


/* =========================================================
   HEADER
========================================================= */

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
   STATS
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
   FILTERS
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

    padding: 10px 14px;

    border: none;

    border-radius: 7px;

    cursor: pointer;

    text-decoration: none;

    font-weight: 700;

    text-align: center;
}


.filter-btn {

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

    min-width: 1100px;

    border-collapse: collapse;
}


th {

    text-align: left;

    background: #0f172a;

    color: #94a3b8;

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


.name {

    font-weight: 700;
}


.email {

    color: #94a3b8;

    font-size: 12px;
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
}


.badge-admin {

    background: #312e81;

    color: #c7d2fe;
}


.badge-analyst {

    background: #164e63;

    color: #a5f3fc;
}


.badge-active {

    background: #14532d;

    color: #bbf7d0;
}


.badge-inactive {

    background: #374151;

    color: #d1d5db;
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    display: flex;

    gap: 7px;

    align-items: center;

    flex-wrap: wrap;
}


.action-btn {

    padding: 7px 10px;

    border: none;

    border-radius: 6px;

    color: white;

    cursor: pointer;

    font-size: 10px;

    font-weight: 700;
}


.activate {

    background: #059669;
}


.deactivate {

    background: #dc2626;
}


.role {

    background: #2563eb;
}


.reset-password {

    background: #7c3aed;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding: 50px;

    text-align: center;

    color: #64748b;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .filters {

        grid-template-columns:
            1fr 1fr;
    }

}


@media(max-width:600px) {

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
     MAIN
===================================================== -->

<div class="container">


    <div class="header">

        <h1>
            User Management
        </h1>

        <p>
            Manage administrators and security analysts.
        </p>

    </div>


    <!-- =================================================
         STATISTICS
    ================================================== -->

    <div class="stats">


        <div class="stat">

            <div class="stat-label">
                Total Users
            </div>

            <div class="stat-value">
                <?= e(
                    (string)$totalUsers
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Active Users
            </div>

            <div class="stat-value">
                <?= e(
                    (string)$activeUsers
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Administrators
            </div>

            <div class="stat-value">
                <?= e(
                    (string)$adminUsers
                ) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Analysts
            </div>

            <div class="stat-value">
                <?= e(
                    (string)$analystUsers
                ) ?>
            </div>

        </div>


    </div>


    <!-- =================================================
         FILTER
    ================================================== -->

    <div class="card">


        <div class="card-header">

            <h2>
                🔎 User Filters
            </h2>

        </div>


        <form
            method="GET"
            action="users.php"
            class="filters"
        >


            <input
                type="text"
                name="search"
                placeholder="Search name or email..."
                value="<?= e(
                    $search
                ) ?>"
            >


            <select name="role">

                <option value="">
                    All Roles
                </option>

                <option
                    value="admin"
                    <?= $role === 'admin'
                        ? 'selected'
                        : '' ?>
                >
                    Admin
                </option>

                <option
                    value="analyst"
                    <?= $role === 'analyst'
                        ? 'selected'
                        : '' ?>
                >
                    Analyst
                </option>

            </select>


            <select name="status">

                <option value="">
                    All Status
                </option>

                <option
                    value="active"
                    <?= $status === 'active'
                        ? 'selected'
                        : '' ?>
                >
                    Active
                </option>

                <option
                    value="inactive"
                    <?= $status === 'inactive'
                        ? 'selected'
                        : '' ?>
                >
                    Inactive
                </option>

            </select>


            <button
                type="submit"
                class="filter-btn"
            >
                Filter
            </button>


            <a
                href="users.php"
                class="reset"
            >
                Reset
            </a>


        </form>


    </div>


    <br>


    <!-- =================================================
         USERS
    ================================================== -->

    <div class="card">


        <div class="card-header">

            <h2>
                👥 System Users
            </h2>

        </div>


        <?php if (!$users): ?>


            <div class="empty">

                No users found.

            </div>


        <?php else: ?>


            <div class="table-wrapper">


                <table>


                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                User
                            </th>

                            <th>
                                Role
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Created
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $users
                        as $user
                    ): ?>


                        <tr>


                            <td>

                                <?= e(
                                    (string)$user['id']
                                ) ?>

                            </td>


                            <td>

                                <div class="name">

                                    <?= e(
                                        $user['name']
                                    ) ?>

                                </div>

                                <div class="email">

                                    <?= e(
                                        $user['email']
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <span
                                    class="badge badge-<?=
                                        e(
                                            $user['role']
                                        )
                                    ?>"
                                >

                                    <?= e(
                                        ucfirst(
                                            $user['role']
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="badge badge-<?=
                                        e(
                                            $user['status']
                                        )
                                    ?>"
                                >

                                    <?= e(
                                        ucfirst(
                                            $user['status']
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= e(
                                    $user['created_at']
                                ) ?>

                            </td>


                            <td>


                                <?php if (
                                    (int)$user['id']
                                    !==
                                    (int)$_SESSION['user_id']
                                ): ?>


                                    <div class="actions">


                                        <!-- STATUS -->

                                        <form
                                            method="POST"
                                            action="users.php"
                                        >

                                            <?= csrfField() ?>

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= e(
                                                    (string)$user['id']
                                                ) ?>"
                                            >

                                            <?php if (
                                                $user['status']
                                                ===
                                                'active'
                                            ): ?>

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="deactivate"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn deactivate"
                                                >
                                                    Deactivate
                                                </button>

                                            <?php else: ?>

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="activate"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn activate"
                                                >
                                                    Activate
                                                </button>

                                            <?php endif; ?>

                                        </form>


                                        <!-- ROLE -->

                                        <form
                                            method="POST"
                                            action="users.php"
                                        >

                                            <?= csrfField() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="change_role"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= e(
                                                    (string)$user['id']
                                                ) ?>"
                                            >


                                            <?php if (
                                                $user['role']
                                                ===
                                                'analyst'
                                            ): ?>

                                                <input
                                                    type="hidden"
                                                    name="role"
                                                    value="admin"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn role"
                                                >
                                                    Make Admin
                                                </button>

                                            <?php else: ?>

                                                <input
                                                    type="hidden"
                                                    name="role"
                                                    value="analyst"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-btn role"
                                                >
                                                    Make Analyst
                                                </button>

                                            <?php endif; ?>


                                        </form>


                                        <!-- PASSWORD -->

                                        <form
                                            method="POST"
                                            action="users.php"
                                        >

                                            <?= csrfField() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="reset_password"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= e(
                                                    (string)$user['id']
                                                ) ?>"
                                            >

                                            <input
                                                type="password"
                                                name="password"
                                                minlength="8"
                                                placeholder="New password"
                                                required
                                                style="
                                                    width:130px;
                                                    padding:7px;
                                                    background:#0f172a;
                                                    color:white;
                                                    border:1px solid #334155;
                                                    border-radius:6px;
                                                "
                                            >

                                            <button
                                                type="submit"
                                                class="action-btn reset-password"
                                            >
                                                Reset Password
                                            </button>

                                        </form>


                                    </div>


                                <?php else: ?>


                                    <span
                                        style="
                                            color:#64748b;
                                            font-size:11px;
                                        "
                                    >
                                        Current Account
                                    </span>


                                <?php endif; ?>


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