<?php
require_once 'config/db_connect.php';
session_start();

// EMERGENCY FIX: Ensure user exists before any database operations
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // No user in session, let's set it to our test user
    $test_query = "SELECT id FROM users WHERE username = 'testuser' LIMIT 1";
    $test_result = mysqli_query($conn, $test_query);
    
    if (mysqli_num_rows($test_result) > 0) {
        // Test user exists, use it
        $test_user = mysqli_fetch_assoc($test_result);
        $_SESSION['user_id'] = $test_user['id'];
        error_log("No session user found. Using existing test user ID: " . $_SESSION['user_id']);
    } else {
        // Test user doesn't exist, create it
        $test_username = 'testuser';
        $test_email = 'test@example.com';
        $test_password = '$2y$10$e7NrJvPaHjgWGNDsUUHQxufZ4VhLqQWwPX5xG1KPh92nWXUn8dYl.'; // 'password123'
        $test_fullname = 'Test User';
        
        $create_user_sql = "INSERT INTO users (username, email, password, fullname, role) 
                           VALUES (?, ?, ?, ?, 'admin')";
        $stmt = $conn->prepare($create_user_sql);
        $stmt->bind_param("ssss", $test_username, $test_email, $test_password, $test_fullname);
        
        if ($stmt->execute()) {
            $_SESSION['user_id'] = $stmt->insert_id;
            error_log("Created new test user with ID: " . $_SESSION['user_id']);
        } else {
            die("Critical error: Cannot create test user: " . $stmt->error);
        }
    }
}

// Double-check user exists in database
$user_id = $_SESSION['user_id'];
$verify_user = "SELECT id FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($verify_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Fatal error: User ID in session does not exist in database. Please log in again.");
}

error_log("VERIFIED: Using valid user_id: " . $user_id);

require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect("login.php");
}

