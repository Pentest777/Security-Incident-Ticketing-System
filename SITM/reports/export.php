<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

requireRole('admin');


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search   = trim($_GET['search'] ?? '');
$status   = trim($_GET['status'] ?? '');
$severity = trim($_GET['severity'] ?? '');
$category = (int)($_GET['category'] ?? 0);

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');


/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        i.id,
        i.ticket_number,
        i.title,
        c.name AS category_name,
        i.severity,
        i.status,
        reporter.name AS reporter_name,
        analyst.name AS analyst_name,
        i.created_at,
        i.updated_at

    FROM incidents i

    LEFT JOIN incident_categories c
        ON i.category_id = c.id

    LEFT JOIN users reporter
        ON i.reported_by = reporter.id

    LEFT JOIN users analyst
        ON i.assigned_to = analyst.id

    WHERE 1 = 1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            i.ticket_number LIKE ?
            OR i.title LIKE ?
        )
    ";

    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'open',
    'in_progress',
    'resolved',
    'closed'
];

if (
    in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    $sql .= "
        AND i.status = ?
    ";

    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| Severity
|--------------------------------------------------------------------------
*/

$allowedSeverities = [
    'low',
    'medium',
    'high',
    'critical'
];

if (
    in_array(
        $severity,
        $allowedSeverities,
        true
    )
) {

    $sql .= "
        AND i.severity = ?
    ";

    $params[] = $severity;
}


/*
|--------------------------------------------------------------------------
| Category
|--------------------------------------------------------------------------
*/

if ($category > 0) {

    $sql .= "
        AND i.category_id = ?
    ";

    $params[] = $category;
}


/*
|--------------------------------------------------------------------------
| Date From
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $sql .= "
        AND DATE(i.created_at) >= ?
    ";

    $params[] = $dateFrom;
}


/*
|--------------------------------------------------------------------------
| Date To
|--------------------------------------------------------------------------
*/

if ($dateTo !== '') {

    $sql .= "
        AND DATE(i.created_at) <= ?
    ";

    $params[] = $dateTo;
}


/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY i.created_at DESC
";


/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$incidents = $stmt->fetchAll();


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
| CSV Filename
|--------------------------------------------------------------------------
*/

$filename =
    'security_incident_report_' .
    date('Y-m-d_H-i-s') .
    '.csv';


/*
|--------------------------------------------------------------------------
| CSV Headers
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: text/csv; charset=UTF-8'
);

header(
    'Content-Disposition: attachment; filename="' .
    $filename .
    '"'
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);


/*
|--------------------------------------------------------------------------
| Open Output
|--------------------------------------------------------------------------
*/

$output = fopen(
    'php://output',
    'w'
);


/*
|--------------------------------------------------------------------------
| UTF-8 BOM
|--------------------------------------------------------------------------
|
| Helps Microsoft Excel display UTF-8
| characters correctly.
|
*/

fwrite(
    $output,
    "\xEF\xBB\xBF"
);


/*
|--------------------------------------------------------------------------
| Report Information
|--------------------------------------------------------------------------
*/

fputcsv(
    $output,
    [
        'Security Incident Ticketing System'
    ]
);

fputcsv(
    $output,
    [
        'Generated At',
        date('Y-m-d H:i:s')
    ]
);

fputcsv(
    $output,
    [
        'Search',
        $search
    ]
);

fputcsv(
    $output,
    [
        'Status Filter',
        $status !== ''
            ? $status
            : 'All'
    ]
);

fputcsv(
    $output,
    [
        'Severity Filter',
        $severity !== ''
            ? $severity
            : 'All'
    ]
);

fputcsv(
    $output,
    [
        'Category ID',
        $category > 0
            ? $category
            : 'All'
    ]
);

fputcsv(
    $output,
    [
        'Date From',
        $dateFrom !== ''
            ? $dateFrom
            : 'All'
    ]
);

fputcsv(
    $output,
    [
        'Date To',
        $dateTo !== ''
            ? $dateTo
            : 'All'
    ]
);


/*
|--------------------------------------------------------------------------
| Empty Line
|--------------------------------------------------------------------------
*/

fputcsv(
    $output,
    []
);


/*
|--------------------------------------------------------------------------
| CSV Column Headers
|--------------------------------------------------------------------------
*/

fputcsv(
    $output,
    [
        'ID',
        'Ticket Number',
        'Title',
        'Category',
        'Severity',
        'Status',
        'Reporter',
        'Assigned Analyst',
        'Created At',
        'Updated At'
    ]
);


/*
|--------------------------------------------------------------------------
| Data Rows
|--------------------------------------------------------------------------
*/

foreach (
    $incidents
    as $incident
) {

    fputcsv(
        $output,
        [
            $incident['id'],

            $incident['ticket_number'],

            $incident['title'],

            $incident['category_name']
                ?? 'N/A',

            ucfirst(
                $incident['severity']
            ),

            ucfirst(
                str_replace(
                    '_',
                    ' ',
                    $incident['status']
                )
            ),

            $incident['reporter_name']
                ?? 'N/A',

            $incident['analyst_name']
                ?? 'Not Assigned',

            $incident['created_at'],

            $incident['updated_at']
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Close
|--------------------------------------------------------------------------
*/

fclose(
    $output
);

exit;