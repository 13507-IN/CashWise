<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect("login.php");
}

// Get current user ID
$user_id = $_SESSION["user_id"];

// Set default date range (current month)
$start_date = date('Y-m-01'); // First day of current month
$end_date = date('Y-m-t'); // Last day of current month

// If user submitted a filter form
if (isset($_POST['filter_date'])) {
    $start_date = $_POST['start_date'] ?? $start_date;
    $end_date = $_POST['end_date'] ?? $end_date;
}

// Get user preferences
$sql = "SELECT allowance_day FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$allowance_day = $user_data['allowance_day'] ?? 1;

// Calculate days until next allowance
$current_day = date('j');
$days_in_month = date('t');
$days_until_next_income = ($allowance_day > $current_day) 
    ? ($allowance_day - $current_day) 
    : ($days_in_month - $current_day + $allowance_day);

// Get total income, expense and balance
$total_income = getTotalIncome($conn, $user_id, $start_date, $end_date);
$total_expense = getTotalExpense($conn, $user_id, $start_date, $end_date);
$balance = $total_income - $total_expense;

// Calculate daily and weekly metrics
$days_remaining = (strtotime($end_date) - time()) / (60 * 60 * 24);
$days_remaining = max(1, round($days_remaining));
$daily_remaining = $balance / $days_remaining;

// Check if daily budget is exceeded
$today = date('Y-m-d');
$sql = "SELECT SUM(t.amount) as today_spent
        FROM transactions t 
        JOIN categories c ON t.category_id = c.id 
        WHERE t.user_id = ? AND c.type = 'expense' AND DATE(t.transaction_date) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result = $stmt->get_result();
$today_spent = $result->fetch_assoc()['today_spent'] ?? 0;
$is_budget_exceeded = ($today_spent > $daily_remaining && $daily_remaining > 0);

// Get week start/end dates
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$weekly_spending = getTotalExpense($conn, $user_id, $week_start, $week_end);

// Set weekly budget as 25% of monthly income by default
$weekly_budget = $total_income * 0.25;

// Get user's weekly budget if set
$sql = "SELECT amount FROM budgets WHERE user_id = ? AND period = 'weekly' AND start_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE()) LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$budget_result = $stmt->get_result();
if ($budget_result->num_rows > 0) {
    $weekly_budget = $budget_result->fetch_assoc()['amount'];
}

// Get recent transactions
$sql = "SELECT t.id, t.amount, t.description, t.transaction_date, c.name as category_name, c.type, c.icon, c.color
        FROM transactions t 
        JOIN categories c ON t.category_id = c.id 
        WHERE t.user_id = ? 
        ORDER BY t.transaction_date DESC LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_transactions = $stmt->get_result();

// Get expense breakdown by category for pie chart
$expense_by_category = getExpenseByCategory($conn, $user_id, $start_date, $end_date);
$chart_data = [];
while ($expense_by_category && $row = $expense_by_category->fetch_assoc()) {
    $chart_data[] = [
        'category' => $row['name'],
        'amount' => floatval($row['total']),
        'color' => $row['color'] ?? '#' . substr(md5($row['name']), 0, 6)
    ];
}
$chart_json = json_encode($chart_data);

// Get monthly income and expense for bar chart
$sql = "SELECT 
            MONTH(t.transaction_date) as month_num,
            MONTHNAME(t.transaction_date) as month,
            SUM(CASE WHEN c.type = 'income' THEN t.amount ELSE 0 END) as income,
            SUM(CASE WHEN c.type = 'expense' THEN t.amount ELSE 0 END) as expense
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.user_id = ? AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month_num, month
        ORDER BY month_num";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$monthly_data = $stmt->get_result();

$monthly_chart_data = [];
while ($monthly_data && $row = $monthly_data->fetch_assoc()) {
    $income = floatval($row['income']);
    $expense = floatval($row['expense']);
    $monthly_chart_data[] = [
        'month' => $row['month'],
        'income' => $income,
        'expense' => $expense
    ];
}
$monthly_chart_json = json_encode($monthly_chart_data);