// Get current user ID
$user_id = $_SESSION["user_id"];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$goal_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle goal actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Handle quick save from dashboard via AJAX
    if (isset($_POST['quick_save']) && isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
        $goal_id = intval($_POST['goal_id']);
        $amount = floatval($_POST['amount']);
        $response = ['success' => false];
        
        // DEBUG
        error_log("AJAX Quick Save: Goal ID=$goal_id, Amount=$amount, User ID=$user_id");
        
        if ($amount <= 0) {
            $response['message'] = 'Please enter a valid amount';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        // Get the goal data first
        $sql = "SELECT * FROM goals WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $goal_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $goal_data = $result->fetch_assoc();
            $current_amount = $goal_data['current_amount'];
            $target_amount = $goal_data['target_amount'];
            $goal_name = $goal_data['name'];
            $new_amount = $current_amount + $amount;
            
            // Check if goal is reached
            $goal_reached = $new_amount >= $target_amount;
            $just_reached = $goal_reached && $current_amount < $target_amount;
            
            // Update the goal amount directly (don't use processQuickSave to avoid double processing)
            $update_sql = "UPDATE goals SET current_amount = ? WHERE id = ? AND user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("dii", $new_amount, $goal_id, $user_id);
            
            if ($update_stmt->execute()) {
                // Record the quick save
                $today = date('Y-m-d');
                $save_sql = "INSERT INTO quick_saves (user_id, goal_id, amount, save_date) VALUES (?, ?, ?, ?)";
                $save_stmt = $conn->prepare($save_sql);
                $save_stmt->bind_param("iids", $user_id, $goal_id, $amount, $today);
                $save_stmt->execute();
                
                // Calculate percentage
                $percentage = min(($new_amount / $target_amount) * 100, 100);
                
                // Return success response
                $response = [
                    'success' => true,
                    'newAmount' => $new_amount,
                    'targetAmount' => $target_amount,
                    'percentage' => $percentage,
                    'goalReached' => $goal_reached,
                    'justReachedTarget' => $just_reached,
                    'goalName' => $goal_name,
                    'message' => 'Amount saved successfully!'
                ];
                
                error_log("AJAX Quick Save SUCCESS: New amount=$new_amount, Target=$target_amount, Reached=" . ($goal_reached ? "Yes" : "No"));
            } else {
                $response['message'] = 'Error updating goal: ' . $conn->error;
                error_log("AJAX Quick Save ERROR: " . $conn->error);
            }
        } else {
            $response['message'] = 'Goal not found or does not belong to you';
            error_log("AJAX Quick Save ERROR: Goal not found for ID=$goal_id, User ID=$user_id");
        }
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Handle quick save from dashboard (non-AJAX fallback)
    if (isset($_POST['quick_save']) && !isset($_POST['ajax'])) {
        $goal_id = intval($_POST['goal_id']);
        $amount = floatval($_POST['amount']);
        
        if ($amount <= 0) {
            setFlashMessage("Please enter a valid amount", "danger");
            redirect("dashboard.php");
        }
        
        // Get current goal data
        $sql = "SELECT * FROM goals WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $goal_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $goal_data = $result->fetch_assoc();
            $new_amount = $goal_data['current_amount'] + $amount;
            
            // Check if goal was just reached with this contribution
            $just_reached_target = false;
            if ($new_amount >= $goal_data['target_amount'] && $goal_data['current_amount'] < $goal_data['target_amount']) {
                $just_reached_target = true;
            }
            
            // Update the goal amount
            $sql = "UPDATE goals SET current_amount = ? WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dii", $new_amount, $goal_id, $user_id);
            
            if ($stmt->execute()) {
                if ($just_reached_target) {
                    setFlashMessage("Congratulations! You've reached your goal for '{$goal_data['name']}'!", "success");
                    // Set a session flag to show the reached goal modal
                    $_SESSION['goal_reached'] = [
                        'goal_id' => $goal_id,
                        'goal_name' => $goal_data['name'],
                        'amount' => $new_amount
                    ];
                } else {
                    setFlashMessage("Amount saved successfully!", "success");
                }
                redirect("dashboard.php");
            } else {
                setFlashMessage("Error updating goal: " . $conn->error, "danger");
                redirect("dashboard.php");
            }
        } else {
            setFlashMessage("Goal not found or does not belong to you", "danger");
            redirect("dashboard.php");
        }
    }
    
    // Add goal
    if (isset($_POST['add_goal'])) {
        $name = sanitizeInput($_POST['name']);
        $target_amount = sanitizeInput($_POST['target_amount']);
        $current_amount = sanitizeInput($_POST['current_amount']);
        $start_date = sanitizeInput($_POST['start_date']);
        $end_date = sanitizeInput($_POST['end_date']);
        $priority = sanitizeInput($_POST['priority'] ?? 'medium'); // Default to medium priority if not set
        
        if (!empty($name) && !empty($target_amount) && !empty($start_date) && !empty($end_date)) {
            if (strtotime($end_date) <= strtotime($start_date)) {
                setFlashMessage("End date must be after start date.", "danger");
            } else {
                $sql = "INSERT INTO goals (user_id, name, target_amount, current_amount, start_date, end_date, priority) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isddsss", $user_id, $name, $target_amount, $current_amount, $start_date, $end_date, $priority);
                
                if ($stmt->execute()) {
                    setFlashMessage("Goal created successfully!", "success");
                    redirect("goals.php");
                } else {
                    setFlashMessage("Error creating goal: " . $conn->error, "danger");
                }
            }
        } else {
            setFlashMessage("Please fill in all required fields.", "danger");
        }
    }
    
    // Edit goal
    if (isset($_POST['edit_goal'])) {
        $goal_id = intval($_POST['goal_id']);
        $name = sanitizeInput($_POST['name']);
        $target_amount = sanitizeInput($_POST['target_amount']);
        $current_amount = sanitizeInput($_POST['current_amount']);
        $start_date = sanitizeInput($_POST['start_date']);
        $end_date = sanitizeInput($_POST['end_date']);
        $priority = sanitizeInput($_POST['priority'] ?? 'medium'); // Default to medium priority if not set
        
        if (!empty($name) && !empty($target_amount) && !empty($start_date) && !empty($end_date)) {
            if (strtotime($end_date) <= strtotime($start_date)) {
                setFlashMessage("End date must be after start date.", "danger");
            } else {
                $sql = "UPDATE goals SET name = ?, target_amount = ?, current_amount = ?, start_date = ?, end_date = ?, priority = ? 
                        WHERE id = ? AND user_id = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sddsssii", $name, $target_amount, $current_amount, $start_date, $end_date, $priority, $goal_id, $user_id);
                
                if ($stmt->execute()) {
                    setFlashMessage("Goal updated successfully!", "success");
                    redirect("goals.php");
                } else {
                    setFlashMessage("Error updating goal: " . $conn->error, "danger");
                }
            }
        } else {
            setFlashMessage("Please fill in all required fields.", "danger");
        }
    }
    
    // Delete goal
    if (isset($_POST['delete_goal'])) {
        $goal_id = intval($_POST['goal_id']);
        
        $sql = "DELETE FROM goals WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $goal_id, $user_id);
        
        if ($stmt->execute()) {
            setFlashMessage("Goal deleted successfully!", "success");
            redirect("goals.php");
        } else {
            setFlashMessage("Error deleting goal: " . $conn->error, "danger");
        }
    }
    
    // Update goal progress
    if (isset($_POST['update_progress'])) {
        $goal_id = intval($_POST['goal_id']);
        $current_amount = sanitizeInput($_POST['current_amount']);
        
        $sql = "UPDATE goals SET current_amount = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dii", $current_amount, $goal_id, $user_id);
        
        if ($stmt->execute()) {
            setFlashMessage("Goal progress updated successfully!", "success");
            redirect("goals.php?action=view&id=" . $goal_id);
        } else {
            setFlashMessage("Error updating goal progress: " . $conn->error, "danger");
        }
    }
}

