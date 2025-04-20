<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect("login.php");
}

// Get current user ID
$user_id = $_SESSION["user_id"];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$budget_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle budget actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Add budget
    if (isset($_POST['add_budget'])) {
        $category_id = intval($_POST['category']);
        $amount = sanitizeInput($_POST['amount']);
        $period = sanitizeInput($_POST['period']);
        $start_date = sanitizeInput($_POST['start_date']);
        
        if (!empty($category_id) && !empty($amount) && !empty($period) && !empty($start_date)) {
            // Check if budget for this category and period already exists
            $sql = "SELECT id FROM budgets WHERE user_id = ? AND category_id = ? AND period = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $user_id, $category_id, $period);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing budget
                $existing_id = $result->fetch_assoc()['id'];
                $sql = "UPDATE budgets SET amount = ?, start_date = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("dsi", $amount, $start_date, $existing_id);
                
                if ($stmt->execute()) {
                    setFlashMessage("Budget updated successfully!", "success");
                    redirect("budgets.php");
                } else {
                    setFlashMessage("Error updating budget: " . $conn->error, "danger");
                }
            } else {
                // Insert new budget
                $sql = "INSERT INTO budgets (user_id, category_id, amount, period, start_date) 
                        VALUES (?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iidss", $user_id, $category_id, $amount, $period, $start_date);
                
                if ($stmt->execute()) {
                    setFlashMessage("Budget set successfully!", "success");
                    redirect("budgets.php");
                } else {
                    setFlashMessage("Error setting budget: " . $conn->error, "danger");
                }
            }
        } else {
            setFlashMessage("Please fill in all required fields.", "danger");
        }
    }
    
    // Edit budget
    if (isset($_POST['edit_budget'])) {
        $budget_id = intval($_POST['budget_id']);
        $amount = sanitizeInput($_POST['amount']);
        $start_date = sanitizeInput($_POST['start_date']);
        
        if (!empty($amount) && !empty($start_date)) {
            $sql = "UPDATE budgets SET amount = ?, start_date = ? WHERE id = ? AND user_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dsii", $amount, $start_date, $budget_id, $user_id);
            
            if ($stmt->execute()) {
                setFlashMessage("Budget updated successfully!", "success");
                redirect("budgets.php");
            } else {
                setFlashMessage("Error updating budget: " . $conn->error, "danger");
            }
        } else {
            setFlashMessage("Please fill in all required fields.", "danger");
        }
    }
    
    // Delete budget
    if (isset($_POST['delete_budget'])) {
        $budget_id = intval($_POST['budget_id']);
        
        $sql = "DELETE FROM budgets WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $budget_id, $user_id);
        
        if ($stmt->execute()) {
            setFlashMessage("Budget deleted successfully!", "success");
            redirect("budgets.php");
        } else {
            setFlashMessage("Error deleting budget: " . $conn->error, "danger");
        }
    }
}

// Get budget data for editing
$budget_data = [];
if ($action == 'edit' && $budget_id > 0) {
    $sql = "SELECT b.*, c.name as category_name, c.type as category_type 
            FROM budgets b
            JOIN categories c ON b.category_id = c.id
            WHERE b.id = ? AND b.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $budget_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $budget_data = $result->fetch_assoc();
    } else {
        setFlashMessage("Budget not found or does not belong to you.", "danger");
        redirect("budgets.php");
    }
}

// Get all expense categories for the user
$sql = "SELECT * FROM categories WHERE (user_id = ? OR user_id IS NULL) AND type = 'expense' ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories_result = $stmt->get_result();

// Process categories, ensuring no duplicates
$expense_categories = [];
$processed_expense_names = [];

if ($categories_result && $categories_result->num_rows > 0) {
    while ($category = $categories_result->fetch_assoc()) {
        // Only add if we haven't seen this name before
        if (!in_array($category['name'], $processed_expense_names)) {
            $expense_categories[] = $category;
            $processed_expense_names[] = $category['name'];
        }
    }
}

// Get all budgets for the user
$sql = "SELECT b.id, b.amount, b.period, b.start_date, c.name as category_name, c.id as category_id
        FROM budgets b
        JOIN categories c ON b.category_id = c.id
        WHERE b.user_id = ? ORDER BY c.name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$budgets = $stmt->get_result();

// Get spending data for each budget
$budget_spending = [];
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth = date('Y-m-t');

while ($budget = $budgets->fetch_assoc()) {
    $category_id = $budget['category_id'];
    
    // For monthly budget
    if ($budget['period'] == 'monthly') {
        $sql = "SELECT SUM(t.amount) as spent FROM transactions t 
                JOIN categories c ON t.category_id = c.id
                WHERE t.user_id = ? AND t.category_id = ? AND c.type = 'expense'
                AND t.transaction_date BETWEEN ? AND ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $user_id, $category_id, $firstDayOfMonth, $lastDayOfMonth);
        $stmt->execute();
        $result = $stmt->get_result();
        $spent = $result->fetch_assoc()['spent'] ?? 0;
        
        $budget_spending[$budget['id']] = [
            'spent' => $spent,
            'percentage' => $budget['amount'] > 0 ? min(($spent / $budget['amount']) * 100, 100) : 0
        ];
    }
    // For weekly budget
    else if ($budget['period'] == 'weekly') {
        // Calculate the start and end of the current week
        $startOfWeek = date('Y-m-d', strtotime('monday this week'));
        $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
        
        $sql = "SELECT SUM(t.amount) as spent FROM transactions t 
                JOIN categories c ON t.category_id = c.id
                WHERE t.user_id = ? AND t.category_id = ? AND c.type = 'expense'
                AND t.transaction_date BETWEEN ? AND ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $user_id, $category_id, $startOfWeek, $endOfWeek);
        $stmt->execute();
        $result = $stmt->get_result();
        $spent = $result->fetch_assoc()['spent'] ?? 0;
        
        $budget_spending[$budget['id']] = [
            'spent' => $spent,
            'percentage' => $budget['amount'] > 0 ? min(($spent / $budget['amount']) * 100, 100) : 0
        ];
    }
}

