<?php
/**
 * Enhanced File Sharing System - Database Migration Script
 * This script safely executes the database migration to update the schema
 */

require_once __DIR__ . '/dssssssssb.php';

// Check if migration has already been run
$migration_check = mysqli_query($con, "SHOW TABLES LIKE 'system_settings'");
$migration_completed = mysqli_num_rows($migration_check) > 0;

if ($migration_completed) {
    echo json_encode([
        'success' => false,
        'message' => 'Migration has already been completed. Use force parameter to run again.'
    ]);
    exit;
}

try {
    // Read the migration SQL file
    $migration_sql = file_get_contents(__DIR__ . '/enhanced_database_migration.sql');

    if (!$migration_sql) {
        throw new Exception('Migration SQL file not found');
    }

    // Split SQL into individual statements
    $statements = [];
    $lines = explode("\n", $migration_sql);
    $current_statement = '';
    $in_delimiter = false;

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and empty lines
        if (empty($line) || strpos($line, '--') === 0) {
            continue;
        }

        // Check for delimiter changes
        if (preg_match('/^DELIMITER\s+(.+)$/i', $line, $matches)) {
            $in_delimiter = true;
            $current_statement .= $line . "\n";
            continue;
        }

        $current_statement .= $line . "\n";

        // Check if statement is complete
        if (!$in_delimiter && substr($line, -1) === ';') {
            $statements[] = trim($current_statement);
            $current_statement = '';
        } elseif ($in_delimiter && strpos($line, '//') !== false) {
            $statements[] = trim($current_statement);
            $current_statement = '';
            $in_delimiter = false;
        }
    }

    // Execute each statement
    $success_count = 0;
    $error_count = 0;
    $errors = [];

    foreach ($statements as $i => $statement) {
        if (empty($statement)) continue;

        try {
            $result = mysqli_query($con, $statement);

            if ($result === false) {
                throw new Exception("Statement $i failed: " . mysqli_error($con));
            }

            // Handle SELECT statements that return results
            if (preg_match('/^\s*SELECT/i', $statement)) {
                $result_data = mysqli_fetch_all($result, MYSQLI_ASSOC);
                if (!empty($result_data)) {
                    echo "Statement $i result: " . json_encode($result_data) . "\n";
                }
            }

            $success_count++;
        } catch (Exception $e) {
            $error_count++;
            $errors[] = "Statement $i: " . $e->getMessage();

            // Log the error but continue with other statements
            error_log("Migration error on statement $i: " . $e->getMessage());
        }
    }

    // Create migration record
    mysqli_query($con, "
        CREATE TABLE IF NOT EXISTS migration_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            migration_name VARCHAR(255) NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success_count INT DEFAULT 0,
            error_count INT DEFAULT 0,
            errors TEXT NULL
        )
    ");

    $errors_text = !empty($errors) ? json_encode($errors) : null;
    mysqli_query($con, "
        INSERT INTO migration_history (migration_name, success_count, error_count, errors)
        VALUES ('enhanced_file_sharing_system', $success_count, $error_count, " . ($errors_text ? "'$errors_text'" : "NULL") . ")
    ");

    echo json_encode([
        'success' => $error_count === 0,
        'message' => "Migration completed. Success: $success_count, Errors: $error_count",
        'details' => [
            'success_count' => $success_count,
            'error_count' => $error_count,
            'errors' => $errors
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed: ' . $e->getMessage()
    ]);
}

mysqli_close($con);
?>
