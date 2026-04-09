<?php
// assets/php/logger.php

function writeToLogFile($category, $email, $resource, $reason, $status) {
    // 1. Format the timestamp and message to make it traceable
    $timestamp = date("Y-m-d H:i:s");
    $logMessage = "[$timestamp] [$category] USER: $email | RES: $resource | STATUS: $status | MSG: $reason" . PHP_EOL;

    // 2. Define the log file path
    // Using __DIR__ . '/../../' puts the log file in the main Bahay-Ni-Kuya folder
    $logFile = __DIR__ . '/../../app_audit.log';

    // 3. Write to the file using PHP's error_log
    // The '3' tells PHP to append the message to the destination file
    error_log($logMessage, 3, $logFile);

    // Syslog Logging
    // Open the syslog connection
    openlog("BahayNiKuyaApp", LOG_PID | LOG_PERROR, LOG_USER);
    // Send the message as an informational log
    syslog(LOG_INFO, $logMessage);
    // Close the connection
    closelog();
}

function handleSystemError($conn, $email, $resource, $actualErrorMsg) {
    // 1. ALWAYS log the real, detailed error to the secure backend log file
    writeToLogFile("ERROR", $email, $resource, "DB Error: " . $actualErrorMsg, "Fail");

    // 2. Check the global DEBUG_MODE constant (defined in dbconfig.php)
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        // Return the detailed stack trace/error for the developer to see
        return "DEBUG ERROR: Failed to execute query. Stack/Error: " . $actualErrorMsg;
    } else {
        // Return a generic, safe error message for production
        return "A system error occurred. Please try again later or contact support.";
    }
}
?>