include 'includes/header.php';
?>

<?php if ($action == 'add'): ?>
<!-- Add Budget Form -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Set New Budget</h5>
            </div>
            <div class="card-body">
                <form action="budgets.php" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category*</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($expense_categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Budget Amount*</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" required>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="period" class="form-label">Period*</label>
                            <select class="form-select" id="period" name="period" required>
                                <option value="monthly">Monthly</option>
                                <option value="weekly">Weekly</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date*</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="submit" name="add_budget" class="btn btn-primary">Set Budget</button>
                        <a href="budgets.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php elseif ($action == 'edit' && !empty($budget_data)): ?>
<!-- Edit Budget Form -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edit Budget for <?php echo htmlspecialchars($budget_data['category_name']); ?></h5>
            </div>
            <div class="card-body">
                <form action="budgets.php" method="post">
                    <input type="hidden" name="budget_id" value="<?php echo $budget_data['id']; ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Budget Amount*</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" value="<?php echo $budget_data['amount']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="period" class="form-label">Period</label>
                            <input type="text" class="form-control" value="<?php echo ucfirst($budget_data['period']); ?>" readonly>
                            <input type="hidden" name="period" value="<?php echo $budget_data['period']; ?>">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date*</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $budget_data['start_date']; ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="submit" name="edit_budget" class="btn btn-primary">Update Budget</button>
                        <a href="budgets.php" class="btn btn-secondary">Cancel</a>
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
                Are you sure you want to delete this budget? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="budgets.php" method="post">
                    <input type="hidden" name="budget_id" value="<?php echo $budget_data['id']; ?>">
                    <button type="submit" name="delete_budget" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Budgets List -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Your Budget Limits</h5>
                <a href="budgets.php?action=add" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i> Add Budget
                </a>
            </div>
            <div class="card-body">
                <?php if ($budgets->num_rows > 0): ?>
                    <div class="budgets-list">
                        <div class="row">
                            <?php 
                            // Reset budgets result pointer
                            $budgets->data_seek(0);
                            while ($budget = $budgets->fetch_assoc()): 
                                $spent = $budget_spending[$budget['id']]['spent'] ?? 0;
                                $percentage = $budget_spending[$budget['id']]['percentage'] ?? 0;
                            ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($budget['category_name']); ?></h6>
                                            <span class="badge bg-primary"><?php echo ucfirst($budget['period']); ?></span>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between mb-2">
                                                <div>Budget Amount:</div>
                                                <div class="fw-bold">₹<?php echo number_format($budget['amount'], 2); ?></div>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <div>Spent So Far:</div>
                                                <div class="fw-bold">₹<?php echo number_format($spent, 2); ?></div>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <div>Remaining:</div>
                                                <div class="fw-bold <?php echo ($budget['amount'] - $spent) < 0 ? 'text-danger' : 'text-success'; ?>">
                                                    ₹<?php echo number_format($budget['amount'] - $spent, 2); ?>
                                                </div>
                                            </div>
                                            
                                            <div class="budget-progress mt-3" data-spent="<?php echo $spent; ?>" data-budget="<?php echo $budget['amount']; ?>">
                                                <div class="progress">
                                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <div class="progress-details mt-1">
                                                    <small class="text-muted"><?php echo number_format($percentage, 0); ?>% used</small>
                                                    <?php if ($spent > $budget['amount']): ?>
                                                        <small class="text-danger">Exceeded by ₹<?php echo number_format($spent - $budget['amount'], 2); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="text-center mt-3">
                                                <a href="budgets.php?action=edit&id=<?php echo $budget['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">You haven't set any budget limits yet.</p>
                        <a href="budgets.php?action=add" class="btn btn-primary">Set Your First Budget</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Budget Tips -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card bg-light">
            <div class="card-header">
                <h5 class="mb-0">Budgeting Tips</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <i class="fas fa-lightbulb fa-2x text-warning"></i>
                            </div>
                            <div>
                                <h6>50/30/20 Rule</h6>
                                <p class="small">Allocate 50% for needs, 30% for wants, and 20% for savings or debt repayment.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <i class="fas fa-piggy-bank fa-2x text-success"></i>
                            </div>
                            <div>
                                <h6>Pay Yourself First</h6>
                                <p class="small">Set aside savings before spending on discretionary items.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <i class="fas fa-chart-line fa-2x text-primary"></i>
                            </div>
                            <div>
                                <h6>Track Every Expense</h6>
                                <p class="small">Awareness of spending habits is the first step toward financial control.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?> 