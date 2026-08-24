<?php if (isset($_GET['timeout'])): ?>

    <div class="alert alert-warning">
        Your session expired due to inactivity.
        Please login again.
    </div>

<?php endif; ?>

<?php

require_once __DIR__ . '/../config/security.php';

if (isLoggedIn()) {

    if ($_SESSION['role'] === 'admin') {
        header('Location: ../admin/dashboard.php');
    } elseif ($_SESSION['role'] === 'analyst') {
        header('Location: ../analyst/dashboard.php');
    } else {
        header('Location: ../index.php');
    }

    exit;
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Security Incident Ticketing</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, sans-serif;
            background: #0b1220;
            color: white;
        }

        .login-box {
            width: 400px;
            padding: 35px;
            background: #111827;
            border: 1px solid #263244;
            border-radius: 15px;
            box-shadow: 0 20px 50px rgba(0,0,0,.5);
        }

        .login-box h1 {
            text-align: center;
            margin-bottom: 10px;
        }

        .login-box p {
            text-align: center;
            color: #94a3b8;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 7px;
        }

        input {
            width: 100%;
            padding: 13px;
            margin-bottom: 18px;
            border-radius: 8px;
            border: 1px solid #334155;
            background: #0f172a;
            color: white;
            outline: none;
        }

        input:focus {
            border-color: #38bdf8;
        }

        button {
            width: 100%;
            padding: 13px;
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
            background: #451a1a;
            border: 1px solid #7f1d1d;
            color: #fca5a5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

    </style>
</head>

<body>

<div class="login-box">

    <h1>Security Portal</h1>

    <p>Security Incident Ticketing System</p>

    <?php if ($error): ?>

        <div class="error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="authenticate.php">
         <?= csrfField() ?>
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrfToken()) ?>"
        >

        <label>Email</label>

        <input
            type="email"
            name="email"
            placeholder="Enter email"
            required
        >

        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Enter password"
            required
        >

        <button type="submit">
            Login
        </button>

    </form>

</div>

</body>
</html>