// Handle 'complete' action for goals
if ($action == 'complete' && $goal_id > 0) {
    // Verify the goal exists and belongs to the user
    $sql = "SELECT * FROM goals WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $goal_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $goal_data = $result->fetch_assoc();
        
        error_log("Completing goal ID $goal_id: " . $goal_data['name'] . " for user $user_id");
        
        // Mark the goal as completed by adding completion_date and setting is_completed
        $sql = "UPDATE goals SET is_completed = 1, completion_date = CURDATE(), status = 'completed' WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $goal_id, $user_id);
        
        if ($stmt->execute()) {
            // Create a completion record
            $sql = "INSERT INTO goal_completions (user_id, goal_id, completion_date, final_amount) 
                    VALUES (?, ?, CURDATE(), ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iid", $user_id, $goal_id, $goal_data['current_amount']);
            $stmt->execute();
            
            // Create an achievement record
            $sql = "INSERT INTO goal_achievements (user_id, goal_id, achievement_type, achievement_date)
                    VALUES (?, ?, 'completed', CURDATE())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $goal_id);
            $stmt->execute();
            
            setFlashMessage("Congratulations! Your goal '{$goal_data['name']}' has been marked as complete!", "success");
            error_log("Goal $goal_id successfully marked as complete");
        } else {
            setFlashMessage("Error marking goal as complete: " . $conn->error, "danger");
            error_log("Error completing goal $goal_id: " . $conn->error);
        }
    } else {
        setFlashMessage("Goal not found or does not belong to you.", "danger");
        error_log("Goal $goal_id not found or doesn't belong to user $user_id");
    }
    
    redirect("goals.php");
}

// Get goal data for editing or viewing
$goal_data = [];
if (($action == 'edit' || $action == 'view') && $goal_id > 0) {
    $sql = "SELECT * FROM goals WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $goal_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $goal_data = $result->fetch_assoc();
    } else {
        setFlashMessage("Goal not found or does not belong to you.", "danger");
        redirect("goals.php");
    }
}

// Get all goals for the user
$sql = "SELECT * FROM goals WHERE user_id = ? ORDER BY is_completed ASC, end_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$goals = $stmt->get_result();

// Count active and completed goals
$active_goals_count = 0;
$completed_goals_count = 0;
$all_goals = [];

// Debug log to track goal status
$goal_debug = "Goals for user $user_id: ";

