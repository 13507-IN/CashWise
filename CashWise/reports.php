<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect("login.php");
}

// Get current user ID
$user_id = $_SESSION["user_id"];

// Set default date range (last 6 months)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-6 months'));

// If user submitted a filter form
if (isset($_POST['filter_date'])) {
    $start_date = $_POST['start_date'] ?? $start_date;
    $end_date = $_POST['end_date'] ?? $end_date;
}

// Get total income, expense and balance for the period
$total_income = getTotalIncome($conn, $user_id, $start_date, $end_date);
$total_expense = getTotalExpense($conn, $user_id, $start_date, $end_date);
$balance = $total_income - $total_expense;

// Get expense breakdown by category
$expense_by_category = getExpenseByCategory($conn, $user_id, $start_date, $end_date);
$expense_chart_data = [];
while ($row = $expense_by_category->fetch_assoc()) {
    $expense_chart_data[] = [
        'category' => $row['name'],
        'amount' => floatval($row['total'])
    ];
}
$expense_chart_json = json_encode($expense_chart_data);

// Get income breakdown by category
$sql = "SELECT c.name, SUM(t.amount) as total FROM transactions t 
        JOIN categories c ON t.category_id = c.id 
        WHERE t.user_id = ? AND c.type = 'income' 
        AND t.transaction_date BETWEEN ? AND ? 
        GROUP BY c.id ORDER BY total DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$income_by_category = $stmt->get_result();

$income_chart_data = [];
while ($row = $income_by_category->fetch_assoc()) {
    $income_chart_data[] = [
        'category' => $row['name'],
        'amount' => floatval($row['total'])
    ];
}
$income_chart_json = json_encode($income_chart_data);

// Get monthly income and expense for line chart
$sql = "SELECT 
            DATE_FORMAT(t.transaction_date, '%Y-%m') as month,
            DATE_FORMAT(t.transaction_date, '%b %Y') as month_name,
            SUM(CASE WHEN c.type = 'income' THEN t.amount ELSE 0 END) as income,
            SUM(CASE WHEN c.type = 'expense' THEN t.amount ELSE 0 END) as expense
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.user_id = ? AND t.transaction_date BETWEEN ? AND ?
        GROUP BY month, month_name
        ORDER BY month";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$monthly_data = $stmt->get_result();

$monthly_chart_data = [];
$labels = [];
$income_data = [];
$expense_data = [];
$savings_data = [];

while ($row = $monthly_data->fetch_assoc()) {
    $labels[] = $row['month_name'];
    $income_data[] = floatval($row['income']);
    $expense_data[] = floatval($row['expense']);
    $savings_data[] = floatval($row['income']) - floatval($row['expense']);
}

$monthly_chart_json = json_encode([
    'labels' => $labels,
    'income' => $income_data,
    'expense' => $expense_data,
    'savings' => $savings_data
]);

// Get top spending categories
$sql = "SELECT c.name, SUM(t.amount) as total FROM transactions t 
        JOIN categories c ON t.category_id = c.id 
        WHERE t.user_id = ? AND c.type = 'expense' 
        AND t.transaction_date BETWEEN ? AND ? 
        GROUP BY c.id ORDER BY total DESC LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$top_expenses = $stmt->get_result();

// Get average daily spending
$sql = "SELECT AVG(daily_total) as avg_daily_expense FROM (
            SELECT DATE(transaction_date) as day, SUM(amount) as daily_total 
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = ? AND c.type = 'expense' 
            AND t.transaction_date BETWEEN ? AND ?
            GROUP BY day
        ) as daily_expenses";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$avg_daily_expense = $stmt->get_result()->fetch_assoc()['avg_daily_expense'] ?? 0;

// Get spending trend (last 7 days compared to previous 7 days)
$last_7_days = date('Y-m-d', strtotime('-7 days'));
$previous_7_days = date('Y-m-d', strtotime('-14 days'));

$sql = "SELECT SUM(amount) as total FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.user_id = ? AND c.type = 'expense' 
        AND t.transaction_date BETWEEN ? AND CURRENT_DATE()";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $last_7_days);
$stmt->execute();
$last_week_expense = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$sql = "SELECT SUM(amount) as total FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.user_id = ? AND c.type = 'expense' 
        AND t.transaction_date BETWEEN ? AND ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $previous_7_days, $last_7_days);
