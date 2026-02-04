<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Create database connection directly (since config/database.php doesn't exist)
$host = 'localhost';
$dbname = 'hrms_paluan';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If database doesn't exist, try to create it
    if ($e->getCode() == 1049) {
        try {
            $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
            $pdo->exec("USE $dbname");
            
            // Create tables
            createTables($pdo);
        } catch (PDOException $e2) {
            die("Database connection failed: " . $e2->getMessage());
        }
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Function to create necessary tables
function createTables($pdo) {
    // Create employees table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id VARCHAR(50) UNIQUE NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            middle_name VARCHAR(100),
            last_name VARCHAR(100) NOT NULL,
            position VARCHAR(100),
            monthly_salary DECIMAL(10,2) DEFAULT 0.00,
            employment_type ENUM('Contractual', 'Job Order', 'Permanent', 'Regular') DEFAULT 'Contractual',
            tax_id VARCHAR(50),
            community_tax_cert VARCHAR(50),
            gsis_no VARCHAR(50),
            deduction_rate DECIMAL(5,2) DEFAULT 0.00,
            status ENUM('Active', 'Inactive', 'Resigned') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    
    // Create attendance_records table (matches the first image structure)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_records (
            id INT PRIMARY KEY AUTO_INCREMENT,
            date DATE NOT NULL,
            employee_id VARCHAR(50) NOT NULL,
            employee_name VARCHAR(100) NOT NULL,
            department VARCHAR(150),
            am_time_in TIME,
            am_time_out TIME,
            pm_time_in TIME,
            pm_time_out TIME,
            ot_hours DECIMAL(5,2) DEFAULT 0.00,
            under_time DECIMAL(5,2) DEFAULT 0.00,
            total_hours DECIMAL(5,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee_date (employee_id, date),
            INDEX idx_date (date)
        ) ENGINE=InnoDB
    ");
    
    // Create contractual_payroll table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contractual_payroll (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            payroll_period VARCHAR(7) NOT NULL,
            gratuity DECIMAL(10,2) DEFAULT 0.00,
            income_tax DECIMAL(10,2) DEFAULT 0.00,
            community_tax DECIMAL(10,2) DEFAULT 0.00,
            gsis DECIMAL(10,2) DEFAULT 0.00,
            total_deductions DECIMAL(10,2) DEFAULT 0.00,
            net_amount DECIMAL(10,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_employee_payroll (employee_id, payroll_period)
        ) ENGINE=InnoDB
    ");
    
    // Insert sample data if tables are empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees");
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        // Insert sample employees
        $sampleEmployees = [
            ['EMP001', 'JASPER', 'A', 'GARCIA', 'MPIO Focal Person', 20000.00, 'Contractual', '123-456-789', '084/1398', 'GSIS001', 0.00, 'Active'],
            ['EMP002', 'APRIL', 'V', 'AGUAVILLA', 'MPIO Focal Person', 20000.00, 'Contractual', '123-456-790', '084/1498', 'GSIS002', 0.00, 'Active'],
            ['EMP003', 'JUAN', 'B', 'DELA CRUZ', 'Admin Assistant', 15000.00, 'Contractual', '123-456-791', '084/1598', 'GSIS003', 0.00, 'Active'],
            ['EMP004', 'MARIA', 'C', 'SANTOS', 'Clerk', 12000.00, 'Contractual', '123-456-792', '084/1698', '', 0.00, 'Active'],
        ];
        
        $insertEmployee = $pdo->prepare("
            INSERT INTO employees (employee_id, first_name, middle_name, last_name, position, monthly_salary, 
                                  employment_type, tax_id, community_tax_cert, gsis_no, deduction_rate, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($sampleEmployees as $employee) {
            $insertEmployee->execute($employee);
        }
        
        // Insert sample attendance records (for current month)
        $currentMonth = date('Y-m');
        $employees = $pdo->query("SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name FROM employees")->fetchAll();
        
        $insertAttendance = $pdo->prepare("
            INSERT INTO attendance_records (date, employee_id, employee_name, total_hours)
            VALUES (?, ?, ?, 8.0)
        ");
        
        foreach ($employees as $emp) {
            // Add 20 days of attendance for each employee (sample data)
            for ($i = 1; $i <= 20; $i++) {
                $date = date('Y-m-d', strtotime($currentMonth . '-' . str_pad($i, 2, '0', STR_PAD_LEFT)));
                $insertAttendance->execute([$date, $emp['employee_id'], $emp['full_name']]);
            }
        }
    }
}

// Logout functionality
if (isset($_GET['logout'])) {
    // Destroy all session data
    session_destroy();

    // Clear remember me cookie
    setcookie('remember_user', '', time() - 3600, "/");

    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Fetch contractual employees with attendance from attendance_records table
$contractual_employees = [];
$total_payroll = 0;
$total_deductions = 0;
$net_amount = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.employee_id,
            e.first_name,
            e.middle_name,
            e.last_name,
            e.position,
            COALESCE(e.monthly_salary, 0) as monthly_salary,
            e.employment_type,
            e.tax_id,
            e.community_tax_cert,
            e.gsis_no,
            COALESCE(e.deduction_rate, 0) as deduction_rate,
            COALESCE(COUNT(DISTINCT a.date), 0) as days_present,
            (COALESCE(e.monthly_salary, 0) / 22) * COALESCE(COUNT(DISTINCT a.date), 0) as earned_salary
        FROM employees e
        LEFT JOIN attendance_records a ON e.employee_id = a.employee_id 
            AND MONTH(a.date) = MONTH(CURRENT_DATE())
            AND YEAR(a.date) = YEAR(CURRENT_DATE())
            AND a.total_hours >= 4  -- Consider as present if at least 4 hours worked
        WHERE e.employment_type = 'Contractual'
            AND e.status = 'Active'
        GROUP BY e.id, e.employee_id, e.first_name, e.middle_name, e.last_name, 
                 e.position, e.monthly_salary, e.employment_type,
                 e.tax_id, e.community_tax_cert, e.gsis_no, e.deduction_rate
        ORDER BY e.last_name, e.first_name
    ");
    $stmt->execute();
    $contractual_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    foreach ($contractual_employees as $employee) {
        $earned = floatval($employee['earned_salary']);
        $total_payroll += $earned;
        
        // Calculate deductions
        $deductions = 0;
        if ($earned > 20833) {
            $deductions += ($earned - 20833) * 0.20; // Income tax
        }
        $deductions += 500; // Community tax
        if (!empty($employee['gsis_no'])) {
            $deductions += $earned * 0.09; // GSIS
        }
        
        $total_deductions += $deductions;
        $net_amount += ($earned - $deductions);
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $contractual_employees = [];
}

// Function to calculate deductions
function calculateDeductions($employee) {
    $deductions = [
        'gratuity' => 0,
        'income_tax' => 0,
        'community_tax' => 500,
        'gsis' => 0,
        'total' => 0
    ];
    
    $monthly_salary = floatval($employee['earned_salary']);
    
    // Calculate income tax (simplified calculation)
    if ($monthly_salary > 20833) {
        $deductions['income_tax'] = ($monthly_salary - 20833) * 0.20;
    }
    
    // Calculate GSIS (if applicable)
    if (!empty($employee['gsis_no'])) {
        $deductions['gsis'] = $monthly_salary * 0.09;
    }
    
    // Calculate total
    $deductions['total'] = array_sum($deductions);
    
    return $deductions;
}

// Handle form submission for saving payroll
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_payroll'])) {
        try {
            $pdo->beginTransaction();
            
            // Get current payroll period
            $payroll_period = date('Y-m');
            
            foreach ($_POST['employee_id'] as $index => $employee_id) {
                $gratuity = floatval($_POST['gratuity'][$index] ?? 0);
                $income_tax = floatval($_POST['income_tax'][$index] ?? 0);
                $community_tax = floatval($_POST['community_tax'][$index] ?? 0);
                $gsis = floatval($_POST['gsis'][$index] ?? 0);
                $total_deduction = floatval($_POST['total_deduction'][$index] ?? 0);
                $net_amount = floatval($_POST['net_amount'][$index] ?? 0);
                
                // Check if payroll record exists
                $check_stmt = $pdo->prepare("
                    SELECT id FROM contractual_payroll 
                    WHERE employee_id = ? AND payroll_period = ?
                ");
                $check_stmt->execute([$employee_id, $payroll_period]);
                
                if ($check_stmt->rowCount() > 0) {
                    // Update existing record
                    $update_stmt = $pdo->prepare("
                        UPDATE contractual_payroll 
                        SET gratuity = ?, income_tax = ?, community_tax = ?, 
                            gsis = ?, total_deductions = ?, net_amount = ?,
                            updated_at = NOW()
                        WHERE employee_id = ? AND payroll_period = ?
                    ");
                    $update_stmt->execute([
                        $gratuity, $income_tax, $community_tax, $gsis,
                        $total_deduction, $net_amount, $employee_id, $payroll_period
                    ]);
                } else {
                    // Insert new record
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO contractual_payroll 
                        (employee_id, payroll_period, gratuity, income_tax, 
                         community_tax, gsis, total_deductions, net_amount)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insert_stmt->execute([
                        $employee_id, $payroll_period, $gratuity, $income_tax,
                        $community_tax, $gsis, $total_deduction, $net_amount
                    ]);
                }
            }
            
            $pdo->commit();
            $success_message = "Payroll saved successfully!";
            
            // Refresh page to show updated data
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Error saving payroll: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contractual Payroll | HR Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            "50": "#eff6ff",
                            "100": "#dbeafe",
                            "200": "#bfdbfe",
                            "300": "#93c5fd",
                            "400": "#60a5fa",
                            "500": "#3b82f6",
                            "600": "#2563eb",
                            "700": "#1d4ed8",
                            "800": "#1e40af",
                            "900": "#1e3a8a",
                            "950": "#172554"
                        }
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --primary: #1e40af;
            --secondary: #1e3a8a;
            --accent: #3b82f6;
            --gradient-nav: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            overflow-x: hidden;
            color: #1f2937;
        }

        /* NAVBAR - FIXED RESPONSIVE STYLES */
        .navbar {
            background: var(--gradient-nav);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 70px;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 1.5rem;
            max-width: 100%;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        /* Logo and Brand */
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: scale(1.02);
        }

        .brand-logo {
            width: 45px;
            height: 45px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .brand-subtitle {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        /* Date & Time Display */
        .datetime-container {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .datetime-box {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 0.6rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            min-width: 180px;
        }

        .datetime-box:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .datetime-icon {
            font-size: 1.1rem;
            color: white;
            opacity: 0.9;
        }

        .datetime-text {
            display: flex;
            flex-direction: column;
        }

        .datetime-label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .datetime-value {
            font-size: 0.95rem;
            color: white;
            font-weight: 600;
            line-height: 1.3;
        }

        /* Logout Button */
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(239, 68, 68, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 0.5rem 1rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .logout-btn:hover {
            background: rgba(220, 38, 38, 0.9);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        /* Mobile Menu Toggle */
        .mobile-toggle {
            display: flex;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            outline: none;
        }

        .mobile-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }

        .mobile-toggle i {
            font-size: 1.25rem;
            color: white;
        }

        @media (min-width: 1024px) {
            .mobile-toggle {
                display: none;
            }
        }

        /* Sidebar Styles */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .sidebar {
            position: fixed;
            top: 70px;
            left: -300px;
            width: 250px;
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            z-index: 1000;
            transition: left 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar.active {
            left: 0;
        }

        @media (min-width: 1024px) {
            .sidebar {
                left: 0;
                top: 70px;
                height: calc(100vh - 70px);
            }

            .sidebar-overlay {
                display: none !important;
            }

            main {
                margin-left: 250px;
            }
        }

        .sidebar-content {
            flex: 1;
            padding: 1.5rem 1rem;
            overflow-y: auto;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8rem;
        }

        /* Sidebar Menu Items */
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 0.875rem 1rem;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .sidebar-item.active {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar-item i {
            width: 1.5rem;
            text-align: center;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        .sidebar-item span {
            flex: 1;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .sidebar-item .chevron {
            transition: transform 0.3s ease;
            font-size: 0.7rem;
        }

        .sidebar-item .chevron.rotated {
            transform: rotate(180deg);
        }

        /* Dropdown Menu in Sidebar */
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            margin-left: 2.5rem;
        }

        .submenu.open {
            max-height: 500px;
        }

        .submenu-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .submenu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .submenu-item.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .submenu-item i {
            font-size: 0.75rem;
            margin-right: 0.5rem;
        }

        /* Mobile Brand Styling */
        .mobile-brand {
            display: flex;
            align-items: center;
        }

        .mobile-brand-text {
            display: flex;
            flex-direction: column;
            margin-left: 0.5rem;
        }

        .mobile-brand-title {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }

        .mobile-brand-subtitle {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        /* Main Content */
        main {
            margin-top: 70px;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
            width: 100%;
            transition: margin-left 0.3s ease;
        }

        @media (min-width: 1024px) {
            main {
                margin-left: 250px;
                width: calc(100% - 250px);
            }
        }

        @media (max-width: 768px) {
            main {
                padding: 1rem;
            }
        }

        /* Table responsive design */
        .table-container {
            overflow-x: auto;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            -webkit-overflow-scrolling: touch;
        }

        .payroll-table {
            min-width: 1400px;
            width: 100%;
            border-collapse: collapse;
        }

        .payroll-table th,
        .payroll-table td {
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            white-space: nowrap;
            text-align: left;
        }

        .payroll-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #374151;
        }

        .payroll-table tbody tr:hover {
            background-color: #f9fafb;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.375rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            border: none;
            cursor: pointer;
        }

        .action-btn:hover {
            opacity: 0.9;
        }

        /* Card design */
        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* Breadcrumb styling */
        .breadcrumb {
            font-size: 0.8rem;
            overflow-x: auto;
            white-space: nowrap;
            padding: 0.5rem 0;
            -webkit-overflow-scrolling: touch;
        }

        .breadcrumb ol {
            display: flex;
            align-items: center;
        }

        /* Responsive utilities - NAVBAR SPECIFIC */
        @media (max-width: 768px) {
            .datetime-container {
                display: none;
            }

            .logout-btn span {
                display: none;
            }

            .logout-btn {
                padding: 0.5rem;
                width: 40px;
                height: 40px;
                justify-content: center;
            }

            .navbar-container {
                padding: 0 1rem;
            }

            .brand-text {
                display: none;
            }

            .mobile-brand {
                display: flex;
            }
        }

        @media (min-width: 769px) {
            .mobile-brand {
                display: none;
            }

            .brand-text {
                display: flex;
            }
        }

        @media (max-width: 640px) {
            .navbar {
                height: 65px;
            }

            .sidebar {
                top: 65px;
                height: calc(100vh - 65px);
            }

            main {
                margin-top: 65px;
            }

            .mobile-toggle {
                width: 36px;
                height: 36px;
            }

            .brand-logo {
                width: 40px;
                height: 40px;
            }
            
            .logout-btn {
                width: 36px;
                height: 36px;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        /* Input field styling */
        .payroll-input {
            width: 100%;
            padding: 0.25rem 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            text-align: right;
        }

        .payroll-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .payroll-input.readonly {
            background-color: #f9fafb;
            border-color: #e5e7eb;
            color: #6b7280;
            cursor: not-allowed;
        }

        /* Alert messages */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 1rem;
            border-radius: 0.5rem;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Navigation Header -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Left Section -->
            <div class="navbar-left">
                <!-- Mobile Menu Toggle -->
                <button class="mobile-toggle" id="mobile-menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- Logo and Brand (Desktop) -->
                <a href="../dashboard.php" class="navbar-brand hidden lg:flex">
                    <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
                    <div class="brand-text">
                        <span class="brand-title">HR Management System</span>
                        <span class="brand-subtitle">Paluan Occidental Mindoro</span>
                    </div>
                </a>

                <!-- Logo and Brand (Mobile) -->
                <div class="mobile-brand lg:hidden">
                    <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
                    <div class="mobile-brand-text">
                        <span class="mobile-brand-title">HRMS</span>
                        <span class="mobile-brand-subtitle">Dashboard</span>
                    </div>
                </div>
            </div>

            <!-- Right Section -->
            <div class="navbar-right">
                <!-- Date & Time -->
                <div class="datetime-container hidden md:flex">
                    <div class="datetime-box">
                        <i class="datetime-icon fas fa-calendar-alt"></i>
                        <div class="datetime-text">
                            <span class="datetime-label">Date</span>
                            <span class="datetime-value" id="current-date">Loading...</span>
                        </div>
                    </div>

                    <div class="datetime-box">
                        <i class="datetime-icon fas fa-clock"></i>
                        <div class="datetime-text">
                            <span class="datetime-label">Time</span>
                            <span class="datetime-value" id="current-time">Loading...</span>
                        </div>
                    </div>
                </div>

               
            </div>
        </div>
    </nav>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <ul class="space-y-1">
                <!-- Dashboard -->
                <li>
                    <a href="../dashboard.php" class="sidebar-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard Analytics</span>
                    </a>
                </li>

                <!-- Employees -->
                <li>
                    <a href="../employees/Employee.php" class="sidebar-item">
                        <i class="fas fa-users"></i>
                        <span>Employees</span>
                    </a>
                </li>

                <!-- Attendance -->
                <li>
                    <a href="../attendance.php" class="sidebar-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Attendance</span>
                    </a>
                </li>

                <!-- Payroll Dropdown -->
                <li>
                    <a href="#" class="sidebar-item active" id="payroll-toggle">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Payroll</span>
                        <i class="fas fa-chevron-down chevron text-xs ml-auto"></i>
                    </a>
                    <div class="submenu" id="payroll-submenu">
                        <a href="contractualpayrolltable1.php" class="submenu-item active">
                            <i class="fas fa-circle text-xs"></i>
                            Contractual
                        </a>
                        <a href="../Payrollmanagement/joboerderpayrolltable1.php" class="submenu-item">
                            <i class="fas fa-circle text-xs"></i>
                            Job Order
                        </a>
                        <a href="../Payrollmanagement/permanentpayrolltable1.php" class="submenu-item">
                            <i class="fas fa-circle text-xs"></i>
                            Permanent
                        </a>
                    </div>
                </li>


                <!-- Reports -->
                <li>
                    <a href="../paysliphistory.php" class="sidebar-item">
                        <i class="fas fa-file-alt"></i>
                        <span>Reports</span>
                    </a>
                </li>

                <!-- Salary -->
                <li>
                    <a href="../sallarypayheads.php" class="sidebar-item">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span>Salary Structure</span>
                    </a>
                </li>

                <!-- Settings -->
                <li>
                    <a href="../settings.php" class="sidebar-item">
                        <i class="fas fa-sliders-h"></i>
                        <span>Settings</span>
                    </a>
                </li>

                <!-- Logout -->
                <a href="?logout=true" class="sidebar-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </ul>
        </div>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <div class="text-center text-white/60 text-sm">
                <p>HRMS v2.0</p>
                <p class="text-xs mt-1">© 2024 Paluan LGU</p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main>
        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success mb-4">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-2">
                <li class="inline-flex items-center">
                    <a href="contractualpayrolltable1.php" class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-700">
                        <i class="fas fa-home mr-2"></i> Contractual Payroll
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                        <a href="contractualpayroll.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-primary-600 md:ml-2">General Payroll</a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                        <a href="contractualobligationrequest.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-primary-600 md:ml-2"> Obligation Request</a>
                    </div>
                </li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="mb-6 mt-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900">Contractual Payroll</h1>
                    <p class="text-gray-600 mt-1 text-sm md:text-base">Manage and process contractual employee payroll for <?php echo date('F Y'); ?></p>
                </div>
                <div class="flex gap-2">
                    <button id="calculate-all-btn" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calculator mr-2"></i> Calculate All
                    </button>
                    <button id="refresh-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center justify-center">
                        <i class="fas fa-sync-alt mr-2"></i> Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-users text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Employees</p>
                        <p class="text-xl md:text-2xl font-bold text-gray-900"><?php echo count($contractual_employees); ?></p>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-money-bill-wave text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Payroll</p>
                        <p class="text-xl md:text-2xl font-bold text-gray-900" id="total-payroll-display">₱<?php echo number_format($total_payroll, 2); ?></p>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-hand-holding-usd text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Deductions</p>
                        <p class="text-xl md:text-2xl font-bold text-gray-900" id="total-deductions-display">₱<?php echo number_format($total_deductions, 2); ?></p>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                        <i class="fas fa-wallet text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Net Amount</p>
                        <p class="text-xl md:text-2xl font-bold text-gray-900" id="net-amount-display">₱<?php echo number_format($net_amount, 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payroll Table -->
        <form method="POST" action="" id="payroll-form">
            <input type="hidden" name="save_payroll" value="1">
            
            <div class="card">
                <div class="p-4 border-b border-gray-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <h2 class="text-lg font-semibold text-gray-900">Employee Payroll Details for <?php echo date('F Y'); ?></h2>
                    <div class="flex items-center space-x-2 w-full md:w-auto">
                        <div class="relative flex-1 md:flex-none">
                            <input type="text" id="search-employees" placeholder="Search employees..." class="pl-9 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 w-full text-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                        <button type="button" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg border border-gray-300" id="filter-btn">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="payroll-table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="w-12">#</th>
                                <th rowspan="2" class="min-w-[120px]">Name</th>
                                <th rowspan="2" class="min-w-[90px]">Position</th>
                                <th colspan="3" class="text-center">Compensation</th>
                                <th colspan="4" class="text-center">Deductions</th>
                                <th colspan="2" class="text-center">Net Amount</th>
                                <th rowspan="2" class="min-w-[100px]">Actions</th>
                            </tr>
                            <tr>
                                <th class="min-w-[70px]">Monthly Salary</th>
                                <th class="min-w-[50px]">Days Present</th>
                                <th class="min-w-[60px]">Earned</th>
                                <th class="min-w-[90px]">Gratuity/Terminal Leave</th>
                                <th class="min-w-[60px]">Income Tax</th>
                                <th class="min-w-[70px]">Community Tax</th>
                                <th class="min-w-[50px]">GSIS</th>
                                <th class="min-w-[60px]">Total Deduction</th>
                                <th class="min-w-[70px]">Net Amount</th>
                                <th class="min-w-[70px]">Date</th>
                            </tr>
                        </thead>
                        <tbody id="payroll-tbody">
                            <?php if (empty($contractual_employees)): ?>
                                <tr>
                                    <td colspan="14" class="text-center py-8 text-gray-500">
                                        No contractual employees found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($contractual_employees as $employee): ?>
                                    <?php 
                                    $deductions = calculateDeductions($employee);
                                    $earned_salary = floatval($employee['earned_salary']);
                                    $net_amount_row = $earned_salary - $deductions['total'];
                                    ?>
                                    <tr class="bg-white border-b hover:bg-gray-50 payroll-row" data-employee-id="<?php echo $employee['id']; ?>">
                                        <td class="text-center"><?php echo $counter++; ?></td>
                                        <td class="font-medium">
                                            <?php echo htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name'] . ' ' . ($employee['middle_name'] ? substr($employee['middle_name'], 0, 1) . '.' : '')); ?>
                                            <input type="hidden" name="employee_id[]" value="<?php echo $employee['id']; ?>">
                                        </td>
                                        <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                        
                                        <!-- Compensation -->
                                        <td>
                                            <input type="text" 
                                                   class="payroll-input readonly monthly-salary" 
                                                   value="₱<?php echo number_format($employee['monthly_salary'], 2); ?>" 
                                                   data-original="<?php echo $employee['monthly_salary']; ?>"
                                                   readonly>
                                        </td>
                                        <td class="text-center days-present">
                                            <?php echo $employee['days_present'] ?? 0; ?> / 22
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   class="payroll-input readonly earned-salary" 
                                                   value="₱<?php echo number_format($earned_salary, 2); ?>"
                                                   readonly>
                                        </td>
                                        
                                        <!-- Deductions -->
                                        <td>
                                            <input type="number" 
                                                   name="gratuity[]" 
                                                   class="payroll-input gratuity" 
                                                   value="<?php echo $deductions['gratuity']; ?>"
                                                   min="0" 
                                                   step="0.01"
                                                   data-employee-id="<?php echo $employee['id']; ?>">
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="income_tax[]" 
                                                   class="payroll-input income-tax" 
                                                   value="<?php echo $deductions['income_tax']; ?>"
                                                   min="0" 
                                                   step="0.01"
                                                   data-employee-id="<?php echo $employee['id']; ?>">
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="community_tax[]" 
                                                   class="payroll-input community-tax" 
                                                   value="<?php echo $deductions['community_tax']; ?>"
                                                   min="0" 
                                                   step="0.01"
                                                   data-employee-id="<?php echo $employee['id']; ?>">
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="gsis[]" 
                                                   class="payroll-input gsis" 
                                                   value="<?php echo $deductions['gsis']; ?>"
                                                   min="0" 
                                                   step="0.01"
                                                   data-employee-id="<?php echo $employee['id']; ?>">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="total_deduction[]" 
                                                   class="payroll-input readonly total-deduction" 
                                                   value="<?php echo number_format($deductions['total'], 2); ?>"
                                                   readonly>
                                        </td>
                                        
                                        <!-- Net Amount -->
                                        <td>
                                            <input type="text" 
                                                   name="net_amount[]" 
                                                   class="payroll-input readonly net-amount" 
                                                   value="₱<?php echo number_format($net_amount_row, 2); ?>"
                                                   readonly>
                                        </td>
                                        <td class="text-center">
                                            <?php echo date('m/d/Y'); ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="action-btn bg-blue-500 text-white hover:bg-blue-600 view-row" data-employee-id="<?php echo $employee['id']; ?>">
                                                    <i class="fas fa-eye"></i> <span class="hidden md:inline">View</span>
                                                </button>
                                                <button type="button" class="action-btn bg-green-500 text-white hover:bg-green-600 calculate-row" data-employee-id="<?php echo $employee['id']; ?>">
                                                    <i class="fas fa-calculator"></i> <span class="hidden md:inline">Calculate</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <!-- Totals Row -->
                            <tr class="bg-gray-100 font-bold">
                                <td colspan="3" class="text-right">TOTAL AMOUNT</td>
                                <td class="text-right" id="total-monthly-salary">₱<?php echo number_format(array_sum(array_column($contractual_employees, 'monthly_salary')), 2); ?></td>
                                <td></td>
                                <td class="text-right" id="total-earned-salary">₱<?php echo number_format($total_payroll, 2); ?></td>
                                <td class="text-right" id="total-gratuity">₱<?php echo number_format(array_sum(array_map(function($emp) { $ded = calculateDeductions($emp); return $ded['gratuity']; }, $contractual_employees)), 2); ?></td>
                                <td class="text-right" id="total-income-tax">₱<?php echo number_format(array_sum(array_map(function($emp) { $ded = calculateDeductions($emp); return $ded['income_tax']; }, $contractual_employees)), 2); ?></td>
                                <td class="text-right" id="total-community-tax">₱<?php echo number_format(array_sum(array_map(function($emp) { $ded = calculateDeductions($emp); return $ded['community_tax']; }, $contractual_employees)), 2); ?></td>
                                <td class="text-right" id="total-gsis">₱<?php echo number_format(array_sum(array_map(function($emp) { $ded = calculateDeductions($emp); return $ded['gsis']; }, $contractual_employees)), 2); ?></td>
                                <td class="text-right" id="total-deduction">₱<?php echo number_format($total_deductions, 2); ?></td>
                                <td class="text-right" id="total-net-amount">₱<?php echo number_format($net_amount, 2); ?></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div class="p-4 border-t border-gray-200 flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="text-sm text-gray-600">
                        Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($contractual_employees); ?></span> of <span class="font-medium"><?php echo count($contractual_employees); ?></span> employees
                    </div>
                    <div class="flex items-center space-x-2">
                        <button type="button" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50" id="prev-btn">
                            Previous
                        </button>
                        <button type="button" class="px-3 py-1.5 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700" id="page-1">
                            1
                        </button>
                        <button type="button" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50" id="next-btn">
                            Next
                        </button>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex flex-col sm:flex-row justify-end gap-3">
                <button type="submit" class="px-4 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 flex items-center justify-center">
                    <i class="fas fa-save mr-2"></i> Save Payroll
                </button>
                <a href="contractualpayroll.php" class="w-full sm:w-auto">
                    <button type="button" class="w-full px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 flex items-center justify-center">
                        Next <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </a>
            </div>
        </form>
    </main>

    <!-- JavaScript -->
    <script>
        // Initialize states
        let sidebarOpen = false;
        let payrollMenuOpen = true;

        // Toggle mobile sidebar
        document.getElementById('mobile-menu-toggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            sidebarOpen = !sidebarOpen;
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');

            if (window.innerWidth < 1024) {
                document.body.style.overflow = sidebarOpen ? 'hidden' : '';
            }
        });

        // Close sidebar when overlay is clicked
        document.getElementById('sidebar-overlay').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.remove('active');
            this.classList.remove('active');
            sidebarOpen = false;
            document.body.style.overflow = '';
        });

        // Toggle payroll dropdown in sidebar
        document.getElementById('payroll-toggle').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const dropdown = document.getElementById('payroll-submenu');
            const chevron = this.querySelector('.chevron');

            payrollMenuOpen = !payrollMenuOpen;
            dropdown.classList.toggle('open');
            chevron.classList.toggle('rotated');
        });

        // Open payroll dropdown by default
        document.addEventListener('DOMContentLoaded', function() {
            const payrollDropdown = document.getElementById('payroll-submenu');
            const payrollChevron = document.getElementById('payroll-toggle').querySelector('.chevron');
            if (payrollDropdown) {
                payrollDropdown.classList.add('open');
                payrollChevron.classList.add('rotated');
            }
        });

        // Close sidebar when clicking on a sidebar link (mobile only)
        document.querySelectorAll('.sidebar-item, .submenu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (window.innerWidth < 1024) {
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.getElementById('sidebar-overlay');

                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    sidebarOpen = false;
                    document.body.style.overflow = '';
                }
            });
        });

        // Update date and time
        function updateDateTime() {
            const now = new Date();

            // Format date
            const dateOptions = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            const dateString = now.toLocaleDateString('en-US', dateOptions);

            // Format time
            const timeOptions = {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            const timeString = now.toLocaleTimeString('en-US', timeOptions);

            const dateElement = document.getElementById('current-date');
            const timeElement = document.getElementById('current-time');

            if (dateElement) dateElement.textContent = dateString;
            if (timeElement) timeElement.textContent = timeString;
        }

        // Update date/time immediately and every second
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Payroll Calculation Functions
        function calculateRow(row) {
            const monthlySalary = parseFloat(row.querySelector('.monthly-salary').dataset.original) || 0;
            const gratuity = parseFloat(row.querySelector('.gratuity').value) || 0;
            const incomeTax = parseFloat(row.querySelector('.income-tax').value) || 0;
            const communityTax = parseFloat(row.querySelector('.community-tax').value) || 0;
            const gsis = parseFloat(row.querySelector('.gsis').value) || 0;
            
            // Calculate earned salary (based on days present)
            const daysPresent = parseInt(row.querySelector('.days-present').textContent.split('/')[0]) || 0;
            const earnedSalary = (monthlySalary / 22) * daysPresent;
            
            // Calculate total deduction
            const totalDeduction = gratuity + incomeTax + communityTax + gsis;
            
            // Calculate net amount
            const netAmount = earnedSalary - totalDeduction;
            
            // Update row values
            row.querySelector('.earned-salary').value = '₱' + earnedSalary.toFixed(2);
            row.querySelector('.total-deduction').value = totalDeduction.toFixed(2);
            row.querySelector('.net-amount').value = '₱' + netAmount.toFixed(2);
            
            return {
                earnedSalary,
                totalDeduction,
                netAmount,
                gratuity,
                incomeTax,
                communityTax,
                gsis
            };
        }

        function calculateAll() {
            let totalEarned = 0;
            let totalGratuity = 0;
            let totalIncomeTax = 0;
            let totalCommunityTax = 0;
            let totalGsis = 0;
            let totalDeduction = 0;
            let totalNetAmount = 0;
            
            document.querySelectorAll('.payroll-row').forEach(row => {
                const result = calculateRow(row);
                
                totalEarned += result.earnedSalary;
                totalGratuity += result.gratuity;
                totalIncomeTax += result.incomeTax;
                totalCommunityTax += result.communityTax;
                totalGsis += result.gsis;
                totalDeduction += result.totalDeduction;
                totalNetAmount += result.netAmount;
            });
            
            // Update total displays
            document.getElementById('total-earned-salary').textContent = '₱' + totalEarned.toFixed(2);
            document.getElementById('total-gratuity').textContent = '₱' + totalGratuity.toFixed(2);
            document.getElementById('total-income-tax').textContent = '₱' + totalIncomeTax.toFixed(2);
            document.getElementById('total-community-tax').textContent = '₱' + totalCommunityTax.toFixed(2);
            document.getElementById('total-gsis').textContent = '₱' + totalGsis.toFixed(2);
            document.getElementById('total-deduction').textContent = '₱' + totalDeduction.toFixed(2);
            document.getElementById('total-net-amount').textContent = '₱' + totalNetAmount.toFixed(2);
            
            // Update stat cards
            document.getElementById('total-payroll-display').textContent = '₱' + totalEarned.toFixed(2);
            document.getElementById('total-deductions-display').textContent = '₱' + totalDeduction.toFixed(2);
            document.getElementById('net-amount-display').textContent = '₱' + totalNetAmount.toFixed(2);
            
            return {
                totalEarned,
                totalDeduction,
                totalNetAmount
            };
        }

        // Event Listeners for Calculations
        document.querySelectorAll('.gratuity, .income-tax, .community-tax, .gsis').forEach(input => {
            input.addEventListener('input', function() {
                const row = this.closest('.payroll-row');
                calculateRow(row);
                calculateAll();
            });
        });

        // Calculate all button
        document.getElementById('calculate-all-btn').addEventListener('click', function() {
            calculateAll();
            showNotification('All calculations updated successfully!', 'success');
        });

        // Calculate individual row
        document.querySelectorAll('.calculate-row').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('.payroll-row');
                calculateRow(row);
                calculateAll();
                showNotification('Row calculation updated!', 'success');
            });
        });

        // View row details
        document.querySelectorAll('.view-row').forEach(button => {
            button.addEventListener('click', function() {
                const employeeId = this.dataset.employeeId;
                const row = this.closest('.payroll-row');
                const name = row.querySelector('.font-medium').textContent.trim();
                const position = row.querySelector('td:nth-child(3)').textContent;
                const monthlySalary = row.querySelector('.monthly-salary').value;
                const earnedSalary = row.querySelector('.earned-salary').value;
                const netAmount = row.querySelector('.net-amount').value;
                
                const details = `
                    Employee: ${name}
                    Position: ${position}
                    Monthly Salary: ${monthlySalary}
                    Earned Salary: ${earnedSalary}
                    Net Amount: ${netAmount}
                `;
                
                alert(details);
            });
        });

        // Search functionality
        const searchInput = document.getElementById('search-employees');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('.payroll-row');
                
                rows.forEach(row => {
                    const name = row.querySelector('.font-medium').textContent.toLowerCase();
                    if (name.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        // Refresh button
        document.getElementById('refresh-btn').addEventListener('click', function() {
            location.reload();
        });

        // Form submission
        document.getElementById('payroll-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate all inputs
            let isValid = true;
            document.querySelectorAll('.payroll-input:not(.readonly)').forEach(input => {
                if (input.value === '' || isNaN(parseFloat(input.value))) {
                    isValid = false;
                    input.style.borderColor = '#ef4444';
                } else {
                    input.style.borderColor = '#d1d5db';
                }
            });
            
            if (!isValid) {
                showNotification('Please check all deduction inputs. They must be valid numbers.', 'error');
                return;
            }
            
            // Calculate final totals
            calculateAll();
            
            // Show confirmation
            if (confirm('Are you sure you want to save the payroll data?')) {
                this.submit();
            }
        });

        // Notification function
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notification => notification.remove());
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification ${type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'}`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            // Add to document
            document.body.appendChild(notification);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Initialize calculations on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateAll();
        });

        // Close sidebar on window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                sidebarOpen = false;
                document.body.style.overflow = '';
            }
        });

        // Initialize sidebar state
        function initSidebarState() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                sidebarOpen = false;
            }
        }

        // Initialize on page load
        initSidebarState();
    </script>
</body>
</html>