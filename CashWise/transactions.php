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
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle transaction actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Add transaction
    if (isset($_POST['add_transaction'])) {
        $amount = sanitizeInput($_POST['amount']);
        $category_id = isset($_POST['category']) ? intval($_POST['category']) : 0;
        $description = sanitizeInput($_POST['description']);
        $transaction_date = sanitizeInput($_POST['date']);
        
        if (!empty($amount) && $category_id > 0 && !empty($transaction_date)) {
            // Verify that user_id is valid
            if (!isset($_SESSION["user_id"]) || empty($_SESSION["user_id"])) {
                setFlashMessage("Session expired. Please log in again.", "danger");
                redirect("login.php");
                exit;
            }
            
            // Check if user_id exists in the users table
            $check_user = "SELECT id FROM users WHERE id = ?";
            $user_stmt = $conn->prepare($check_user);
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            if ($user_result->num_rows === 0) {
                setFlashMessage("Invalid user account. Please log in again.", "danger");
                // Force logout
                session_unset();
                session_destroy();
                redirect("login.php");
                exit;
            }
            
            // Also verify the category exists
            $check_category = "SELECT id FROM categories WHERE id = ?";
            $cat_stmt = $conn->prepare($check_category);
            $cat_stmt->bind_param("i", $category_id);
            $cat_stmt->execute();
            $cat_result = $cat_stmt->get_result();
            
            if ($cat_result->num_rows === 0) {
                setFlashMessage("Selected category does not exist.", "danger");
                redirect("transactions.php?action=add");
                exit;
            }
            
            // If validation passes, insert transaction
            $sql = "INSERT INTO transactions (user_id, category_id, amount, description, transaction_date) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iidss", $user_id, $category_id, $amount, $description, $transaction_date);
            
            if ($stmt->execute()) {
                setFlashMessage("Transaction added successfully!", "success");
                redirect("transactions.php");
            } else {
                // Log the error for debugging
                logError("Error adding transaction: " . $conn->error . " for user ID: " . $user_id);
                setFlashMessage("Error adding transaction: " . $conn->error, "danger");
            }
        } else {
            if (empty($amount)) {
                setFlashMessage("Please enter a valid amount.", "danger");
            } elseif ($category_id <= 0) {
                setFlashMessage("Please select a valid category.", "danger");
            } elseif (empty($transaction_date)) {
                setFlashMessage("Please select a valid date.", "danger");
            } else {
                setFlashMessage("Please fill in all required fields.", "danger");
            }
        }
    }
    
    // Edit transaction
    if (isset($_POST['edit_transaction'])) {
        $transaction_id = intval($_POST['transaction_id']);
        $amount = sanitizeInput($_POST['amount']);
        $category_id = isset($_POST['category']) ? intval($_POST['category']) : 0;
        $description = sanitizeInput($_POST['description']);
        $transaction_date = sanitizeInput($_POST['date']);
        
        if (!empty($amount) && $category_id > 0 && !empty($transaction_date)) {
            $sql = "UPDATE transactions SET amount = ?, category_id = ?, description = ?, transaction_date = ? 
                    WHERE id = ? AND user_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dissii", $amount, $category_id, $description, $transaction_date, $transaction_id, $user_id);
            
            if ($stmt->execute()) {
                setFlashMessage("Transaction updated successfully!", "success");
                redirect("transactions.php");
            } else {
                setFlashMessage("Error updating transaction: " . $conn->error, "danger");
            }
        } else {
            if (empty($amount)) {
                setFlashMessage("Please enter a valid amount.", "danger");
            } elseif ($category_id <= 0) {
                setFlashMessage("Please select a valid category.", "danger");
            } elseif (empty($transaction_date)) {
                setFlashMessage("Please select a valid date.", "danger");
            } else {
                setFlashMessage("Please fill in all required fields.", "danger");
            }
        }
    }
    
    // Delete transaction
    if (isset($_POST['delete_transaction'])) {
        $transaction_id = intval($_POST['transaction_id']);
        
        $sql = "DELETE FROM transactions WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $transaction_id, $user_id);
        
        if ($stmt->execute()) {
            setFlashMessage("Transaction deleted successfully!", "success");
            redirect("transactions.php");
        } else {
            setFlashMessage("Error deleting transaction: " . $conn->error, "danger");
        }
    }

    // Add category handling
    if (isset($_POST['add_category'])) {
        $category_name = sanitizeInput($_POST['category_name']);
        $category_type = sanitizeInput($_POST['category_type']);
        
        if (!empty($category_name) && in_array($category_type, ['income', 'expense'])) {
            // Check if category already exists
            $check_sql = "SELECT id FROM categories WHERE name = ? AND (user_id = ? OR user_id IS NULL) AND type = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("sis", $category_name, $user_id, $category_type);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                setFlashMessage("A category with this name already exists.", "warning");
            } else {
                // Insert new category
                $sql = "INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $user_id, $category_name, $category_type);
                
                if ($stmt->execute()) {
                    setFlashMessage("Category added successfully!", "success");
                    redirect("transactions.php" . ($action ? "?action=$action" : ""));
                } else {
                    setFlashMessage("Error adding category: " . $conn->error, "danger");
                }
            }
        } else {
            setFlashMessage("Please enter a valid category name and type.", "danger");
        }
    }

    // Delete category
    if (isset($_POST['delete_category'])) {
        $category_id = intval($_POST['category_id']);
        
        // Check if this is a user-created category (not system default)
        $check_sql = "SELECT * FROM categories WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $category_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $category_data = $check_result->fetch_assoc();
        
        if (!$category_data || $category_data['user_id'] === NULL) {
            setFlashMessage("You cannot delete system default categories.", "warning");
        } else {
            // Check if category is used in any transactions
            $check_usage = "SELECT COUNT(*) as count FROM transactions WHERE category_id = ?";
            $usage_stmt = $conn->prepare($check_usage);
            $usage_stmt->bind_param("i", $category_id);
            $usage_stmt->execute();
            $usage_result = $usage_stmt->get_result();
            $category_used = $usage_result->fetch_assoc()['count'] > 0;
            
            if ($category_used) {
                setFlashMessage("Cannot delete category as it is used in transactions.", "warning");
            } else {
                // Delete the category
                $sql = "DELETE FROM categories WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $category_id, $user_id);
                
                if ($stmt->execute()) {
                    setFlashMessage("Category deleted successfully!", "success");
                    
                    // No need to manually update arrays - they will be refreshed when the page reloads
                } else {
                    setFlashMessage("Error deleting category: " . $conn->error, "danger");
                }
            }
        }
        
        redirect("transactions.php?action=manage_categories");
    }
}

