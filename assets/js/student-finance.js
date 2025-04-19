/**
 * Student Finance Assistant JavaScript
 * Provides interactive features for the student finance dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    initCharts();
    
    // Check for budget alerts
    checkBudgetAlerts();
    
    // Initialize quick save forms
    initQuickSaveButtons();
    
    // Initialize daily budget calculator
    initDailyBudgetCalculator();
    
    // Track transactions for budget alerts
    const transactionForm = document.querySelector('form[action*="transactions.php"]');
    
    if (transactionForm) {
        transactionForm.addEventListener('submit', function(event) {
            // Get amount and transaction type
            const amountInput = this.querySelector('input[name="amount"]');
            const typeSelect = this.querySelector('select[name="type"]');
            const categorySelect = this.querySelector('select[name="category_id"]');
            
            if (amountInput && typeSelect) {
                const amount = parseFloat(amountInput.value);
                const type = typeSelect.value;
                const category = categorySelect ? categorySelect.options[categorySelect.selectedIndex].text : '';
                
                // Only track expenses for budget alerts
                if (type === 'expense' && !isNaN(amount)) {
                    // Get today's date
                    const today = new Date().toISOString().split('T')[0];
                    
                    // Get current spent amount from localStorage
                    let todaySpent = localStorage.getItem(`budget_spent_${today}`);
                    todaySpent = todaySpent ? parseFloat(todaySpent) : 0;
                    
                    // Add current transaction
                    todaySpent += amount;
                    
                    // Save back to localStorage
                    localStorage.setItem(`budget_spent_${today}`, todaySpent);
                    
                    // Check if daily budget is available on page
                    const dailyBudgetElement = document.querySelector('.daily-budget-value');
                    if (dailyBudgetElement) {
                        const dailyBudget = parseFloat(dailyBudgetElement.textContent.replace(/[^0-9.-]+/g, ''));
                        
                        // If we're over budget, show an alert after form submission
                        if (todaySpent > dailyBudget && dailyBudget > 0) {
                            // We'll let the submission continue, but store that we need to show an alert
                            localStorage.setItem('show_budget_alert', 'true');
                        }
                    }
                }
            }
        });
    }
    
    // Check if we need to show a budget alert (this runs after redirect from transaction add)
    const showBudgetAlert = localStorage.getItem('show_budget_alert');
    if (showBudgetAlert === 'true') {
        // Clear the flag
        localStorage.removeItem('show_budget_alert');
        
        // Get today's date
        const today = new Date().toISOString().split('T')[0];
        const todaySpent = localStorage.getItem(`budget_spent_${today}`);
        
        if (todaySpent) {
            // Get daily budget if available
            const dailyBudgetElement = document.querySelector('.daily-budget-value');
            if (dailyBudgetElement) {
                const dailyBudget = parseFloat(dailyBudgetElement.textContent.replace(/[^0-9.-]+/g, ''));
                const parsedTodaySpent = parseFloat(todaySpent);
                
                if (parsedTodaySpent > dailyBudget) {
                    const overBudgetAmount = parsedTodaySpent - dailyBudget;
                    
                    // Show alert if showAlert function exists
                    if (typeof showAlert === 'function') {
                        showAlert(`
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-exclamation-triangle text-danger"></i>
                                </div>
                                <div>
                                    <strong>Daily Budget Exceeded!</strong><br>
                                    You've spent ₹${parsedTodaySpent.toFixed(2)} today, which is ₹${overBudgetAmount.toFixed(2)} over your daily budget.
                                </div>
                            </div>
                        `, 'danger budget-alert');
                    }
                }
            }
        }
    }
    
    // Setup budget data sync with server
    function syncTransactionsWithBudget() {
        // Only run if we have the necessary elements
        const dailyBudgetElement = document.querySelector('.daily-budget-value');
        if (!dailyBudgetElement) return;
        
        // Get today's date
        const today = new Date().toISOString().split('T')[0];
        
        // If we don't have transaction data for today, fetch it from server
        if (!localStorage.getItem(`budget_spent_${today}`)) {
            // This would ideally call an API endpoint that returns today's transactions
            // For now, we'll use the PHP-calculated value if available
            const phpCalculatedValue = document.querySelector('#today-spent-amount');
            if (phpCalculatedValue) {
                const amount = parseFloat(phpCalculatedValue.value || '0');
                localStorage.setItem(`budget_spent_${today}`, amount);
            }
        }
    }
    
    // Run sync on page load
    syncTransactionsWithBudget();
    
    // Run the checkDailyBudgetAlert function on page load (which uses checkDailyBudget)
    checkDailyBudgetAlert();
    
    // Get the transaction form for transaction-form specifically
    const transactionFormSpecific = document.getElementById('transaction-form');
    
    // Track transactions when form is submitted
    if (transactionFormSpecific) {
        transactionFormSpecific.addEventListener('submit', function() {
            // Get form data
            const amount = parseFloat(document.querySelector('[name="amount"]').value);
            const type = document.querySelector('[name="type"]').value;
            const category = document.querySelector('[name="category_id"] option:checked').textContent;
            
            // Track the transaction
            trackTransaction({
                amount,
                type,
                category
            });
        });
    }
    
    // Reset daily spending at midnight
    const now = new Date();
    const tomorrow = new Date(now);
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(0, 0, 0, 0);
    
    const timeUntilMidnight = tomorrow - now;
    setTimeout(function() {
        localStorage.setItem('spentToday', '0');
        localStorage.removeItem('budgetAlertShown');
        // Set up the next day's reset
        setInterval(function() {
            localStorage.setItem('spentToday', '0');
            localStorage.removeItem('budgetAlertShown');
        }, 86400000); // 24 hours
    }, timeUntilMidnight);
});

/**
 * Initialize visualization charts
 */
