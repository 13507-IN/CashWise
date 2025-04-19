# Budget Tracker

A comprehensive PHP web application for tracking personal finances, managing budgets, and setting savings goals.

## Features

### Core Features
- **User Registration/Login**: Secure user accounts with password hashing
- **Dashboard**: Visual summary of income, expenses, and savings
- **Transaction Management**: Add, edit, and delete income and expense transactions
- **Categories**: Organize transactions by customizable categories
- **Budget Limits**: Set monthly or weekly spending limits for different categories
- **Visual Reports**: View spending patterns with interactive charts
- **Date Range Filtering**: Analyze finances across different time periods

### Advanced Features
- **Savings Goals**: Set and track progress towards financial goals
- **Financial Reports & Insights**: Get deeper analysis of spending patterns
- **Export Functionality**: Export reports to CSV or print them as PDF
- **Mobile Responsive Design**: Access from any device with a responsive layout

## Technology Stack

- **PHP**: Core backend language
- **MySQL**: Database for storing user data and transactions
- **Bootstrap 5**: Frontend framework for responsive design
- **Chart.js**: JavaScript library for creating interactive charts
- **Font Awesome**: Icon library for user interface enhancements

## Installation

1. Clone the repository to your web server directory:
   ```
   git clone https://github.com/yourusername/budget-tracker.git
   ```

2. Make sure you have a web server (like Apache) and MySQL installed (XAMPP or similar will work)

3. Navigate to the project directory and update database configuration in `config/database.php` with your MySQL credentials

4. There are two ways to set up the database:

   **Option 1: Automatic Installation (Recommended)**
   - Simply visit `http://localhost/budget/install.php` in your browser
   - The installer will set up the database schema and default categories automatically

   **Option 2: Manual Installation**
   - Create a MySQL database named `budget_tracker`
   - Import the `database/schema.sql` file into your MySQL database:
     ```
     mysql -u username -p budget_tracker < database/schema.sql
     ```

5. Access the application through your web browser:
   ```
   http://localhost/budget
   ```

6. Register an account and start tracking your finances!

## Application Structure

- `/assets`: CSS, JavaScript, and image files
- `/config`: Database configuration
- `/database`: Database setup scripts and schema
- `/includes`: Reusable PHP components and functions
- Root PHP files: Main application pages

## Usage

1. Register a new account or log in if you already have one
2. Add your income and expense transactions
3. Set up budget limits for different spending categories
4. Create savings goals to track your progress
5. Use the reports section to analyze your spending patterns
6. Export or print reports as needed

## Security Features

- Password hashing for secure user authentication
- Form input validation and sanitization
- Prepared SQL statements to prevent SQL injection
- Session-based authentication

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- Bootstrap 5 for the responsive frontend framework
- Chart.js for the interactive charts
- Font Awesome for the icons

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. 