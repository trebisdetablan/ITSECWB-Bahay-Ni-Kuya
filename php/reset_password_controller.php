<?php
/*
    reset_password_controller.php
    Handles password reset and records old passwords
*/

include('validate_password.php');

function changePassword(&$conn) {

    // Get email from session (recovery or logged-in session)
    $email = $_SESSION['recovery_email'] ?? $_SESSION['user_email'] ?? '';

    if (empty($email)) {
        echo "<p class='error-message'>No account found for this reset process.</p>";
        return;
    }

    // Process only on POST
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $error = "";
        $success = "";

        // Get form inputs
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Check if email exists in the database before updating
        $checkStmt = $conn->prepare("SELECT 1 FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();

        if (!$exists) {
            echo "<p class='error-message'>This account does not exist.</p>";
            return;
        }

        // Validate password using your existing function
        if (passwordIsValid($conn, $email, $password, $confirm_password, $error)) {

            // Update password in users table
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $stmt->bind_param("ss", $hash, $email);

            if ($stmt->execute()) {
                // Record password in old_passwords (via stored procedure)
                $pass_stmt = $conn->prepare("CALL sp_record_password(?, ?)");
                $pass_stmt->bind_param("ss", $email, $hash);
                $pass_stmt->execute();
                $pass_stmt->close();

                $success = "Password changed successfully!";
                // Redirect to logout to force re-login
                header("Location: logout.php");
                exit();

            } else {
                $error = "Error updating password: " . $stmt->error;
            }

            $stmt->close();
        }

        // Show errors if any
        if (!empty($error)) {
            echo "<p class='error-message'>{$error}</p>";
        }
    }
}
?>
