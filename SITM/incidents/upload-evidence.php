<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireLogin();


/*
|--------------------------------------------------------------------------
| Only POST Requests
|--------------------------------------------------------------------------
*/

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET')
    !== 'POST'
) {
    header('Location: ../index.php');
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
    http_response_code(403);

    die('Invalid CSRF token.');
}


/*
|--------------------------------------------------------------------------
| Incident ID
|--------------------------------------------------------------------------
*/

$incidentId = (int)(
    $_POST['incident_id'] ?? 0
);

if ($incidentId <= 0) {
    die('Invalid incident ID.');
}


/*
|--------------------------------------------------------------------------
| Get Incident
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        id,
        ticket_number,
        assigned_to

     FROM incidents

     WHERE id = ?

     LIMIT 1"
);

$stmt->execute([
    $incidentId
]);

$incident = $stmt->fetch();


if (!$incident) {

    http_response_code(404);

    die('Incident not found.');
}


/*
|--------------------------------------------------------------------------
| Access Control
|--------------------------------------------------------------------------
|
| Admin:
|   Can upload evidence to any incident.
|
| Analyst:
|   Can upload only to assigned incidents.
|
*/

if (
    $_SESSION['role'] === 'analyst'
) {

    if (
        (int)$incident['assigned_to']
        !==
        (int)$_SESSION['user_id']
    ) {

        http_response_code(403);

        die(
            'Access Denied: This incident is not assigned to you.'
        );
    }

} elseif (
    $_SESSION['role'] !== 'admin'
) {

    http_response_code(403);

    die('Access Denied.');
}


/*
|--------------------------------------------------------------------------
| File Check
|--------------------------------------------------------------------------
*/

if (
    !isset($_FILES['evidence'])
) {

    header(
        'Location: view.php?id=' .
        $incidentId .
        '&evidence_error=no_file'
    );

    exit;
}


$file = $_FILES['evidence'];


/*
|--------------------------------------------------------------------------
| Upload Error Check
|--------------------------------------------------------------------------
*/

if (
    $file['error'] !== UPLOAD_ERR_OK
) {

    header(
        'Location: view.php?id=' .
        $incidentId .
        '&evidence_error=upload'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Maximum File Size
|--------------------------------------------------------------------------
|
| 10 MB
|
*/

$maxFileSize =
    10 * 1024 * 1024;


if (
    $file['size'] <= 0
    ||
    $file['size'] > $maxFileSize
) {

    header(
        'Location: view.php?id=' .
        $incidentId .
        '&evidence_error=size'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Original Filename
|--------------------------------------------------------------------------
*/

$originalName =
    basename(
        $file['name']
    );


/*
|--------------------------------------------------------------------------
| Extension
|--------------------------------------------------------------------------
*/

$extension =
    strtolower(
        pathinfo(
            $originalName,
            PATHINFO_EXTENSION
        )
    );


/*
|--------------------------------------------------------------------------
| Allowed Extensions
|--------------------------------------------------------------------------
|
| Keep this list intentionally limited.
|
*/

$allowedExtensions = [

    'jpg',
    'jpeg',
    'png',
    'gif',

    'pdf',

    'txt',
    'log',
    'csv',

    'doc',
    'docx',

    'xls',
    'xlsx'

];


if (
    !in_array(
        $extension,
        $allowedExtensions,
        true
    )
) {

    header(
        'Location: view.php?id=' .
        $incidentId .
        '&evidence_error=type'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| MIME Type Verification
|--------------------------------------------------------------------------
*/

$finfo =
    new finfo(
        FILEINFO_MIME_TYPE
    );

$mimeType =
    $finfo->file(
        $file['tmp_name']
    );


/*
|--------------------------------------------------------------------------
| Allowed MIME Types
|--------------------------------------------------------------------------
*/

$allowedMimeTypes = [

    'image/jpeg',
    'image/png',
    'image/gif',

    'application/pdf',

    'text/plain',
    'text/csv',

    'application/msword',

    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

    'application/vnd.ms-excel',

    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'

];


if (
    !in_array(
        $mimeType,
        $allowedMimeTypes,
        true
    )
) {

    header(
        'Location: view.php?id=' .
        $incidentId .
        '&evidence_error=mime'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| SHA-256 Hash
|--------------------------------------------------------------------------
*/

$fileHash =
    hash_file(
        'sha256',
        $file['tmp_name']
    );


if (!$fileHash) {

    header(
        'Location: view.php?id=' .
        $incidentId .
        '&evidence_error=hash'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Evidence Storage Directory
|--------------------------------------------------------------------------
*/

$uploadDirectory =
    __DIR__ .
    '/../uploads/evidence/';


if (
    !is_dir(
        $uploadDirectory
    )
) {

    if (
        !mkdir(
            $uploadDirectory,
            0755,
            true
        )
    ) {

        die(
            'Unable to create evidence storage directory.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Random Stored Filename
|--------------------------------------------------------------------------
*/

$storedName =
    bin2hex(
        random_bytes(16)
    )
    .
    '.'
    .
    $extension;


$destination =
    $uploadDirectory .
    $storedName;


/*
|--------------------------------------------------------------------------
| Move Uploaded File
|--------------------------------------------------------------------------
*/

if (
    !move_uploaded_file(
        $file['tmp_name'],
        $destination
    )
) {

    header(
        'Location: view.php?id=' .
        $incidentId .
        '&evidence_error=save'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Database Path
|--------------------------------------------------------------------------
*/

$filePath =
    'uploads/evidence/' .
    $storedName;


/*
|--------------------------------------------------------------------------
| Save Evidence Record
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare(
        "INSERT INTO incident_evidence
        (
            incident_id,
            uploaded_by,
            original_name,
            stored_name,
            file_path,
            mime_type,
            file_size,
            file_hash
        )

        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )"
    );

    $stmt->execute([

        $incidentId,

        (int)$_SESSION['user_id'],

        $originalName,

        $storedName,

        $filePath,

        $mimeType,

        (int)$file['size'],

        $fileHash

    ]);

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Remove Physical File if DB Insert Fails
    |--------------------------------------------------------------------------
    */

    if (
        is_file(
            $destination
        )
    ) {

        unlink(
            $destination
        );
    }

    header(
        'Location: view.php?id=' .
        $incidentId .
        '&evidence_error=database'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Activity History
|--------------------------------------------------------------------------
*/

try {

    $activityStmt = $pdo->prepare(
        "INSERT INTO incident_activity
        (
            incident_id,
            user_id,
            action,
            comment,
            created_at
        )

        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            NOW()
        )"
    );

    $activityStmt->execute([

        $incidentId,

        (int)$_SESSION['user_id'],

        'Evidence Uploaded',

        $originalName

    ]);

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Do not delete evidence.
    |--------------------------------------------------------------------------
    |
    | Evidence is already stored successfully.
    | Activity logging failure should not destroy evidence.
    |
    */
}


/*
|--------------------------------------------------------------------------
| Update Incident Timestamp
|--------------------------------------------------------------------------
*/

try {

    $updateStmt = $pdo->prepare(
        "UPDATE incidents

         SET updated_at = NOW()

         WHERE id = ?"
    );

    $updateStmt->execute([
        $incidentId
    ]);

} catch (Throwable $e) {

    // Evidence is already saved.
}


/*
|--------------------------------------------------------------------------
| Success Redirect
|--------------------------------------------------------------------------
*/

header(
    'Location: view.php?id=' .
    $incidentId .
    '&evidence_added=1'
);

exit;