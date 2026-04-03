<?php 

// Set timeout duration (in seconds)
$timeout_duration = 300; // 5 minutes

// Check if last activity is set
if (isset($_SESSION['LAST_ACTIVITY'])) {
    // Calculate inactive time
    $inactive_time = time() - $_SESSION['LAST_ACTIVITY'];

    if ($inactive_time > $timeout_duration) {
        // Session expired
        session_unset();
        session_destroy();

        // Redirect to login with message
        header("Location: /views/login.php?timeout=1");
        exit();
    }
}

// Update last activity time
$_SESSION['LAST_ACTIVITY'] = time();
/* 
    authentication.php
    - redirect user depending on role
*/

require_once('logger.php');

function redirectUser($user){
    // Redirect based on role
    if ($user['role'] == 'A' || $user['role'] == 'S') {
        header("Location: admin.php");
        exit();
    } 
    
    else {
        header("Location: property_listing.php");
        exit();
    }
}

// Log successful authentication to EVENT_LOGS table
function logAuthentication(&$conn, $email, $resource, $reason, $status) {

    $type = 'A'; // Authentication Log

    /*
        CREATE PROCEDURE sp_log_event
        IN this_type VARCHAR(1),
        IN this_user_email VARCHAR(254),
        IN this_resource TEXT,
        IN this_reason TEXT,
        IN this_result VARCHAR(10)
    
    */
    $log_stmt = $conn->prepare("CALL sp_log_event(?, ?, ?, ?, ?)");

    $log_stmt->bind_param("sssss", $type, $email, $resource, $reason, $status);
    $log_stmt->execute();

    writeToLogFile("AUTH", $email, $resource, $reason, $status);
}
?>
