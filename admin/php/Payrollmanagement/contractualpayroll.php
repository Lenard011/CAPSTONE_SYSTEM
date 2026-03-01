<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Database configuration
$host = 'localhost';
$dbname = 'hrms_paluan';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Load personnel configuration from JSON
function loadPayrollPersonnel()
{
    $config_file = __DIR__ . '/config/payroll_personnel.json';
    $default_config = [
        'certifying_officers' => [
            'A' => ['name' => 'JOREL B. VICENTE', 'title' => 'Administrative Officer IV (HRMO II)', 'active' => true],
            'B' => ['name' => 'JULIE ANNE T. VALLESTERO, CPA', 'title' => 'Municipal Accountant', 'active' => true],
            'C' => ['name' => 'ARLENE A. DE VEAS', 'title' => 'Municipal Treasurer', 'active' => true],
            'D' => ['name' => 'HON. MICHAEL D. DIAZ', 'title' => 'Municipal Mayor', 'active' => true],
            'F' => ['name' => 'EVA V. DUEÑAS', 'title' => 'Disbursing Officer', 'active' => true]
        ],
        'entity_info' => [
            'entity_name' => 'LGU PALUAN',
            'fund_cluster' => 'General Fund',
            'address' => 'Paluan, Occidental Mindoro'
        ]
    ];

    // Create config directory if it doesn't exist
    $config_dir = dirname($config_file);
    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }

    if (file_exists($config_file)) {
        $json_content = file_get_contents($config_file);
        $config = json_decode($json_content, true);
        if (json_last_error() === JSON_ERROR_NONE && $config) {
            return $config;
        }
    }

    // Create default config file if it doesn't exist
    file_put_contents($config_file, json_encode($default_config, JSON_PRETTY_PRINT));

    return $default_config;
}

// Load the configuration
$personnel_config = loadPayrollPersonnel();
$certifying_officers = $personnel_config['certifying_officers'];
$entity_info = $personnel_config['entity_info'];

// Helper function to get officer info
function getOfficer($letter, $officers)
{
    $letter = strtoupper($letter);
    if (isset($officers[$letter]) && $officers[$letter]['active']) {
        return $officers[$letter];
    }
    return ['name' => '__________', 'title' => ''];
}

// Get officer information
$officer_A = getOfficer('A', $certifying_officers);
$officer_B = getOfficer('B', $certifying_officers);
$officer_C = getOfficer('C', $certifying_officers);
$officer_D = getOfficer('D', $certifying_officers);
$officer_F = getOfficer('F', $certifying_officers);

// Set entity information
$entity_name = $entity_info['entity_name'];
$fund_cluster = $entity_info['fund_cluster'];

// Get filter parameters from URL
$selected_period = isset($_GET['period']) ? $_GET['period'] : date('Y-m');
$selected_cutoff = isset($_GET['cutoff']) ? $_GET['cutoff'] : 'full';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10; // Fixed at 10 employees per page
$offset = ($page - 1) * $per_page;

// Parse the selected period
$year_month = explode('-', $selected_period);
$year = $year_month[0];
$month = $year_month[1];

// Calculate cutoff date ranges
function getWorkingDays($start_date, $end_date)
{
    $working_days = 0;
    $current = strtotime($start_date);
    $end = strtotime($end_date);

    while ($current <= $end) {
        $day_of_week = date('N', $current);
        if ($day_of_week <= 5) { // Monday to Friday
            $working_days++;
        }
        $current = strtotime('+1 day', $current);
    }
    return $working_days;
}

$cutoff_ranges = [
    'full' => [
        'start' => "$year-$month-01",
        'end' => date('Y-m-t', strtotime("$year-$month-01")),
        'label' => 'Full Month',
        'working_days' => getWorkingDays("$year-$month-01", date('Y-m-t', strtotime("$year-$month-01")))
    ],
    'first_half' => [
        'start' => "$year-$month-01",
        'end' => "$year-$month-15",
        'label' => 'First Half (1-15)',
        'working_days' => getWorkingDays("$year-$month-01", "$year-$month-15")
    ],
    'second_half' => [
        'start' => "$year-$month-16",
        'end' => date('Y-m-t', strtotime("$year-$month-01")),
        'label' => 'Second Half (16-' . date('t', strtotime("$year-$month-01")) . ')',
        'working_days' => getWorkingDays("$year-$month-16", date('Y-m-t', strtotime("$year-$month-01")))
    ]
];

$current_cutoff = $cutoff_ranges[$selected_cutoff];
$is_full_month = ($selected_cutoff == 'full');

// Logout functionality
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('remember_user', '', time() - 3600, "/");
    header('Location: login.php');
    exit();
}

// Helper function to get salary column name (from contractualpayrolltable1.php)
function getSalaryColumnName($pdo)
{
    try {
        $columns_query = $pdo->query("SHOW COLUMNS FROM contractofservice");
        $existing_columns = $columns_query->fetchAll(PDO::FETCH_COLUMN);

        $possible_salary_columns = ['wages', 'monthly_salary', 'salary', 'basic_salary', 'rate_per_day', 'daily_rate'];

        foreach ($possible_salary_columns as $col) {
            if (in_array($col, $existing_columns)) {
                return $col;
            }
        }
    } catch (Exception $e) {
        error_log("Error checking salary columns: " . $e->getMessage());
    }
    return null;
}