function initCharts() {
    // Expense breakdown chart (pie/doughnut)
    const expenseChartElement = document.getElementById('expenseChart');
    if (expenseChartElement) {
        const chartData = JSON.parse(expenseChartElement.dataset.chartData || '[]');
        
        if (chartData.length > 0) {
            // Extract data for Chart.js
            const labels = chartData.map(item => item.category);
            const data = chartData.map(item => item.amount);
            
            // Enhanced color palette for expense categories
            const colorPalette = [
                '#e74c3c', // Red
                '#9b59b6', // Purple
                '#f39c12', // Orange
                '#16a085', // Teal
                '#3498db', // Blue
                '#2ecc71', // Green
                '#e67e22', // Dark Orange
                '#1abc9c', // Light Teal
                '#8e44ad', // Dark Purple
                '#d35400', // Dark Orange
                '#27ae60', // Dark Green
                '#2980b9', // Dark Blue
                '#c0392b', // Dark Red
                '#f1c40f'  // Yellow
            ];
            
            // Use custom colors or fallback to color palette
            const colors = chartData.map((item, index) => 
                item.color || colorPalette[index % colorPalette.length]
            );
            
            new Chart(expenseChartElement, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ₹${value.toFixed(2)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
    }
    
    // Income vs Expense chart (bar)
    const incomeExpenseChartElement = document.getElementById('incomeExpenseChart');
    if (incomeExpenseChartElement) {
        const chartData = JSON.parse(incomeExpenseChartElement.dataset.chartData || '[]');
        
        if (chartData.length > 0) {
            // Extract data for Chart.js
            const labels = chartData.map(item => item.month);
            const incomeData = chartData.map(item => item.income);
            const expenseData = chartData.map(item => item.expense);
            
            // Calculate savings for each month
            const savingsData = incomeData.map((income, index) => income - expenseData[index]);
            
            new Chart(incomeExpenseChartElement, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Income',
                            data: incomeData,
                            borderColor: 'rgba(46, 204, 113, 1)',
                            backgroundColor: 'rgba(46, 204, 113, 0.2)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Expense',
                            data: expenseData,
                            borderColor: 'rgba(231, 76, 60, 1)',
                            backgroundColor: 'rgba(231, 76, 60, 0.2)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Savings',
                            data: savingsData,
                            borderColor: 'rgba(52, 152, 219, 1)',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.4
                        }
                    ]
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
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.raw || 0;
                                    return `${label}: ₹${value.toFixed(2)}`;
                                },
                                footer: function(tooltipItems) {
                                    const income = tooltipItems[0].dataset.data[tooltipItems[0].dataIndex];
                                    const expense = tooltipItems[1].dataset.data[tooltipItems[1].dataIndex];
                                    const savings = income - expense;
                                    const percentage = (income > 0) ? (savings / income * 100) : 0;
                                    return `Savings Rate: ${percentage.toFixed(1)}%`;
                                }
                            }
                        },
                        legend: {
                            labels: {
                                usePointStyle: true,
                                boxWidth: 6
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value;
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    }
}

/**
 * Check budget thresholds and display alerts
 */
function checkBudgetAlerts() {
    const budgetProgress = document.querySelectorAll('.budget-progress');
    const alertsContainer = document.querySelector('.alerts-container');
    
    if (!alertsContainer) return;
    
    // Check category budget alerts
    budgetProgress.forEach(item => {
        const spent = parseFloat(item.dataset.spent || 0);
        const budget = parseFloat(item.dataset.budget || 1);
        const categoryId = item.dataset.categoryId;
        const categoryName = item.dataset.categoryName;
        
        const percentage = (spent / budget) * 100;
        
        if (percentage >= 90) {
            // Create critical alert
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show budget-alert';
            alertDiv.id = `budget-alert-${categoryId}`;
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Warning!</strong> You've used ${Math.round(percentage)}% of your 
                ${categoryName} budget this month.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            alertsContainer.appendChild(alertDiv);
            
            // Auto-dismiss after 10 seconds
            setTimeout(() => {
                const alert = bootstrap.Alert.getOrCreateInstance(alertDiv);
                if (alert) alert.close();
            }, 10000);
        } 
        else if (percentage >= 75 && percentage < 90) {
            // Create warning alert
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-warning alert-dismissible fade show budget-alert';
            alertDiv.id = `budget-alert-${categoryId}`;
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                You're approaching your ${categoryName} budget limit (${Math.round(percentage)}%).
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            alertsContainer.appendChild(alertDiv);
            
            // Auto-dismiss after 8 seconds
            setTimeout(() => {
                const alert = bootstrap.Alert.getOrCreateInstance(alertDiv);
                if (alert) alert.close();
            }, 8000);
        }
    });
    
    // Check daily budget alert
    checkDailyBudgetAlert();
}

/**
 * Check and display daily budget alert if exceeded
 */
function checkDailyBudgetAlert() {
    const dailyBudgetElement = document.querySelector('.daily-budget .amount');
    let budgetStatus;
    
    if (dailyBudgetElement) {
        // Get daily budget amount from UI
        const dailyBudget = parseFloat(dailyBudgetElement.textContent.replace(/[^0-9.-]+/g, ''));
        if (isNaN(dailyBudget) || dailyBudget <= 0) return;
        
        // Get today's date for localStorage key
        const today = getCurrentDate();
        
        // Get today's spending from localStorage
        const todaySpentStr = localStorage.getItem(`spending_${today}`);
        const todaySpent = todaySpentStr ? parseFloat(todaySpentStr) : 0;
        
        // Save values to localStorage for the checkDailyBudget function to use
        localStorage.setItem('dailyBudget', dailyBudget.toString());
        localStorage.setItem('spentToday', todaySpent.toString());
    }
    
    // Use the checkDailyBudget function to get budget status
    budgetStatus = checkDailyBudget();
    
    // If budget is exceeded, show alert
    if (budgetStatus.exceeded) {
        // Display alert
        showAlert(`
            <div class="d-flex align-items-start">
                <div class="me-3">
                    <i class="fas fa-exclamation-triangle text-danger fa-lg mt-1"></i>
                </div>
                <div>
                    <h5 class="alert-heading">Daily Budget Exceeded!</h5>
                    <p class="mb-0">You've spent ₹${budgetStatus.spentToday.toFixed(2)} today, which is:</p>
                    <ul class="mb-0 ps-3 mt-1">
                        <li>₹${Math.abs(budgetStatus.remaining).toFixed(2)} over your daily budget</li>
                        <li>${budgetStatus.percentage.toFixed(0)}% of your allocated daily amount</li>
                    </ul>
                    <p class="mt-2 mb-0"><small>Try to adjust your spending for the rest of the month to stay on track.</small></p>
                </div>
            </div>
        `, 'danger budget-alert');
        
        // Mark that we've shown the alert today
        localStorage.setItem('budgetAlertShown', getCurrentDate());
    }
}

/**
 * Initialize quick save functionality for goals
 */
function initQuickSaveButtons() {
    const quickSaveForms = document.querySelectorAll('.quick-save-form');
    
    quickSaveForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const goalId = this.dataset.goalId;
            if (goalId) {
                e.preventDefault();
                handleQuickSave(e, goalId);
            } else {
                const amountInput = form.querySelector('input[name="quick_save_amount"]');
                const amount = parseFloat(amountInput.value);
                
                if (!amount || amount <= 0) {
                    e.preventDefault();
                    showAlert(`
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-exclamation-circle text-danger"></i>
                            </div>
                            <div>
                                <p class="mb-0">Please enter a valid amount greater than zero</p>
                            </div>
                        </div>
                    `, 'alert-danger');
                }
            }
        });
    });
}

