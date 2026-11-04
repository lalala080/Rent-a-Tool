<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Access denied.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

$sessionToken = $_SESSION['delete_tool_csrf'] ?? null;
$submittedToken = $_POST['csrf_token'] ?? null;

if (
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !is_string($submittedToken) ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit(
        'Invalid request. <a href="my_tools.php">Reload My Tools</a> and try again.'
    );
}

function redirectAfterToolDeletion($message, $type = 'error')
{
    $_SESSION['delete_tool_flash'] = [
        'message' => $message,
        'type' => $type
    ];

    header('Location: my_tools.php', true, 303);
    exit;
}

$toolId = filter_var(
    $_POST['tool_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($toolId === false) {
    redirectAfterToolDeletion(
        'Invalid tool selection. Reload My Tools and try again.'
    );
}

$ownerId = (int) $_SESSION['user_id'];

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    require __DIR__ . '/db.php';

    $stmt = $conn->prepare("
        DELETE FROM tools
        WHERE id = ?
          AND owner_id = ?
          AND NOT EXISTS (
              SELECT 1
              FROM rentals
              WHERE rentals.tool_id = tools.id
          )
    ");

    $stmt->bind_param('ii', $toolId, $ownerId);
    $stmt->execute();

    $deletedRows = (int) $stmt->affected_rows;
    $stmt->close();

} catch (Throwable $error) {
    error_log('Tool deletion failed: ' . $error->getMessage());

    redirectAfterToolDeletion(
        'The tool could not be deleted. Please try again later.'
    );
}

if ($deletedRows !== 1) {
    redirectAfterToolDeletion(
        'Tool was not deleted. Only your own listings without rental records can be deleted.'
    );
}

redirectAfterToolDeletion('Tool deleted successfully.', 'success');