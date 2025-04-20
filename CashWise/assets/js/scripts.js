// Budget Tracker JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all dropdowns
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl)
    });

    // Initialize charts if they exist on the page
    initExpensePieChart();
    initIncomeExpenseChart();
    
    // Date range picker initialization
    initDateFilters();
    
    // Make transaction rows clickable
    const transactionTable = document.getElementById('transactionTable');
    if (transactionTable) {
        const rows = transactionTable.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.addEventListener('click', () => {
                const id = row.dataset.id;
                if (id) {
                    window.location.href = `transactions.php?action=view&id=${id}`;
                }
            });
        });
    }
    
    // Budget progress bars
    updateBudgetProgress();

    // Update the transaction history monthly/weekly/daily toggle
    const periodFilter = document.getElementById('periodFilter');
    if (periodFilter) {
        periodFilter.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    }
});

// Function to create expense breakdown pie chart
function initExpensePieChart() {
    const expenseChartEl = document.getElementById('expenseChart');
    if (!expenseChartEl) return;
    
    // Get data from the data attribute
    const chartData = JSON.parse(expenseChartEl.dataset.chartData || '[]');
    
    if (chartData.length === 0) return;
    
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
            maintainAspectRatio: false,
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
                            return `${label}: ₹${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Function to create income vs expense bar chart
function initIncomeExpenseChart() {
    const incomeExpenseChartEl = document.getElementById('incomeExpenseChart');
    if (!incomeExpenseChartEl) return;
    
    // Get data from the data attribute
    const chartData = JSON.parse(incomeExpenseChartEl.dataset.chartData || '[]');
    
    if (chartData.length === 0) return;
    
    const labels = chartData.map(item => item.month);
    const incomeData = chartData.map(item => item.income);
    const expenseData = chartData.map(item => item.expense);
    
    new Chart(incomeExpenseChartEl, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Income',
                    data: incomeData,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Expense',
                    data: expenseData,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Generate random colors for chart
function generateColors(count) {
    const colors = [
        'rgba(255, 99, 132, 0.7)',
        'rgba(54, 162, 235, 0.7)',
        'rgba(255, 206, 86, 0.7)',
        'rgba(75, 192, 192, 0.7)',
        'rgba(153, 102, 255, 0.7)',
        'rgba(255, 159, 64, 0.7)',
        'rgba(199, 199, 199, 0.7)',
        'rgba(83, 102, 255, 0.7)',
        'rgba(40, 159, 64, 0.7)',
        'rgba(210, 199, 199, 0.7)'
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

// Initialize date filters
function initDateFilters() {
    const dateFilterForm = document.getElementById('dateFilterForm');
    if (!dateFilterForm) return;
    
    // Set default date values if not already set
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    
    if (startDateInput && !startDateInput.value) {
        // Default to first day of current month
        const firstDay = new Date();
        firstDay.setDate(1);
        startDateInput.value = formatDate(firstDay);
    }
    
    if (endDateInput && !endDateInput.value) {
        // Default to current date
        endDateInput.value = formatDate(new Date());
    }
}

// Format date as YYYY-MM-DD
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Update budget progress bars
function updateBudgetProgress() {
    const progressBars = document.querySelectorAll('.budget-progress');
    
    progressBars.forEach(progressBar => {
        const spent = parseFloat(progressBar.dataset.spent || 0);
        const budget = parseFloat(progressBar.dataset.budget || 1);
        const percentage = Math.min((spent / budget) * 100, 100);
        
        const progressElement = progressBar.querySelector('.progress-bar');
        if (progressElement) {
            progressElement.style.width = `${percentage}%`;
            
            // Change color based on percentage
            if (percentage >= 90) {
                progressElement.classList.add('bg-danger');
            } else if (percentage >= 70) {
                progressElement.classList.add('bg-warning');
            } else {
                progressElement.classList.add('bg-success');
            }
        }
    });
}

// Form validation for transaction entry
function validateTransactionForm() {
    const amount = document.getElementById('amount').value;
    const categoryElement = document.getElementById('category');
    const category = categoryElement ? categoryElement.value : '';
    const date = document.getElementById('date').value;
    
    if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
        alert('Please enter a valid amount');
        return false;
    }
    
    if (!category) {
        alert('Please select a category');
        if (categoryElement) {
            categoryElement.focus();
        }
        return false;
    }
    
    if (!date) {
        alert('Please select a date');
        return false;
    }
    
    return true;
}

// Toggle dark mode
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    
    // Store preference in local storage
    const isDarkMode = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDarkMode ? 'enabled' : 'disabled');
    
    // Refresh charts if they exist
    initExpensePieChart();
    initIncomeExpenseChart();
}

// Export data to CSV
function exportToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Clean the text content and handle commas
            let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
            text = text.replace(/"/g, '""');
            row.push('"' + text + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], {type: 'text/csv'});
    const downloadLink = document.createElement('a');
    
    // File features
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    // Add to document, trigger click, and then remove
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Function to export dashboard to PDF with proper chart rendering
function exportToPDF() {
    // Show loading spinner
    const loadingSpinner = document.createElement('div');
    loadingSpinner.className = 'loading-spinner';
    loadingSpinner.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
    loadingSpinner.style.position = 'fixed';
    loadingSpinner.style.top = '50%';
    loadingSpinner.style.left = '50%';
    loadingSpinner.style.transform = 'translate(-50%, -50%)';
    loadingSpinner.style.zIndex = '9999';
    loadingSpinner.style.backgroundColor = 'rgba(255, 255, 255, 0.7)';
    loadingSpinner.style.padding = '20px';
    loadingSpinner.style.borderRadius = '5px';
    document.body.appendChild(loadingSpinner);

    // Prepare the document for printing
    const dashboardContent = document.querySelector('.container').cloneNode(true);
    
    // Convert charts to images
    const expenseChart = document.getElementById('expenseChart');
    const incomeExpenseChart = document.getElementById('incomeExpenseChart');
    
    let promises = [];
    
    if (expenseChart) {
        promises.push(new Promise(resolve => {
            const expenseChartImage = new Image();
            expenseChartImage.src = expenseChart.toDataURL('image/png', 1.0);
            expenseChartImage.style.width = '100%';
            expenseChartImage.style.maxHeight = '300px';
            expenseChartImage.onload = function() {
                const chartContainer = dashboardContent.querySelector('#expenseChart').parentNode;
                chartContainer.innerHTML = '';
                chartContainer.appendChild(expenseChartImage);
                resolve();
            };
        }));
    }
    
    if (incomeExpenseChart) {
        promises.push(new Promise(resolve => {
            const incomeExpenseChartImage = new Image();
            incomeExpenseChartImage.src = incomeExpenseChart.toDataURL('image/png', 1.0);
            incomeExpenseChartImage.style.width = '100%';
            incomeExpenseChartImage.style.maxHeight = '300px';
            incomeExpenseChartImage.onload = function() {
                const chartContainer = dashboardContent.querySelector('#incomeExpenseChart').parentNode;
                chartContainer.innerHTML = '';
                chartContainer.appendChild(incomeExpenseChartImage);
                resolve();
            };
        }));
    }
    
    // Wait for all charts to be converted to images
    Promise.all(promises).then(() => {
        // Create a new window for the printable content
        const printWindow = window.open('', '_blank');
        
        // Add content to the print window
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Budget Dashboard</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { padding: 20px; }
                    .card { margin-bottom: 20px; border: 1px solid #ddd; }
                    .card-header { background-color: #f8f9fa; padding: 10px 15px; border-bottom: 1px solid #ddd; }
                    .card-body { padding: 15px; }
                    .summary-card { text-align: center; }
                    .income { color: #28a745; }
                    .expense { color: #dc3545; }
                    .balance { color: #007bff; }
                    @media print {
                        .no-print { display: none; }
                        .card { break-inside: avoid; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="row mb-4">
                        <div class="col-12 text-center">
                            <h2>Budget Dashboard Report</h2>
                            <p class="text-muted">Generated on ${new Date().toLocaleDateString()}</p>
                        </div>
                    </div>
                    ${dashboardContent.innerHTML}
                </div>
                <div class="mt-4 text-center no-print">
                    <button onclick="window.print();window.close();" class="btn btn-primary">Print Report</button>
                </div>
            </body>
            </html>
        `);
        
        // Remove the loading spinner
        document.body.removeChild(loadingSpinner);
        
        printWindow.document.close();
    });
} 