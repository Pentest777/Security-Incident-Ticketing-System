<?php

/*
|--------------------------------------------------------------------------
| Session Security
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax'
    ]);

    session_start();
}


/*
|--------------------------------------------------------------------------
| Check Login Status
|--------------------------------------------------------------------------
*/

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}


/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/

function requireLogin(): void
{
    if (!isLoggedIn()) {

        header(
            'Location: ../auth/login.php'
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Session Timeout
    |--------------------------------------------------------------------------
    */

    $timeout = 1800; // 30 minutes

    if (
        isset($_SESSION['last_activity'])
        &&
        (time() - $_SESSION['last_activity']) > $timeout
    ) {

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header(
            'Location: ../auth/login.php?timeout=1'
        );

        exit;
    }

    $_SESSION['last_activity'] = time();
}


/*
|--------------------------------------------------------------------------
| Role Authorization
|--------------------------------------------------------------------------
*/

function requireRole(string $requiredRole): void
{
    requireLogin();

    if (
        empty($_SESSION['role'])
        ||
        $_SESSION['role'] !== $requiredRole
    ) {

        http_response_code(403);

        die('Access Denied');
    }
}


/*
|--------------------------------------------------------------------------
| HTML Escape
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

function csrfToken(): string
{
    if (
        empty($_SESSION['csrf_token'])
    ) {

        $_SESSION['csrf_token'] =
            bin2hex(
                random_bytes(32)
            );
    }

    return $_SESSION['csrf_token'];
}


/*
|--------------------------------------------------------------------------
| CSRF Hidden Field
|--------------------------------------------------------------------------
*/

function csrfField(): string
{
    return
        '<input type="hidden" ' .
        'name="csrf_token" value="' .
        e(csrfToken()) .
        '">';
}


/*
|--------------------------------------------------------------------------
| Verify CSRF Token
|--------------------------------------------------------------------------
*/

function verifyCsrfToken(?string $token): bool
{
    if (
        empty($_SESSION['csrf_token'])
        ||
        empty($token)
    ) {

        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}


/*
|--------------------------------------------------------------------------
| Automatic CSRF Protection
|--------------------------------------------------------------------------
*/

function verifyCsrf(): void
{
    if (
        ($_SERVER['REQUEST_METHOD'] ?? 'GET')
        !==
        'POST'
    ) {

        return;
    }

    $token =
        $_POST['csrf_token'] ?? '';

    if (
        !verifyCsrfToken($token)
    ) {

        http_response_code(403);

        die(
            'Invalid CSRF token.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Login Audit Log
|--------------------------------------------------------------------------
*/

function logLoginEvent(
    PDO $pdo,
    ?int $userId,
    string $email,
    string $eventType
): void {

    $ipAddress =
        $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';

    $userAgent =
        $_SERVER['HTTP_USER_AGENT']
        ?? null;

    $stmt = $pdo->prepare(
        "INSERT INTO login_audit_logs
        (
            user_id,
            email,
            ip_address,
            event_type,
            user_agent
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?
        )"
    );

    $stmt->execute([
        $userId,
        $email,
        $ipAddress,
        $eventType,
        $userAgent
    ]);
}


/*
|--------------------------------------------------------------------------
| Run Automatic CSRF Check
|--------------------------------------------------------------------------
*/

verifyCsrf();