$stmt->execute();
$previous_week_expense = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$expense_trend = 0;
if ($previous_week_expense > 0) {
    $expense_trend = (($last_week_expense - $previous_week_expense) / $previous_week_expense) * 100;
}

include 'includes/header.php';
?>

<div class="row mb-4">
    <!-- Filter Section -->
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <form id="dateFilterForm" method="POST" class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label for="start_date" class="form-label">From</label>
                        <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-auto">
                        <label for="end_date" class="form-label">To</label>
                        <input type="date" class="form-control" id="endDate" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-auto d-flex align-items-end">
                        <button type="submit" name="filter_date" class="btn btn-primary">Generate Reports</button>
                    </div>
                    <div class="col-auto ms-auto d-flex align-items-end">
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Print Reports
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-2" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf me-1"></i> Export PDF
                        </button>
                    </div>
                </form>
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
                <h3>₹<?php echo number_format($total_income, 2); ?></h3>
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
                <h3>₹<?php echo number_format($total_expense, 2); ?></h3>
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
                <h3>₹<?php echo number_format($balance, 2); ?></h3>
                <p>Net Savings</p>
            </div>
        </div>
    </div>
</div>

<!-- Income vs Expense Chart -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Income vs Expense Trend</h5>
                <div class="btn-group chart-view-options">
                    <button type="button" class="btn btn-sm btn-outline-secondary active" data-view="line">Line</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-view="bar">Bar</button>
                </div>
            </div>
            <div class="card-body chart-container">
                <div class="chart-background-pattern"></div>
                <?php if (count($labels) > 0): ?>
                    <div style="height:300px; max-height:300px; overflow:hidden;">
                        <canvas id="incomeExpenseChart" height="300" data-chart-data='<?php echo $monthly_chart_json; ?>'></canvas>
                    </div>
                    <div class="mt-1 text-center">
                        <small class="text-muted" style="font-size:10px;">The chart shows your income (blue), expenses (red), and savings (dashed blue).</small>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-4 text-center">
                            <h6 class="small" style="font-size:10px; margin-bottom:2px;">Average Monthly Income</h6>
                            <p class="fw-bold text-primary" style="font-size:12px; margin-bottom:0;">₹<?php echo number_format(array_sum($income_data) / count($income_data), 2); ?></p>
                        </div>
                        <div class="col-md-4 text-center">
                            <h6 class="small" style="font-size:10px; margin-bottom:2px;">Average Monthly Expenses</h6>
                            <p class="fw-bold text-danger" style="font-size:12px; margin-bottom:0;">₹<?php echo number_format(array_sum($expense_data) / count($expense_data), 2); ?></p>
                        </div>
                        <div class="col-md-4 text-center">
                            <h6 class="small" style="font-size:10px; margin-bottom:2px;">Average Monthly Savings</h6>
                            <p class="fw-bold <?php echo array_sum($savings_data) > 0 ? 'text-success' : 'text-danger'; ?>" style="font-size:12px; margin-bottom:0;">
                                ₹<?php echo number_format(array_sum($savings_data) / count($savings_data), 2); ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No data available for the selected period.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Expense Breakdown -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Expense Breakdown</h5>
            </div>
            <div class="card-body">
                <?php if (count($expense_chart_data) > 0): ?>
                    <canvas id="expenseChart" height="260" data-chart-data='<?php echo $expense_chart_json; ?>'></canvas>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No expense data available for the selected period.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Income Breakdown -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Income Breakdown</h5>
            </div>
            <div class="card-body">
                <?php if (count($income_chart_data) > 0): ?>
                    <canvas id="incomeChart" height="260" data-chart-data='<?php echo $income_chart_json; ?>'></canvas>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No income data available for the selected period.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Top Expenses -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Top Spending Categories</h5>
            </div>
            <div class="card-body">
                <?php if ($top_expenses->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">% of Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $top_expenses->fetch_assoc()): 
                                    $percentage = ($total_expense > 0) ? ($row['total'] / $total_expense) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td class="text-end">₹<?php echo number_format($row['total'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($percentage, 1); ?>%</td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No expense data available for the selected period.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Financial Insights -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Financial Insights</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-piggy-bank fa-2x text-primary"></i>
                            </div>
                            <div>
                                <h6>Savings Rate</h6>
                                <p class="fw-bold mb-0">
                                    <?php 
                                    $savings_rate = ($total_income > 0) ? ($balance / $total_income) * 100 : 0;
                                    echo number_format($savings_rate, 1) . '%'; 
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-calendar-day fa-2x text-primary"></i>
                            </div>
                            <div>
                                <h6>Avg. Daily Expense</h6>
                                <p class="fw-bold mb-0">₹<?php echo number_format($avg_daily_expense, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-chart-line fa-2x <?php echo $expense_trend <= 0 ? 'text-success' : 'text-danger'; ?>"></i>
                            </div>
                            <div>
                                <h6>Weekly Trend</h6>
                                <p class="fw-bold mb-0 <?php echo $expense_trend <= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php 
                                    echo ($expense_trend <= 0) ? '↓ ' : '↑ ';
                                    echo number_format(abs($expense_trend), 1) . '%'; 
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-balance-scale fa-2x text-primary"></i>
                            </div>
                            <div>
                                <h6>Income/Expense Ratio</h6>
                                <p class="fw-bold mb-0">
                                    <?php 
                                    $ratio = ($total_expense > 0) ? $total_income / $total_expense : 0;
                                    echo number_format($ratio, 2); 
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recommendations based on data -->
                <div class="alert <?php echo $savings_rate >= 20 ? 'alert-success' : 'alert-warning'; ?> mt-3">
                    <h6 class="alert-heading">
                        <i class="fas <?php echo $savings_rate >= 20 ? 'fa-thumbs-up' : 'fa-info-circle'; ?> me-2"></i>
                        <?php echo $savings_rate >= 20 ? 'Good Progress!' : 'Room for Improvement'; ?>
                    </h6>
                    <p class="mb-0 small">
                        <?php if ($savings_rate >= 20): ?>
                            You're saving <?php echo number_format($savings_rate, 1); ?>% of your income, which is excellent! Financial experts recommend saving at least 20% of your income.
                        <?php else: ?>
                            Try to increase your savings rate to at least 20%. Consider reviewing your expenses in top spending categories to find opportunities to save more.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Breakdown -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Monthly Breakdown</h5>
            </div>
            <div class="card-body">
                <?php if (count($labels) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th class="text-end">Income</th>
                                    <th class="text-end">Expenses</th>
                                    <th class="text-end">Savings</th>
                                    <th class="text-end">Savings Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 0; $i < count($labels); $i++): 
                                    $month_income = $income_data[$i];
                                    $month_expense = $expense_data[$i];
                                    $month_savings = $savings_data[$i];
                                    $month_savings_rate = ($month_income > 0) ? ($month_savings / $month_income) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><?php echo $labels[$i]; ?></td>
                                        <td class="text-end">₹<?php echo number_format($month_income, 2); ?></td>
                                        <td class="text-end">₹<?php echo number_format($month_expense, 2); ?></td>
                                        <td class="text-end <?php echo $month_savings >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            ₹<?php echo number_format($month_savings, 2); ?>
                                        </td>
                                        <td class="text-end <?php echo $month_savings_rate >= 20 ? 'text-success' : ($month_savings_rate < 0 ? 'text-danger' : 'text-warning'); ?>">
                                            <?php echo number_format($month_savings_rate, 1); ?>%
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No monthly data available for the selected period.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add jsPDF library for better PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Income vs Expense Chart
    const incomeExpenseChartEl = document.getElementById('incomeExpenseChart');
    let incomeExpenseChart;
    
    // Chart view toggle
    const chartViewButtons = document.querySelectorAll('.chart-view-options button');
    let currentChartType = 'line';
    
    if (incomeExpenseChartEl) {
        const chartData = JSON.parse(incomeExpenseChartEl.dataset.chartData || '{}');
        
        if (chartData.labels && chartData.labels.length > 0) {
            // Initial chart creation
            createIncomeExpenseChart(chartData, currentChartType);
            
            // Add event listeners for chart type toggle
            chartViewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    chartViewButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    const chartType = this.dataset.view;
                    if (chartType !== currentChartType) {
                        currentChartType = chartType;
                        // Destroy existing chart
                        if (incomeExpenseChart) {
                            incomeExpenseChart.destroy();
                        }
                        // Create new chart with selected type
                        createIncomeExpenseChart(chartData, chartType);
                    }
                });
            });
        }
    }
    
    // Function to create income/expense chart
    function createIncomeExpenseChart(chartData, chartType) {
        const datasets = [
            {
                label: 'Income',
                data: chartData.income,
                borderColor: 'rgba(76, 201, 240, 1)',
                backgroundColor: chartType === 'bar' ? 'rgba(76, 201, 240, 0.7)' : 'rgba(76, 201, 240, 0.1)',
                borderWidth: 2,
                fill: chartType === 'line',
                tension: 0.4,
                pointBackgroundColor: 'rgba(76, 201, 240, 1)',
                pointBorderColor: '#fff',
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                label: 'Expense',
                data: chartData.expense,
                borderColor: 'rgba(247, 37, 133, 1)',
                backgroundColor: chartType === 'bar' ? 'rgba(247, 37, 133, 0.7)' : 'rgba(247, 37, 133, 0.1)',
                borderWidth: 2,
                fill: chartType === 'line',
                tension: 0.4,
                pointBackgroundColor: 'rgba(247, 37, 133, 1)',
                pointBorderColor: '#fff',
                pointRadius: 4,
                pointHoverRadius: 6
            }
        ];
        
        // Add savings line only for line chart
        if (chartType === 'line') {
            datasets.push({
                label: 'Savings',
                data: chartData.savings,
                borderColor: 'rgba(58, 134, 255, 1)',
                backgroundColor: 'rgba(58, 134, 255, 0.05)',
                borderWidth: 2,
                borderDash: [4, 4],
                fill: false,
                tension: 0.4,
                pointBackgroundColor: 'rgba(58, 134, 255, 1)',
                pointBorderColor: '#fff',
                pointRadius: 4,
                pointHoverRadius: 6
            });
        }
        
        incomeExpenseChart = new Chart(incomeExpenseChartEl, {
            type: chartType,
            data: {
                labels: chartData.labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    tooltip: {
                        usePointStyle: true,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            size: 10,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 9
                        },
                        padding: 6,
                        cornerRadius: 4,
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw || 0;
                                return `${label}: ₹${value.toLocaleString()}`;
                            },
                            footer: function(tooltipItems) {
                                // Get income and expense values
                                let income, expense;
                                tooltipItems.forEach(item => {
                                    if (item.dataset.label === 'Income') income = item.raw;
                                    if (item.dataset.label === 'Expense') expense = item.raw;
                                });
                                
                                if (income !== undefined && expense !== undefined) {
                                    const savings = income - expense;
                                    const percentage = (income > 0) ? (savings / income * 100) : 0;
                                    return [
                                        `Savings: ₹${savings.toLocaleString()}`,
                                        `Savings Rate: ${percentage.toFixed(1)}%`
                                    ];
                                }
                                return '';
                            }
                        }
                    },
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 10,
                            font: {
                                size: 10
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        display: true,
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                size: 9
                            },
                            padding: 5,
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            },
                            maxTicksLimit: 6
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 8
                            },
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 6
                        }
                    }
                },
                layout: {
                    padding: 0
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuad'
                }
            }
        });
    }
    
    // Expense Pie Chart
    const expenseChartEl = document.getElementById('expenseChart');
    if (expenseChartEl) {
        const chartData = JSON.parse(expenseChartEl.dataset.chartData || '[]');
        
        if (chartData.length > 0) {
            const labels = chartData.map(item => item.category);
            const data = chartData.map(item => item.amount);
            const backgroundColors = generateColors(data.length);
            
            new Chart(expenseChartEl, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ₹${value.toLocaleString()} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
    }
    
    // Income Pie Chart
    const incomeChartEl = document.getElementById('incomeChart');
    if (incomeChartEl) {
        const chartData = JSON.parse(incomeChartEl.dataset.chartData || '[]');
        
        if (chartData.length > 0) {
            const labels = chartData.map(item => item.category);
            const data = chartData.map(item => item.amount);
            const backgroundColors = generateColors(data.length, true);
            
            new Chart(incomeChartEl, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ₹${value.toLocaleString()} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
    }
});

// Generate random colors for chart
function generateColors(count, isIncome = false) {
    const colors = isIncome ? [
        'rgba(40, 167, 69, 0.8)',
        'rgba(32, 201, 151, 0.8)',
        'rgba(23, 162, 184, 0.8)',
        'rgba(0, 123, 255, 0.8)',
        'rgba(111, 66, 193, 0.8)',
        'rgba(102, 16, 242, 0.8)',
        'rgba(111, 66, 193, 0.8)',
        'rgba(23, 162, 184, 0.8)'
    ] : [
        'rgba(220, 53, 69, 0.8)',
        'rgba(253, 126, 20, 0.8)',
        'rgba(255, 193, 7, 0.8)',
        'rgba(40, 167, 69, 0.8)',
        'rgba(23, 162, 184, 0.8)',
        'rgba(0, 123, 255, 0.8)',
        'rgba(111, 66, 193, 0.8)',
        'rgba(108, 117, 125, 0.8)'
    ];
    
    // If we need more colors than in our preset array, generate them
    if (count > colors.length) {
        for (let i = colors.length; i < count; i++) {
            const r = Math.floor(Math.random() * 255);
            const g = Math.floor(Math.random() * 255);
            const b = Math.floor(Math.random() * 255);
            colors.push(`rgba(${r}, ${g}, ${b}, 0.7)`);
        }
    }
    
    return colors.slice(0, count);
}

// Export to PDF function
function exportToPDF() {
    // If jsPDF is available, use it for better quality export
    if (typeof window.jspdf !== 'undefined') {
        exportToPDFAdvanced();
    } else {
        // Fallback to print method
        exportToPDFPrint();
    }
}

// Advanced PDF export using jsPDF
function exportToPDFAdvanced() {
    const { jsPDF } = window.jspdf;
    
    // Create PDF with landscape orientation
    const doc = new jsPDF({
        orientation: 'landscape',
        unit: 'mm',
        format: 'a4'
    });
    
    // Get the report container
    const reportContainer = document.querySelector('.container');
    
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
    doc.text('Financial Report', 15, 15);
    
    // Add date range
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    doc.setFontSize(12);
    doc.text(`Report Period: ${startDate} to ${endDate}`, 15, 22);
    
    // Use html2canvas to capture the report content
    html2canvas(reportContainer, {
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
        doc.save('financial-report.pdf');
        
        // Remove loading indicator
        document.body.removeChild(loadingIndicator);
    });
}

// Basic PDF export using print
function exportToPDFPrint() {
    // Prepare for printing
    document.body.classList.add('printing');
    
    // We need to wait for all charts to render properly
    setTimeout(() => {
        window.print();
        
        // Remove printing class after printing dialog is closed
        setTimeout(() => {
            document.body.classList.remove('printing');
        }, 1000);
    }, 500);
}
</script>

<style>
@media print {
    .no-print, .btn, .chart-view-options, button, .form-control, label, #dateFilterForm {
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
    
    .chart-container {
        max-height: none !important;
        overflow: visible !important;
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
        size: A4 landscape;
        margin: 10mm;
    }
    
    /* Ensure page breaks don't occur in the middle of content */
    .row {
        page-break-inside: avoid;
    }
}

body.printing .chart-container {
    max-height: none !important;
}

body.printing div[style*="height:300px"] {
    height: auto !important;
    max-height: none !important;
    overflow: visible !important;
}

/* Chart styles */
.chart-container {
    position: relative;
    padding: 0.25rem;
    max-height: 380px !important;
}

.chart-background-pattern {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    opacity: 0.02;
    background-image: linear-gradient(45deg, #000 25%, transparent 25%), 
                      linear-gradient(-45deg, #000 25%, transparent 25%), 
                      linear-gradient(45deg, transparent 75%, #000 75%), 
                      linear-gradient(-45deg, transparent 75%, #000 75%);
    background-size: 20px 20px;
    background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
    z-index: 0;
    border-radius: 0 0 0.25rem 0.25rem;
}

.chart-view-options {
    height: 24px;
}

.chart-view-options .btn {
    padding: 0.125rem 0.375rem;
    font-size: 0.7rem;
    line-height: 1;
}

.chart-view-options .btn.active {
    background-color: #6c757d;
    color: white;
}

canvas {
    position: relative;
    z-index: 1;
}
</style>

<?php include 'includes/footer.php'; ?> 