// Get budget limits and progress
$sql = "SELECT b.id, b.amount as budget_amount, c.name as category_name, c.id as category_id, c.color, c.icon, b.alert_threshold
        FROM budgets b
        JOIN categories c ON b.category_id = c.id
        WHERE b.user_id = ? AND c.type = 'expense' AND b.period = 'monthly'
        ORDER BY b.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$budgets = $stmt->get_result();

// Get current month's goals
$sql = "SELECT * FROM goals 
        WHERE user_id = ? AND end_date >= CURDATE() 
        ORDER BY priority DESC, end_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$goals = $stmt->get_result();

// Generate spending insights
$spending_insights = generateSpendingInsights($conn, $user_id, $start_date, $end_date);

// Get savings tips
$savings_tips = getSavingsTips($conn, $user_id);

// Function to calculate budget spent with error handling
function getBudgetSpent($conn, $user_id, $category_id, $firstDayOfMonth, $lastDayOfMonth) {
    if (!$conn) {
        return 0;
    }
    
    try {
        $sql = "SELECT SUM(t.amount) as spent FROM transactions t
                JOIN categories c ON t.category_id = c.id
                WHERE t.user_id = ? AND t.category_id = ? AND c.type = 'expense'
                AND t.transaction_date BETWEEN ? AND ?";
                
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        
        $stmt->bind_param("iiss", $user_id, $category_id, $firstDayOfMonth, $lastDayOfMonth);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['spent'] ?? 0;
    } catch (Exception $e) {
        // Log the error
        error_log("Error calculating budget spent: " . $e->getMessage());
        return 0;
    }
}

// Get unread insights
$sql = "SELECT * FROM insights WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_insights = $stmt->get_result();

