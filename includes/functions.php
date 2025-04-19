<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect to a specific page
function redirect($location) {
    header("Location: $location");
    exit;
}

// Flash message function
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Display flash message
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message']['message'];
        $type = $_SESSION['flash_message']['type'];
        
        echo "<div class='alert alert-{$type}'>{$message}</div>";
        
        // Clear the message after displaying
        unset($_SESSION['flash_message']);
    }
}

// Sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Calculate total income for a period
function getTotalIncome($conn, $userId, $startDate, $endDate) {
    if (!$conn) {
        return 0; // Return 0 if database connection is not valid
    }
    
    $sql = "SELECT SUM(t.amount) as total FROM transactions t 
            JOIN categories c ON t.category_id = c.id 
            WHERE t.user_id = ? AND c.type = 'income' 
            AND t.transaction_date BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0; // Return 0 if the statement couldn't be prepared
    }
    
    $stmt->bind_param("iss", $userId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        return 0; // Return 0 if there was an error getting results
    }
    
    $row = $result->fetch_assoc();
    
    return $row['total'] ?? 0;
}

// Calculate total expense for a period
function getTotalExpense($conn, $userId, $startDate, $endDate) {
    if (!$conn) {
        return 0; // Return 0 if database connection is not valid
    }
    
    $sql = "SELECT SUM(t.amount) as total FROM transactions t 
            JOIN categories c ON t.category_id = c.id 
            WHERE t.user_id = ? AND c.type = 'expense' 
            AND t.transaction_date BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0; // Return 0 if the statement couldn't be prepared
    }
    
    $stmt->bind_param("iss", $userId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        return 0; // Return 0 if there was an error getting results
    }
    
    $row = $result->fetch_assoc();
    
    return $row['total'] ?? 0;
}

// Get expense breakdown by category
function getExpenseByCategory($conn, $userId, $startDate, $endDate) {
    if (!$conn) {
        return false; // Return false if database connection is not valid
    }
    
    $sql = "SELECT c.name, SUM(t.amount) as total FROM transactions t 
            JOIN categories c ON t.category_id = c.id 
            WHERE t.user_id = ? AND c.type = 'expense' 
            AND t.transaction_date BETWEEN ? AND ? 
            GROUP BY c.id ORDER BY total DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false; // Return false if the statement couldn't be prepared
    }
    
    $stmt->bind_param("iss", $userId, $startDate, $endDate);
    $stmt->execute();
    return $stmt->get_result();
}

// Check if budget limit is exceeded
function isBudgetExceeded($conn, $userId, $categoryId) {
    if (!$conn) {
        return false; // Return false if database connection is not valid
    }
    
    // Get current month's budget
    $firstDayOfMonth = date('Y-m-01');
    $lastDayOfMonth = date('Y-m-t');
    
    // Get budget amount
    $sql = "SELECT amount FROM budgets WHERE user_id = ? AND category_id = ? AND period = 'monthly' ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false; // Return false if the statement couldn't be prepared
    }
    
    $stmt->bind_param("ii", $userId, $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows == 0) {
        return false; // No budget set
    }
    
    $budgetAmount = $result->fetch_assoc()['amount'];
    
    // Get current spending
    $sql = "SELECT SUM(amount) as spent FROM transactions 
            WHERE user_id = ? AND category_id = ? 
            AND transaction_date BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false; // Return false if the statement couldn't be prepared
    }
    
    $stmt->bind_param("iiss", $userId, $categoryId, $firstDayOfMonth, $lastDayOfMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        return false; // Return false if there was an error getting results
    }
    
    $spent = $result->fetch_assoc()['spent'] ?? 0;
    
    return $spent >= $budgetAmount;
}

// Log errors to a file
function logError($message) {
    $logDir = dirname(__FILE__) . '/../logs';
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    
    error_log($logMessage, 3, $logFile);
}

/**
 * Generate spending insights based on user's transaction history
 * 
 * @param object $conn Database connection
 * @param int $user_id User ID
 * @param string $start_date Start date for analysis
 * @param string $end_date End date for analysis
 * @return array Array of insight messages
 */
