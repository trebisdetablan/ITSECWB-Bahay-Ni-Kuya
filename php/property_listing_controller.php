<?php
require_once('../includes/dbconfig.php');

// Default values for login variables
$last_login = 'No previous logins yet.';

// Check if user is logged in and email exists in the session
if (isset($_SESSION['user_email'])) {
    $user_email = $_SESSION['user_email'];  // Assuming the user's email is stored in session

    // Fetch the last login attempt of the user
    $query = "SELECT * FROM event_logs WHERE user_email = ? ORDER BY datetime DESC LIMIT 1 OFFSET 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($login = $result -> fetch_assoc()) {
        if($login['result'] == 'Success') {
            $last_login = "Successful login at: " . $login['datetime'];
        }
        elseif($login['result'] == 'Fail') {
            $last_login = "Failed login at: " . $login['datetime'];
        }
    }

    $stmt->close();
}

// Check if the user has just logged in and show the overlay once
$showOverlay = isset($_SESSION['show_overlay']) && $_SESSION['show_overlay'] === true;

if ($showOverlay) {
    unset($_SESSION['show_overlay']); // Unset after showing to prevent multiple displays
}
?>