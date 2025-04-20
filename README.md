🌟 FinMate
A comprehensive PHP web application for tracking personal finances, managing budgets, and setting savings goals.

Youtube Video Link
https://youtu.be/GctE1j80GHo?si=9hTkaSuL3VqiIIBw

🚀 Features
🔧 Core Features
🔐 User Registration/Login
Secure user accounts with password hashing

📊 Dashboard
Visual summary of income, expenses, and savings

💸 Transaction Management
Add, edit, and delete income and expense transactions

📂 Categories
Organize transactions by customizable categories

📉 Budget Limits
Set monthly or weekly spending limits for different categories

📈 Visual Reports
View spending patterns with interactive charts

📅 Date Range Filtering
Analyze finances across different time periods

🌟 Advanced Features
🎯 Savings Goals
Set and track progress towards financial goals

📘 Financial Reports & Insights
Get deeper analysis of your spending habits

📤 Export Functionality
Export reports to CSV or print them as PDFs

📱 Mobile Responsive Design
Access from any device with a responsive layout

🛠️ Technology Stack
Tech	Purpose
PHP	Core backend language
MySQL	Database for storing user data
Bootstrap 5	Responsive frontend design framework
Chart.js	Interactive charts and data visualization
Font Awesome	Icons for enhanced UI
📥 Installation Guide
1. Clone the Repository
bash git clone https://github.com/yourusername/budget-tracker.git

2. Setup Your Environment
Ensure you have a web server like Apache and MySQL installed (e.g., XAMPP, WAMP).

3. Configure Database
Edit the file at:

/config/database.php

Update it with your MySQL credentials.

4. Database Setup
✅ Option 1: Automatic Installation (Recommended)
Visit in your browser:
http://localhost/budget/install.php

The installer will auto-create the database schema and default categories.
🛠️ Option 2: Manual Installation
Create a MySQL database named budget_tracker
Import the SQL schema: bash mysql -u username -p budget_tracker < database/schema.sql
5. Launch the App
bash http://localhost/budget

6. Start Using FinMate
Register a new account
Add your income and expenses
Set budgets and savings goals
Analyze your spending and export reports
📁 Application Structure
/assets → CSS, JS, and image files
/config → Database configuration
/database → Setup scripts and SQL schema
/includes → Reusable PHP components
/*.php → Main application pages

🧭 Usage Flow
Register or log in
Add transactions (income or expenses)
Set budget limits by category
Create and track savings goals
View reports and gain insights
Export or print reports when needed
🔐 Security Features
Secure password hashing
Input validation and sanitization
Prepared SQL statements to prevent SQL injection
Session-based authentication

Demo Link: 
https://youtu.be/GctE1j80GHo?si=9hTkaSuL3VqiIIBw

📄 License
This project is licensed under the MIT License – see the LICENSE file for details.

🙌 Acknowledgments
Bootstrap 5 – for the responsive frontend
Chart.js – for interactive financial charts
Font Awesome – for a beautiful icon library
🤝 Contributing
Pull requests are welcome! If you find a bug or want to suggest a feature, feel free to contribute.
