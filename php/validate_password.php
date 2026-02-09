<?php
/*
    INPUT VALIDATION OF PASSWORDS
    Included in: views/register.php
*/
    $minLength = 8;

    // Regex for password complexity
    $uppercase_regex = '/\p{Lu}/u'; // Uppercase letters
    $lowercase_regex = '/\p{Ll}/u'; // Lowercase letters
    $digit_regex = '/\d/'; // Base 10 digits
    $specialchar_regex  = '/[-!"#$%&()*<>\/:;?@\[\]^_`{|}~+<>]/'; // Special characters 

    // Checks validity of password
    // Called in: register_controller.php, reset_password_controller.php
    function passwordIsValid(&$conn, $email, $password, $confirm_password, &$error) {
        global $uppercase_regex, $lowercase_regex, $digit_regex, $specialchar_regex, $minLength;

        // If password is NOT older than 1 day
        if (!isOld($conn, $email, $error)) {
            return false;
        }

        // If either [Password] or [Confirm Password] is empty
        if (empty($password) || empty($confirm_password)) {
            $error = "Password fields cannot be empty.";
            return false;
        }

        // =========== PASSWORD COMPLEXITY CHECKS =============
        $invalid = 0;

        // If password is shorter than 8 characters
        if (strlen($password) < $minLength) {
            $invalid++;
        }

        // If password lacks uppercase letters
        if (!preg_match($uppercase_regex, $password)) {
            $invalid++;
        }

        // If password lacks lowercase letters
        if (!preg_match($lowercase_regex, $password)) {
            $invalid++;
        }

        // If password lacks digits from 0-9
        if (!preg_match($digit_regex, $password)) {
            $invalid++;
        }

        // If password lacks special characters
        if (!preg_match($specialchar_regex, $password)) {
            $invalid++;
        }

        // If any of the complexity checks failed
        if ($invalid > 0) {
            $error = "Password must be at least $minLength characters long and contain:\n- at least one letter\n- at least one digit\n- at least one uppercase letter\n- and at least one symbol.";
            return false;
        }

        // If either [Password] and [Confirm Password] do not match
        if ($password != $confirm_password) {
            $error = "Passwords do not match";
            return false;
        }

        // If password is being reused
        if (isReused($conn, $email, $password, $error)) {
            return false;
        }

        // Password is completely VALID
        return true;
    }

    // Check if password is being reused
    function isReused(&$conn, $email, $new_password, &$error) {

        $stmt = $conn->prepare("SELECT password_hash
        FROM old_passwords
        WHERE email=?");

        if($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($old_hash);

            while($stmt->fetch()){
                if(password_verify($new_password, $old_hash)) {
                    $error = "You cannot reuse passwords!";
                    $stmt->close();
                    return true;
                }
            }
            $stmt->close();
            return false; // Not reused
        }
        
        return false; // query failed
    }

    // Check if password is at least 1 day old
    function isOld(&$conn, $email, &$error) {
        $stmt = $conn->prepare("SELECT password_created
        FROM old_passwords
        WHERE email=?
        ORDER BY password_created DESC
        LIMIT 1");

        if($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($password_created);

            if($stmt->fetch()){
                $stmt->close();

                $changed_time = strtotime($password_created);
                $now = time();
                $difference = $now - $changed_time;

                if($difference < 86400) { // 86400 seconds = 1 day
                    $error = "You must wait at least one (1) day before changing password.<br>Last changed: $password_created<br>";
                    return false; // password is not old enough
                }
            }
            else {
                $stmt->close();
            }
            
            return true; // Password is old enough
        }
    }
?>