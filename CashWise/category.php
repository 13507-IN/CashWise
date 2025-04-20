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
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle category actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add category
    if (isset($_POST['add_category'])) {
        $category_name = sanitizeInput($_POST['category_name']);
        $category_type = sanitizeInput($_POST['category_type']);
        $color = sanitizeInput($_POST['color'] ?? '#6c757d');
        $icon = sanitizeInput($_POST['icon'] ?? 'fa-folder');
        $image_path = null;
        
        // Handle image upload if provided
        if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($_FILES['category_image']['type'], $allowed_types)) {
                setFlashMessage("Only JPG, PNG and GIF images are allowed.", "danger");
            } elseif ($_FILES['category_image']['size'] > $max_size) {
                setFlashMessage("Image size should be less than 2MB.", "danger");
            } else {
                // Create uploads directory if it doesn't exist
                $upload_dir = 'uploads/categories/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $file_extension = pathinfo($_FILES['category_image']['name'], PATHINFO_EXTENSION);
                $filename = 'category_' . time() . '_' . uniqid() . '.' . $file_extension;
                $target_file = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['category_image']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                } else {
                    setFlashMessage("Failed to upload image.", "danger");
                }
            }
        }
        
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
                $sql = "INSERT INTO categories (user_id, name, type, color, icon, image_path) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isssss", $user_id, $category_name, $category_type, $color, $icon, $image_path);
                
                if ($stmt->execute()) {
                    setFlashMessage("Category added successfully!", "success");
                    redirect("category.php");
                } else {
                    setFlashMessage("Error adding category: " . $conn->error, "danger");
                }
            }
        } else {
            setFlashMessage("Please enter a valid category name and type.", "danger");
        }
    }
    
    // Edit category
    if (isset($_POST['edit_category'])) {
        $category_id = intval($_POST['category_id']);
        $category_name = sanitizeInput($_POST['category_name']);
        $category_type = sanitizeInput($_POST['category_type']);
        $color = sanitizeInput($_POST['color'] ?? '#6c757d');
        $icon = sanitizeInput($_POST['icon'] ?? 'fa-folder');
        
        // Get current category data
        $current_sql = "SELECT * FROM categories WHERE id = ? AND user_id = ?";
        $current_stmt = $conn->prepare($current_sql);
        $current_stmt->bind_param("ii", $category_id, $user_id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        $current_category = $current_result->fetch_assoc();
        
        $image_path = $current_category['image_path'] ?? null;
        
        // Handle image upload if provided
        if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($_FILES['category_image']['type'], $allowed_types)) {
                setFlashMessage("Only JPG, PNG and GIF images are allowed.", "danger");
            } elseif ($_FILES['category_image']['size'] > $max_size) {
                setFlashMessage("Image size should be less than 2MB.", "danger");
            } else {
                // Create uploads directory if it doesn't exist
                $upload_dir = 'uploads/categories/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $file_extension = pathinfo($_FILES['category_image']['name'], PATHINFO_EXTENSION);
                $filename = 'category_' . time() . '_' . uniqid() . '.' . $file_extension;
                $target_file = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['category_image']['tmp_name'], $target_file)) {
                    // Delete old image if exists
                    if (!empty($current_category['image_path']) && file_exists($current_category['image_path'])) {
                        @unlink($current_category['image_path']);
                    }
                    $image_path = $target_file;
                } else {
                    setFlashMessage("Failed to upload image.", "danger");
                }
            }
        }
        
        if (!empty($category_name) && in_array($category_type, ['income', 'expense'])) {
            // Check if category belongs to user
            $check_sql = "SELECT * FROM categories WHERE id = ? AND user_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $category_id, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows == 0) {
                setFlashMessage("You cannot edit system default categories.", "warning");
                redirect("category.php");
            }
            
            // Check if name already exists (excluding current category)
            $check_sql = "SELECT id FROM categories WHERE name = ? AND (user_id = ? OR user_id IS NULL) AND type = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("sisi", $category_name, $user_id, $category_type, $category_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                setFlashMessage("A category with this name already exists.", "warning");
            } else {
                // Update category
                $sql = "UPDATE categories SET name = ?, type = ?, color = ?, icon = ?, image_path = ? WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssii", $category_name, $category_type, $color, $icon, $image_path, $category_id, $user_id);
                
                if ($stmt->execute()) {
                    setFlashMessage("Category updated successfully!", "success");
                    redirect("category.php");
                } else {
                    setFlashMessage("Error updating category: " . $conn->error, "danger");
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
                // Check if category is used in any budgets
                $check_budget = "SELECT COUNT(*) as count FROM budgets WHERE category_id = ?";
                $budget_stmt = $conn->prepare($check_budget);
                $budget_stmt->bind_param("i", $category_id);
                $budget_stmt->execute();
                $budget_result = $budget_stmt->get_result();
                $budget_used = $budget_result->fetch_assoc()['count'] > 0;
                
                if ($budget_used) {
                    setFlashMessage("Cannot delete category as it is used in budgets.", "warning");
                } else {
                    // Delete category image if exists
                    if (!empty($category_data['image_path']) && file_exists($category_data['image_path'])) {
                        @unlink($category_data['image_path']);
                    }
                    
                    // Delete the category
                    $sql = "DELETE FROM categories WHERE id = ? AND user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $category_id, $user_id);
                    
                    if ($stmt->execute()) {
                        setFlashMessage("Category deleted successfully!", "success");
                    } else {
                        setFlashMessage("Error deleting category: " . $conn->error, "danger");
                    }
                }
            }
        }
        
        redirect("category.php");
    }
}