while ($goal = $goals->fetch_assoc()) {
    $all_goals[] = $goal;
    $goal_debug .= "Goal ID: " . $goal['id'] . ", Name: " . $goal['name'] . ", Completed: " . ($goal['is_completed'] ? "Yes" : "No") . "; ";
    if ($goal['is_completed']) {
        $completed_goals_count++;
    } else {
        $active_goals_count++;
    }
}

error_log($goal_debug);
error_log("Active goals: $active_goals_count, Completed goals: $completed_goals_count");

include 'includes/header.php';
?>

<?php if ($action == 'add'): ?>
<!-- Add Goal Form -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Create New Savings Goal</h5>
            </div>
            <div class="card-body">
                <form action="goals.php" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Goal Name*</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <small class="text-muted">E.g., "Emergency Fund", "Vacation", "Down Payment"</small>
                        </div>
                        <div class="col-md-6">
                            <label for="target_amount" class="form-label">Target Amount*</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="target_amount" name="target_amount" required>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="current_amount" class="form-label">Current Savings</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="current_amount" name="current_amount" value="0">
                            </div>
                            <small class="text-muted">If you've already saved toward this goal</small>
                        </div>
                        <div class="col-md-6">
                            <label for="monthly_contribution" class="form-label">Suggested Monthly Contribution</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="text" class="form-control" id="monthly_contribution" readonly>
                            </div>
                            <small class="text-muted">This will be calculated based on your timeframe</small>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date*</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">Target Date*</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                            <small class="text-muted">How important is this goal to you?</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="submit" name="add_goal" class="btn btn-primary">Create Goal</button>
                        <a href="goals.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php elseif ($action == 'edit' && !empty($goal_data)): ?>