// Get transaction data for editing
$transaction_data = [];
if ($action == 'edit' && $transaction_id > 0) {
    $sql = "SELECT * FROM transactions WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $transaction_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $transaction_data = $result->fetch_assoc();
    } else {
        setFlashMessage("Transaction not found or does not belong to you.", "danger");
        redirect("transactions.php");
    }
}

// Get all categories for the current user (both system defaults and user-created)
// Get categories with a single query that properly handles both system default and user-created categories
$sql_categories = "SELECT * FROM categories WHERE user_id IS NULL OR user_id = ? ORDER BY type, name";
$categories_stmt = $conn->prepare($sql_categories);
$categories_stmt->bind_param("i", $user_id);
$categories_stmt->execute();
$categories_result = $categories_stmt->get_result();

// Store categories in a more organized way for better handling
$income_categories = [];
$expense_categories = [];

// Process categories, ensuring no duplicates
if ($categories_result && $categories_result->num_rows > 0) {
    // We'll use name as a unique key to prevent duplicates
    $processed_income_names = [];
    $processed_expense_names = [];
    
    while ($category = $categories_result->fetch_assoc()) {
        if ($category['type'] == 'income') {
            // Only add if we haven't seen this name before
            if (!in_array($category['name'], $processed_income_names)) {
                $income_categories[] = $category;
                $processed_income_names[] = $category['name'];
            }
        } else {
            // Only add if we haven't seen this name before
            if (!in_array($category['name'], $processed_expense_names)) {
                $expense_categories[] = $category;
                $processed_expense_names[] = $category['name'];
            }
        }
    }
}

// Set default date range (current month)
$start_date = date('Y-m-01'); // First day of current month
$end_date = date('Y-m-t'); // Last day of current month

// Get filter parameters
$filter_category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';

// If date filters are provided in the URL
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $_GET['start_date'] ?? $start_date;
    $end_date = $_GET['end_date'] ?? $end_date;
}
// For backward compatibility with POST form
elseif (isset($_POST['filter_date'])) {
    $start_date = $_POST['start_date'] ?? $start_date;
    $end_date = $_POST['end_date'] ?? $end_date;
}

// Get transactions with pagination
$limit = 10; // Records per page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Build query with filters
$sql = "SELECT t.id, t.amount, t.description, t.transaction_date, c.name as category_name, c.type 
        FROM transactions t 
        JOIN categories c ON t.category_id = c.id 
        WHERE t.user_id = ? AND t.transaction_date BETWEEN ? AND ?";

$params = [$user_id, $start_date, $end_date];
$types = "iss";

if ($filter_category > 0) {
    $sql .= " AND t.category_id = ?";
    $params[] = $filter_category;
    $types .= "i";
}

