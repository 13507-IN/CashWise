<?php
/**
 * FinMate Installation Script
 * 
 * This script sets up the database structure for the FinMate application.
 * Run this script once to create all necessary tables.
 */

// Display errors during installation
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Header
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinMate Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-top: 40px; }
        .step { margin-bottom: 30px; padding: 20px; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
        .warning { background-color: #fff3cd; color: #856404; }
        .info { background-color: #d1ecf1; color: #0c5460; }
        pre { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h2>FinMate Installation</h2>
                    </div>
                    <div class="card-body">';

// Check if config file exists
if (!file_exists('config/database.php')) {
    echo '<div class="step error">
            <h4>Error: Configuration File Missing</h4>
            <p>The database configuration file (config/database.php) was not found. 
            Please make sure all files are correctly uploaded.</p>
          </div>';
    echo showActionButtons(false);
    echo '</div></div></div></div></div></body></html>';
    exit;
}

// Test database connection before including config
echo '<div class="step info">
        <h4>Step 1: Database Connection Test</h4>';

// Get database config information to test connection
$db_config = parse_ini_string(preg_replace('/^<\?php|\?>$/s', '', file_get_contents('config/database.php')), true);

// Try to manually establish connection
$db_host = getConfigValue($db_config, 'DB_HOST', 'localhost');
$db_user = getConfigValue($db_config, 'DB_USER', 'root');
$db_pass = getConfigValue($db_config, 'DB_PASS', '');
$db_name = getConfigValue($db_config, 'DB_NAME', 'budget_tracker');

echo "<p>Testing connection to MySQL server...</p>";

$conn_test = @mysqli_connect($db_host, $db_user, $db_pass);
if (!$conn_test) {
    echo '<div class="error">
            <h5>Failed to connect to MySQL server</h5>
            <p>Could not connect to the database server with the provided credentials. Please check your database settings in config/database.php:</p>
            <ul>
                <li>Host: ' . htmlspecialchars($db_host) . '</li>
                <li>User: ' . htmlspecialchars($db_user) . '</li>
                <li>Password: ' . (empty($db_pass) ? 'Empty' : '******') . '</li>
            </ul>
            <p>Error: ' . mysqli_connect_error() . '</p>
            <p>Common issues:</p>
            <ul>
                <li>MySQL server is not running</li>
                <li>Incorrect username or password</li>
                <li>Host configuration issue</li>
            </ul>
          </div>';
    echo showActionButtons(false);
    echo '</div></div></div></div></div></body></html>';
    exit;
}

echo "<p class='success'>Successfully connected to MySQL server!</p>";

// Try to create/select the database
echo "<p>Testing database creation/selection...</p>";
if (!mysqli_query($conn_test, "CREATE DATABASE IF NOT EXISTS `$db_name`")) {
    echo '<div class="error">
            <h5>Failed to create database</h5>
            <p>Could not create the database. Please check your permissions and database name.</p>
            <p>Error: ' . mysqli_error($conn_test) . '</p>
          </div>';
    echo showActionButtons(false);
    echo '</div></div></div></div></div></body></html>';
    mysqli_close($conn_test);
    exit;
}

// Test database selection
if (!mysqli_select_db($conn_test, $db_name)) {
    echo '<div class="error">
            <h5>Failed to select database</h5>
            <p>Could not select the database. Please check your permissions and database name.</p>
            <p>Error: ' . mysqli_error($conn_test) . '</p>
          </div>';
    echo showActionButtons(false);
    echo '</div></div></div></div></div></body></html>';
    mysqli_close($conn_test);
    exit;
}

echo "<p class='success'>Successfully created/selected database: " . htmlspecialchars($db_name) . "</p>";
mysqli_close($conn_test);

echo '</div>';  // Close connection test step

// Now include the actual database configuration
require_once 'config/database.php';

echo '<div class="step info">
        <h4>Step 2: Database Structure</h4>';

// Check database connection from the included file
if ($conn->connect_error) {
    echo '<p class="error">Connection failed: ' . $conn->connect_error . '</p>';
    echo showActionButtons(false);
    echo '</div></div></div></div></div></body></html>';
    exit;
} else {
    echo '<p class="success">Database connection successful!</p>';
}

// Read and execute SQL schema
echo '<h5>Creating Database Schema</h5>';

try {
    // Read SQL file
    if (file_exists('database/schema.sql')) {
        $sql = file_get_contents('database/schema.sql');
        
        // Split SQL by semicolon
        $queries = explode(';', $sql);
        
        $executed_queries = 0;
        $errors = [];
        
        // Execute each query
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                // Skip USE statement as we already connected to the database
                if (stripos($query, 'USE') === 0) {
                    continue;
                }
                // Skip CREATE DATABASE as we already created it
                if (stripos($query, 'CREATE DATABASE') === 0) {
                    continue;
                }
                
                if ($conn->query($query)) {
                    $executed_queries++;
                } else {
                    $errors[] = "Error on query: " . htmlspecialchars(substr($query, 0, 100)) . "... : " . $conn->error;
                }
            }
        }
        
        if (empty($errors)) {
            echo '<p class="success">Database schema created successfully! Executed ' . $executed_queries . ' queries.</p>';
        } else {
            echo '<div class="warning">
                    <p>Encountered errors while setting up database schema:</p>
                    <ul>';
            foreach ($errors as $error) {
                echo '<li>' . $error . '</li>';
            }
            echo '</ul>
                  </div>';
        }
    } else {
        echo '<p class="error">Schema file not found. Please make sure database/schema.sql exists.</p>';
    }
} catch (Exception $e) {
    echo '<p class="error">Error: ' . $e->getMessage() . '</p>';
}

echo '</div>';

// Setting up default categories
echo '<div class="step info">
        <h4>Step 3: Setting Up Default Categories</h4>';

try {
    // Check if categories table exists
    $tables_result = $conn->query("SHOW TABLES LIKE 'categories'");
    if ($tables_result && $tables_result->num_rows > 0) {
        // Try to count categories
        $sql = "SELECT COUNT(*) as count FROM categories WHERE user_id IS NULL";
        $result = $conn->query($sql);
        
        if (!$result) {
            echo '<p class="warning">Could not check categories: ' . $conn->error . '</p>';
        } else {
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                echo '<p class="success">Default categories are already set up! Found ' . $row['count'] . ' categories.</p>';
            } else {
                // Include the file to create default categories
                echo '<p>Adding default categories...</p>';
                $default_categories = [
                    ['Salary', 'income'],
                    ['Bonus', 'income'],
                    ['Gift', 'income'],
                    ['Other Income', 'income'],
                    ['Food', 'expense'],
                    ['Rent', 'expense'],
                    ['Utilities', 'expense'],
                    ['Entertainment', 'expense'],
                    ['Transportation', 'expense'],
                    ['Healthcare', 'expense'],
                    ['Shopping', 'expense'],
                    ['Education', 'expense'],
                    ['Other Expense', 'expense']
                ];
                
                // Insert categories
                $stmt = $conn->prepare("INSERT INTO categories (name, type, user_id) VALUES (?, ?, NULL)");
                if (!$stmt) {
                    echo '<p class="error">Failed to prepare statement: ' . $conn->error . '</p>';
                } else {
                    $added = 0;
                    foreach ($default_categories as $category) {
                        $stmt->bind_param("ss", $category[0], $category[1]);
                        if ($stmt->execute()) {
                            $added++;
                        }
                    }
                    echo '<p class="success">Successfully added ' . $added . ' default categories!</p>';
                }
            }
        }
    } else {
        echo '<p class="error">Categories table does not exist! Database setup may have failed.</p>';
    }
} catch (Exception $e) {
    echo '<p class="error">Error setting up default categories: ' . $e->getMessage() . '</p>';
}

echo '</div>';

// Final step - installation complete
echo '<div class="step success">
        <h4>Installation Complete!</h4>
        <p>The FinMate database has been successfully set up. You can now:</p>
        <ul>
            <li>Create a user account using the registration page</li>
            <li>Start tracking your income and expenses</li>
            <li>Set up budget limits and financial goals</li>
        </ul>';

echo showActionButtons(true);
echo '</div>';

// Close the connection
$conn->close();

// Footer
echo '</div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';

// Helper function to extract config values
function getConfigValue($config, $key, $default) {
    if (is_array($config) && isset($config[$key])) {
        return $config[$key];
    }
    
    // Try a different approach by looking for define statements
    $matches = [];
    if (preg_match('/define\s*\(\s*[\'"]' . $key . '[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]/', $config, $matches)) {
        return $matches[1];
    }
    
    return $default;
}

// Helper function to show action buttons
function showActionButtons($success) {
    $html = '<div class="mt-4">';
    
    if ($success) {
        $html .= '<a href="index.php" class="btn btn-primary">Go to Homepage</a> ';
        $html .= '<a href="register.php" class="btn btn-success">Create Account</a>';
    } else {
        $html .= '<a href="install.php" class="btn btn-warning">Try Again</a> ';
        $html .= '<a href="https://github.com/yourusername/budget-tracker/issues" target="_blank" class="btn btn-info">Get Help</a>';
    }
    
    $html .= '</div>';
    return $html;
}
?> 