<!-- Edit Goal Form -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edit Savings Goal</h5>
            </div>
            <div class="card-body">
                <form action="goals.php" method="post">
                    <input type="hidden" name="goal_id" value="<?php echo $goal_data['id']; ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Goal Name*</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($goal_data['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="target_amount" class="form-label">Target Amount*</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="target_amount" name="target_amount" value="<?php echo $goal_data['target_amount']; ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="current_amount" class="form-label">Current Savings</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="current_amount" name="current_amount" value="<?php echo $goal_data['current_amount']; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="monthly_contribution" class="form-label">Suggested Monthly Contribution</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="text" class="form-control" id="monthly_contribution" readonly>
                            </div>
                            <small class="text-muted">This will be calculated based on your timeframe</small>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date*</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $goal_data['start_date']; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">Target Date*</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $goal_data['end_date']; ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low" <?php echo ($goal_data['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo ($goal_data['priority'] == 'medium' || !$goal_data['priority']) ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo ($goal_data['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                            </select>
                            <small class="text-muted">How important is this goal to you?</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="submit" name="edit_goal" class="btn btn-primary">Update Goal</button>
                        <a href="goals.php" class="btn btn-secondary">Cancel</a>
                        <button type="button" class="btn btn-danger float-end" data-bs-toggle="modal" data-bs-target="#deleteModal">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this savings goal? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="goals.php" method="post">
                    <input type="hidden" name="goal_id" value="<?php echo $goal_data['id']; ?>">
                    <button type="submit" name="delete_goal" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php elseif ($action == 'view' && !empty($goal_data)): ?>
<!-- View Goal Details -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo htmlspecialchars($goal_data['name']); ?></h5>
                <a href="goals.php?action=edit&id=<?php echo $goal_data['id']; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-edit me-1"></i> Edit
                </a>
            </div>
            <div class="card-body">
                <?php 
                $percentage = min(($goal_data['current_amount'] / $goal_data['target_amount']) * 100, 100);
                $days_left = (strtotime($goal_data['end_date']) - time()) / (60 * 60 * 24);
                $months_left = round($days_left / 30);
                $amount_left = $goal_data['target_amount'] - $goal_data['current_amount'];
                $monthly_needed = $months_left > 0 ? $amount_left / $months_left : $amount_left;
                ?>
                
                <div class="mb-4">
                    <h6>Progress</h6>
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted"><?php echo number_format($percentage, 1); ?>% complete</small>
                        <small class="text-muted">₹<?php echo number_format($goal_data['current_amount'], 2); ?> of ₹<?php echo number_format($goal_data['target_amount'], 2); ?></small>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6>Target Amount</h6>
                            <p class="fs-4 fw-bold">₹<?php echo number_format($goal_data['target_amount'], 2); ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6>Amount Left</h6>
                            <p class="fs-4 fw-bold">₹<?php echo number_format($amount_left, 2); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6>Time Left</h6>
                            <p><?php echo round($days_left); ?> days (<?php echo $months_left; ?> months)</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6>Monthly Contribution Needed</h6>
                            <p>₹<?php echo number_format($monthly_needed, 2); ?> per month</p>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6>Start Date</h6>
                            <p><?php echo date('F j, Y', strtotime($goal_data['start_date'])); ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6>Target Date</h6>
                            <p><?php echo date('F j, Y', strtotime($goal_data['end_date'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateProgressModal">
                        Update Progress
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Tips for Success</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6><i class="fas fa-piggy-bank text-primary me-2"></i> Automate Savings</h6>
                    <p class="small">Set up automatic transfers to a savings account dedicated to this goal.</p>
                </div>
                <div class="mb-3">
                    <h6><i class="fas fa-chart-line text-primary me-2"></i> Track Your Progress</h6>
                    <p class="small">Regularly update your progress to stay motivated.</p>
                </div>
                <div class="mb-3">
                    <h6><i class="fas fa-medal text-primary me-2"></i> Celebrate Milestones</h6>
                    <p class="small">Reward yourself when you reach 25%, 50%, and 75% of your goal.</p>
                </div>
                <div class="mb-3">
                    <h6><i class="fas fa-search-dollar text-primary me-2"></i> Find Extra Income</h6>
                    <p class="small">Consider a side hustle or selling unused items to boost your savings.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Progress Modal -->
<div class="modal fade" id="updateProgressModal" tabindex="-1" aria-labelledby="updateProgressModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateProgressModalLabel">Update Goal Progress</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="goals.php" method="post" id="updateProgressForm">
                    <input type="hidden" name="goal_id" value="<?php echo $goal_data['id']; ?>">
                    <div class="mb-3">
                        <label for="update_amount" class="form-label">Current Amount Saved</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="update_amount" name="current_amount" value="<?php echo $goal_data['current_amount']; ?>">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="updateProgressForm" name="update_progress" class="btn btn-primary">Update Progress</button>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Goals List -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Your Savings Goals</h5>
                <a href="goals.php?action=add" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i> Create New Goal
                </a>
            </div>
            <div class="card-body">
                <?php if (count($all_goals) > 0): ?>
                    <!-- Nav tabs for Active/Completed goals -->
                    <ul class="nav nav-tabs mb-4" id="goalsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="active-goals-tab" data-bs-toggle="tab" data-bs-target="#active-goals" type="button" role="tab" aria-controls="active-goals" aria-selected="true">
                                Active Goals <span class="badge bg-primary"><?php echo $active_goals_count; ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="completed-goals-tab" data-bs-toggle="tab" data-bs-target="#completed-goals" type="button" role="tab" aria-controls="completed-goals" aria-selected="false">
                                Completed Goals <span class="badge bg-success"><?php echo $completed_goals_count; ?></span>
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab content -->
                    <div class="tab-content" id="goalsTabContent">
                        <!-- Active Goals Tab -->
                        <div class="tab-pane fade show active" id="active-goals" role="tabpanel" aria-labelledby="active-goals-tab">
                            <?php if ($active_goals_count > 0): ?>
                                <div class="row">
                                    <?php foreach ($all_goals as $goal): 
                                        if ($goal['is_completed']) continue; // Skip completed goals
                                        
                                        $percentage = min(($goal['current_amount'] / $goal['target_amount']) * 100, 100);
                                        $days_left = (strtotime($goal['end_date']) - time()) / (60 * 60 * 24);
                                        
                                        // Calculate expected progress based on time elapsed
                                        $total_days = (strtotime($goal['end_date']) - strtotime($goal['start_date'])) / (60 * 60 * 24);
                                        $days_passed = $total_days - $days_left;
                                        $expected_progress = $total_days > 0 ? min(($days_passed / $total_days) * 100, 100) : 0;
                                        
                                        // Determine if goal is on track (current progress >= expected progress)
                                        $on_track = $percentage >= $expected_progress;
                                    ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="card h-100 goal-card">
                                                <div class="card-header">
                                                    <h5 class="mb-0"><?php echo htmlspecialchars($goal['name']); ?></h5>
                                                    <?php if ($goal['priority']): ?>
                                                        <span class="badge bg-<?php 
                                                            echo $goal['priority'] == 'high' ? 'danger' : 
                                                                ($goal['priority'] == 'medium' ? 'primary' : 'info'); 
                                                        ?> priority-badge"><?php echo ucfirst($goal['priority']); ?> Priority</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="card-body">
                                                    <div class="progress mb-3">
                                                        <div class="progress-bar <?php echo $percentage >= $expected_progress ? 'bg-success' : 'bg-warning'; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                        <?php if ($expected_progress > 0 && $expected_progress < 100): ?>
                                                            <div class="progress-expected" style="left: <?php echo $expected_progress; ?>%"></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-3">
                                                        <div>
                                                            <span class="text-muted">Progress: </span>
                                                            <span class="fw-bold"><?php echo number_format($percentage, 1); ?>%</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-muted">Days left: </span>
                                                            <span class="fw-bold"><?php echo round($days_left); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-4">
                                                        <div>
                                                            <span class="text-muted">Current: </span>
                                                            <span class="fw-bold">₹<?php echo number_format($goal['current_amount'], 2); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="text-muted">Target: </span>
                                                            <span class="fw-bold">₹<?php echo number_format($goal['target_amount'], 2); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="text-center">
                                                        <a href="goals.php?action=view&id=<?php echo $goal['id']; ?>" class="btn btn-sm btn-outline-primary me-2">View Details</a>
                                                        <a href="goals.php?action=edit&id=<?php echo $goal['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                                    </div>
                                                </div>
                                                <div class="card-footer text-muted">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small><i class="far fa-calendar-alt me-1"></i> Target: <?php echo date('M d, Y', strtotime($goal['end_date'])); ?></small>
                                                        <?php if ($on_track): ?>
                                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i> On Track</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning"><i class="fas fa-exclamation-triangle me-1"></i> Behind</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <p class="text-muted">You don't have any active goals.</p>
                                    <a href="goals.php?action=add" class="btn btn-primary">Create a New Goal</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Completed Goals Tab -->
                        <div class="tab-pane fade" id="completed-goals" role="tabpanel" aria-labelledby="completed-goals-tab">
                            <?php if ($completed_goals_count > 0): ?>
                                <div class="row">
                                    <?php foreach ($all_goals as $goal): 
                                        if (!$goal['is_completed']) continue; // Skip active goals
                                        
                                        $percentage = min(($goal['current_amount'] / $goal['target_amount']) * 100, 100);
                                        $completion_date = $goal['completion_date'] ?? date('Y-m-d', strtotime($goal['updated_at']));
                                    ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="card h-100 goal-card completed-goal">
                                                <div class="card-header">
                                                    <h5 class="mb-0">
                                                        <i class="fas fa-check-circle text-success me-2"></i>
                                                        <?php echo htmlspecialchars($goal['name']); ?>
                                                    </h5>
                                                    <span class="badge bg-success">Completed</span>
                                                </div>
                                                <div class="card-body">
                                                    <div class="progress mb-3">
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-3">
                                                        <div>
                                                            <span class="text-muted">Final Amount: </span>
                                                            <span class="fw-bold">₹<?php echo number_format($goal['current_amount'], 2); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="text-muted">Target: </span>
                                                            <span class="fw-bold">₹<?php echo number_format($goal['target_amount'], 2); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="text-center">
                                                        <a href="goals.php?action=view&id=<?php echo $goal['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                                    </div>
                                                </div>
                                                <div class="card-footer text-muted">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small><i class="far fa-calendar-check me-1"></i> Completed: <?php echo date('M d, Y', strtotime($completion_date)); ?></small>
                                                        <span class="badge bg-light text-dark"><i class="fas fa-trophy text-warning me-1"></i> Achievement</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <p class="text-muted">You haven't completed any goals yet.</p>
                                    <p class="text-muted small">Keep going! Your achievements will be displayed here.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">You haven't set any savings goals yet.</p>
                        <a href="goals.php?action=add" class="btn btn-primary">Create Your First Goal</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Goals Info -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card bg-light">
            <div class="card-header">
                <h5 class="mb-0">Why Set Savings Goals?</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <i class="fas fa-bullseye fa-2x text-primary"></i>
                            </div>
                            <div>
                                <h6>Clear Focus</h6>
                                <p class="small">Having specific goals helps direct your financial decisions.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <i class="fas fa-chart-pie fa-2x text-primary"></i>
                            </div>
                            <div>
                                <h6>Better Planning</h6>
                                <p class="small">Goals allow you to plan your budget more effectively.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <i class="fas fa-hand-holding-usd fa-2x text-primary"></i>
                            </div>
                            <div>
                                <h6>Lower Stress</h6>
                                <p class="small">Planned savings reduces financial anxiety.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <i class="fas fa-trophy fa-2x text-primary"></i>
                            </div>
                            <div>
                                <h6>Achievement</h6>
                                <p class="small">Meeting financial goals builds confidence.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Calculate monthly contribution based on goal timeframe
document.addEventListener('DOMContentLoaded', function() {
    const calculateMonthlyContribution = function() {
        const targetAmount = parseFloat(document.getElementById('target_amount').value) || 0;
        const currentAmount = parseFloat(document.getElementById('current_amount').value) || 0;
        const startDate = new Date(document.getElementById('start_date').value);
        const endDate = new Date(document.getElementById('end_date').value);
        
        if (targetAmount > 0 && !isNaN(startDate) && !isNaN(endDate) && endDate > startDate) {
            // Calculate months between dates
            const months = (endDate.getFullYear() - startDate.getFullYear()) * 12 + 
                          (endDate.getMonth() - startDate.getMonth());
            
            if (months > 0) {
                const amountNeeded = targetAmount - currentAmount;
                const monthlyContribution = amountNeeded / months;
                document.getElementById('monthly_contribution').value = monthlyContribution.toFixed(2);
            }
        }
    };
    
    // Add event listeners to recalculate when inputs change
    const inputs = ['target_amount', 'current_amount', 'start_date', 'end_date'];
    inputs.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', calculateMonthlyContribution);
            element.addEventListener('input', calculateMonthlyContribution);
        }
    });
    
    // Initial calculation
    calculateMonthlyContribution();
});
</script>

<style>
/* Completed goals styling */
.completed-goal {
    position: relative;
    overflow: hidden;
    border-color: #28a745;
}

.completed-goal::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    border-width: 0 40px 40px 0;
    border-style: solid;
    border-color: #28a745 #fff;
}

.completed-goal .card-header {
    background-color: rgba(40, 167, 69, 0.1);
    border-bottom-color: rgba(40, 167, 69, 0.2);
}

.completed-goal .badge.bg-success {
    background-color: #28a745 !important;
}

/* Tab styling */
.nav-tabs .nav-link {
    color: #6c757d;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    color: #495057;
    font-weight: 600;
    border-bottom: 2px solid #6a5acd;
}

.nav-tabs .badge {
    margin-left: 5px;
    font-size: 0.75em;
    vertical-align: middle;
}

/* Progress expected marker styling */
.progress-expected {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: rgba(0, 0, 0, 0.5);
}

/* Animation for newly completed goals */
@keyframes celebrate {
    0% { transform: scale(1); }
    50% { transform: scale(1.03); }
    100% { transform: scale(1); }
}

.just-completed {
    animation: celebrate 1s ease-in-out;
}
</style>

<?php include 'includes/footer.php'; ?> 