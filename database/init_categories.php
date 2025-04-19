<?php
// Adjust path for proper inclusion when script is run directly
$config_path = dirname(__FILE__) . '/../config/database.php';
require_once $config_path;

// Insert default categories if they don't exist
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

// Check if default categories exist
$sql = "SELECT COUNT(*) as count FROM categories WHERE user_id IS NULL";
$result = $conn->query($sql);

if (!$result) {
    die("Error checking categories: " . $conn->error);
}

$row = $result->fetch_assoc();

// Insert default categories if they don't exist
if ($row['count'] == 0) {
    $stmt = $conn->prepare("INSERT INTO categories (name, type, user_id) VALUES (?, ?, NULL)");
    
    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }
    
    foreach ($default_categories as $category) {
        $stmt->bind_param("ss", $category[0], $category[1]);
        if (!$stmt->execute()) {
            die("Error adding category: " . $stmt->error);
        }
    }
    
    echo "Default categories added successfully!<br>";
}

// Function to add default categories for a specific user
function addDefaultCategoriesForUser($conn, $userId) {
    $sql = "INSERT INTO categories (user_id, name, type)
            SELECT ?, name, type FROM categories WHERE user_id IS NULL";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        return true;
    } else {
        return false;
    }
}

echo "Category initialization script completed!";
?> 