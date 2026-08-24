<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireLogin();


/*
|--------------------------------------------------------------------------
| Evidence ID
|--------------------------------------------------------------------------
*/

$evidenceId = (int)($_GET['id'] ?? 0);

if ($evidenceId <= 0) {

    http_response_code(400);

    die('Invalid evidence ID.');
}


/*
|--------------------------------------------------------------------------
| Get Evidence + Incident
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        ie.id,
        ie.incident_id,
        ie.original_name,
        ie.stored_name,
        ie.file_path,
        ie.mime_type,
        ie.file_size,
        ie.file_hash,

        i.ticket_number,
        i.assigned_to

     FROM incident_evidence ie

     INNER JOIN incidents i
        ON ie.incident_id = i.id

     WHERE ie.id = ?

     LIMIT 1"
);

$stmt->execute([
    $evidenceId
]);

$evidence = $stmt->fetch();


if (!$evidence) {

    http_response_code(404);

    die('Evidence not found.');
}


/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
|
| Admin:
|   Can download any evidence.
|
| Analyst:
|   Can download evidence only from
|   incidents assigned to them.
|
*/

if ($_SESSION['role'] === 'analyst') {

    if (
        (int)$evidence['assigned_to']
        !==
        (int)$_SESSION['user_id']
    ) {

        http_response_code(403);

        die(
            'Access Denied: This incident is not assigned to you.'
        );
    }

} elseif ($_SESSION['role'] !== 'admin') {

    http_response_code(403);

    die('Access Denied.');
}


/*
|--------------------------------------------------------------------------
| Build Physical File Path
|--------------------------------------------------------------------------
*/

$filePath =
    __DIR__ .
    '/../uploads/evidence/' .
    basename(
        $evidence['stored_name']
    );


/*
|--------------------------------------------------------------------------
| Prevent Path Traversal
|--------------------------------------------------------------------------
*/

$realFilePath =
    realpath($filePath);

$evidenceDirectory =
    realpath(
        __DIR__ .
        '/../uploads/evidence/'
    );


if (
    $realFilePath === false
    ||
    $evidenceDirectory === false
) {

    http_response_code(404);

    die('Evidence file not found.');
}


$directoryPrefix =
    rtrim(
        $evidenceDirectory,
        DIRECTORY_SEPARATOR
    )
    .
    DIRECTORY_SEPARATOR;


if (
    strpos(
        $realFilePath,
        $directoryPrefix
    ) !== 0
) {

    http_response_code(403);

    die('Invalid evidence path.');
}


/*
|--------------------------------------------------------------------------
| File Check
|--------------------------------------------------------------------------
*/

if (
    !is_file($realFilePath)
    ||
    !is_readable($realFilePath)
) {

    http_response_code(404);

    die('Evidence file is unavailable.');
}


/*
|--------------------------------------------------------------------------
| Integrity Verification
|--------------------------------------------------------------------------
|
| Compare current SHA-256 with the hash stored
| during upload.
|
*/

$currentHash =
    hash_file(
        'sha256',
        $realFilePath
    );


if (
    !$currentHash
    ||
    !hash_equals(
        $evidence['file_hash'],
        $currentHash
    )
) {

    http_response_code(409);

    die(
        'Evidence integrity verification failed.'
    );
}


/*
|--------------------------------------------------------------------------
| Download Filename
|--------------------------------------------------------------------------
*/

$downloadName =
    basename(
        $evidence['original_name']
    );


/*
|--------------------------------------------------------------------------
| Clean Output Buffer
|--------------------------------------------------------------------------
*/

while (
    ob_get_level() > 0
) {

    ob_end_clean();
}


/*
|--------------------------------------------------------------------------
| HTTP Headers
|--------------------------------------------------------------------------
*/

header(
    'Content-Description: File Transfer'
);

header(
    'Content-Type: ' .
    $evidence['mime_type']
);

header(
    'Content-Disposition: attachment; filename="' .
    str_replace(
        '"',
        '',
        $downloadName
    )
    .
    '"'
);

header(
    'Content-Length: ' .
    filesize($realFilePath)
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate'
);

header(
    'Pragma: no-cache'
);

header(
    'X-Content-Type-Options: nosniff'
);


/*
|--------------------------------------------------------------------------
| Download File
|--------------------------------------------------------------------------
*/

readfile(
    $realFilePath
);

exit;