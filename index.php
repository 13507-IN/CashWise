<?php
require_once 'includes/functions.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect("dashboard.php");
}
?>

<?php include 'includes/header.php'; ?>

<!-- Hero Section -->
<div class="row align-items-center py-5">
    <div class="col-lg-6">
        <h1 class="display-4 fw-bold">Take Control of Your Finances</h1>
        <p class="lead">FinMate helps you monitor your income and expenses, set budget limits, and achieve your financial goals.</p>
        <div class="d-grid gap-2 d-md-flex justify-content-md-start">
            <a href="register.php" class="btn btn-primary btn-lg px-4 me-md-2">Get Started</a>
            <a href="login.php" class="btn btn-outline-secondary btn-lg px-4">Login</a>
        </div>
    </div>
    <div class="col-lg-6">
        <img src="assets/img/budget-illustration.svg" alt="Budget Tracking Illustration" class="img-fluid" onerror="this.src='https://via.placeholder.com/600x400?text=Budget+Tracker';this.onerror='';">
    </div>
</div>

<!-- Features Section -->
<div class="row g-4 py-5">
    <h2 class="text-center mb-4">Core Features</h2>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3">
                    <i class="fas fa-chart-pie fa-3x text-primary"></i>
                </div>
                <h3>Track Expenses</h3>
                <p>Record and categorize your expenses to understand your spending habits.</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3">
                    <i class="fas fa-money-bill-wave fa-3x text-primary"></i>
                </div>
                <h3>Set Budgets</h3>
                <p>Create monthly or weekly budgets for different spending categories.</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3">
                    <i class="fas fa-chart-line fa-3x text-primary"></i>
                </div>
                <h3>Visualize Data</h3>
                <p>Understand your finances with interactive charts and reports.</p>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Features -->
<div class="row g-4 py-5">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3">
                    <i class="fas fa-bullseye fa-2x text-primary"></i>
                </div>
                <h4>Goal Setting</h4>
                <p>Set savings goals and track your progress.</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3">
                    <i class="fas fa-calendar-alt fa-2x text-primary"></i>
                </div>
                <h4>Recurring Expenses</h4>
                <p>Track regular payments and subscriptions.</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3">
                    <i class="fas fa-file-export fa-2x text-primary"></i>
                </div>
                <h4>Export Data</h4>
                <p>Download your data in PDF or CSV format.</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="feature-icon mb-3">
                    <i class="fas fa-mobile-alt fa-2x text-primary"></i>
                </div>
                <h4>Mobile Friendly</h4>
                <p>Access your budget from any device.</p>
            </div>
        </div>
    </div>
</div>

<!-- Testimonials -->
<div class="row py-5">
    <h2 class="text-center mb-4">What Our Users Say</h2>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-center mb-3">
                    <span class="text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </span>
                </div>
                <p class="card-text text-center">"This app helped me save enough for a down payment on my house. Simple but powerful!"</p>
                <p class="text-center"><strong>John D.</strong></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-center mb-3">
                    <span class="text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </span>
                </div>
                <p class="card-text text-center">"I finally understand where my money goes each month. The charts are very helpful!"</p>
                <p class="text-center"><strong>Sarah M.</strong></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-center mb-3">
                    <span class="text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </span>
                </div>
                <p class="card-text text-center">"Setting budget limits for different categories has transformed my spending habits."</p>
                <p class="text-center"><strong>Michael K.</strong></p>
            </div>
        </div>
    </div>
</div>

<!-- Call to Action -->
<div class="row py-5">
    <div class="col-12 text-center">
        <div class="card bg-primary text-white">
            <div class="card-body py-5">
                <h2 class="card-title">Ready to Start Managing Your Budget?</h2>
                <p class="card-text">Join thousands of users who have improved their financial health with FinMate.</p>
                <a href="register.php" class="btn btn-light btn-lg px-4 mt-3">Create Free Account</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 