// Mark insights as read
if ($unread_insights->num_rows > 0) {
    $sql = "UPDATE insights SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

?>

<?php include 'includes/header.php'; ?>

<!-- Daily Budget Alert -->
<?php if ($is_budget_exceeded): ?>
<div class="container-fluid mb-4">
    <div class="alert alert-danger alert-dismissible fade show budget-alert" role="alert">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <i class="fas fa-exclamation-triangle fa-2x"></i>
            </div>
            <div>
                <h5 class="alert-heading">Daily Budget Exceeded!</h5>
                <p class="mb-0">You've spent ₹<?php echo number_format($today_spent, 2); ?> today, which is ₹<?php echo number_format($today_spent - $daily_remaining, 2); ?> over your daily budget.</p>
                <p class="mb-0 mt-1">Consider adjusting your spending for the rest of the month to stay on track.</p>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <form id="dateFilterForm" method="POST" class="row row-cols-lg-auto g-3 align-items-center">
                        <div class="col-12">
                            <label for="start_date" class="form-label">From</label>
                            <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-12">
                            <label for="end_date" class="form-label">To</label>
                            <input type="date" class="form-control" id="endDate" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-12 d-flex align-items-end">
                            <button type="submit" name="filter_date" class="btn btn-primary">Apply Filter</button>
                        </div>
                    </form>
                    <div>
                        <button onclick="exportToPDF()" class="btn btn-outline-secondary">
                            <i class="fas fa-file-pdf me-1"></i> Export Report
                        </button>
                        <button onclick="window.print()" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Student Budget Summary -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Student Budget Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Daily Budget -->
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-calendar-day me-2 text-primary"></i> Daily Budget
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted">Available to spend today:</span>
                                    <h3 class="daily-budget-value text-<?php echo $daily_remaining > 0 ? 'success' : 'danger'; ?>">₹<?php echo number_format($daily_remaining, 2); ?></h3>
                                </div>
                                
                                <?php
                                // Store the total spent today in a hidden input for JavaScript to use
                                ?>
                                <input type="hidden" id="today-spent-amount" value="<?php echo $today_spent; ?>">
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Days until allowance:</span>
                                    <h4><?php echo $days_until_next_income; ?> days</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <h6>This Week's Spending</h6>
                        <h3 class="<?php echo $weekly_spending < $weekly_budget ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format($weekly_spending, 2); ?>
                            <small class="text-muted"> / <?php echo number_format($weekly_budget, 2); ?></small>
                        </h3>
                        <div class="progress mt-2">
                            <div class="progress-bar <?php echo $weekly_spending < $weekly_budget ? 'bg-success' : 'bg-danger'; ?>" 
                                 style="width: <?php echo min(($weekly_spending/$weekly_budget)*100, 100); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary -->
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

<!-- Insights and Tips -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Spending Insights</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($spending_insights)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($spending_insights as $insight): ?>
                            <li class="list-group-item">
                                <i class="fas fa-lightbulb text-warning me-2"></i>
                                <?php echo $insight; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif ($unread_insights->num_rows > 0): ?>
                    <ul class="list-group list-group-flush">
                        <?php while ($insight = $unread_insights->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <i class="fas fa-lightbulb text-warning me-2"></i>
                                <?php echo $insight['insight_text']; ?>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Add more transactions to get personalized insights.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Savings Tips</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($savings_tips)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($savings_tips as $tip): ?>
                            <li class="list-group-item">
                                <i class="fas fa-piggy-bank text-success me-2"></i>
                                <?php echo $tip; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-hand-holding-usd fa-3x text-muted mb-3"></i>
                        <p class="text-muted">We're analyzing your spending patterns to generate savings tips.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Expense Breakdown Chart -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Expense Breakdown</h5>
            </div>
            <div class="card-body">
                <?php if (count($chart_data) > 0): ?>
                    <canvas id="expenseChart" height="300" data-chart-data='<?php echo $chart_json; ?>'></canvas>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No expense data available for the selected period.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Income vs Expense Chart -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Income vs Expense Trend</h5>
            </div>
            <div class="card-body">
                <?php if (count($monthly_chart_data) > 0): ?>
                    <div style="height:220px; max-height:220px; overflow:hidden;">
                        <canvas id="incomeExpenseChart" height="220" data-chart-data='<?php echo $monthly_chart_json; ?>'></canvas>
                    </div>
                    <div class="mt-1 text-center">
                        <small class="text-muted" style="font-size:10px;">Showing financial trends for the last 6 months. The dashed line represents your savings.</small>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No data available for the last 6 months.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Recent Transactions -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Transactions</h5>
                <a href="transactions.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="transaction-list">
                    <?php if ($recent_transactions->num_rows > 0): ?>
                        <?php while($row = $recent_transactions->fetch_assoc()): ?>
                            <div class="transaction-item">
                                <div class="d-flex align-items-center">
                                    <div class="transaction-icon me-3" style="background-color: <?php echo $row['color'] ?? '#4361ee'; ?>">
                                        <i class="fas <?php echo $row['icon'] ?? 'fa-receipt'; ?>"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($row['description']); ?></div>
                                        <div class="small text-muted">
                                            <span class="category-badge bg-light"><?php echo htmlspecialchars($row['category_name']); ?></span> • 
                                            <?php echo date('M d, Y', strtotime($row['transaction_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="<?php echo $row['type'] == 'income' ? 'income' : 'expense'; ?> fw-bold">
                                    <?php echo $row['type'] == 'income' ? '+' : '-'; ?><?php echo number_format($row['amount'], 2); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <p class="text-muted">No recent transactions.</p>
                            <a href="transactions.php?action=add" class="btn btn-primary">Add Transaction</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Budget Status -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Budget Status</h5>
                <a href="budgets.php" class="btn btn-sm btn-outline-primary">Manage Budgets</a>
            </div>
            <div class="card-body">
                <?php if ($budgets->num_rows > 0): ?>
                    <?php while($budget = $budgets->fetch_assoc()): 
                        // Get current spending for this category
                        $firstDayOfMonth = date('Y-m-01');
                        $lastDayOfMonth = date('Y-m-t');
                        
                        $spent = getBudgetSpent($conn, $user_id, $budget['category_id'], $firstDayOfMonth, $lastDayOfMonth);
                        
                        $percentage = min(($spent / $budget['budget_amount']) * 100, 100);
                        $alert_threshold = $budget['alert_threshold'] ?? 80;
                        $progress_class = $percentage >= $alert_threshold ? 'bg-danger' : 'bg-success';
                    ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-1">
                                <div class="d-flex align-items-center">
                                    <div class="budget-icon me-2" style="background-color: <?php echo $budget['color'] ?? '#4361ee'; ?>">
                                        <i class="fas <?php echo $budget['icon'] ?? 'fa-receipt'; ?>"></i>
                                    </div>
                                    <?php echo htmlspecialchars($budget['category_name']); ?>
                                </div>
                                <div><?php echo number_format($spent, 2); ?> / <?php echo number_format($budget['budget_amount'], 2); ?></div>
                            </div>
                            <div class="budget-progress" 
                                 data-spent="<?php echo $spent; ?>" 
                                 data-budget="<?php echo $budget['budget_amount']; ?>"
                                 data-category-id="<?php echo $budget['category_id']; ?>"
                                 data-category-name="<?php echo $budget['category_name']; ?>">
                                <div class="progress">
                                    <div class="progress-bar <?php echo $progress_class; ?>" role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%" 
                                         aria-valuenow="<?php echo $percentage; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                                <div class="progress-details">
                                    <small class="text-muted"><?php echo number_format($percentage, 0); ?>% spent</small>
                                    <small class="text-muted">
                                        <?php if ($spent > $budget['budget_amount']): ?>
                                            <span class="text-danger">Exceeded by <?php echo number_format($spent - $budget['budget_amount'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-success"><?php echo number_format($budget['budget_amount'] - $spent, 2); ?> left</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No budget limits set.</p>
                        <a href="budgets.php?action=add" class="btn btn-primary">Set Budget</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Savings Goals -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Savings Goals</h5>
                <a href="goals.php" class="btn btn-sm btn-outline-primary">View All Goals</a>
            </div>
            <div class="card-body">
                <?php if ($goals->num_rows > 0): ?>
                    <div class="row">
                        <?php while($goal = $goals->fetch_assoc()): 
                            $percentage = min(($goal['current_amount'] / $goal['target_amount']) * 100, 100);
                            $days_left = max(0, round((strtotime($goal['end_date']) - time()) / (60 * 60 * 24)));
                            
                            // Calculate if on track
                            $total_days = max(1, round((strtotime($goal['end_date']) - strtotime($goal['start_date'])) / (60 * 60 * 24)));
                            $days_passed = $total_days - $days_left;
                            $expected_progress = ($days_passed / $total_days) * 100;
                            $on_track = $percentage >= $expected_progress;
                            
                            // Priority colors
                            $priority_colors = [
                                'low' => 'bg-info',
                                'medium' => 'bg-primary',
                                'high' => 'bg-danger'
                            ];
                            $priority_color = $priority_colors[$goal['priority']] ?? 'bg-primary';
                        ?>
                            <div class="col-md-6 mb-4">
                                <div class="card border-0 shadow-sm goal-card" data-goal-id="<?php echo $goal['id']; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5><?php echo htmlspecialchars($goal['name']); ?></h5>
                                            <span class="badge <?php echo $priority_color; ?>"><?php echo ucfirst($goal['priority']); ?> Priority</span>
                                        </div>
                                        
                                        <div class="progress mb-3">
                                            <div class="progress-bar <?php echo $on_track ? 'bg-success' : 'bg-warning'; ?>" role="progressbar" 
                                                 style="width: <?php echo $percentage; ?>%" 
                                                 aria-valuenow="<?php echo $percentage; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100"></div>
                                            <?php if ($expected_progress > 0 && $expected_progress < 100): ?>
                                            <div class="progress-expected" style="left: <?php echo $expected_progress; ?>%"></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between mb-3">
                                            <div>
                                                <span class="text-muted">Progress: </span>
                                                <span class="fw-bold"><?php echo number_format($goal['current_amount'], 2); ?> / <?php echo number_format($goal['target_amount'], 2); ?></span>
                                            </div>
                                            <div>
                                                <span class="text-muted">Days left: </span>
                                                <span class="fw-bold"><?php echo $days_left; ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="goal-quick-save">
                                            <form class="quick-save-form">
                                                <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" name="amount" placeholder="Amount" min="1" step="1" required>
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-plus"></i> Quick Save
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No savings goals set.</p>
                        <a href="goals.php?action=add" class="btn btn-primary">Create Goal</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Alerts container for JavaScript notifications -->
<div class="alerts-container"></div>

<!-- Add Transaction Button (Fixed) -->
<a href="transactions.php?action=add" class="btn btn-primary btn-add">
    <i class="fas fa-plus"></i>
</a>

<!-- Add jsPDF library for better PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
// Export to PDF function
function exportToPDF() {
    // If jsPDF is available, use it for better quality export
    if (typeof window.jspdf !== 'undefined') {
        try {
            const { jsPDF } = window.jspdf;
            
            // Create PDF with portrait orientation
            const doc = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });
            
            // Get the dashboard container
            const dashboardContainer = document.querySelector('.container');
            
            // Show loading indicator
            const loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'loading-indicator';
            loadingIndicator.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><div class="mt-2">Generating PDF, please wait...</div>';
            loadingIndicator.style.position = 'fixed';
            loadingIndicator.style.top = '50%';
            loadingIndicator.style.left = '50%';
            loadingIndicator.style.transform = 'translate(-50%, -50%)';
            loadingIndicator.style.padding = '20px';
            loadingIndicator.style.backgroundColor = 'rgba(255, 255, 255, 0.9)';
            loadingIndicator.style.borderRadius = '5px';
            loadingIndicator.style.zIndex = '9999';
            loadingIndicator.style.textAlign = 'center';
            document.body.appendChild(loadingIndicator);
            
            // Add title
            doc.setFontSize(18);
            doc.text('Financial Dashboard', 15, 15);
            
            // Add date
            doc.setFontSize(12);
            doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 15, 22);
            
            // Use html2canvas to capture the dashboard content
            html2canvas(dashboardContainer, {
                scale: 1,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff'
            }).then(canvas => {
                // Convert the canvas to an image
                const imgData = canvas.toDataURL('image/png');
                
                // Calculate the width to maintain aspect ratio
                const imgProps = doc.getImageProperties(imgData);
                const pdfWidth = doc.internal.pageSize.getWidth() - 30;
                const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
                
                // Add the image to the PDF
                doc.addImage(imgData, 'PNG', 15, 30, pdfWidth, pdfHeight);
                
                // Save the PDF
                doc.save('financial-dashboard.pdf');
                
                // Remove loading indicator
                document.body.removeChild(loadingIndicator);
            });
        } catch (error) {
            console.error('Error generating PDF:', error);
            window.print();
        }
    } else {
        // Fallback to print method
        window.print();
    }
}

// Handle Quick Save functionality
function handleQuickSave(event, goalId) {
    event.preventDefault();
    
    const form = event.target;
    const amount = form.querySelector('input[name="amount"]').value;
    const submitButton = form.querySelector('button[type="submit"]');
    
    // Validate amount
    if (!amount || amount <= 0) {
        showAlert('Please enter a valid amount', 'danger');
        return;
    }
    
    // Disable the button to prevent multiple submissions
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    // Create form data
    const formData = new FormData();
    formData.append('goal_id', goalId);
    formData.append('amount', amount);
    formData.append('quick_save', 'true');
    formData.append('ajax', 'true');
    
    // Send AJAX request
    fetch('goals.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the goal progress UI
            const goalCard = document.querySelector(`.goal-card[data-goal-id="${goalId}"]`);
            if (goalCard) {
                // Update the progress bar
                const progressBar = goalCard.querySelector('.progress-bar');
                if (progressBar) {
                    const percentage = (data.newAmount / data.targetAmount) * 100;
                    progressBar.style.width = `${Math.min(100, percentage)}%`;
                    progressBar.setAttribute('aria-valuenow', percentage);
                }
                
                // Update the progress text
                const progressText = goalCard.querySelector('.d-flex.justify-content-between.mb-3 .fw-bold');
                if (progressText) {
                    progressText.textContent = `${parseFloat(data.newAmount).toFixed(2)} / ${parseFloat(data.targetAmount).toFixed(2)}`;
                }
            }
            
            // Check if goal was reached
            if (data.goalReached) {
                // Show goal reached alert
                showAlert(`
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-trophy text-warning fa-2x"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">Congratulations!</h5>
                            <p class="mb-0">You've reached your goal for "${data.goalName || 'Your goal'}"!</p>
                        </div>
                    </div>
                `, 'success');
            } else {
                // Show success message
                showAlert(`
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-check-circle text-success"></i>
                        </div>
                        <div>
                            <p class="mb-0">₹${amount} added to your goal successfully!</p>
                        </div>
                    </div>
                `, 'success');
            }
            
            // Reset the form
            form.reset();
        } else {
            // Show error message
            showAlert(data.message || 'Error saving amount', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred. Please try again.', 'danger');
    })
    .finally(() => {
        // Re-enable the button
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fas fa-plus"></i> Quick Save';
    });
}

// Show alert message
function showAlert(message, type = 'info') {
    const alertsContainer = document.querySelector('.alerts-container');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    alertsContainer.appendChild(alert);
    
    // Automatically dismiss after 5 seconds
    setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}

// Budget alert and monitoring functionality
document.addEventListener('DOMContentLoaded', function() {
    // Variables to track budget
    const dailyBudgetElement = document.querySelector('.daily-budget-value');
    const dailyBudget = dailyBudgetElement ? parseFloat(dailyBudgetElement.textContent.replace(/[^0-9.-]+/g, '')) : 0;
    
    // Check if we need to show a goal reached modal (from non-AJAX quick save)
    <?php if (isset($_SESSION['goal_reached'])): ?>
    showGoalReachedModal(
        '<?php echo htmlspecialchars($_SESSION['goal_reached']['goal_name']); ?>', 
        <?php echo intval($_SESSION['goal_reached']['goal_id']); ?>,
        <?php echo floatval($_SESSION['goal_reached']['amount']); ?>
    );
    <?php 
    // Clear the session flag
    unset($_SESSION['goal_reached']);
    endif; 
    ?>
    
    // Function to check transactions and update budget alerts
    function checkBudgetStatus() {
        // Get today's date in format YYYY-MM-DD
        const today = new Date().toISOString().split('T')[0];
        
        // Check if there's local storage data for today's spending
        const localData = localStorage.getItem(`budget_spent_${today}`);
        const todaySpent = localData ? parseFloat(localData) : 0;
        
        // If we're over budget and have a valid budget, show an alert
        if (todaySpent > dailyBudget && dailyBudget > 0) {
            const overBudgetAmount = todaySpent - dailyBudget;
            
            // Only show if we don't already have a budget alert
            if (!document.querySelector('.budget-alert')) {
                showAlert(`
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-exclamation-triangle text-danger"></i>
                        </div>
                        <div>
                            <strong>Daily Budget Exceeded!</strong><br>
                            You've spent ₹${todaySpent.toFixed(2)} today, which is ₹${overBudgetAmount.toFixed(2)} over your daily budget.
                        </div>
                    </div>
                `, 'danger budget-alert');
            }
        }
    }
    
    // Check budget status on page load
    checkBudgetStatus();
    
    // Set interval to periodically check budget status
    setInterval(checkBudgetStatus, 60000); // Check every minute
    
    // Initialize quick save forms
    const quickSaveForms = document.querySelectorAll('.quick-save-form');
    quickSaveForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const goalId = this.querySelector('input[name="goal_id"]').value;
            handleQuickSave(e, goalId);
        });
    });
});
</script>

