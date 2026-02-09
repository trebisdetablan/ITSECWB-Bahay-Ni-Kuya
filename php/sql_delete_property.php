<?php
// Database configuration
require_once('../../includes/dbconfig.php');

session_start();

// Authorization Check
// Only allow 'A' (Admin) or 'S' (Staff) to delete
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'A' && $_SESSION['user_role'] !== 'S')) {
    die("Error: Unauthorized access.");
}

if($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id'])){
    // Sanitize ID
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("DELETE FROM properties WHERE property_id = ?");
    $stmt->bind_param("i", $id);

    $stmt->execute();

    $stmt->close();
    $conn->close();
}
?>