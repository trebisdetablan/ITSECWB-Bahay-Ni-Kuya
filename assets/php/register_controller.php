<?php
    /*
        register_controller.php
        - backend of views/register.php
    */

    include('validate_password.php');

    // Called in <div class="register_container">
    function register(&$conn) {

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Collect and sanitize user input
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $error = "";

            // Collect the password and hash it
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password']; 

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format";
            }

            // Check if Email already exists
            elseif (empty($error)) { // Only check DB if format is valid
                $check_stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
                $check_stmt->bind_param("s", $email);
                $check_stmt->execute();
                $check_stmt->store_result();
                
                if ($check_stmt->num_rows > 0) {
                    $error = "This email is already registered.";
                }
                $check_stmt->close();
            }

            // Validate Names (Letters and spaces only)
            elseif (!preg_match("/^[a-zA-Z\s]+$/", $first_name) || !preg_match("/^[a-zA-Z\s]+$/", $last_name)) {
                $error = "Names can only contain letters and spaces.";
            }

            // Validate the password
            elseif (!passwordIsValid($conn, $email, $password, $confirm_password, $error)) {
                // Error is already set inside passwordIsValid function
            }

            // If no errors, proceed with registration
            if (empty($error)) {
                // Will only hash the password if all validations are passed to avoid unnecessary hashing
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // STORED PROCEDURE: CALL sp_add_user
                $stmt = $conn->prepare("CALL sp_add_user(?, ?, ?, ?)");


                // Bind the email, first name, last name, and hashed password
                $stmt->bind_param("ssss", $email, $first_name, $last_name, $hash);

                if ($stmt->execute()) {
                    $success = "Account created successfully!";

                    // Store password in OLD_PASSWORDS TABLE to prevent future reuse
                    $pass_stmt = $conn->prepare("CALL sp_record_password(?, ?)");
                    $pass_stmt->bind_param("ss", $email, $hash);
                    $pass_stmt->execute();
                } else {
                    $error = "Error: " . $stmt->error;
                }

                $stmt->close();
            }

            $conn->close();
        }

        // If Successful, user proceeds to login
        if (isset($success)) {
            header("Location: login.php");
            exit();
        } elseif (isset($error)) {
            // If error contains new lines, replace them with actual line breaks in the alert
            $error_message = str_replace("\n", '\\n', $error);
            echo "<script>alert('{$error_message}');</script>";
        }
    }
?>