<style>
@media print {
    .no-print, .btn, button, .form-control, label, #dateFilterForm, .btn-add {
        display: none !important;
    }
    
    body {
        padding: 10px;
        background-color: white !important;
    }
    
    .container {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
    }
    
    .card {
        break-inside: avoid;
        margin-bottom: 20px;
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
    
    canvas {
        max-width: 100%;
        height: auto !important;
        page-break-inside: avoid;
    }
    
    /* Fix for chart overflow */
    div[style*="height:300px"] {
        height: auto !important;
        max-height: none !important;
        overflow: visible !important;
    }
    
    .table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    
    .table td, .table th {
        background-color: white !important;
        border: 1px solid #ddd !important;
    }
    
    @page {
        size: portrait;
        margin: 10mm;
    }
    
    /* Ensure page breaks don't occur in the middle of content */
    .row {
        page-break-inside: avoid;
    }
}

/* Alerts container styling */
.alerts-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    width: 300px;
}

.alerts-container .alert {
    margin-bottom: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    transition: opacity 0.3s ease-in-out;
}

/* Budget alert styling */
.budget-alert {
    border-left: 5px solid #dc3545;
    background-color: rgba(255, 235, 235, 0.95);
}

.budget-alert .fa-exclamation-triangle {
    color: #dc3545;
}