if ($filter_type != '' && in_array($filter_type, ['income', 'expense'])) {
    $sql .= " AND c.type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

$sql .= " ORDER BY t.transaction_date DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result();

// Count total records for pagination
$sql_count = "SELECT COUNT(*) as total FROM transactions t 
              JOIN categories c ON t.category_id = c.id 
              WHERE t.user_id = ? AND t.transaction_date BETWEEN ? AND ?";

$params_count = [$user_id, $start_date, $end_date];
$types_count = "iss";

if ($filter_category > 0) {
    $sql_count .= " AND t.category_id = ?";
    $params_count[] = $filter_category;
    $types_count .= "i";
}

if ($filter_type != '' && in_array($filter_type, ['income', 'expense'])) {
    $sql_count .= " AND c.type = ?";
    $params_count[] = $filter_type;
    $types_count .= "s";
}

$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param($types_count, ...$params_count);
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get total income and expense for the filtered period
$total_income = getTotalIncome($conn, $user_id, $start_date, $end_date);
$total_expense = getTotalExpense($conn, $user_id, $start_date, $end_date);
$balance = $total_income - $total_expense;

include 'includes/header.php';
?>

<div class="row mb-4">
    <!-- Filter Section -->
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <form id="filterForm" method="GET" action="transactions.php" class="row g-3">
                    <input type="hidden" name="page" value="1">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">From</label>
                        <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">To</label>
                        <input type="date" class="form-control" id="endDate" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <optgroup label="Income">
                                <?php foreach ($income_categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($filter_category == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Expense">
                                <?php foreach ($expense_categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($filter_category == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">All Types</option>
                            <option value="income" <?php echo ($filter_type == 'income') ? 'selected' : ''; ?>>Income</option>
                            <option value="expense" <?php echo ($filter_type == 'expense') ? 'selected' : ''; ?>>Expense</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card summary-card income">
            <div class="card-body">
                <div class="icon">
                    <i class="fas fa-arrow-circle-down"></i>
                </div>
                <h3><?php echo number_format($total_income, 2); ?></h3>
                <p>Total Income</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card summary-card expense">
            <div class="card-body">
                <div class="icon">
                    <i class="fas fa-arrow-circle-up"></i>
                </div>
                <h3><?php echo number_format($total_expense, 2); ?></h3>
                <p>Total Expenses</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card summary-card balance">
            <div class="card-body">
                <div class="icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <h3><?php echo number_format($balance, 2); ?></h3>
                <p>Balance</p>
            </div>
        </div>
    </div>
</div>

<?php if ($action == 'add'): ?>
<!-- Add Transaction Form -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Add New Transaction</h5>
            </div>
            <div class="card-body">
                <form action="transactions.php" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount*</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" required>
                        </div>
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category*</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <optgroup label="Income">
                                    <?php foreach ($income_categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Expense">
                                    <?php foreach ($expense_categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date" class="form-label">Date*</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description" placeholder="Enter a description">
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="submit" name="add_transaction" class="btn btn-primary">Add Transaction</button>
                        <a href="transactions.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php elseif ($action == 'edit' && !empty($transaction_data)): ?>
<!-- Edit Transaction Form -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edit Transaction</h5>
            </div>
            <div class="card-body">
                <form action="transactions.php" method="post">
                    <input type="hidden" name="transaction_id" value="<?php echo $transaction_data['id']; ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount*</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" value="<?php echo $transaction_data['amount']; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category*</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <optgroup label="Income">
                                    <?php foreach ($income_categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo (isset($transaction_data['category_id']) && $transaction_data['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Expense">
                                    <?php foreach ($expense_categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo (isset($transaction_data['category_id']) && $transaction_data['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date" class="form-label">Date*</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo $transaction_data['transaction_date']; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description" value="<?php echo htmlspecialchars($transaction_data['description']); ?>" placeholder="Enter a description">
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="submit" name="edit_transaction" class="btn btn-primary">Update Transaction</button>
                        <a href="transactions.php" class="btn btn-secondary">Cancel</a>
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
                Are you sure you want to delete this transaction? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="transactions.php" method="post">
                    <input type="hidden" name="transaction_id" value="<?php echo $transaction_data['id']; ?>">
                    <button type="submit" name="delete_transaction" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action == 'add_category'): ?>
<!-- Add Category Form -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Add New Category</h5>
            </div>
            <div class="card-body">
                <form action="transactions.php" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="category_name" class="form-label">Category Name*</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="category_type" class="form-label">Type*</label>
                            <select class="form-select" id="category_type" name="category_type" required>
                                <option value="">Select Type</option>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                        <a href="transactions.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action == 'manage_categories'): ?>
<!-- Manage Categories -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Remove Categories</h5>
                <a href="transactions.php?action=add_category" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i> Add Category
                </a>
            </div>
            <div class="card-body">
                <!-- Navigation tabs for category types -->
                <ul class="nav nav-tabs mb-3" id="categoryTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="expense-tab" data-bs-toggle="tab" data-bs-target="#expense-categories" type="button" role="tab" aria-controls="expense-categories" aria-selected="true">Expense Categories</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="income-tab" data-bs-toggle="tab" data-bs-target="#income-categories" type="button" role="tab" aria-controls="income-categories" aria-selected="false">Income Categories</button>
                    </li>
                </ul>
                
                <!-- Tab content -->
                <div class="tab-content" id="categoryTabContent">
                    <!-- Expense Categories Tab -->
                    <div class="tab-pane fade show active" id="expense-categories" role="tabpanel" aria-labelledby="expense-tab">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>System Default</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expense_categories as $category): 
                                        $is_system_category = is_null($category['user_id']);
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                                            <td><?php echo $is_system_category ? 'Yes' : 'No'; ?></td>
                                            <td>
                                                <?php if (!$is_system_category): ?>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteCategoryModal<?php echo $category['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    
                                                    <!-- Delete Category Modal -->
                                                    <div class="modal fade" id="deleteCategoryModal<?php echo $category['id']; ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Confirm Delete</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Are you sure you want to delete the category "<?php echo htmlspecialchars($category['name']); ?>"? 
                                                                    This will only work if no transactions use this category.
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <form action="transactions.php" method="post">
                                                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                                        <button type="submit" name="delete_category" class="btn btn-danger">Delete</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">System category</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Income Categories Tab -->
                    <div class="tab-pane fade" id="income-categories" role="tabpanel" aria-labelledby="income-tab">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>System Default</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($income_categories as $category): 
                                        $is_system_category = is_null($category['user_id']);
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                                            <td><?php echo $is_system_category ? 'Yes' : 'No'; ?></td>
                                            <td>
                                                <?php if (!$is_system_category): ?>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteCategoryModal<?php echo $category['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    
                                                    <!-- Delete Category Modal -->
                                                    <div class="modal fade" id="deleteCategoryModal<?php echo $category['id']; ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Confirm Delete</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Are you sure you want to delete the category "<?php echo htmlspecialchars($category['name']); ?>"? 
                                                                    This will only work if no transactions use this category.
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <form action="transactions.php" method="post">
                                                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                                        <button type="submit" name="delete_category" class="btn btn-danger">Delete</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">System category</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Transactions List -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Transactions</h5>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-purple export-pdf-btn action-btn" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-1"></i> Export PDF
                    </button>
                    <a href="transactions.php?action=add" class="btn btn-sm btn-primary action-btn">
                        <i class="fas fa-plus me-1"></i> Add Transaction
                    </a>
                    <a href="transactions.php?action=add_category" class="btn btn-sm btn-success action-btn ms-2">
                        <i class="fas fa-folder-plus me-1"></i> Add Category
                    </a>
                    <a href="transactions.php?action=manage_categories" class="btn btn-sm btn-danger action-btn ms-2">
                        <i class="fas fa-trash me-1"></i> Remove Category
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($transactions->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="transactionsTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $transactions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($row['transaction_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td class="<?php echo $row['type'] == 'income' ? 'income' : 'expense'; ?>">
                                            <?php echo $row['type'] == 'income' ? '+' : '-'; ?><?php echo number_format($row['amount'], 2); ?>
                                        </td>
                                        <td>
                                            <a href="transactions.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Transactions pagination">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&category=<?php echo $filter_category; ?>&type=<?php echo $filter_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" tabindex="-1">Previous</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo $filter_category; ?>&type=<?php echo $filter_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&category=<?php echo $filter_category; ?>&type=<?php echo $filter_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No transactions found for the selected period.</p>
                        <a href="transactions.php?action=add" class="btn btn-primary">Add Your First Transaction</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<style>
/* Export PDF Button Styling */
.export-pdf-btn {
    background: linear-gradient(135deg, #6a5acd, #483d8b);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    box-shadow: 0 3px 10px rgba(106, 90, 205, 0.3);
    transition: all 0.3s ease;
}

.export-pdf-btn:hover {
    background: linear-gradient(135deg, #483d8b, #322a60);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(106, 90, 205, 0.4);
}

.export-pdf-btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 5px rgba(106, 90, 205, 0.4);
}

.export-pdf-btn i {
    color: #fff;
    transition: transform 0.3s ease;
}

.export-pdf-btn:hover i {
    transform: translateY(-2px);
}

/* Fix for button jumping */
.action-buttons {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 120px;
    height: 38px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    border: none;
    font-weight: 500;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.action-btn:active {
    transform: translateY(0);
}

/* Ensure consistent button sizing on all screens */
@media (max-width: 768px) {
    .action-buttons {
        margin-top: 10px;
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
    }
    
    .action-btn, .export-pdf-btn {
        margin: 5px 0;
        width: 100%;
    }
    
    .ms-2 {
        margin-left: 0 !important;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style> 