function generateSpendingInsights($conn, $user_id, $start_date, $end_date) {
    $insights = [];
    
    // Get top spending categories
    $sql = "SELECT c.name, SUM(t.amount) as total 
            FROM transactions t 
            JOIN categories c ON t.category_id = c.id 
            WHERE t.user_id = ? AND c.type = 'expense' 
            AND t.transaction_date BETWEEN ? AND ? 
            GROUP BY c.name 
            ORDER BY total DESC LIMIT 3";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $insights[] = "Your highest spending category is " . $row['name'] . " ($" . number_format($row['total'], 2) . ").";
    }
    
    // Check for frequent small purchases
    $sql = "SELECT COUNT(*) as count FROM transactions t 
            JOIN categories c ON t.category_id = c.id 
            WHERE t.user_id = ? AND c.type = 'expense' 
            AND t.amount < 10 AND t.transaction_date BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $small_purchases = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($small_purchases > 5) {
        $insights[] = "You made " . $small_purchases . " small purchases under ₹10 this month. These add up to ₹" . 
                     number_format($small_purchases * 5, 2) . " on average.";
    }
    
    // Compare to previous period
    $prev_start = date('Y-m-d', strtotime($start_date . ' -1 month'));
    $prev_end = date('Y-m-d', strtotime($end_date . ' -1 month'));
    
    $curr_expenses = getTotalExpense($conn, $user_id, $start_date, $end_date);
    $prev_expenses = getTotalExpense($conn, $user_id, $prev_start, $prev_end);
    
    if ($prev_expenses > 0) {
        $change = (($curr_expenses - $prev_expenses) / $prev_expenses) * 100;
        if ($change > 10) {
            $insights[] = "Your spending is up " . number_format(abs($change), 1) . "% compared to last month.";
        } elseif ($change < -10) {
            $insights[] = "Great job! Your spending is down " . number_format(abs($change), 1) . "% compared to last month.";
        }
    }
    
    // Check for budget overruns
    $sql = "SELECT c.name, b.amount as budget, SUM(t.amount) as spent
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            JOIN budgets b ON b.category_id = c.id AND b.user_id = t.user_id
            WHERE t.user_id = ? AND c.type = 'expense' 
            AND t.transaction_date BETWEEN ? AND ?
            AND b.period = 'monthly'
            GROUP BY c.name, b.amount
            HAVING SUM(t.amount) > b.amount
            ORDER BY (SUM(t.amount)/b.amount) DESC LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $overspend_percent = round(($row['spent'] / $row['budget'] - 1) * 100);
        $insights[] = "You've exceeded your " . $row['name'] . " budget by " . $overspend_percent . "%. Try to cut back in this area.";
    }
    
    // Check for savings opportunities
    $sql = "SELECT 
                c.name,
                COUNT(*) as frequency,
                SUM(t.amount) as total,
                CASE
                    WHEN c.name = 'Coffee' AND COUNT(*) >= 5 THEN 'coffee shops'
                    WHEN c.name = 'Dining Out' AND COUNT(*) >= 5 THEN 'eating out'
                    WHEN c.name = 'Entertainment' AND COUNT(*) >= 3 THEN 'entertainment'
                    ELSE NULL
                END as spending_pattern
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = ? AND c.type = 'expense'
            AND t.transaction_date BETWEEN ? AND ?
            AND (c.name = 'Coffee' OR c.name = 'Dining Out' OR c.name = 'Entertainment')
            GROUP BY c.name
            HAVING spending_pattern IS NOT NULL
            ORDER BY total DESC
            LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $insights[] = "You spent $" . number_format($row['total'], 2) . " on " . $row['spending_pattern'] . " (" . $row['frequency'] . " times). Consider cutting back to save money.";
    }
    
    // Store insights in database for future reference
    if (!empty($insights)) {
        $sql = "INSERT INTO insights (user_id, insight_text, insight_type, is_read) VALUES (?, ?, 'spending', 0)";
        $stmt = $conn->prepare($sql);
        
        foreach ($insights as $insight) {
            $stmt->bind_param("is", $user_id, $insight);
            $stmt->execute();
        }
    }
    
    return $insights;
}

/**
 * Get savings tips based on user's spending patterns
 * 
 * @param object $conn Database connection
 * @param int $user_id User ID
 * @return array Array of savings tips
 */