.budget-alert .alert-heading {
    color: #dc3545;
    font-weight: 600;
}

/* Quick-save form styling */
.quick-save-form .input-group {
    transition: all 0.3s ease;
}

.quick-save-form .input-group:focus-within {
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

/* Goal Reached Modal Styling */
#goalReachedModal .modal-content {
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

#goalReachedModal .modal-header {
    border-bottom: none;
    padding: 1.5rem 1.5rem 0.75rem;
}

#goalReachedModal .modal-body {
    padding: 1.5rem;
}

#goalReachedModal .modal-footer {
    border-top: none;
    padding: 0.75rem 1.5rem 1.5rem;
}

#goalReachedModal .fa-check-circle {
    color: #28a745;
    animation: pulse 1.5s infinite;
}

#goalReachedModal .fa-trophy {
    color: #ffc107;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

@keyframes pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

/* Enhanced color scheme */
:root {
    --income-color: #2ecc71;
    --expense-color: #e74c3c;
    --balance-color: #3498db;
    --primary-color: #6a5acd;
    --secondary-color: #9b59b6;
    --accent-color: #f39c12;
    --light-bg: #f8f9fa;
    --dark-bg: #343a40;
}

/* Card styling enhancements */
.card {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    overflow: hidden;
}

.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.card-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border-bottom: none;
    padding: 1rem 1.25rem;
}

