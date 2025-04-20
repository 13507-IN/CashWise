<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
?>

<?php include 'includes/header.php'; ?>

<div class="container my-5">
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h1 class="h3 mb-0">Frequently Asked Questions</h1>
                </div>
                <div class="card-body">
                    <div class="accordion" id="faqAccordion">
                        <!-- Getting Started -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    Getting Started with FinMate
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <div class="mb-4">
                                        <h5>How do I create an account?</h5>
                                        <p>To create an account, click on the "Register" button at the top-right corner of the page. Fill in your details including username, email, and password, then click "Create Account".</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5>Is FinMate free to use?</h5>
                                        <p>Yes, FinMate is completely free for personal use. We don't have any hidden fees or premium features that require payment.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5>How do I add my first transaction?</h5>
                                        <p>After logging in, navigate to the "Transactions" page from the main menu. Click the "Add Transaction" button, fill in the details of your income or expense, and click "Save".</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tracking Expenses -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    Tracking Expenses & Income
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <div class="mb-4">
                                        <h5>How do I categorize my transactions?</h5>
                                        <p>When adding a transaction, you can select from pre-defined categories or create your own. Categories help you track where your money is going and identify spending patterns.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5>Can I edit or delete transactions?</h5>
                                        <p>Yes, you can edit or delete any transaction. Just find the transaction in your list, and click on the edit (pencil) or delete (trash) icons to make changes.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5>How do I record recurring transactions?</h5>
                                        <p>Currently, you need to add each transaction manually. We're working on a feature to allow automatic recurring transactions in a future update.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Budgeting -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    Budgeting Features
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <div class="mb-4">
                                        <h5>How do I set up a budget?</h5>
                                        <p>Go to the "Budgets" page from the main menu, then click "Create Budget". Select a category, set your budget amount, and choose a time period (weekly, monthly, etc.).</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5>How does the daily budget calculator work?</h5>
                                        <p>The daily budget calculator takes your remaining balance and divides it by the number of days left in your selected period, giving you a suggested daily spending limit.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5>What happens when I exceed my budget?</h5>
                                        <p>When you exceed a budget, FinMate will display a warning notification. You'll also see visual indicators in your budget progress bars when you're getting close to your limits.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Savings Goals -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFour">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                    Savings Goals
                                </button>
                            </h2>
                            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <div class="mb-4">
                                        <h5>How do I create a savings goal?</h5>
                                        <p>Navigate to the "Goals" page from the main menu and click "Create New Goal". Enter a name, target amount, and timeframe for your goal.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5>What is the Quick Save feature?</h5>
                                        <p>Quick Save allows you to quickly add small amounts to your savings goals directly from the dashboard, making it easier to build your savings with regular contributions.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5>How do I track my progress toward goals?</h5>
                                        <p>Your goals are displayed with progress bars showing how close you are to reaching them. You can also see if you're on track based on your timeframe, and update your progress manually.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reports and Insights -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFive">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                                    Reports and Insights
                                </button>
                            </h2>
                            <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <div class="mb-4">
                                        <h5>How do I view reports of my spending?</h5>
                                        <p>Go to the "Reports" page to see visualizations of your income and expenses. You can filter by date range to focus on specific periods.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5>What are the spending insights?</h5>
                                        <p>Spending insights are personalized observations about your financial habits. FinMate analyzes your transactions to identify patterns and provide suggestions for improvement.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5>Can I export my financial data?</h5>
                                        <p>Yes, you can export your transaction data to PDF or CSV format from the Reports page for record-keeping or further analysis in spreadsheet software.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Account and Security -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingSix">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                                    Account and Security
                                </button>
                            </h2>
                            <div id="collapseSix" class="accordion-collapse collapse" aria-labelledby="headingSix" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <div class="mb-4">
                                        <h5>How secure is my financial data?</h5>
                                        <p>FinMate uses encrypted connections and secure password storage to protect your data. We don't store actual bank account information or have access to your financial accounts.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5>How do I change my password?</h5>
                                        <p>Go to your Profile page by clicking on your username at the top right, then select "Edit Profile". You can change your password in the account settings section.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5>Can I delete my account?</h5>
                                        <p>Yes, you can delete your account and all associated data from the Profile settings page. Please note that this action is irreversible.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4>Still have questions?</h4>
                    <p>If you couldn't find the answer to your question, please visit our <a href="contact.php">Contact page</a> to reach out to our support team.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 