function getSavingsTips($conn, $user_id) {
    $tips = [];
    
    // Get 2 general student tips
    $sql = "SELECT tip_text FROM savings_tips 
            WHERE tip_type = 'student'
            ORDER BY RAND() LIMIT 2";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $tips[] = $row['tip_text'];
    }
    
    // Get personalized tip based on top spending category
    $sql = "SELECT c.name, SUM(t.amount) as total 
            FROM transactions t 
            JOIN categories c ON t.category_id = c.id 
            WHERE t.user_id = ? AND c.type = 'expense' 
            AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY c.name 
            ORDER BY total DESC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $top_category = $result->fetch_assoc()['name'];
        
        // Get category-specific tip if available
        $sql = "SELECT tip_text FROM savings_tips 
                WHERE category_id = (SELECT id FROM categories WHERE name = ? LIMIT 1)
                ORDER BY RAND() LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $top_category);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $tips[] = $result->fetch_assoc()['tip_text'];
        } else {
            // Common category-specific tips if none in database
            if (stripos($top_category, 'food') !== false || $top_category == 'Dining Out' || $top_category == 'Campus Food') {
                $tips[] = "Your top expense is " . $top_category . ". Try meal prepping to reduce dining out expenses.";
            } elseif (stripos($top_category, 'shopping') !== false || $top_category == 'Clothing') {
                $tips[] = $top_category . " is your biggest expense. Consider a 30-day waiting period for non-essential purchases.";
            } elseif ($top_category == 'Entertainment' || $top_category == 'Student Events') {
                $tips[] = "You spend a lot on " . $top_category . ". Look for student discounts or free campus events.";
            } elseif ($top_category == 'Textbooks' || $top_category == 'Course Materials') {
                $tips[] = "Textbooks are expensive. Check your library, buy used books, or look for digital versions to save money.";
            }
        }
    }
    
    // Add general tip
    $sql = "SELECT tip_text FROM savings_tips 
            WHERE tip_type = 'general'
            ORDER BY RAND() LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $tips[] = $result->fetch_assoc()['tip_text'];
    }
    
    // If we still don't have enough tips, add fallback tips
    $fallback_tips = [
        "Try the 24-hour rule: Wait 24 hours before making non-essential purchases over ₹20.",
        "Use campus resources like free gym facilities instead of paid alternatives.",
        "Look for student discounts on streaming services and software subscriptions.",
        "Consider getting a roommate to split housing costs if you live off-campus.",
        "Take advantage of student discounts for public transportation."
    ];
    
    while (count($tips) < 3) {
        $tips[] = $fallback_tips[array_rand($fallback_tips)];
    }
    
    // Return up to 3 unique tips
    $tips = array_unique($tips);
    return array_slice($tips, 0, 3);
}

/**
 * Process quick save for goals
 * 
 * @param object $conn Database connection
 * @param int $user_id User ID
 * @param int $goal_id Goal ID
 * @param float $amount Amount to save
 * @return bool Success status
 */
function processQuickSave($conn, $user_id, $goal_id, $amount) {
    if ($amount <= 0) {
        return false;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update goal progress
        $sql = "UPDATE goals SET current_amount = current_amount + ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dii", $amount, $goal_id, $user_id);
        $stmt->execute();
        
        if ($stmt->affected_rows == 0) {
            throw new Exception("Goal not found or does not belong to user");
        }
        
        // Add quick save record
        $sql = "INSERT INTO quick_saves (user_id, goal_id, amount, save_date) VALUES (?, ?, ?, CURDATE())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iid", $user_id, $goal_id, $amount);
        $stmt->execute();
        
        // Get goal info for transaction description
        $sql = "SELECT name FROM goals WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $goal_id);
        $stmt->execute();
        $goal_name = $stmt->get_result()->fetch_assoc()['name'];
        
        // Add corresponding transaction
        $sql = "INSERT INTO transactions (user_id, category_id, amount, description, transaction_date)
                VALUES (?, (SELECT id FROM categories WHERE name = 'Savings' AND (user_id = ? OR user_id IS NULL) LIMIT 1), 
                ?, ?, CURDATE())";
        $description = "Quick save to goal: " . $goal_name;
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iids", $user_id, $user_id, $amount, $description);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Quick save error: " . $e->getMessage());
        return false;
    }
}
?> 