// Function to get employee's payroll data (from contractualpayrolltable1.php)
function getEmployeePayrollData($pdo, $employee_id, $period, $cutoff, $prorated_salary = 0)
{
    $data = [
        'other_comp' => 0,
        'withholding_tax' => 0,
        'sss' => 0,
        'total_deductions' => 0,
        'gross_amount' => $prorated_salary,
        'net_amount' => $prorated_salary,
        'days_present' => 0,
        'status' => 'draft',
        'payroll_id' => null,
        'exists' => false
    ];

    try {
        if ($cutoff == 'full') {
            // For full month, get data from both halves and sum them
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(other_comp), 0) as other_comp,
                    COALESCE(SUM(withholding_tax), 0) as withholding_tax,
                    COALESCE(SUM(sss), 0) as sss,
                    COALESCE(SUM(total_deductions), 0) as total_deductions,
                    COALESCE(SUM(gross_amount), 0) as gross_amount,
                    COALESCE(SUM(net_amount), 0) as net_amount,
                    COALESCE(SUM(days_present), 0) as days_present,
                    COUNT(*) as record_count
                FROM payroll_history 
                WHERE employee_id = ? AND employee_type = 'contractual' 
                    AND payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half')
            ");
            $stmt->execute([$employee_id, $period]);
            $result = $stmt->fetch();

            if ($result && $result['record_count'] > 0) {
                $data['other_comp'] = floatval($result['other_comp']);
                $data['withholding_tax'] = floatval($result['withholding_tax']);
                $data['sss'] = floatval($result['sss']);
                $data['total_deductions'] = floatval($result['total_deductions']);
                $data['gross_amount'] = floatval($result['gross_amount']);
                $data['net_amount'] = floatval($result['net_amount']);
                $data['days_present'] = floatval($result['days_present']);
                $data['exists'] = true;
            }
        } else {
            // For specific half, get just that half's data
            $stmt = $pdo->prepare("
                SELECT id, other_comp, withholding_tax, sss, total_deductions, gross_amount, net_amount, days_present, status
                FROM payroll_history 
                WHERE employee_id = ? AND employee_type = 'contractual' 
                    AND payroll_period = ? AND payroll_cutoff = ?
            ");
            $stmt->execute([$employee_id, $period, $cutoff]);
            $result = $stmt->fetch();

            if ($result) {
                $data['other_comp'] = floatval($result['other_comp'] ?? 0);
                $data['withholding_tax'] = floatval($result['withholding_tax'] ?? 0);
                $data['sss'] = floatval($result['sss'] ?? 0);
                $data['total_deductions'] = floatval($result['total_deductions'] ?? 0);
                $data['gross_amount'] = floatval($result['gross_amount'] ?? 0);
                $data['net_amount'] = floatval($result['net_amount'] ?? 0);
                $data['days_present'] = floatval($result['days_present'] ?? 0);
                $data['status'] = $result['status'] ?? 'draft';
                $data['payroll_id'] = $result['id'] ?? null;
                $data['exists'] = true;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching payroll data: " . $e->getMessage());
    }

    return $data;
}

// Helper function to get employee's community tax certificate
function getCommunityTaxCertificate($pdo, $employee_id, $period)
{
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'employee_cedula'");
        if ($table_check->rowCount() == 0) {
            return ['number' => '', 'date' => ''];
        }

        $stmt = $pdo->prepare("
            SELECT cedula_number, date_issued 
            FROM employee_cedula 
            WHERE employee_id = ? AND (year = ? OR year = YEAR(?))
            ORDER BY date_issued DESC 
            LIMIT 1
        ");
        $year = date('Y', strtotime($period . '-01'));
        $stmt->execute([$employee_id, $year, $period]);
        $result = $stmt->fetch();

        if ($result) {
            return [
                'number' => $result['cedula_number'] ?? '',
                'date' => $result['date_issued'] ?? ''
            ];
        }
    } catch (Exception $e) {
        error_log("Error fetching community tax: " . $e->getMessage());
    }
    return ['number' => '', 'date' => ''];
}

// Get total count of employees
$total_employees = 0;
try {
    $count_sql = "SELECT COUNT(*) FROM contractofservice WHERE status = 'active'";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute();
    $total_employees = $count_stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Error counting employees: " . $e->getMessage());
    $total_employees = 0;
}

$total_pages = max(1, ceil($total_employees / $per_page));

// Fetch contractual employees (exactly like contractualpayrolltable1.php)
$contractual_employees = [];
$totals = [
    'monthly_salaries' => 0,
    'other_comp' => 0,
    'gross_amount' => 0,
    'withholding_tax' => 0,
    'sss' => 0,
    'total_deductions' => 0,
    'net_amount' => 0
];

try {
    $salary_column = getSalaryColumnName($pdo);

    $columns_query = $pdo->query("SHOW COLUMNS FROM contractofservice");
    $existing_columns = $columns_query->fetchAll(PDO::FETCH_COLUMN);

    $select_fields = "id as user_id, employee_id, CONCAT(first_name, ' ', last_name) as full_name, first_name, last_name, designation as position, office as department, period_from, period_to, status";

    // Add address field if it exists
    if (in_array('address', $existing_columns)) {
        $select_fields .= ", address";
    } else {
        $select_fields .= ", 'Paluan Occ. Mdo.' as address";
    }

    // Get salary
    $salary_col = getSalaryColumnName($pdo);
    if ($salary_col) {
        if ($salary_col == 'rate_per_day' || $salary_col == 'daily_rate') {
            $select_fields .= ", ($salary_col * 22) as monthly_salary";
        } else {
            $select_fields .= ", $salary_col as monthly_salary";
        }
    } else {
        $select_fields .= ", 0 as monthly_salary";
    }

    $sql = "
        SELECT $select_fields
        FROM contractofservice 
        WHERE status = 'active'
        ORDER BY last_name, first_name 
        LIMIT :offset, :per_page
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $employees = $stmt->fetchAll();

    // Process each employee (exactly like contractualpayrolltable1.php)
    foreach ($employees as &$employee) {
        // Get attendance for the selected cutoff period
        $attendance_days = 0;
        $total_hours = 0;

        try {
            $table_check = $pdo->query("SHOW TABLES LIKE 'attendance'");
            if ($table_check->rowCount() > 0) {
                $att_columns = $pdo->query("SHOW COLUMNS FROM attendance")->fetchAll(PDO::FETCH_COLUMN);

                if (in_array('total_hours', $att_columns)) {
                    $attendance_stmt = $pdo->prepare("
                        SELECT 
                            SUM(CASE 
                                WHEN total_hours >= 8 THEN 1
                                WHEN total_hours >= 4 THEN 0.5
                                ELSE 0
                            END) as attendance_days,
                            SUM(total_hours) as total_hours_worked
                        FROM attendance 
                        WHERE employee_id = ? 
                            AND date BETWEEN ? AND ?
                            AND total_hours > 0
                    ");
                    $attendance_stmt->execute([
                        $employee['employee_id'],
                        $current_cutoff['start'],
                        $current_cutoff['end']
                    ]);
                    $attendance = $attendance_stmt->fetch();

                    $attendance_days = floatval($attendance['attendance_days'] ?? 0);
                    $total_hours = floatval($attendance['total_hours_worked'] ?? 0);
                }
            }
        } catch (Exception $e) {
            error_log("Attendance fetch error: " . $e->getMessage());
        }

        $employee['days_present'] = $attendance_days;
        $employee['total_hours'] = $total_hours;

        $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
        $daily_rate = $monthly_salary / 22;
        $prorated_salary = $daily_rate * $attendance_days;

        // Get payroll data from database
        $payroll_data = getEmployeePayrollData($pdo, $employee['user_id'], $selected_period, $selected_cutoff, $prorated_salary);

        // Get community tax certificate
        $cedula = getCommunityTaxCertificate($pdo, $employee['user_id'], $selected_period);

        $employee['other_comp'] = $payroll_data['other_comp'];
        $employee['withholding_tax'] = $payroll_data['withholding_tax'];
        $employee['sss'] = $payroll_data['sss'];
        $employee['total_deductions'] = $payroll_data['total_deductions'];
        $employee['net_amount'] = $payroll_data['net_amount'];
        $employee['gross_amount'] = $payroll_data['gross_amount'] > 0 ? $payroll_data['gross_amount'] : $prorated_salary + $payroll_data['other_comp'];
        $employee['monthly_salary'] = $monthly_salary;
        $employee['daily_rate'] = $daily_rate;
        $employee['prorated_salary'] = $prorated_salary;
        $employee['payroll_status'] = $payroll_data['status'];
        $employee['payroll_id'] = $payroll_data['payroll_id'];
        $employee['payroll_exists'] = $payroll_data['exists'];
        $employee['community_tax_number'] = $cedula['number'];
        $employee['community_tax_date'] = $cedula['date'];

        // Update totals
        $totals['monthly_salaries'] += $prorated_salary;
        $totals['other_comp'] += $payroll_data['other_comp'];
        $totals['gross_amount'] += $payroll_data['gross_amount'] > 0 ? $payroll_data['gross_amount'] : $prorated_salary + $payroll_data['other_comp'];
        $totals['withholding_tax'] += $payroll_data['withholding_tax'];
        $totals['sss'] += $payroll_data['sss'];
        $totals['total_deductions'] += $payroll_data['total_deductions'];
        $totals['net_amount'] += $payroll_data['net_amount'];
    }

    $contractual_employees = $employees;
} catch (Exception $e) {
    error_log("Error fetching employees: " . $e->getMessage());
    $contractual_employees = [];
}

// Get entity information
$payroll_number = "CON-" . date('Ymd', strtotime($selected_period . '-01')) . "-" . strtoupper(substr($selected_cutoff, 0, 1));

// Format period display
$period_display = date('F d, Y', strtotime($current_cutoff['start'])) . ' - ' . date('F d, Y', strtotime($current_cutoff['end']));

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contractual Payroll</title>
    <!-- Use only one version of Flowbite -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet" />
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

        /* User Menu */
        .user-menu {
            position: relative;
        }

        .user-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 0.4rem 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            outline: none;
        }

        .user-button:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .user-chevron {
            font-size: 0.8rem;
            color: white;
            opacity: 0.8;
            transition: transform 0.3s ease;
        }

        .user-button.active .user-chevron {
            transform: rotate(180deg);
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 280px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow: hidden;
        }

        .user-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-header {
            padding: 1.25rem;
            background: var(--gradient-nav);
            color: white;
        }

        .dropdown-header h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .dropdown-header p {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .dropdown-menu {
            padding: 0.5rem;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #4b5563;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: #f3f4f6;
            color: var(--primary);
            transform: translateX(5px);
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            color: #9ca3af;
        }

        .dropdown-item:hover i {
            color: var(--primary);
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

        /* Responsive utilities - NAVBAR SPECIFIC */
        @media (max-width: 768px) {
            .datetime-container {
                display: none;
            }

            .user-info {
                display: none;
            }

            .user-button {
                padding: 0.4rem;
            }

            .user-dropdown {
                position: fixed;
                top: 70px;
                right: 1rem;
                left: 1rem;
                width: auto;
                max-width: 300px;
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

            .user-avatar {
                width: 32px;
                height: 32px;
            }

            .brand-logo {
                width: 40px;
                height: 40px;
            }
        }

        /* Custom styles for the table to ensure border collapse and thin borders */
        .payroll-table,
        .payroll-table th,
        .payroll-table td {
            border: 1px solid #e5e7eb;
            border-collapse: collapse;
            font-size: 0.65rem;
            padding: 4px;
            vertical-align: top;
        }

        /* Styling for the header rows */
        .payroll-table thead tr th {
            background-color: #eff6ff;
            color: #1f2937;
            text-align: center;
            font-weight: 700;
        }

        /* Make the column headers more readable by forcing text wrap */
        .payroll-table th {
            word-wrap: break-word;
        }

        /* Center all cells in the first column (#) */
        .payroll-table tbody tr td:first-child {
            text-align: center;
        }

        /* CSS to ensure only the form content is visible and formatted for print */
        @media print {

            /* Force all text to be black */
            * {
                color: #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Ensure the payroll container prints properly */
            .max-w-7xl.mx-auto.bg-white.shadow-lg.p-2.mt-4 {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 5mm !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                border: 1px solid #000 !important;
                width: 100% !important;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
            }

            /* Ensure table fits perfectly */
            .payroll-table {
                width: 100% !important;
                font-size: 8px !important;
                table-layout: fixed !important;
                border-collapse: collapse !important;
                page-break-inside: avoid !important;
            }

            /* Thin borders for all cells */
            .payroll-table th,
            .payroll-table td {
                border: 0.5px solid #000000 !important;
                padding: 2px 3px !important;
                line-height: 1.1 !important;
                min-height: 18px !important;
                vertical-align: middle !important;
            }

            /* Table header styling */
            .payroll-table thead th {
                background-color: #f0f0f0 !important;
                font-weight: bold !important;
                border: 0.5px solid #000000 !important;
                font-size: 8px !important;
                padding: 3px 4px !important;
            }

            /* Signature sections */
            .section-box {
                page-break-inside: avoid !important;
                border: 0.5px solid #000000 !important;
                margin-bottom: 2mm !important;
                padding: 1.5mm !important;
                min-height: 25mm !important;
                font-size: 9px !important;
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

        /* Sidebar */
        .sidebar-container {
            position: fixed;
            left: 0;
            top: 70px;
            width: 260px;
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
            z-index: 999;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
        }

        .sidebar-container::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .sidebar {
            height: 100%;
            padding: 1.5rem 0;
        }

        .sidebar-content {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            padding: 0 1rem;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.9rem 1.25rem;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 12px;
            margin-bottom: 0.25rem;
            position: relative;
            overflow: hidden;
        }

        .sidebar-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: white;
            transform: scaleY(0);
            transition: transform 0.3s ease;
            border-radius: 0 4px 4px 0;
        }

        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.12);
            color: white;
            transform: translateX(4px);
        }

        .sidebar-item:hover::before {
            transform: scaleY(1);
        }

        .sidebar-item.active {
            background: rgba(255, 255, 255, 0.18);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .sidebar-item.active::before {
            transform: scaleY(1);
        }

        .sidebar-item i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .sidebar-item span {
            font-size: 0.95rem;
            font-weight: 600;
            flex: 1;
        }

        .sidebar-item.logout {
            color: #fecaca;
            margin-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
        }

        .sidebar-item.logout:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #fecaca;
        }

        .payroll-table {
            min-width: 1400px;
        }

        .payroll-table th,
        .payroll-table td {
            white-space: nowrap;
            border: 1px solid #e2e8f0;
        }

        .payroll-table td {
            padding: 8px 12px;
        }

        .payroll-table thead th {
            font-weight: 600;
            background: #f7fafc;
        }

        /* Make sure columns don't overlap */
        .payroll-table {
            table-layout: auto;
        }

        /* For printing */
        @media print {
            .payroll-table {
                min-width: 100% !important;
                font-size: 10px !important;
            }

            .payroll-table th,
            .payroll-table td {
                padding: 4px 6px !important;
            }
        }

        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .pagination-btn {
            padding: 0.5rem 0.75rem;
            border: 1px solid #e5e7eb;
            background-color: white;
            color: #4b5563;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .pagination-btn:hover {
            background-color: #f3f4f6;
            border-color: #d1d5db;
        }

        .pagination-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Period info badge */
        .period-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: #dbeafe;
            color: #1e40af;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Cutoff selector styles - from contractualpayrolltable1.php */
        .cutoff-selector {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .cutoff-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #4b5563;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .cutoff-btn:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }

        .cutoff-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .cutoff-btn i {
            margin-right: 0.25rem;
        }

        .cutoff-info {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        /* Header action bar - from contractualpayrolltable1.php */
        .header-action-bar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 1024px) {
            .header-action-bar {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            line-height: 1.2;
        }

        .page-subtitle {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .controls-wrapper {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            width: 100%;
        }

        @media (min-width: 1024px) {
            .controls-wrapper {
                width: auto;
                min-width: 400px;
            }
        }

        .period-cutoff-wrapper {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Navigation Header -->
    <nav class="navbar print-hide">
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

                <!-- User Menu -->
                <div class="user-menu">
                    <button class="user-button" id="user-menu-button">
                        <img class="user-avatar" src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name'] ?? 'User'); ?>&background=1e40af&color=fff" alt="User">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin User'); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Administrator'); ?></span>
                        </div>
                        <i class="fas fa-chevron-down user-chevron"></i>
                    </button>
                    <div class="user-dropdown" id="user-dropdown">
                        <div class="dropdown-header">
                            <h3><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin User'); ?></h3>
                            <p><?php echo htmlspecialchars($_SESSION['email'] ?? 'admin@example.com'); ?></p>
                        </div>
                        <div class="dropdown-menu">
                            <a href="../profile.php" class="dropdown-item">
                                <i class="fas fa-user-circle"></i>
                                My Profile
                            </a>
                            <a href="../settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                Settings
                            </a>
                            <hr class="my-2 border-gray-200">
                            <a href="?logout=true" class="dropdown-item text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay print-hide" id="sidebar-overlay"></div>

    <!-- Sidebar -->
    <div class="sidebar print-hide" id="sidebar">
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
                        <a href="#" class="submenu-item active">
                            <i class="fas fa-circle text-xs"></i>
                            Contractual
                        </a>
                        <a href="joboerderpayrolltable1.php" class="submenu-item">
                            <i class="fas fa-circle text-xs"></i>
                            Job Order
                        </a>
                        <a href="permanentpayrolltable1.php" class="submenu-item">
                            <i class="fas fa-circle text-xs"></i>
                            Permanent
                        </a>
                    </div>
                </li>

                <!-- Reports -->
                <li>
                    <a href="../paysliplist.php" class="sidebar-item">
                        <i class="fas fa-file-alt"></i>
                        <span>Reports</span>
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

    <!-- MAIN -->
    <main class="main-content">
        <div class="breadcrumb-container print-hide">
            <nav class="mt-4 flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-2">
                    <li class="inline-flex items-center">
                        <a href="contractualpayrolltable1.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-primary-600 md:ml-2">
                            <i class="fas fa-home mr-2"></i> Contractual Payroll
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                            <a href="contractualpayroll.php?period=<?php echo urlencode($selected_period); ?>&cutoff=<?php echo urlencode($selected_cutoff); ?>&page=<?php echo $page; ?>" class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-700">General Payroll</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                            <a href="contractualobligationrequest.php?period=<?php echo urlencode($selected_period); ?>&cutoff=<?php echo urlencode($selected_cutoff); ?>" class="ml-1 text-sm font-medium text-gray-700 hover:text-primary-600 md:ml-2"> Contractual Obligation Request</a>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>

        <!-- Page Header with Title and Controls (from contractualpayrolltable1.php) -->
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-4">
            <!-- Left side - Title -->
            <div class="flex items-center gap-3">
                <div class="bg-primary-100 p-2 rounded-lg">
                    <i class="fas fa-file-invoice text-primary-600 text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Contractual Payroll</h1>
                    <p class="text-xs text-gray-500">Generate and manage payroll</p>
                </div>
            </div>

            <!-- Right side - Controls (from contractualpayrolltable1.php) -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <!-- Period and Cutoff Group -->
                <div class="flex flex-col sm:flex-row bg-white border border-gray-200 rounded-lg overflow-hidden divide-y sm:divide-y-0 sm:divide-x divide-gray-200">
                    <!-- Period Dropdown -->
                    <select id="payroll-period" class="px-3 py-2 text-sm border-0 focus:ring-0 bg-transparent min-w-[140px]">
                        <?php
                        for ($i = 0; $i < 12; $i++) {
                            $date = date('Y-m', strtotime("-$i months"));
                            $display = date('F Y', strtotime("-$i months"));
                            $selected = ($date == $selected_period) ? 'selected' : '';
                            echo "<option value=\"$date\" $selected>$display</option>";
                        }
                        ?>
                    </select>

                    <!-- Cutoff Buttons Group (from contractualpayrolltable1.php) -->
                    <div class="flex divide-x divide-gray-200">
                        <a href="?period=<?php echo $selected_period; ?>&cutoff=full&page=1"
                            class="px-3 py-2 text-xs font-medium <?php echo ($selected_cutoff == 'full') ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            Full
                        </a>
                        <a href="?period=<?php echo $selected_period; ?>&cutoff=first_half&page=1"
                            class="px-3 py-2 text-xs font-medium <?php echo ($selected_cutoff == 'first_half') ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            1st Half
                        </a>
                        <a href="?period=<?php echo $selected_period; ?>&cutoff=second_half&page=1"
                            class="px-3 py-2 text-xs font-medium <?php echo ($selected_cutoff == 'second_half') ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            2nd Half
                        </a>
                    </div>

                    <!-- Compact Info Badge -->
                    <div class="hidden sm:flex items-center px-3 bg-gray-50">
                        <span class="text-xs text-gray-600 whitespace-nowrap">
                            <i class="fas fa-calendar text-gray-400 mr-1"></i>
                            <?php echo date('M d', strtotime($current_cutoff['start'])); ?> -
                            <?php echo date('d', strtotime($current_cutoff['end'])); ?>
                            (<?php echo $current_cutoff['working_days']; ?>d)
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Period Info Bar -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-3 mb-4 flex flex-wrap items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="period-badge">
                    <i class="fas fa-calendar-alt mr-1"></i> <?php echo date('F Y', strtotime($selected_period . '-01')); ?>
                </span>
                <span class="period-badge">
                    <i class="fas fa-cut mr-1"></i> <?php echo $current_cutoff['label']; ?>
                </span>
                <span class="text-sm text-gray-600">
                    <i class="fas fa-calendar-week mr-1"></i> <?php echo date('M d', strtotime($current_cutoff['start'])); ?> - <?php echo date('M d, Y', strtotime($current_cutoff['end'])); ?>
                    (<?php echo $current_cutoff['working_days']; ?> working days)
                </span>
            </div>
            <div class="text-sm text-gray-600">
                <i class="fas fa-users mr-1"></i> Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> | Total: <?php echo $total_employees; ?> employees
            </div>
        </div>

        <!-- Business Rules Info Banner (from contractualpayrolltable1.php) -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>Payroll Calculation Rules:</strong>
                        Gross amount is calculated based on days present × daily rate + other compensation.
                        <span class="font-bold text-green-700">For Full Month view, all deduction fields (Other Compensation, Withholding Tax, and SSS Contribution) are read-only and must be edited in Half Month views.</span>
                        <?php if ($is_full_month): ?>
                            You are viewing <strong>Full Month</strong>. All deduction fields are disabled.
                            Please switch to First Half or Second Half to edit these values.
                        <?php else: ?>
                            You are viewing <strong><?php echo $current_cutoff['label']; ?></strong>.
                            All deduction fields can be edited here.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto bg-white shadow-lg rounded-lg p-2 mt-4">
            <!-- to print -->
            <div>
                <div class="absolute right-12">
                    <p class="text-xs font-medium">Appendix 33</p>
                </div>
                <!-- Header Section with Appendix on top right -->
                <div class="relative mb-4 mt-5">
                    <div class="text-center">
                        <h1 class="text-lg font-bold">PAYROLL</h1>
                    </div>

                    <!-- Period section below PAYROLL title -->
                    <div class="flex justify-center font-bold">
                        <div class="flex items-center">
                            <span class="whitespace-nowrap text-[12px]">For the period:</span>
                            <div class="relative">
                                <span class="relative z-10 px-1 text-[12px] uppercase"><?php echo $period_display; ?></span>
                                <div class="absolute bottom-0 mb-[4px] left-0 right-0 border-b border-gray-500"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Entity Information Section -->
                <div class="flex flex-col md:flex-row justify-between text-xs mb-4 pb-2">
                    <div class="mb-2 md:mb-0">
                        <div class="flex items-center mb-2">
                            <strong class="whitespace-nowrap text-[12px]">Entity Name:</strong>
                            <div class="relative flex-1">
                                <span class="relative z-10 px-1 bg-white text-[11px]"><?php echo $entity_name; ?></span>
                                <div class="absolute bottom-0 left-0 right-0 w-[200px] border-b border-gray-500"></div>
                            </div>
                        </div>
                        <div class="flex">
                            <strong class="whitespace-nowrap text-[12px]">Fund/Cluster:</strong>
                            <div class="relative flex-1">
                                <span class="relative z-10 px-1 bg-white text-[11px]"><?php echo $fund_cluster; ?></span>
                                <div class="absolute bottom-0 left-0 right-0 w-[195px] border-b border-gray-500"></div>
                            </div>
                        </div>
                    </div>
                    <div class="text-left mr-20">
                        <div class="flex justify-end mb-2">
                            <strong class="whitespace-nowrap">Payroll No. :</strong>
                            <div class="relative w-[119px]">
                                <span class="relative z-10 px-1 bg-white"><?php echo $payroll_number; ?></span>
                                <div class="absolute bottom-0 left-0 right-0 border-b border-gray-500"></div>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <strong class="whitespace-nowrap">Sheet:</strong>
                            <div class="relative">
                                <!-- Split the text into separate underlined parts -->
                                <div class="flex justify-between">
                                    <div class="relative">
                                        <span class="relative z-10 px-1 bg-white ml-4"><?php echo $page; ?></span>
                                        <div class="absolute bottom-0 left-0 w-[50px] right-0 border-b border-gray-500"></div>
                                    </div>
                                    <span class="mx-1 ml-5 mr-1">of</span>
                                    <div class="relative">
                                        <span class="relative z-10 px-1 bg-white ml-4"><?php echo $total_pages; ?></span>
                                        <div class="absolute bottom-0 left-0 w-[50px] right-0 border-b border-gray-500"></div>
                                    </div>
                                    <span class="ml-5">Sheets</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-xs italic mb-4">
                    We acknowledge receipt of cash shown opposite our names as full compensation for services rendered for the period covered.
                </p>

                <div class="payroll-table-container overflow-auto border bg-white">
                    <table class="payroll-table w-full border-collapse text-sm">
                        <thead class="bg-gray-100 text-gray-700">
                            <!-- Header Row 1 -->
                            <tr class="border-b uppercase">
                                <th rowspan="2" class="border p-2 w-10">#</th>
                                <th rowspan="2" class="border p-2">Name</th>
                                <th rowspan="2" class="border p-2">Position</th>
                                <th rowspan="2" class="border p-2">Address</th>
                                <th colspan="3" class="border p-2 text-center bg-blue-50">Compensation</th>
                                <th colspan="3" class="border p-2 text-center bg-red-50">Deductions</th>
                                <th colspan="2" class="border p-2 text-center bg-green-50">Community Tax Certificate</th>
                                <th rowspan="2" class="border p-2 text-center bg-green-50">
                                    <div>Net Amount</div>
                                    <div>Due</div>
                                </th>
                                <th rowspan="2" class="border p-2">Signature</th>
                            </tr>

                            <!-- Header Row 2 (Sub-headers) -->
                            <tr class="border-b text-xs">
                                <!-- Compensation sub-headers -->
                                <th class="border p-1 print-header">
                                    <div>Monthly</div>
                                    <div>Salaries</div>
                                    <div>and Wages</div>
                                </th>
                                <th class="border p-1 print-header">
                                    <div>Other</div>
                                    <div>Compen-</div>
                                    <div>sation</div>
                                </th>
                                <th class="border p-1 print-header">
                                    <div>Gross</div>
                                    <div>Amount</div>
                                    <div>Earned</div>
                                </th>

                                <!-- Deductions sub-headers -->
                                <th class="border p-1 print-header">
                                    <div>With-</div>
                                    <div>holding</div>
                                    <div>Tax</div>
                                </th>
                                <th class="border p-1 print-header">
                                    <div>SSS</div>
                                    <div>Contri-</div>
                                    <div>bution</div>
                                </th>
                                <th class="border p-1 print-header">
                                    <div>Total</div>
                                    <div>Deduc-</div>
                                    <div>tions</div>
                                </th>

                                <!-- Community Tax Certificate sub-headers -->
                                <th class="border p-1 print-header">
                                    <div>Number</div>
                                </th>
                                <th class="border p-1 print-header">
                                    <div>Date</div>
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($contractual_employees)): ?>
                                <tr>
                                    <td colspan="16" class="text-center py-8 text-gray-500">
                                        No employees found for this period.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $counter = $offset + 1; ?>
                                <?php foreach ($contractual_employees as $employee): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="border p-2 text-center"><?php echo $counter++; ?></td>
                                        <td class="border p-2 font-medium"><?php echo htmlspecialchars($employee['full_name']); ?></td>
                                        <td class="border p-2"><?php echo htmlspecialchars($employee['position']); ?></td>
                                        <td class="border p-2"><?php echo htmlspecialchars($employee['address'] ?? 'Paluan Occ. Mdo.'); ?></td>

                                        <!-- Compensation -->
                                        <td class="border p-2 text-right"><?php echo number_format($employee['prorated_salary'], 2); ?></td>
                                        <td class="border p-2 text-right"><?php echo $employee['other_comp'] > 0 ? number_format($employee['other_comp'], 2) : ''; ?></td>
                                        <td class="border p-2 text-right"><?php echo number_format($employee['gross_amount'], 2); ?></td>

                                        <!-- Deductions -->
                                        <td class="border p-2 text-right"><?php echo $employee['withholding_tax'] > 0 ? number_format($employee['withholding_tax'], 2) : ''; ?></td>
                                        <td class="border p-2 text-right"><?php echo $employee['sss'] > 0 ? number_format($employee['sss'], 2) : ''; ?></td>
                                        <td class="border p-2 text-right"><?php echo number_format($employee['total_deductions'], 2); ?></td>

                                        <!-- Community Tax Cert -->
                                        <td class="border p-2 text-center"><?php echo htmlspecialchars($employee['community_tax_number']); ?></td>
                                        <td class="border p-2 text-center"><?php echo $employee['community_tax_date'] ? date('m/d/Y', strtotime($employee['community_tax_date'])) : ''; ?></td>

                                        <!-- Net Amount -->
                                        <td class="border p-2 text-right font-bold"><?php echo number_format($employee['net_amount'], 2); ?></td>

                                        <!-- Signature -->
                                        <td class="border p-2"></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Total Row -->
                            <tr class="bg-gray-100 font-bold border-t-2">
                                <td colspan="4" class="border p-2 text-right">TOTAL AMOUNT</td>
                                <td class="border p-2 text-right"><?php echo number_format($totals['monthly_salaries'], 2); ?></td>
                                <td class="border p-2 text-right"><?php echo number_format($totals['other_comp'], 2); ?></td>
                                <td class="border p-2 text-right"><?php echo number_format($totals['gross_amount'], 2); ?></td>
                                <td class="border p-2 text-right"><?php echo number_format($totals['withholding_tax'], 2); ?></td>
                                <td class="border p-2 text-right"><?php echo number_format($totals['sss'], 2); ?></td>
                                <td class="border p-2 text-right"><?php echo number_format($totals['total_deductions'], 2); ?></td>
                                <td class="border p-2"></td>
                                <td class="border p-2"></td>
                                <td class="border p-2 text-right"><?php echo number_format($totals['net_amount'], 2); ?></td>
                                <td class="border p-2"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination print-hide mt-4">
                        <button class="pagination-btn" onclick="goToPage(1)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                            <i class="fas fa-angle-double-left"></i>
                        </button>
                        <button class="pagination-btn" onclick="goToPage(<?php echo $page - 1; ?>)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                            <i class="fas fa-angle-left"></i>
                        </button>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <button class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>" onclick="goToPage(<?php echo $i; ?>)">
                                <?php echo $i; ?>
                            </button>
                        <?php endfor; ?>

                        <button class="pagination-btn" onclick="goToPage(<?php echo $page + 1; ?>)" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                            <i class="fas fa-angle-right"></i>
                        </button>
                        <button class="pagination-btn" onclick="goToPage(<?php echo $total_pages; ?>)" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                            <i class="fas fa-angle-double-right"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 text-xs">
                        <!-- Set A -->
                        <div class="section-box border border-gray-300 px-2">
                            <p class="font-bold">A. CERTIFIED: Services duly rendered as stated.</p>
                            <div class="flex flex-row mt-10 items-end justify-center w-full">
                                <div class="">
                                    <p class="font-bold text-center"><?php echo htmlspecialchars($officer_A['name']); ?></p>
                                    <p class="text-center border-gray-600 italic text-[11px] "><?php echo htmlspecialchars($officer_A['title']); ?></p>
                                </div>
                                <div class="flex flex-row ml-4">
                                    <p class="relative">Date</p>
                                    <p class="border-b border-gray-600 min-w-[70px]"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Set C -->
                        <div class="section-box border border-gray-300 px-2">
                            <div class="flex justify-between">
                                <p class="font-bold">C. CERTIFIED: Cash available in the amount of</p>
                                <p class="font-bold text-right border-b border-gray-600 min-w-[50px]"><span>₱ </span><?php echo number_format($totals['net_amount'], 2); ?></p>
                            </div>
                            <div class="flex flex-row mt-10 items-end justify-between w-full">
                                <div class="flex-1"></div>
                                <div class="mr-5">
                                    <p class="font-bold text-center"><?php echo htmlspecialchars($officer_C['name']); ?></p>
                                    <p class="text-center border-gray-600 italic text-[11px]"><?php echo htmlspecialchars($officer_C['title']); ?></p>
                                </div>
                                <div class="flex flex-row items-end">
                                    <p class="relative">Date</p>
                                    <p class="border-b border-gray-600 min-w-[70px] ml-2"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Set F -->
                        <div class="section-box border border-gray-300 px-2">
                            <div class="flex justify-between">
                                <p class="font-bold">F. CERTIFIED: Each employee whose name appears on the payroll has been paid the amount as indicated opposite his/her name.</p>
                            </div>
                            <div class="mt-5">
                                <p class="font-bold text-center"><?php echo htmlspecialchars($officer_F['name']); ?></p>
                                <p class="text-center border-gray-600 italic text-[11px]"><?php echo htmlspecialchars($officer_F['title']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 text-xs">
                        <!-- Set B -->
                        <div class="section-box border border-gray-300 px-2">
                            <p class="font-bold">B. CERTIFIED: Supporting documents complete and proper.</p>
                            <div class="flex flex-row mt-5 items-end justify-center w-full">
                                <div class="">
                                    <p class="font-bold text-center"><?php echo htmlspecialchars($officer_B['name']); ?></p>
                                    <p class="text-center border-gray-600 italic text-[11px] "><?php echo htmlspecialchars($officer_B['title']); ?></p>
                                </div>
                                <div class="flex flex-row ml-4">
                                    <p class="relative">Date</p>
                                    <p class="border-b border-gray-600 min-w-[70px]"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Set D -->
                        <div class="section-box border border-gray-300 px-2">
                            <p class="font-bold">D. APPROVED: For payment</p>
                            <div class="flex flex-row mt-10 items-end justify-center w-full">
                                <div class="">
                                    <p class="font-bold text-center"><?php echo htmlspecialchars($officer_D['name']); ?></p>
                                    <p class="text-center border-gray-600 italic text-[11px] "><?php echo htmlspecialchars($officer_D['title']); ?></p>
                                </div>
                                <div class="flex flex-row ml-4">
                                    <p class="relative">Date</p>
                                    <p class="border-b border-gray-600 min-w-[70px]"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Set E -->
                        <div class="section-box border border-gray-300 p-2">
                            <p class="font-bold">E.</p>
                            <div class="flex justify-between">
                                <p>ORS/BURS No. :</p>
                                <p class="border-b border-gray-600 w-3/5"></p>
                            </div>
                            <div class="flex justify-between">
                                <p>Date</p>
                                <p class="border-b border-gray-600 w-3/5"></p>
                            </div>
                            <div class="flex justify-between">
                                <p>Jev No. :</p>
                                <p class="border-b border-gray-600 w-3/5"></p>
                            </div>
                            <div class="flex justify-between">
                                <p>Date</p>
                                <p class="border-b border-gray-600 w-3/5"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-4 border-t border-gray-200 print-hide">
                <div class="flex flex-col md:flex-row justify-end space-y-3 md:space-y-0 md:space-x-4">
                    <button id="print-btn" type="button" class="flex items-center justify-center text-gray-900 bg-white border border-gray-300 hover:bg-gray-50 focus:ring-2 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-3 w-full md:w-auto transition-all duration-200 shadow-sm hover:shadow">
                        <svg class="w-4 h-4 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M5 20h10a1 1 0 0 0 1-1v-5H4v5a1 1 0 0 0 1 1Z" />
                            <path d="M18 7H2a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2v-3a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Zm-1-2V2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3h14Z" />
                        </svg>
                        Print Payroll
                    </button>

                    <!-- <button id="save-btn" type="button" class="flex items-center justify-center text-white bg-green-600 hover:bg-green-700 focus:ring-2 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-3 w-full md:w-auto transition-all duration-200 shadow-sm hover:shadow">
                        <svg class="w-4 h-4 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M14.707 7.793a1 1 0 0 0-1.414 0L11 10.086V1.5a1 1 0 0 0-2 0v8.586L6.707 7.793a1 1 0 1 0-1.414 1.414l4 4a1 1 0 0 0 1.416 0l4-4a1 1 0 0 0-.002-1.414Z" />
                            <path d="M18 12h-2.55l-2.975 2.975a3.5 3.5 0 0 1-4.95 0L4.55 12H2a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2Zm-3 5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z" />
                        </svg>
                        Save Changes
                    </button> -->

                    <a href="contractualobligationrequest.php?period=<?php echo urlencode($selected_period); ?>&cutoff=<?php echo urlencode($selected_cutoff); ?>" class="w-full md:w-auto">
                        <button id="next-btn" type="button" class="flex items-center justify-center text-white bg-blue-600 hover:bg-blue-700 focus:ring-2 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-3 w-full md:w-auto transition-all duration-200 shadow-sm hover:shadow">
                            Next
                            <svg class="w-4 h-4 ml-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9" />
                            </svg>
                        </button>
                    </a>
                </div>
            </div>
        </div>

        <!-- Edit Personnel Button - Always visible for all logged-in users -->
        <div class="fixed bottom-4 right-4 z-50 print-hide">
            <button onclick="toggleEditModal()" class="bg-blue-600 text-white p-3 rounded-full shadow-lg hover:bg-blue-700 flex items-center justify-center" style="width: 50px; height: 50px;">
                <i class="fas fa-edit text-xl"></i>
            </button>
        </div>

        <!-- Edit Personnel Modal -->
        <div id="editPersonnelModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center" style="z-index: 99999;">
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold">Edit Certifying Officers</h3>
                    <button onclick="toggleEditModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form id="personnelEditForm" onsubmit="savePersonnelChanges(event)">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Officer A -->
                        <div class="border p-3 rounded">
                            <h4 class="font-bold mb-2 text-blue-800">Officer A (HRMO)</h4>
                            <div class="mb-2">
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="officer_A_name" value="<?php echo htmlspecialchars($officer_A['name']); ?>" class="w-full border rounded p-2 text-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="block text-sm font-medium text-gray-700">Title</label>
                                <input type="text" name="officer_A_title" value="<?php echo htmlspecialchars($officer_A['title']); ?>" class="w-full border rounded p-2 text-sm" required>
                            </div>
                        </div>

                        <!-- Officer B -->
                        <div class="border p-3 rounded">
                            <h4 class="font-bold mb-2 text-blue-800">Officer B (Accountant)</h4>
                            <div class="mb-2">
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="officer_B_name" value="<?php echo htmlspecialchars($officer_B['name']); ?>" class="w-full border rounded p-2 text-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="block text-sm font-medium text-gray-700">Title</label>
                                <input type="text" name="officer_B_title" value="<?php echo htmlspecialchars($officer_B['title']); ?>" class="w-full border rounded p-2 text-sm" required>
                            </div>
                        </div>

                        <!-- Officer C -->
                        <div class="border p-3 rounded">
                            <h4 class="font-bold mb-2 text-blue-800">Officer C (Treasurer)</h4>
                            <div class="mb-2">
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="officer_C_name" value="<?php echo htmlspecialchars($officer_C['name']); ?>" class="w-full border rounded p-2 text-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="block text-sm font-medium text-gray-700">Title</label>
                                <input type="text" name="officer_C_title" value="<?php echo htmlspecialchars($officer_C['title']); ?>" class="w-full border rounded p-2 text-sm" required>
                            </div>
                        </div>

                        <!-- Officer D -->
                        <div class="border p-3 rounded">
                            <h4 class="font-bold mb-2 text-blue-800">Officer D (Mayor)</h4>
                            <div class="mb-2">
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="officer_D_name" value="<?php echo htmlspecialchars($officer_D['name']); ?>" class="w-full border rounded p-2 text-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="block text-sm font-medium text-gray-700">Title</label>
                                <input type="text" name="officer_D_title" value="<?php echo htmlspecialchars($officer_D['title']); ?>" class="w-full border rounded p-2 text-sm" required>
                            </div>
                        </div>

                        <!-- Officer F -->
                        <div class="border p-3 rounded col-span-2">
                            <h4 class="font-bold mb-2 text-blue-800">Officer F (Disbursing)</h4>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name</label>
                                    <input type="text" name="officer_F_name" value="<?php echo htmlspecialchars($officer_F['name']); ?>" class="w-full border rounded p-2 text-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Title</label>
                                    <input type="text" name="officer_F_title" value="<?php echo htmlspecialchars($officer_F['title']); ?>" class="w-full border rounded p-2 text-sm" required>
                                </div>
                            </div>
                        </div>

                        <!-- Entity Info -->
                        <div class="border p-3 rounded col-span-2">
                            <h4 class="font-bold mb-2 text-green-800">Entity Information</h4>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Entity Name</label>
                                    <input type="text" name="entity_name" value="<?php echo htmlspecialchars($entity_name); ?>" class="w-full border rounded p-2 text-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Fund Cluster</label>
                                    <input type="text" name="fund_cluster" value="<?php echo htmlspecialchars($fund_cluster); ?>" class="w-full border rounded p-2 text-sm" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" onclick="toggleEditModal()" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Update date and time
            function updateDateTime() {
                const now = new Date();
                const dateOptions = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                };
                const timeOptions = {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                };

                const dateElement = document.getElementById('current-date');
                const timeElement = document.getElementById('current-time');

                if (dateElement) {
                    dateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
                }
                if (timeElement) {
                    timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
                }
            }

            updateDateTime();
            setInterval(updateDateTime, 1000);

            // Period selector change
            const periodSelect = document.getElementById('payroll-period');
            if (periodSelect) {
                periodSelect.addEventListener('change', function() {
                    const period = this.value;
                    const url = new URL(window.location.href);
                    url.searchParams.set('period', period);
                    url.searchParams.set('page', '1');
                    window.location.href = url.toString();
                });
            }

            // Print button functionality
            const printButton = document.getElementById('print-btn');
            if (printButton) {
                printButton.addEventListener('click', function() {
                    // Store original values
                    const originalBodyOverflow = document.body.style.overflow;
                    const originalBodyBackground = document.body.style.backgroundColor;

                    // Get the exact payroll container div
                    const payrollContent = document.querySelector('.max-w-7xl.mx-auto.bg-white.shadow-lg.rounded-lg.p-2.mt-4');

                    // Create a deep clone with all styles preserved
                    const clone = payrollContent.cloneNode(true);

                    // Remove any print-hide elements from the clone
                    clone.querySelectorAll('.print-hide').forEach(el => {
                        if (el.parentNode) {
                            el.parentNode.removeChild(el);
                        }
                    });

                    // Remove any buttons and navigation from the clone
                    clone.querySelectorAll('button, a[href*="logout"], .mt-8.pt-4.border-t.border-gray-200').forEach(el => {
                        if (el.parentNode) {
                            el.parentNode.removeChild(el);
                        }
                    });

                    // Create a clean print container
                    const printContainer = document.createElement('div');
                    printContainer.id = 'print-container';
                    printContainer.style.cssText = `
                        position: fixed !important;
                        top: 0 !important;
                        left: 0 !important;
                        width: 100% !important;
                        height: 100% !important;
                        z-index: 99999 !important;
                        background: white !important;
                        padding: 0 !important;
                        margin: 0 !important;
                        overflow: visible !important;
                        visibility: visible !important;
                        display: block !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    `;

                    // Style the cloned content for printing
                    clone.style.cssText = `
                        max-width: 100% !important;
                        margin: 0 auto !important;
                        padding: 10mm !important;
                        box-shadow: none !important;
                        border-radius: 0 !important;
                        border: 1px solid #000 !important;
                        width: 100% !important;
                        page-break-inside: avoid !important;
                        page-break-after: avoid !important;
                        background: white !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    `;

                    // Ensure all text is black for printing
                    clone.querySelectorAll('*').forEach(el => {
                        el.style.color = '#000000 !important';
                        el.style.backgroundColor = 'transparent !important';
                    });

                    // Apply print-specific table styling
                    const table = clone.querySelector('.payroll-table');
                    if (table) {
                        table.style.cssText = `
                            width: 100% !important;
                            font-size: 8px !important;
                            table-layout: fixed !important;
                            border-collapse: collapse !important;
                            page-break-inside: avoid !important;
                            -webkit-print-color-adjust: exact !important;
                            print-color-adjust: exact !important;
                        `;

                        // Style all table cells
                        table.querySelectorAll('th, td').forEach(cell => {
                            cell.style.cssText = `
                                border: 0.5px solid #000000 !important;
                                padding: 2px 3px !important;
                                line-height: 1.1 !important;
                                height: auto !important;
                                min-height: 18px !important;
                                vertical-align: middle !important;
                                color: #000000 !important;
                                background-color: transparent !important;
                                -webkit-print-color-adjust: exact !important;
                                print-color-adjust: exact !important;
                                font-size: 8px !important;
                            `;
                        });

                        // Style table headers
                        table.querySelectorAll('thead th').forEach(th => {
                            th.style.cssText = `
                                background-color: #f0f0f0 !important;
                                font-weight: bold !important;
                                border: 0.5px solid #000000 !important;
                                font-size: 8px !important;
                                padding: 3px 4px !important;
                                color: #000000 !important;
                                -webkit-print-color-adjust: exact !important;
                                print-color-adjust: exact !important;
                            `;
                        });
                    }

                    // Style section boxes
                    clone.querySelectorAll('.section-box').forEach(box => {
                        box.style.cssText = `
                            page-break-inside: avoid !important;
                            border: 0.5px solid #000000 !important;
                            margin-bottom: 2mm !important;
                            padding: 1.5mm !important;
                            height: auto !important;
                            min-height: 25mm !important;
                            font-size: 9px !important;
                            color: #000000 !important;
                            background-color: transparent !important;
                            -webkit-print-color-adjust: exact !important;
                            print-color-adjust: exact !important;
                        `;
                    });

                    // Add the clone to print container
                    printContainer.appendChild(clone);

                    // Add print container to body
                    document.body.appendChild(printContainer);

                    // Hide all other elements
                    const allElements = document.body.children;
                    for (let element of allElements) {
                        if (element.id !== 'print-container') {
                            element.style.visibility = 'hidden';
                            element.style.display = 'none';
                        }
                    }

                    // Set body for printing
                    document.body.style.cssText = `
                        background: white !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        width: 100% !important;
                        height: auto !important;
                        overflow: visible !important;
                        visibility: visible !important;
                    `;

                    // Set print page size and margins
                    const style = document.createElement('style');
                    style.innerHTML = `
                    @page {
                        size: landscape;
                        margin: 0.25cm 0.5cm;
                    }
                    
                    @media print {
                        * {
                            -webkit-print-color-adjust: exact !important;
                            print-color-adjust: exact !important;
                        }
                        
                        body {
                            background: white !important;
                            margin: 0 !important;
                            padding: 0 !important;
                        }
                        
                        #print-container {
                            position: absolute !important;
                            left: 0 !important;
                            top: 0 !important;
                            width: 100% !important;
                            height: auto !important;
                            margin: 0 !important;
                            padding: 0 !important;
                            background: white !important;
                            overflow: visible !important;
                        }
                    }
                `;
                    document.head.appendChild(style);

                    // Wait a moment for styles to apply, then print
                    setTimeout(() => {
                        window.print();

                        // Clean up after printing
                        setTimeout(() => {
                            // Remove print container and style
                            if (document.getElementById('print-container')) {
                                document.body.removeChild(printContainer);
                            }
                            if (style.parentNode) {
                                style.parentNode.removeChild(style);
                            }

                            // Restore visibility of all elements
                            for (let element of allElements) {
                                element.style.visibility = '';
                                element.style.display = '';
                            }

                            // Restore original values
                            document.body.style.overflow = originalBodyOverflow;
                            document.body.style.backgroundColor = originalBodyBackground;
                            document.body.style.background = '';
                        }, 500);
                    }, 100);
                });
            }

            // Save button functionality
            const saveButton = document.getElementById('save-btn');
            if (saveButton) {
                saveButton.addEventListener('click', function() {
                    alert('Payroll saved successfully!');
                });
            }

            // Mobile sidebar toggle functionality
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            if (mobileMenuToggle && sidebar && sidebarOverlay) {
                mobileMenuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    sidebarOverlay.classList.toggle('active');
                    document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
                });

                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                });

                const sidebarLinks = sidebar.querySelectorAll('a');
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 1024) {
                            sidebar.classList.remove('active');
                            sidebarOverlay.classList.remove('active');
                            document.body.style.overflow = '';
                        }
                    });
                });
            }

            // User dropdown functionality
            const userMenuButton = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-dropdown');

            if (userMenuButton && userDropdown) {
                userMenuButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                    userMenuButton.classList.toggle('active');
                });

                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
                        userDropdown.classList.remove('active');
                        userMenuButton.classList.remove('active');
                    }
                });
            }

            // Payroll dropdown in sidebar
            const payrollToggle = document.getElementById('payroll-toggle');
            const payrollDropdown = document.getElementById('payroll-submenu');

            if (payrollToggle && payrollDropdown) {
                payrollDropdown.classList.add('open');
                const chevron = payrollToggle.querySelector('.chevron');
                if (chevron) {
                    chevron.classList.add('rotated');
                }

                payrollToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    payrollDropdown.classList.toggle('open');
                    const chevron = this.querySelector('.chevron');
                    if (chevron) {
                        chevron.classList.toggle('rotated');
                    }
                });
            }

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    if (sidebar) sidebar.classList.remove('active');
                    if (sidebarOverlay) sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Pagination function
        function goToPage(page) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            window.location.href = url.toString();
        }

        // Modal functions
        function toggleEditModal() {
            const modal = document.getElementById('editPersonnelModal');
            if (modal.classList.contains('hidden')) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            } else {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        function savePersonnelChanges(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            const data = {
                certifying_officers: {
                    A: {
                        name: formData.get('officer_A_name'),
                        title: formData.get('officer_A_title'),
                        active: true
                    },
                    B: {
                        name: formData.get('officer_B_name'),
                        title: formData.get('officer_B_title'),
                        active: true
                    },
                    C: {
                        name: formData.get('officer_C_name'),
                        title: formData.get('officer_C_title'),
                        active: true
                    },
                    D: {
                        name: formData.get('officer_D_name'),
                        title: formData.get('officer_D_title'),
                        active: true
                    },
                    F: {
                        name: formData.get('officer_F_name'),
                        title: formData.get('officer_F_title'),
                        active: true
                    }
                },
                entity_info: {
                    entity_name: formData.get('entity_name'),
                    fund_cluster: formData.get('fund_cluster'),
                    address: 'Paluan, Occidental Mindoro'
                }
            };

            // Show loading state
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Saving...';
            submitBtn.disabled = true;

            // Save to server
            fetch('save_personnel_config.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Personnel information saved successfully! The page will now reload.');
                        location.reload();
                    } else {
                        alert('Error saving: ' + (result.error || 'Unknown error'));
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    alert('Error saving personnel information. Please try again.');
                    console.error(error);
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editPersonnelModal');
            if (event.target == modal) {
                toggleEditModal();
            }
        }
    </script>
</body>

</html>