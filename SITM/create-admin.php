<?php

require_once __DIR__ . '/config/database.php';

$name = 'System Administrator';
$email = 'admin@security.local';
$password = 'Admin@12345';

$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);

try {

    $check = $pdo->prepare(
        "SELECT id FROM users WHERE email = ?"
    );

    $check->execute([$email]);

    if ($check->fetch()) {

        echo "<h2>Admin already exists.</h2>";

        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO users
        (name, email, password, role, status)
        VALUES (?, ?, ?, 'admin', 'active')"
    );

    $stmt->execute([
        $name,
        $email,
        $hashedPassword
    ]);

    echo "<h2>Admin Created Successfully!</h2>";

    echo "<p>Email: <b>$email</b></p>";
    echo "<p>Password: <b>$password</b></p>";

} catch (PDOException $e) {

    die(
        "Error: " .
        htmlspecialchars($e->getMessage())
    );
}