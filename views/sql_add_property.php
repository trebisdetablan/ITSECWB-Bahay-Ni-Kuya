<?php
session_start();

// Database configuration
require_once('../includes/dbconfig.php');
include('../assets/php/validation.php');

$resource = "/admin_add_property";
$reason = "";
$status = "Fail";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $price = $_POST['price'];

    // Validate price: must be numeric and between 1 and 999,999,999
    $price = isset($_POST['price']) ? str_replace([',', ' '], '', $_POST['price']) : '';
    if ($price === '' || !is_numeric($price)) {
        $reason = "Price entered is not a numeric value";
        logValidation($conn, $_SESSION['user_email'], $resource, $reason, $status);

        echo "<script>alert('Error: Price must be a numeric value.'); window.location.href='admin.php';</script>";
        
        exit;
    }
    $price = (float)$price;
    if ($price < 100000 || $price > 999999999) {
        $reason = "Entered price not between 100000 and 999,000,000";
        logValidation($conn, $_SESSION['user_email'], $resource, $reason, $status);

        echo "<script>alert('Error: Price must be between 100000 and 999,000,000.'); window.location.href='admin.php';</script>";
        
        exit;
    }

    // Normalize and validate property name length (max 200 chars)
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $maxLen = 200;

    // Names with accents/UTF-8 characters are counted correctly
    $len = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);

    if ($name === '') {
        $reason = "Entered empty property name";
        logValidation($conn, $_SESSION['user_email'], $resource, $reason, $status);
        echo "<script>alert('Property name is required.'); window.location.href='admin.php';</script>";
        exit;
    }
    if ($len > $maxLen) {
        $reason = "Property name exceeds 200 characters";
        logValidation($conn, $_SESSION['user_email'], $resource, $reason, $status);
        echo "<script>alert('Property name must not exceed 200 characters.'); window.location.href='admin.php';</script>";
        exit;
    }

    // Normalize and validate description length (max 1,500 chars)
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $maxDesc = 1500;

    // Names with accents/UTF-8 characters are counted correctly
    $descLen = function_exists('mb_strlen') ? mb_strlen($description, 'UTF-8') : strlen($description);

    if ($description === '') {
        $reason = "Entered empty description";
        logValidation($conn, $_SESSION['user_email'], $resource, $reason, $status);
        echo "<script>alert('Description is required.'); window.location.href='admin.php';</script>";
        exit;
    }
    if ($descLen > $maxDesc) {
        $reason = "Description exceeds 1500 characters";
        logValidation($conn, $_SESSION['user_email'], $resource, $reason, $status);
        echo "<script>alert('Description must not exceed 1,500 characters.'); window.location.href='admin.php';</script>";
        exit;
    }

    // Normalize and validate location length (max 75 chars)
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $maxAddress = 75;

    // Names with accents/UTF-8 characters are counted correctly
    $addressLen = function_exists('mb_strlen') ? mb_strlen($address, 'UTF-8') : strlen($address);

    if ($address === '') {
        $reason = "Empty location entered";
        logValidation($conn, $_SESSION['user_email'], $resource, $reason, $status);
        echo "<script>alert('Location is required.'); window.location.href='admin.php';</script>";
        exit;
    }
    if ($addressLen > $maxAddress) {
        $reason = "Location exceeds 75 characters";
        logValidation($conn, $_SESSION['user_email'], $resource, $reason, $status);
        echo "<script>alert('Location must not exceed 75 characters.'); window.location.href='admin.php';</script>";
        exit;
    }

    $description = $_POST['description'];
        $photo = null;
    

    // --- SECURE PHOTO UPLOAD START ---
    if (!empty($_FILES['image']['name'])) {

    $file_name = $_FILES['image']['name'];
    $tempname  = $_FILES['image']['tmp_name'];

    // Allowed types
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];

    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // EXTENSION CHECK
    if (!in_array($file_ext, $allowed_ext)) {
        echo "<script>alert('Invalid file extension. Only JPG, PNG, and GIF allowed.'); window.location.href='admin.php';</script>";
        exit;
    }

    // MIME CHECK (Stronger)
    $mime = mime_content_type($tempname);

    if ($mime === false || !in_array($mime, $allowed_mimes)) {
        echo "<script>alert('Invalid file type. Only real image files allowed.'); window.location.href='admin.php';</script>";
        exit;
    }

    // EXTRA SECURITY: Confirm it is a real image
    if (getimagesize($tempname) === false) {
        echo "<script>alert('File is not a valid image.'); window.location.href='admin.php';</script>";
        exit;
    }

    // Generate safe filename
    $new_file_name = bin2hex(random_bytes(16)) . "." . $file_ext;
    $folder = '../assets/images/' . $new_file_name;

    if (!move_uploaded_file($tempname, $folder)) {
        echo "<script>alert('Failed to upload image.'); window.location.href='admin.php';</script>";
        exit;
    }

    $photo = $folder;
}

    
    // Insert new property
    // CALL PROCEDURE: sp_add_property
    $stmt = $conn->prepare("CALL sp_add_property(?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdss", $name, $address, $price, $description, $photo);
    require_once('../assets/php/logger.php');


    if ($stmt->execute()) {
        $_SESSION['admin_message'] = "Property added successfully";
        $_SESSION['admin_message_type'] = "success";
        writeToLogFile("ADMIN", $_SESSION['user_email'], "/admin_add_property", "Added property: $name", "Success");
    } else {
        // Call the centralized error handler
        $safeErrorMessage = handleSystemError($conn, $_SESSION['user_email'], "/admin_add_property", $conn->error);
        
        $_SESSION['admin_message'] = $safeErrorMessage;
        $_SESSION['admin_message_type'] = "error";
    }
} else {
    } else {
        $_SESSION['admin_message'] = "Error adding property: " . $conn->error;
        $_SESSION['admin_message_type'] = "error";
        // Log admin failure
        writeToLogFile("ADMIN", $_SESSION['user_email'], "/admin_add_property", "Failed to add property", "Fail");
    }
    
    // Return JSON response for AJAX or redirect
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => $stmt->affected_rows > 0]);
        exit;
    } else {
        header("Location: admin.php");
        exit;
    }
}
?>