// Get category data for editing
$category_data = [];
if ($action == 'edit' && $category_id > 0) {
    $sql = "SELECT * FROM categories WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $category_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $category_data = $result->fetch_assoc();
    } else {
        setFlashMessage("Category not found or does not belong to you.", "danger");
        redirect("category.php");
    }
}

// Get all categories for the current user (both system defaults and user-created)
// First, get all system default categories (user_id IS NULL)
$sql_default = "SELECT * FROM categories WHERE user_id IS NULL ORDER BY type, name";
$default_stmt = $conn->prepare($sql_default);
$default_stmt->execute();
$default_categories = $default_stmt->get_result();

// Then, get user-created categories
$sql_user = "SELECT * FROM categories WHERE user_id = ? ORDER BY type, name";
$user_stmt = $conn->prepare($sql_user);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_categories = $user_stmt->get_result();

// Store categories in a more organized way for better handling
$income_categories = [];
$expense_categories = [];

// Process system default categories
if ($default_categories && $default_categories->num_rows > 0) {
    while ($category = $default_categories->fetch_assoc()) {
        if ($category['type'] == 'income') {
            $income_categories[$category['id']] = $category;
        } else {
            $expense_categories[$category['id']] = $category;
        }
    }
}

// Process user categories (overriding system defaults if same ID)
if ($user_categories && $user_categories->num_rows > 0) {
    while ($category = $user_categories->fetch_assoc()) {
        if ($category['type'] == 'income') {
            $income_categories[$category['id']] = $category;
        } else {
            $expense_categories[$category['id']] = $category;
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="border-bottom pb-2">Category Management</h2>
    </div>
</div>

<?php if ($action == 'add'): ?>
<!-- Add Category Form -->
<div class="row mb-4">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Add New Category</h5>
            </div>
            <div class="card-body">
                <form action="category.php" method="post" enctype="multipart/form-data">
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
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="color" class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color w-100" id="color" name="color" value="#6c757d">
                        </div>
                        <div class="col-md-4">
                            <label for="icon" class="form-label">Icon</label>
                            <div class="input-group">
                                <span class="input-group-text"><i id="iconPreview" class="fas fa-folder"></i></span>
                                <select class="form-select" id="icon" name="icon">
                                    <option value="fa-folder">Default</option>
                                    <option value="fa-money-bill-wave">Money</option>
                                    <option value="fa-gift">Gift</option>
                                    <option value="fa-chart-line">Investment</option>
                                    <option value="fa-laptop">Work</option>
                                    <option value="fa-wallet">Wallet</option>
                                    <option value="fa-utensils">Food</option>
                                    <option value="fa-home">Home</option>
                                    <option value="fa-bolt">Utilities</option>
                                    <option value="fa-film">Entertainment</option>
                                    <option value="fa-bus">Transportation</option>
                                    <option value="fa-heart">Healthcare</option>
                                    <option value="fa-shopping-bag">Shopping</option>
                                    <option value="fa-book">Education</option>
                                    <option value="fa-piggy-bank">Savings</option>
                                    <option value="fa-repeat">Subscriptions</option>
                                    <option value="fa-receipt">Receipt</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="category_image" class="form-label">Custom Image (Optional)</label>
                            <input type="file" class="form-control" id="category_image" name="category_image" accept="image/*">
                            <small class="text-muted">Max 2MB (JPG, PNG, GIF)</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                        <a href="category.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action == 'edit' && !empty($category_data)): ?>
<!-- Edit Category Form -->
<div class="row mb-4">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edit Category</h5>
            </div>
            <div class="card-body">
                <form action="category.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="category_id" value="<?php echo $category_data['id']; ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="category_name" class="form-label">Category Name*</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" value="<?php echo htmlspecialchars($category_data['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="category_type" class="form-label">Type*</label>
                            <select class="form-select" id="category_type" name="category_type" required>
                                <option value="">Select Type</option>
                                <option value="income" <?php echo ($category_data['type'] == 'income') ? 'selected' : ''; ?>>Income</option>
                                <option value="expense" <?php echo ($category_data['type'] == 'expense') ? 'selected' : ''; ?>>Expense</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="color" class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color w-100" id="color" name="color" value="<?php echo htmlspecialchars($category_data['color'] ?? '#6c757d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="icon" class="form-label">Icon</label>
                            <div class="input-group">
                                <span class="input-group-text"><i id="iconPreview" class="fas <?php echo htmlspecialchars($category_data['icon'] ?? 'fa-folder'); ?>"></i></span>
                                <select class="form-select" id="icon" name="icon">
                                    <option value="fa-folder" <?php echo ($category_data['icon'] == 'fa-folder') ? 'selected' : ''; ?>>Default</option>
                                    <option value="fa-money-bill-wave" <?php echo ($category_data['icon'] == 'fa-money-bill-wave') ? 'selected' : ''; ?>>Money</option>
                                    <option value="fa-gift" <?php echo ($category_data['icon'] == 'fa-gift') ? 'selected' : ''; ?>>Gift</option>
                                    <option value="fa-chart-line" <?php echo ($category_data['icon'] == 'fa-chart-line') ? 'selected' : ''; ?>>Investment</option>
                                    <option value="fa-laptop" <?php echo ($category_data['icon'] == 'fa-laptop') ? 'selected' : ''; ?>>Work</option>
                                    <option value="fa-wallet" <?php echo ($category_data['icon'] == 'fa-wallet') ? 'selected' : ''; ?>>Wallet</option>
                                    <option value="fa-utensils" <?php echo ($category_data['icon'] == 'fa-utensils') ? 'selected' : ''; ?>>Food</option>
                                    <option value="fa-home" <?php echo ($category_data['icon'] == 'fa-home') ? 'selected' : ''; ?>>Home</option>
                                    <option value="fa-bolt" <?php echo ($category_data['icon'] == 'fa-bolt') ? 'selected' : ''; ?>>Utilities</option>
                                    <option value="fa-film" <?php echo ($category_data['icon'] == 'fa-film') ? 'selected' : ''; ?>>Entertainment</option>
                                    <option value="fa-bus" <?php echo ($category_data['icon'] == 'fa-bus') ? 'selected' : ''; ?>>Transportation</option>
                                    <option value="fa-heart" <?php echo ($category_data['icon'] == 'fa-heart') ? 'selected' : ''; ?>>Healthcare</option>
                                    <option value="fa-shopping-bag" <?php echo ($category_data['icon'] == 'fa-shopping-bag') ? 'selected' : ''; ?>>Shopping</option>
                                    <option value="fa-book" <?php echo ($category_data['icon'] == 'fa-book') ? 'selected' : ''; ?>>Education</option>
                                    <option value="fa-piggy-bank" <?php echo ($category_data['icon'] == 'fa-piggy-bank') ? 'selected' : ''; ?>>Savings</option>
                                    <option value="fa-repeat" <?php echo ($category_data['icon'] == 'fa-repeat') ? 'selected' : ''; ?>>Subscriptions</option>
                                    <option value="fa-receipt" <?php echo ($category_data['icon'] == 'fa-receipt') ? 'selected' : ''; ?>>Receipt</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="category_image" class="form-label">Custom Image (Optional)</label>
                            <?php if (!empty($category_data['image_path']) && file_exists($category_data['image_path'])): ?>
                                <div class="mb-2">
                                    <img src="<?php echo htmlspecialchars($category_data['image_path']); ?>" alt="Category Image" class="img-thumbnail" style="max-height: 100px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="category_image" name="category_image" accept="image/*">
                            <small class="text-muted">Max 2MB (JPG, PNG, GIF)</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="submit" name="edit_category" class="btn btn-primary">Update Category</button>
                        <a href="category.php" class="btn btn-secondary">Cancel</a>
                        <button type="button" class="btn btn-danger float-end" data-bs-toggle="modal" data-bs-target="#deleteCategoryModal">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCategoryModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete the category "<?php echo htmlspecialchars($category_data['name']); ?>"? 
                This will only work if no transactions or budgets use this category.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="category.php" method="post">
                    <input type="hidden" name="category_id" value="<?php echo $category_data['id']; ?>">
                    <button type="submit" name="delete_category" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Manage Categories -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Manage Categories</h5>
                <a href="category.php?action=add" class="btn btn-sm btn-primary">
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
                                        <th>Icon/Image</th>
                                        <th>Color</th>
                                        <th>System Default</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expense_categories as $category): 
                                        $is_system_category = is_null($category['user_id']);
                                        $has_custom_image = !empty($category['image_path']) && file_exists($category['image_path']);
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                                            <td>
                                                <?php if ($has_custom_image): ?>
                                                    <img src="<?php echo htmlspecialchars($category['image_path']); ?>" alt="Category" class="img-thumbnail" style="max-height: 30px; max-width: 30px;">
                                                <?php else: ?>
                                                    <i class="fas <?php echo htmlspecialchars($category['icon'] ?? 'fa-folder'); ?>" style="color: <?php echo htmlspecialchars($category['color'] ?? '#6c757d'); ?>"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: <?php echo htmlspecialchars($category['color'] ?? '#6c757d'); ?>">
                                                    <?php echo htmlspecialchars($category['color'] ?? '#6c757d'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $is_system_category ? 'Yes' : 'No'; ?></td>
                                            <td>
                                                <?php if (!$is_system_category): ?>
                                                    <a href="category.php?action=edit&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">System default</span>
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
                                        <th>Icon/Image</th>
                                        <th>Color</th>
                                        <th>System Default</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($income_categories as $category): 
                                        $is_system_category = is_null($category['user_id']);
                                        $has_custom_image = !empty($category['image_path']) && file_exists($category['image_path']);
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                                            <td>
                                                <?php if ($has_custom_image): ?>
                                                    <img src="<?php echo htmlspecialchars($category['image_path']); ?>" alt="Category" class="img-thumbnail" style="max-height: 30px; max-width: 30px;">
                                                <?php else: ?>
                                                    <i class="fas <?php echo htmlspecialchars($category['icon'] ?? 'fa-folder'); ?>" style="color: <?php echo htmlspecialchars($category['color'] ?? '#6c757d'); ?>"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: <?php echo htmlspecialchars($category['color'] ?? '#6c757d'); ?>">
                                                    <?php echo htmlspecialchars($category['color'] ?? '#6c757d'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $is_system_category ? 'Yes' : 'No'; ?></td>
                                            <td>
                                                <?php if (!$is_system_category): ?>
                                                    <a href="category.php?action=edit&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">System default</span>
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
<?php endif; ?>

<!-- JavaScript for icon preview -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const iconSelect = document.getElementById('icon');
    const iconPreview = document.getElementById('iconPreview');
    
    if (iconSelect && iconPreview) {
        iconSelect.addEventListener('change', function() {
            iconPreview.className = 'fas ' + this.value;
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?> 