/**
 * Handle quick save submission via AJAX
 */
function handleQuickSave(event, goalId) {
    event.preventDefault();
    
    // Get form and amount
    const form = event.target;
    const amountInput = form.querySelector('input[name="amount"]');
    const amount = parseFloat(amountInput.value);
    
    // Validate amount
    if (isNaN(amount) || amount <= 0) {
        showAlert(`
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-exclamation-circle text-danger"></i>
                </div>
                <div>
                    <p class="mb-0">Please enter a valid amount greater than zero</p>
                </div>
            </div>
        `, 'alert-danger');
        return;
    }
    
    // Get submit button and show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
    
    // Prepare form data
    const formData = new FormData(form);
    formData.append('quick_save', 'true');
    formData.append('ajax', 'true');
    
    // Send AJAX request
    fetch('goals.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Reset form
        form.reset();
        
        if (data.success) {
            // Show success message
            showAlert(`
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-check-circle text-success"></i>
                    </div>
                    <div>
                        <p class="mb-0">Successfully added ₹${amount.toFixed(2)} to your goal!</p>
                    </div>
                </div>
            `, 'alert-success');
            
            // Update goal progress if needed
            if (data.currentAmount && data.targetAmount) {
                updateGoalProgress(goalId, data.currentAmount, data.targetAmount);
            }
        } else {
            // Show error message
            showAlert(`
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-exclamation-circle text-danger"></i>
                    </div>
                    <div>
                        <p class="mb-0">${data.message || 'Failed to save amount'}</p>
                    </div>
                </div>
            `, 'alert-danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert(`
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-exclamation-circle text-danger"></i>
                </div>
                <div>
                    <p class="mb-0">An error occurred while saving</p>
                </div>
            </div>
        `, 'alert-danger');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
    });
}

/**
 * Update goal progress after quick save
 */
function updateGoalProgress(goalId, currentAmount, targetAmount) {
    // Find goal card
    const goalCard = document.querySelector(`.goal-card[data-goal-id="${goalId}"]`);
    if (!goalCard) return;
    
    // Update progress bar
    const progressBar = goalCard.querySelector('.progress-bar');
    if (progressBar) {
        const percentage = (currentAmount / targetAmount) * 100;
        progressBar.style.width = `${Math.min(100, percentage)}%`;
        progressBar.setAttribute('aria-valuenow', percentage);
        
        // Update progress text if it exists
        const progressText = goalCard.querySelector('.progress-text');
        if (progressText) {
            progressText.textContent = `${percentage.toFixed(1)}%`;
        }
    }
    
    // Update amount display
    const amountDisplay = goalCard.querySelector('.goal-amount');
    if (amountDisplay) {
        amountDisplay.textContent = `₹${currentAmount.toFixed(2)} / ₹${targetAmount.toFixed(2)}`;
    }
    
    // Check if goal is complete
    if (currentAmount >= targetAmount) {
        goalCard.classList.add('goal-complete');
        const completeBadge = document.createElement('span');
        completeBadge.className = 'badge bg-success position-absolute top-0 end-0 mt-2 me-2';
        completeBadge.textContent = 'Completed!';
        
        // Only add badge if it doesn't exist yet
        if (!goalCard.querySelector('.badge.bg-success')) {
            goalCard.appendChild(completeBadge);
        }
    }
}

/**
 * Initialize the daily budget calculator
 */
function initDailyBudgetCalculator() {
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    
    if (startDateInput && endDateInput) {
        [startDateInput, endDateInput].forEach(input => {
            input.addEventListener('change', updateDailyBudget);
        });
    }
}

/**
 * Update daily budget based on date range
 */
function updateDailyBudget() {
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    
    if (!startDateInput || !endDateInput) return;
    
    const startDate = new Date(startDateInput.value);
    const endDate = new Date(endDateInput.value);
    
    if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) return;
    
    // Calculate days difference
    const diffTime = Math.abs(endDate - startDate);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    
    // Get balance value from the UI
    const balanceElement = document.querySelector('.summary-card.balance h3');
    if (!balanceElement) return;
    
    const balance = parseFloat(balanceElement.textContent.replace(/[^0-9.-]+/g, ''));
    const dailyBudget = balance / diffDays;
    
    // Update daily budget display
    const dailyBudgetElement = document.querySelector('.col-md-4:first-child h3');
    if (dailyBudgetElement) {
        dailyBudgetElement.textContent = dailyBudget.toFixed(2);
        dailyBudgetElement.className = dailyBudget > 0 ? 'text-success' : 'text-danger';
    }
}

/**
 * Generate a random color for charts
 */
function getRandomColor() {
    const letters = '0123456789ABCDEF';
    let color = '#';
    for (let i = 0; i < 6; i++) {
        color += letters[Math.floor(Math.random() * 16)];
    }
    return color;
}

/**
 * Show toast notification
 * @param {string} message - Message to display
 * @param {string} type - Bootstrap alert type (success, danger, warning, info)
 * @param {number} duration - Duration in milliseconds before auto-dismiss
 */
function showToast(message, type = 'info', duration = 3000) {
    // Find alerts container or create one if it doesn't exist
    let alertsContainer = document.querySelector('.alerts-container');
    
    if (!alertsContainer) {
        alertsContainer = document.createElement('div');
        alertsContainer.className = 'alerts-container';
        document.body.appendChild(alertsContainer);
    }
    
    // Create alert element
    const alertElement = document.createElement('div');
    alertElement.className = `alert alert-${type} alert-dismissible fade show`;
    alertElement.setAttribute('role', 'alert');
    
    // Add close button
    alertElement.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to container
    alertsContainer.appendChild(alertElement);
    
    // Initialize Bootstrap alert
    const bootstrapAlert = new bootstrap.Alert(alertElement);
    
    // Auto-dismiss after duration
    if (duration > 0) {
        setTimeout(() => {
            try {
                bootstrapAlert.close();
            } catch (e) {
                // Fallback if bootstrap alert fails
                alertElement.remove();
            }
        }, duration);
    }
    
    // Remove from DOM completely after animation
    alertElement.addEventListener('closed.bs.alert', function() {
        alertElement.remove();
    });
    
    return alertElement;
}

/**
 * Show alert message in alerts container
 * @param {string} html - HTML content for the alert
 * @param {string} classes - Additional CSS classes for styling
 * @param {boolean} autoDismiss - Whether to auto-dismiss the alert
 */
function showAlert(html, classes = '', autoDismiss = true) {
    // Find alerts container or create one if it doesn't exist
    let alertsContainer = document.querySelector('.alerts-container');
    
    if (!alertsContainer) {
        alertsContainer = document.createElement('div');
        alertsContainer.className = 'alerts-container';
        document.body.appendChild(alertsContainer);
    }
    
    // Create alert element
    const alertElement = document.createElement('div');
    alertElement.className = `alert ${classes} alert-dismissible fade show`;
    alertElement.setAttribute('role', 'alert');
    
    // Add content and close button
    alertElement.innerHTML = `
        ${html}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to container
    alertsContainer.appendChild(alertElement);
    
    // Initialize Bootstrap alert if available
    if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
        const bootstrapAlert = new bootstrap.Alert(alertElement);
        
        // Auto-dismiss after duration
        if (autoDismiss) {
            setTimeout(() => {
                try {
                    bootstrapAlert.close();
                } catch (e) {
                    // Fallback if bootstrap alert fails
                    alertElement.remove();
                }
            }, 5000); // 5 seconds
        }
        
        // Remove from DOM completely after animation
        alertElement.addEventListener('closed.bs.alert', function() {
            alertElement.remove();
        });
    }
    
    return alertElement;
}

/**
 * Export dashboard or transactions to PDF
 */
function exportToPDF() {
    if (typeof html2pdf !== 'undefined') {
        const element = document.querySelector('.dashboard-content');
        
        if (!element) {
            showToast('Nothing to export', 'warning');
            return;
        }
        
        // Show loading toast
        showToast('Generating PDF...', 'info');
        
        // Set options for PDF
        const opt = {
            margin: 10,
            filename: `student_budget_${getCurrentDate()}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        // Generate PDF
        html2pdf().set(opt).from(element).save().catch(err => {
            console.error('PDF generation error:', err);
        });
    } else {
        showToast('PDF generation library not loaded', 'warning');
    }
}

/**
 * Get current date in YYYY-MM-DD format
 */
function getCurrentDate() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

/**
 * Check if daily budget is exceeded and return status
 * @returns {Object} Budget status with exceeded flag and amount details
 */
function checkDailyBudget() {
    try {
        // Get current values from localStorage
        const dailyBudget = parseFloat(localStorage.getItem('dailyBudget') || '0');
        const spentToday = parseFloat(localStorage.getItem('spentToday') || '0');
        
        return {
            dailyBudget: dailyBudget,
            spentToday: spentToday,
            exceeded: dailyBudget > 0 && spentToday > dailyBudget,
            remaining: dailyBudget - spentToday,
            percentage: dailyBudget > 0 ? Math.min(100, (spentToday / dailyBudget) * 100) : 0
        };
    } catch (error) {
        console.error('Error checking daily budget:', error);
        return {
            dailyBudget: 0,
            spentToday: 0,
            exceeded: false,
            remaining: 0,
            percentage: 0
        };
    }
}

/**
 * Track transaction and update daily budget in localStorage
 * @param {Object} transactionData - Transaction data object
 */
function trackTransaction(transactionData) {
    try {
        // Get current values from localStorage
        const dailyBudget = parseFloat(localStorage.getItem('dailyBudget') || '0');
        let spentToday = parseFloat(localStorage.getItem('spentToday') || '0');
        
        // Only track expenses for daily budget
        if (transactionData.type === 'expense') {
            // Add amount to today's spending
            const amount = parseFloat(transactionData.amount);
            spentToday += amount;
            
            // Update localStorage
            localStorage.setItem('spentToday', spentToday.toString());
            
            // Check if we need to show budget alert
            if (dailyBudget > 0 && spentToday > dailyBudget && 
                localStorage.getItem('budgetAlertShown') !== getCurrentDate()) {
                checkDailyBudgetAlert();
            }
        }
        
        // Add transaction to history in localStorage
        const transactions = JSON.parse(localStorage.getItem('transactions') || '[]');
        transactions.push({
            ...transactionData,
            date: getCurrentDate(),
            time: new Date().toLocaleTimeString()
        });
        
        // Keep only last 10 transactions in localStorage to save space
        if (transactions.length > 10) {
            transactions.shift();
        }
        
        localStorage.setItem('transactions', JSON.stringify(transactions));
    } catch (error) {
        console.error('Error tracking transaction:', error);
    }
} 