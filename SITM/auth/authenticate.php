<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';


/*
|--------------------------------------------------------------------------
| Only POST Requests
|--------------------------------------------------------------------------
*/

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET')
    !== 'POST'
) {

    header('Location: login.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

if (
    !verifyCsrfToken(
        $_POST['csrf_token'] ?? null
    )
) {

    $_SESSION['login_error'] =
        'Invalid security token.';

    header('Location: login.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| Input
|--------------------------------------------------------------------------
*/

$email = trim(
    strtolower(
        $_POST['email'] ?? ''
    )
);

$password =
    $_POST['password'] ?? '';


if (
    $email === ''
    ||
    $password === ''
) {

    $_SESSION['login_error'] =
        'Email and password are required.';

    header('Location: login.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| Email Validation
|--------------------------------------------------------------------------
*/

if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    $_SESSION['login_error'] =
        'Please enter a valid email address.';

    header('Location: login.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| Client IP
|--------------------------------------------------------------------------
*/

$ipAddress =
    $_SERVER['REMOTE_ADDR']
    ?? '0.0.0.0';


/*
|--------------------------------------------------------------------------
| Brute Force Configuration
|--------------------------------------------------------------------------
*/

$maxAttempts = 5;

$lockMinutes = 15;


/*
|--------------------------------------------------------------------------
| Get Login Attempt Record
|--------------------------------------------------------------------------
*/

$attemptStmt = $pdo->prepare(
    "SELECT
        id,
        attempts,
        last_attempt,
        locked_until

     FROM login_attempts

     WHERE email = ?
       AND ip_address = ?

     LIMIT 1"
);

$attemptStmt->execute([
    $email,
    $ipAddress
]);

$attemptRecord =
    $attemptStmt->fetch();


/*
|--------------------------------------------------------------------------
| Check Temporary Lock
|--------------------------------------------------------------------------
*/

if (
    $attemptRecord
    &&
    !empty(
        $attemptRecord['locked_until']
    )
) {

    $lockedUntil =
        strtotime(
            $attemptRecord['locked_until']
        );

    if (
        $lockedUntil !== false
        &&
        $lockedUntil > time()
    ) {

        logLoginEvent(
            $pdo,
            null,
            $email,
            'LOGIN_LOCKED'
        );

        $_SESSION['login_error'] =
            'Too many failed login attempts. ' .
            'Please try again later.';

        header('Location: login.php');

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Find User
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        id,
        name,
        email,
        password,
        role,
        status

     FROM users

     WHERE email = ?

     LIMIT 1"
);

$stmt->execute([
    $email
]);

$user = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| Verify Password
|--------------------------------------------------------------------------
*/

$passwordValid = false;

if ($user) {

    $passwordValid =
        password_verify(
            $password,
            $user['password']
        );
}


/*
|--------------------------------------------------------------------------
| Failed Authentication
|--------------------------------------------------------------------------
*/

if (
    !$user
    ||
    !$passwordValid
) {

    $currentAttempts =
        $attemptRecord
        ? (int)$attemptRecord['attempts']
        : 0;

    $newAttempts =
        $currentAttempts + 1;


    /*
    |--------------------------------------------------------------------------
    | Lock Calculation
    |--------------------------------------------------------------------------
    */

    $lockedUntil = null;

    if (
        $newAttempts >= $maxAttempts
    ) {

        $lockedUntil =
            date(
                'Y-m-d H:i:s',
                time()
                +
                ($lockMinutes * 60)
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Save Login Attempt
    |--------------------------------------------------------------------------
    */

    if ($attemptRecord) {

        $updateAttempt = $pdo->prepare(
            "UPDATE login_attempts

             SET
                attempts = ?,
                last_attempt = NOW(),
                locked_until = ?

             WHERE id = ?"
        );

        $updateAttempt->execute([
            $newAttempts,
            $lockedUntil,
            $attemptRecord['id']
        ]);

    } else {

        $insertAttempt = $pdo->prepare(
            "INSERT INTO login_attempts
            (
                email,
                ip_address,
                attempts,
                last_attempt,
                locked_until
            )

            VALUES
            (
                ?,
                ?,
                ?,
                NOW(),
                ?
            )"
        );

        $insertAttempt->execute([
            $email,
            $ipAddress,
            $newAttempts,
            $lockedUntil
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Login Audit
    |--------------------------------------------------------------------------
    */

    logLoginEvent(
        $pdo,
        $user
            ? (int)$user['id']
            : null,
        $email,
        $newAttempts >= $maxAttempts
            ? 'LOGIN_LOCKED'
            : 'LOGIN_FAILED'
    );


    /*
    |--------------------------------------------------------------------------
    | Error Message
    |--------------------------------------------------------------------------
    */

    if (
        $newAttempts >= $maxAttempts
    ) {

        $_SESSION['login_error'] =
            'Too many failed login attempts. ' .
            'Login has been temporarily locked ' .
            'for 15 minutes.';

    } else {

        $_SESSION['login_error'] =
            'Invalid email or password.';
    }


    header('Location: login.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| Account Status
|--------------------------------------------------------------------------
*/

if (
    $user['status'] !== 'active'
) {

    logLoginEvent(
        $pdo,
        (int)$user['id'],
        $email,
        'ACCOUNT_INACTIVE'
    );

    $_SESSION['login_error'] =
        'Your account is inactive. ' .
        'Contact administrator.';

    header('Location: login.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| Clear Failed Attempts
|--------------------------------------------------------------------------
*/

$clearAttempts = $pdo->prepare(
    "DELETE FROM login_attempts

     WHERE email = ?
       AND ip_address = ?"
);

$clearAttempts->execute([
    $email,
    $ipAddress
]);


/*
|--------------------------------------------------------------------------
| Regenerate Session
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);


/*
|--------------------------------------------------------------------------
| Create Session
|--------------------------------------------------------------------------
*/

$_SESSION['user_id'] =
    $user['id'];

$_SESSION['name'] =
    $user['name'];

$_SESSION['email'] =
    $user['email'];

$_SESSION['role'] =
    $user['role'];

$_SESSION['login_time'] =
    time();

$_SESSION['last_activity'] =
    time();


/*
|--------------------------------------------------------------------------
| Successful Login Audit
|--------------------------------------------------------------------------
*/

logLoginEvent(
    $pdo,
    (int)$user['id'],
    $email,
    'LOGIN_SUCCESS'
);


/*
|--------------------------------------------------------------------------
| Role Based Redirect
|--------------------------------------------------------------------------
*/

if (
    $user['role'] === 'admin'
) {

    header(
        'Location: ../admin/dashboard.php'
    );

    exit;
}


if (
    $user['role'] === 'analyst'
) {

    header(
        'Location: ../analyst/dashboard.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Unknown Role
|--------------------------------------------------------------------------
*/

$_SESSION = [];

session_destroy();

header(
    'Location: login.php'
);

exit;