/* Financial summary cards */
.summary-card {
    position: relative;
    overflow: hidden;
}

.summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    z-index: 1;
}

.summary-card.income::before {
    background-color: var(--income-color);
}

.summary-card.expense::before {
    background-color: var(--expense-color);
}

.summary-card.balance::before {
    background-color: var(--balance-color);
}

.summary-card .icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 1rem;
}

.summary-card.income .icon {
    background-color: rgba(46, 204, 113, 0.15);
    color: var(--income-color);
}

.summary-card.expense .icon {
    background-color: rgba(231, 76, 60, 0.15);
    color: var(--expense-color);
}

.summary-card.balance .icon {
    background-color: rgba(52, 152, 219, 0.15);
    color: var(--balance-color);
}

.summary-card .icon i {
    font-size: 1.5rem;
}

/* Transaction list styling */
.transaction-list {
    border-radius: 8px;
    overflow: hidden;
}

.transaction-item {
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    transition: background-color 0.2s ease;
}

.transaction-item:hover {
    background-color: rgba(0,0,0,0.01);
}

.transaction-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    color: white;
}

/* Income/Expense text colors */
.income {
    color: var(--income-color);
    font-weight: 600;
}

.expense {
    color: var(--expense-color);
    font-weight: 600;
}

/* Progress bars and badges */
.progress {
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    border-radius: 4px;
}

.badge {
    padding: 0.5em 0.75em;
    border-radius: 50rem;
    font-weight: 500;
}

/* Button styling */
.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: darken(var(--primary-color), 10%);
    border-color: darken(var(--primary-color), 12%);
}

.btn-success {
    background-color: var(--income-color);
    border-color: var(--income-color);
}

.btn-success:hover {
    background-color: darken(var(--income-color), 10%);
    border-color: darken(var(--income-color), 12%);
}

/* Fixed add button with pulsing effect */
.btn-add {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    animation: pulse 2s infinite;
    z-index: 999;
}

.btn-add i {
    font-size: 1.5rem;
}
</style>

<!-- Load student-finance.js -->
<script src="assets/js/student-finance.js"></script>

<?php include 'includes/footer.php'; ?> 