<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any unexpected output
ob_start();

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

// Get current user ID from session
$current_user_id = $_SESSION['user_id'] ?? 1;
$current_user_name = $_SESSION['full_name'] ?? 'System User';

// Logout functionality
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('remember_user', '', time() - 3600, "/");
    header('Location: login.php');
    exit();
}

// Get current payroll period
$current_payroll_period = date('Y-m');

// Get selected period and cutoff from URL
$selected_period = isset($_GET['period']) ? $_GET['period'] : $current_payroll_period;
$selected_cutoff = isset($_GET['cutoff']) ? $_GET['cutoff'] : 'full';

// Determine if we're in full month mode
$is_full_month = ($selected_cutoff == 'full');

// PAGINATION SETTINGS
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($current_page < 1) $current_page = 1;

// Parse selected period
$year_month = explode('-', $selected_period);
$year = $year_month[0];
$month = $year_month[1];

// Calculate cutoff date ranges and working days
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

// Function to ensure Contractual payroll tables exist
function ensureContractualPayrollTables($pdo)
{
    // Check if payroll_history_contractual table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_history_contractual'");
    if ($table_check->rowCount() == 0) {
        // Create the table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payroll_history_contractual (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id VARCHAR(50) NOT NULL,
                user_id INT,
                employee_type VARCHAR(50) DEFAULT 'contractual',
                payroll_period VARCHAR(7) NOT NULL,
                payroll_cutoff ENUM('full', 'first_half', 'second_half') DEFAULT 'full',
                
                -- Compensation fields
                monthly_salaries_wages DECIMAL(12,2) DEFAULT 0.00,
                monthly_salary DECIMAL(12,2) DEFAULT 0.00,
                other_comp DECIMAL(12,2) DEFAULT 0.00,
                gross_amount DECIMAL(12,2) DEFAULT 0.00,
                earned_salary DECIMAL(12,2) DEFAULT 0.00,
                
                -- Deduction fields
                withholding_tax DECIMAL(12,2) DEFAULT 0.00,
                sss DECIMAL(12,2) DEFAULT 0.00,
                philhealth DECIMAL(12,2) DEFAULT 0.00,
                pagibig DECIMAL(12,2) DEFAULT 0.00,
                total_deductions DECIMAL(12,2) DEFAULT 0.00,
                net_amount DECIMAL(12,2) DEFAULT 0.00,
                
                -- Other fields
                days_present DECIMAL(5,2) DEFAULT 0,
                working_days INT DEFAULT 22,
                
                -- Status fields
                status ENUM('draft', 'pending', 'approved', 'paid', 'cancelled') DEFAULT 'draft',
                approved_by VARCHAR(100),
                approved_date DATETIME,
                processed_date DATETIME,
                
                -- Obligation fields
                obligation_id INT,
                obligation_status VARCHAR(20) DEFAULT 'pending',
                
                -- Timestamps
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                -- Unique constraint
                UNIQUE KEY unique_employee_period_cutoff (employee_id, payroll_period, payroll_cutoff),
                
                INDEX idx_employee_id (employee_id),
                INDEX idx_period (payroll_period),
                INDEX idx_cutoff (payroll_cutoff),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    // Check if payroll_deductions_contractual table exists
    $deductions_check = $pdo->query("SHOW TABLES LIKE 'payroll_deductions_contractual'");
    if ($deductions_check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payroll_deductions_contractual (
                id INT AUTO_INCREMENT PRIMARY KEY,
                payroll_id INT NOT NULL,
                deduction_type VARCHAR(50) NOT NULL,
                deduction_amount DECIMAL(12,2) DEFAULT 0.00,
                deduction_description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (payroll_id) REFERENCES payroll_history_contractual(id) ON DELETE CASCADE,
                INDEX idx_payroll_id (payroll_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}

// Ensure tables exist
ensureContractualPayrollTables($pdo);

// Function to get employee's payroll data from contractual table - FIXED to avoid duplicates
function getEmployeePayrollData($pdo, $employee_id, $user_id, $period, $cutoff, $prorated_salary = 0)
{
    $data = [
        'other_comp' => 0,
        'withholding_tax' => 0,
        'sss' => 0,
        'philhealth' => 0,
        'pagibig' => 0,
        'total_deductions' => 0,
        'gross_amount' => $prorated_salary,
        'net_amount' => $prorated_salary,
        'days_present' => 0,
        'monthly_salaries_wages' => 0,
        'monthly_salary' => 0,
        'earned_salary' => 0,
        'status' => 'draft',
        'payroll_id' => null,
        'exists' => false
    ];

    try {
        if ($cutoff == 'full') {
            // For full month, get data from both halves and sum them - FIXED to use DISTINCT and avoid double counting
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(other_comp), 0) as other_comp,
                    COALESCE(SUM(withholding_tax), 0) as withholding_tax,
                    COALESCE(SUM(sss), 0) as sss,
                    COALESCE(SUM(total_deductions), 0) as total_deductions,
                    COALESCE(SUM(gross_amount), 0) as gross_amount,
                    COALESCE(SUM(net_amount), 0) as net_amount,
                    COALESCE(SUM(days_present), 0) as days_present,
                    COALESCE(SUM(monthly_salaries_wages), 0) as monthly_salaries_wages,
                    COUNT(DISTINCT id) as record_count
                FROM payroll_history_contractual 
                WHERE employee_id = ? 
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
                $data['monthly_salaries_wages'] = floatval($result['monthly_salaries_wages']);
                $data['exists'] = true;
            }
        } else {
            // For specific half, get just that half's data
            $stmt = $pdo->prepare("
                SELECT id, other_comp, withholding_tax, sss, 
                       total_deductions, gross_amount, net_amount, days_present, 
                       monthly_salaries_wages, monthly_salary, earned_salary, status
                FROM payroll_history_contractual 
                WHERE employee_id = ? 
                    AND payroll_period = ? AND payroll_cutoff = ?
                LIMIT 1
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
                $data['monthly_salaries_wages'] = floatval($result['monthly_salaries_wages'] ?? 0);
                $data['monthly_salary'] = floatval($result['monthly_salary'] ?? 0);
                $data['earned_salary'] = floatval($result['earned_salary'] ?? 0);
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

// Function to get salary column name from contractofservice
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    // Clean any output buffers to ensure clean JSON response
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');

    try {
        // GET ALL EMPLOYEES FOR SELECTION ACROSS PAGES - FIXED to use DISTINCT
        if ($_POST['ajax_action'] === 'get_all_employees_for_selection') {
            $period = $_POST['period'] ?? $selected_period;
            $cutoff = $_POST['cutoff'] ?? $selected_cutoff;
            $search = $_POST['search'] ?? '';

            try {
                // Build query to get all employees (without pagination) - FIXED with DISTINCT
                $sql = "
                    SELECT DISTINCT
                        id as user_id, 
                        employee_id, 
                        CONCAT(first_name, ' ', last_name) as employee_name,
                        designation as position, 
                        office as department
                    FROM contractofservice 
                    WHERE status = 'active'
                ";

                if (!empty($search)) {
                    $sql .= " AND (employee_id LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR CONCAT(first_name, ' ', last_name) LIKE :search OR designation LIKE :search OR office LIKE :search)";
                }

                $sql .= " ORDER BY last_name, first_name";

                $stmt = $pdo->prepare($sql);

                if (!empty($search)) {
                    $search_param = "%$search%";
                    $stmt->bindParam(':search', $search_param);
                }

                $stmt->execute();
                $employees = $stmt->fetchAll();

                echo json_encode([
                    'success' => true,
                    'employees' => $employees,
                    'total' => count($employees)
                ]);
                exit();
            } catch (Exception $e) {
                error_log("Error getting all employees: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
                exit();
            }
        }

        // PAGINATION AJAX HANDLER - FIXED to avoid duplicates
        if ($_POST['ajax_action'] === 'get_paginated_employees') {
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $per_page = isset($_POST['per_page']) ? (int)$_POST['per_page'] : 10;
            $period = $_POST['period'] ?? $selected_period;
            $cutoff = $_POST['cutoff'] ?? $selected_cutoff;
            $search = isset($_POST['search']) ? trim($_POST['search']) : '';

            $offset = ($page - 1) * $per_page;

            $contractual_employees_paginated = [];
            $total_employees = 0;

            try {
                $table_check = $pdo->query("SHOW TABLES LIKE 'contractofservice'");
                if ($table_check->rowCount() == 0) {
                    throw new Exception("contractofservice table does not exist");
                }

                // Count total distinct employees
                $count_sql = "SELECT COUNT(DISTINCT id) as total FROM contractofservice WHERE status = 'active'";
                if (!empty($search)) {
                    $count_sql .= " AND (employee_id LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR CONCAT(first_name, ' ', last_name) LIKE :search OR designation LIKE :search OR office LIKE :search)";
                }

                $count_stmt = $pdo->prepare($count_sql);
                if (!empty($search)) {
                    $search_term_param = "%$search%";
                    $count_stmt->bindParam(':search', $search_term_param);
                }
                $count_stmt->execute();
                $total_employees = $count_stmt->fetchColumn();

                // Get distinct employees with pagination
                $sql = "
                    SELECT DISTINCT
                        id as user_id, 
                        employee_id, 
                        CONCAT(first_name, ' ', last_name) as full_name,
                        first_name, 
                        last_name,
                        designation as position, 
                        office as department
                    FROM contractofservice 
                    WHERE status = 'active'
                ";

                if (!empty($search)) {
                    $sql .= " AND (employee_id LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR CONCAT(first_name, ' ', last_name) LIKE :search OR designation LIKE :search OR office LIKE :search)";
                }

                $sql .= " ORDER BY last_name, first_name LIMIT :offset, :per_page";

                $stmt = $pdo->prepare($sql);

                if (!empty($search)) {
                    $search_term_param = "%$search%";
                    $stmt->bindParam(':search', $search_term_param);
                }
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
                $stmt->execute();
                $employees = $stmt->fetchAll();

                $year_month = explode('-', $period);
                $year = $year_month[0];
                $month = $year_month[1];

                $cutoff_ranges_local = [
                    'full' => [
                        'start' => "$year-$month-01",
                        'end' => date('Y-m-t', strtotime("$year-$month-01"))
                    ],
                    'first_half' => [
                        'start' => "$year-$month-01",
                        'end' => "$year-$month-15"
                    ],
                    'second_half' => [
                        'start' => "$year-$month-16",
                        'end' => date('Y-m-t', strtotime("$year-$month-01"))
                    ]
                ];

                $current_cutoff_range = $cutoff_ranges_local[$cutoff];

                // Process each employee - FIXED to avoid reference issues
                $processed_employees = [];
                $seen_ids = [];

                foreach ($employees as $employee) {
                    // Skip if we've already processed this ID
                    if (in_array($employee['user_id'], $seen_ids)) {
                        continue;
                    }
                    $seen_ids[] = $employee['user_id'];

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
                                    $current_cutoff_range['start'],
                                    $current_cutoff_range['end']
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

                    // Get salary using the salary column function
                    $salary_col = getSalaryColumnName($pdo);
                    $monthly_salary = 0;

                    if ($salary_col) {
                        $salary_stmt = $pdo->prepare("
                            SELECT $salary_col as salary_value 
                            FROM contractofservice 
                            WHERE id = ?
                        ");
                        $salary_stmt->execute([$employee['user_id']]);
                        $salary_data = $salary_stmt->fetch();

                        if ($salary_data) {
                            $salary_value = floatval($salary_data['salary_value'] ?? 0);
                            if ($salary_col == 'rate_per_day' || $salary_col == 'daily_rate') {
                                $monthly_salary = $salary_value * 22;
                                $daily_rate = $salary_value;
                            } else {
                                $monthly_salary = $salary_value;
                                $daily_rate = $monthly_salary / 22;
                            }
                        }
                    } else {
                        $monthly_salary = 0;
                        $daily_rate = 0;
                    }

                    $prorated_salary = $daily_rate * $attendance_days;

                    // Get payroll data from database - use employee_id string for lookup
                    $payroll_data = getEmployeePayrollData($pdo, $employee['employee_id'], $employee['user_id'], $period, $cutoff, $prorated_salary);

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

                    $processed_employees[] = $employee;
                }

                $contractual_employees_paginated = $processed_employees;
            } catch (Exception $e) {
                error_log("Database error: " . $e->getMessage());
                $contractual_employees_paginated = [];
                $total_employees = 0;
            }

            // Calculate totals
            $total_monthly_salaries_wages = 0;
            $total_other_comp = 0;
            $total_gross_amount = 0;
            $total_withholding_tax = 0;
            $total_sss = 0;
            $total_deductions = 0;
            $total_net_amount = 0;

            foreach ($contractual_employees_paginated as $employee) {
                $total_monthly_salaries_wages += floatval($employee['prorated_salary'] ?? 0);
                $total_other_comp += floatval($employee['other_comp'] ?? 0);
                $total_gross_amount += floatval($employee['gross_amount'] ?? 0);
                $total_withholding_tax += floatval($employee['withholding_tax'] ?? 0);
                $total_sss += floatval($employee['sss'] ?? 0);
                $total_deductions += floatval($employee['total_deductions'] ?? 0);
                $total_net_amount += floatval($employee['net_amount'] ?? 0);
            }

            ob_start();
            $counter = $offset + 1;
            $is_full_month_ajax = ($cutoff == 'full');

            foreach ($contractual_employees_paginated as $employee):
                $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
                $daily_rate = floatval($employee['daily_rate'] ?? 0);
                $prorated_salary = floatval($employee['prorated_salary'] ?? 0);
                $other_comp = floatval($employee['other_comp'] ?? 0);
                $days_present = floatval($employee['days_present'] ?? 0);
                $gross_amount = floatval($employee['gross_amount'] ?? 0);
                $withholding_tax = floatval($employee['withholding_tax'] ?? 0);
                $sss = floatval($employee['sss'] ?? 0);
                $total_deductions_row = floatval($employee['total_deductions'] ?? 0);
                $net_amount_row = floatval($employee['net_amount'] ?? 0);
                $payroll_id = $employee['payroll_id'] ?? null;
                $payroll_exists = $employee['payroll_exists'] ?? false;

                $has_attendance = ($days_present > 0);
                $row_class = $has_attendance ? '' : 'no-attendance';

                $full_month_disabled_class = $is_full_month_ajax ? 'full-month-disabled' : '';
                $full_month_readonly = $is_full_month_ajax ? 'readonly disabled' : '';
                $full_month_style = $is_full_month_ajax ? 'background-color: #f0f0f0; color: #888; cursor: not-allowed;' : '';
?>
                <tr class="bg-white border-b hover:bg-gray-50 payroll-row <?php echo $row_class; ?>" data-user-id="<?php echo $employee['user_id']; ?>" data-employee-id="<?php echo htmlspecialchars($employee['employee_id']); ?>" data-payroll-id="<?php echo $payroll_id; ?>" data-payroll-exists="<?php echo $payroll_exists ? '1' : '0'; ?>">
                    <td class="text-center">
                        <input type="checkbox" class="employee-checkbox" value="<?php echo htmlspecialchars($employee['employee_id']); ?>" data-employee-name="<?php echo htmlspecialchars($employee['full_name']); ?>" data-user-id="<?php echo $employee['user_id']; ?>">
                    </td>
                    <td class="text-center"><?php echo $counter++; ?></td>
                    <td class="font-medium"><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                    <td class="font-medium">
                        <?php echo htmlspecialchars($employee['full_name']); ?>
                        <input type="hidden" name="user_id[]" class="hidden-user-id" value="<?php echo $employee['user_id']; ?>">
                        <input type="hidden" name="employee_id[]" class="hidden-employee-id" value="<?php echo htmlspecialchars($employee['employee_id']); ?>">
                        <input type="hidden" name="days_present[]" class="hidden-days-present" value="<?php echo $days_present; ?>">
                    </td>
                    <td><?php echo htmlspecialchars($employee['position']); ?></td>
                    <td><?php echo htmlspecialchars($employee['department']); ?></td>
                    <td>
                        <span class="font-medium"><?php echo number_format($days_present, 1); ?></span>
                        <?php if ($days_present <= 0): ?>
                            <span class="days-present-badge warning">No attendance</span>
                        <?php else: ?>
                            <span class="days-present-badge success">Present</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <input type="number"
                            name="daily_rate[]"
                            class="payroll-input readonly disabled-field daily-rate"
                            value="<?php echo number_format($daily_rate, 2, '.', ''); ?>"
                            readonly
                            disabled
                            tabindex="-1">
                    </td>
                    <td>
                        <input type="number"
                            name="monthly_salaries_wages[]"
                            class="payroll-input readonly disabled-field monthly-salaries-wages"
                            value="<?php echo number_format($monthly_salary, 2, '.', ''); ?>"
                            readonly
                            disabled
                            tabindex="-1">
                    </td>
                    <td>
                        <input type="number"
                            name="other_comp[]"
                            class="payroll-input other-comp <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                            value="<?php echo number_format($other_comp, 2, '.', ''); ?>"
                            min="0"
                            step="0.01"
                            data-user-id="<?php echo $employee['user_id']; ?>"
                            data-field="other_comp"
                            <?php echo $full_month_readonly; ?>
                            <?php echo $is_full_month_ajax ? 'title="Other Compensation can only be edited in Half Month views"' : ''; ?>
                            style="<?php echo $full_month_style; ?>">
                    </td>
                    <td>
                        <input type="number"
                            name="gross_amount[]"
                            class="payroll-input gross-amount readonly disabled-field <?php echo ($gross_amount <= 0) ? 'zero-amount' : ''; ?>"
                            value="<?php echo number_format($gross_amount, 2, '.', ''); ?>"
                            readonly
                            disabled
                            tabindex="-1">
                    </td>
                    <td>
                        <input type="number"
                            name="withholding_tax[]"
                            class="payroll-input withholding-tax <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                            value="<?php echo number_format($withholding_tax, 2, '.', ''); ?>"
                            min="0"
                            step="0.01"
                            data-user-id="<?php echo $employee['user_id']; ?>"
                            data-field="withholding_tax"
                            <?php echo $full_month_readonly; ?>
                            <?php echo $is_full_month_ajax ? 'title="Withholding Tax can only be edited in Half Month views"' : ''; ?>
                            style="<?php echo $full_month_style; ?>">
                    </td>
                    <td>
                        <input type="number"
                            name="sss[]"
                            class="payroll-input sss <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                            value="<?php echo number_format($sss, 2, '.', ''); ?>"
                            min="0"
                            step="0.01"
                            data-user-id="<?php echo $employee['user_id']; ?>"
                            data-field="sss"
                            <?php echo $full_month_readonly; ?>
                            <?php echo $is_full_month_ajax ? 'title="SSS Contribution can only be edited in Half Month views"' : ''; ?>
                            style="<?php echo $full_month_style; ?>">
                    </td>
                    <td>
                        <input type="number"
                            name="total_deduction[]"
                            class="payroll-input total-deduction readonly disabled-field"
                            value="<?php echo number_format($total_deductions_row, 2, '.', ''); ?>"
                            readonly
                            disabled
                            tabindex="-1">
                    </td>
                    <td>
                        <input type="number"
                            name="net_amount[]"
                            class="payroll-input net-amount readonly disabled-field <?php echo ($net_amount_row <= 0) ? 'zero-amount' : ''; ?>"
                            value="<?php echo number_format($net_amount_row, 2, '.', ''); ?>"
                            readonly
                            disabled
                            tabindex="-1">
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button type="button" class="action-btn view-btn" onclick="viewEmployeeDetails(<?php echo $employee['user_id']; ?>, '<?php echo $period; ?>', '<?php echo $cutoff; ?>')">
                                <i class="fas fa-eye"></i> <span class="hidden md:inline">View</span>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php
            $table_rows_html = ob_get_clean();

            $total_pages = ceil($total_employees / $per_page);
            ob_start();
            ?>
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 w-full">
                <div class="text-sm text-gray-600">
                    Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to
                    <span class="font-medium"><?php echo min($offset + $per_page, $total_employees); ?></span> of
                    <span class="font-medium"><?php echo $total_employees; ?></span> employees
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed pagination-prev" <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page - 1; ?>)">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </button>
                    <span class="px-3 py-2 text-sm font-medium text-gray-700">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                    <button type="button" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed pagination-next" <?php echo $page >= $total_pages ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page + 1; ?>)">
                        Next <i class="fas fa-chevron-right ml-1"></i>
                    </button>
                </div>
            </div>
<?php
            $pagination_html = ob_get_clean();

            echo json_encode([
                'success' => true,
                'table_rows' => $table_rows_html,
                'pagination' => $pagination_html,
                'total_employees' => $total_employees,
                'current_page' => $page,
                'total_pages' => $total_pages,
                'totals' => [
                    'total_monthly_salaries_wages' => number_format($total_monthly_salaries_wages, 2),
                    'total_other_comp' => number_format($total_other_comp, 2),
                    'total_gross_amount' => number_format($total_gross_amount, 2),
                    'total_withholding_tax' => number_format($total_withholding_tax, 2),
                    'total_sss' => number_format($total_sss, 2),
                    'total_deductions' => number_format($total_deductions, 2),
                    'total_net_amount' => number_format($total_net_amount, 2)
                ]
            ]);
            exit();
        }

        // Save Deductions (Handles ALL fields at once - called by auto-save)
        if ($_POST['ajax_action'] === 'save_deductions') {
            $user_id = intval($_POST['employee_id']);
            $withholding_tax = floatval($_POST['withholding_tax'] ?? 0);
            $sss = floatval($_POST['sss'] ?? 0);
            $other_comp = floatval($_POST['other_comp'] ?? 0);
            $gross_amount = floatval($_POST['gross_amount'] ?? 0);
            $total_deductions = floatval($_POST['total_deductions'] ?? 0);
            $net_amount = floatval($_POST['net_amount'] ?? 0);
            $monthly_salary = floatval($_POST['monthly_salary'] ?? 0);
            $days_present = floatval($_POST['days_present'] ?? 0);
            $period = $_POST['period'];
            $cutoff = $_POST['cutoff'];

            // Prevent saving for full month
            if ($cutoff == 'full') {
                echo json_encode([
                    'success' => false,
                    'error' => 'Deductions cannot be edited in Full Month view. Please switch to Half Month view.'
                ]);
                exit();
            }

            try {
                // Get the actual employee_id string from contractofservice table
                $emp_stmt = $pdo->prepare("
                    SELECT employee_id
                    FROM contractofservice 
                    WHERE id = ?
                ");
                $emp_stmt->execute([$user_id]);
                $employee = $emp_stmt->fetch();

                if (!$employee) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Employee not found'
                    ]);
                    exit();
                }

                $employee_id = $employee['employee_id'];

                // Get salary for monthly_salaries_wages calculation
                $salary_col = getSalaryColumnName($pdo);
                $daily_rate = 0;

                if ($salary_col) {
                    $salary_stmt = $pdo->prepare("
                        SELECT $salary_col as salary_value 
                        FROM contractofservice 
                        WHERE id = ?
                    ");
                    $salary_stmt->execute([$user_id]);
                    $salary_data = $salary_stmt->fetch();

                    if ($salary_data) {
                        $salary_value = floatval($salary_data['salary_value'] ?? 0);
                        if ($salary_col == 'rate_per_day' || $salary_col == 'daily_rate') {
                            $daily_rate = $salary_value;
                        } else {
                            $daily_rate = $salary_value / 22;
                        }
                    }
                }

                // Calculate monthly_salaries_wages
                $monthly_salaries_wages = $daily_rate * $days_present;

                // Ensure calculations are consistent
                if ($total_deductions == 0 && ($withholding_tax > 0 || $sss > 0)) {
                    $total_deductions = $withholding_tax + $sss;
                }

                if ($net_amount == 0 && $gross_amount > 0) {
                    $net_amount = $gross_amount - $total_deductions;
                    if ($net_amount < 0) $net_amount = 0;
                }

                // Check if record exists in contractual table
                $check_stmt = $pdo->prepare("
                    SELECT id FROM payroll_history_contractual 
                    WHERE employee_id = ? 
                        AND payroll_period = ? AND payroll_cutoff = ?
                ");
                $check_stmt->execute([$employee_id, $period, $cutoff]);
                $existing = $check_stmt->fetch();

                if ($existing) {
                    // UPDATE existing record
                    $update_sql = "UPDATE payroll_history_contractual SET 
                        monthly_salaries_wages = ?,
                        monthly_salary = ?,
                        other_comp = ?,
                        gross_amount = ?,
                        earned_salary = ?,
                        withholding_tax = ?,
                        sss = ?,
                        total_deductions = ?,
                        net_amount = ?,
                        days_present = ?,
                        updated_at = NOW()
                        WHERE id = ?";

                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->execute([
                        $monthly_salaries_wages,
                        $monthly_salary,
                        $other_comp,
                        $gross_amount,
                        $gross_amount,
                        $withholding_tax,
                        $sss,
                        $total_deductions,
                        $net_amount,
                        $days_present,
                        $existing['id']
                    ]);

                    // Update deductions - delete old and insert new
                    $delete_deductions = $pdo->prepare("DELETE FROM payroll_deductions_contractual WHERE payroll_id = ?");
                    $delete_deductions->execute([$existing['id']]);

                    $deduction_types = [];
                    if ($withholding_tax > 0) $deduction_types[] = ['Withholding Tax', $withholding_tax];
                    if ($sss > 0) $deduction_types[] = ['SSS Contribution', $sss];

                    foreach ($deduction_types as $deduction) {
                        if ($deduction[1] > 0) {
                            $deduction_stmt = $pdo->prepare("
                                INSERT INTO payroll_deductions_contractual 
                                (payroll_id, deduction_type, deduction_amount, deduction_description)
                                VALUES (?, ?, ?, ?)
                            ");
                            $deduction_stmt->execute([
                                $existing['id'],
                                $deduction[0],
                                $deduction[1],
                                $deduction[0] . ' deduction for ' . $period . ' (' . $cutoff . ')'
                            ]);
                        }
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Payroll data updated successfully',
                        'id' => $existing['id'],
                        'gross_amount' => $gross_amount,
                        'total_deductions' => $total_deductions,
                        'net_amount' => $net_amount
                    ]);
                } else {
                    // INSERT new record
                    $insert_sql = "INSERT INTO payroll_history_contractual 
                        (employee_id, user_id, payroll_period, payroll_cutoff, 
                         monthly_salaries_wages, monthly_salary, other_comp, gross_amount, earned_salary,
                         withholding_tax, sss, total_deductions, net_amount, days_present, working_days,
                         status, processed_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

                    $insert_stmt = $pdo->prepare($insert_sql);
                    $insert_stmt->execute([
                        $employee_id,
                        $user_id,
                        $period,
                        $cutoff,
                        $monthly_salaries_wages,
                        $monthly_salary,
                        $other_comp,
                        $gross_amount,
                        $gross_amount,
                        $withholding_tax,
                        $sss,
                        $total_deductions,
                        $net_amount,
                        $days_present,
                        22
                    ]);

                    $new_id = $pdo->lastInsertId();

                    // Insert deductions
                    $deduction_types = [];
                    if ($withholding_tax > 0) $deduction_types[] = ['Withholding Tax', $withholding_tax];
                    if ($sss > 0) $deduction_types[] = ['SSS Contribution', $sss];

                    foreach ($deduction_types as $deduction) {
                        if ($deduction[1] > 0) {
                            $deduction_stmt = $pdo->prepare("
                                INSERT INTO payroll_deductions_contractual 
                                (payroll_id, deduction_type, deduction_amount, deduction_description)
                                VALUES (?, ?, ?, ?)
                            ");
                            $deduction_stmt->execute([
                                $new_id,
                                $deduction[0],
                                $deduction[1],
                                $deduction[0] . ' deduction for ' . $period . ' (' . $cutoff . ')'
                            ]);
                        }
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Payroll data saved successfully',
                        'id' => $new_id,
                        'gross_amount' => $gross_amount,
                        'total_deductions' => $total_deductions,
                        'net_amount' => $net_amount
                    ]);
                }
            } catch (PDOException $e) {
                error_log("Save deductions error: " . $e->getMessage());

                // If duplicate entry, try to update instead
                if ($e->errorInfo[1] == 1062) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Record already exists. Please refresh the page and try again.'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Database error: ' . $e->getMessage()
                    ]);
                }
            }
            exit();
        }

        // Get employee details with cutoff filtering
        if ($_POST['ajax_action'] === 'get_employee_details') {
            $user_id = $_POST['employee_id'];
            $period = $_POST['period'] ?? date('Y-m');
            $cutoff = $_POST['cutoff'] ?? 'full';

            try {
                // Get salary column
                $salary_col = getSalaryColumnName($pdo);

                $select_fields = "
                    id as user_id, 
                    employee_id, 
                    CONCAT(first_name, ' ', last_name) as full_name,
                    first_name, 
                    last_name,
                    designation as position, 
                    office as department,
                    email_address,
                    mobile_number
                ";

                if ($salary_col) {
                    $select_fields .= ", $salary_col as salary_value";
                }

                $stmt = $pdo->prepare("
                    SELECT $select_fields
                    FROM contractofservice 
                    WHERE id = ?
                ");
                $stmt->execute([$user_id]);
                $employee = $stmt->fetch();

                if (!$employee) {
                    echo json_encode(['success' => false, 'error' => 'Employee not found']);
                    exit();
                }

                // Parse period
                $year_month = explode('-', $period);
                $year = $year_month[0];
                $month = $year_month[1];

                // Get cutoff dates
                $cutoff_ranges_local = [
                    'full' => [
                        'start' => "$year-$month-01",
                        'end' => date('Y-m-t', strtotime("$year-$month-01"))
                    ],
                    'first_half' => [
                        'start' => "$year-$month-01",
                        'end' => "$year-$month-15"
                    ],
                    'second_half' => [
                        'start' => "$year-$month-16",
                        'end' => date('Y-m-t', strtotime("$year-$month-01"))
                    ]
                ];

                $date_range = $cutoff_ranges_local[$cutoff];
                $working_days = getWorkingDays($date_range['start'], $date_range['end']);

                // Get attendance for the selected cutoff period
                $attendance_data = ['total_records' => 0, 'days_present' => 0, 'total_hours' => 0];

                try {
                    $table_check = $pdo->query("SHOW TABLES LIKE 'attendance'");
                    if ($table_check->rowCount() > 0) {
                        $att_columns = $pdo->query("SHOW COLUMNS FROM attendance")->fetchAll(PDO::FETCH_COLUMN);

                        if (in_array('total_hours', $att_columns)) {
                            $attendance_stmt = $pdo->prepare("
                                SELECT 
                                    COUNT(*) as total_records,
                                    SUM(CASE 
                                        WHEN total_hours >= 8 THEN 1
                                        WHEN total_hours >= 4 THEN 0.5
                                        ELSE 0
                                    END) as days_present,
                                    SUM(total_hours) as total_hours
                                FROM attendance 
                                WHERE employee_id = ? 
                                    AND date BETWEEN ? AND ?
                            ");
                            $attendance_stmt->execute([$employee['employee_id'], $date_range['start'], $date_range['end']]);
                            $attendance_data = $attendance_stmt->fetch();
                        }
                    }
                } catch (Exception $e) {
                    error_log("Attendance query error: " . $e->getMessage());
                    $attendance_data = ['total_records' => 0, 'days_present' => 0, 'total_hours' => 0];
                }

                // Calculate salary values
                $salary_value = floatval($employee['salary_value'] ?? 0);
                $is_daily_rate = ($salary_col == 'rate_per_day' || $salary_col == 'daily_rate');

                if ($is_daily_rate) {
                    $daily_rate = $salary_value;
                    $monthly_salary = $salary_value * 22;
                } else {
                    $monthly_salary = $salary_value;
                    $daily_rate = $monthly_salary / 22;
                }

                $prorated_salary = $daily_rate * floatval($attendance_data['days_present'] ?? 0);

                // Get data from payroll_history_contractual
                $payroll_data = getEmployeePayrollData($pdo, $employee['employee_id'], $employee['user_id'], $period, $cutoff, $prorated_salary);

                // Get payroll history from contractual table
                $payroll_history = [];
                try {
                    $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_history_contractual'");
                    if ($table_check->rowCount() > 0) {
                        $payroll_stmt = $pdo->prepare("
                            SELECT * FROM payroll_history_contractual 
                            WHERE employee_id = ? 
                            ORDER BY payroll_period DESC, payroll_cutoff DESC
                            LIMIT 6
                        ");
                        $payroll_stmt->execute([$employee['employee_id']]);
                        $payroll_history = $payroll_stmt->fetchAll();
                    }
                } catch (Exception $e) {
                    error_log("Payroll history fetch error: " . $e->getMessage());
                    $payroll_history = [];
                }

                echo json_encode([
                    'success' => true,
                    'employee' => $employee,
                    'cutoff' => [
                        'type' => $cutoff,
                        'start' => $date_range['start'],
                        'end' => $date_range['end'],
                        'working_days' => $working_days,
                        'label' => $cutoff_ranges[$cutoff]['label'] ?? ucfirst(str_replace('_', ' ', $cutoff))
                    ],
                    'attendance' => [
                        'total_days' => intval($attendance_data['total_records'] ?? 0),
                        'days_present' => floatval($attendance_data['days_present'] ?? 0),
                        'total_hours' => floatval($attendance_data['total_hours'] ?? 0)
                    ],
                    'payroll_history' => $payroll_history,
                    'calculations' => [
                        'days_present' => floatval($attendance_data['days_present'] ?? 0),
                        'monthly_salary' => $monthly_salary,
                        'daily_rate' => $daily_rate,
                        'prorated_salary' => $prorated_salary,
                        'other_comp' => $payroll_data['other_comp'],
                        'withholding_tax' => $payroll_data['withholding_tax'],
                        'sss' => $payroll_data['sss'],
                        'total_deductions' => $payroll_data['total_deductions'],
                        'net_amount' => $payroll_data['net_amount'],
                        'gross_amount' => $payroll_data['gross_amount'],
                        'total_hours' => floatval($attendance_data['total_hours'] ?? 0)
                    ]
                ]);
                exit();
            } catch (Exception $e) {
                error_log("Get employee details error: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => 'Error fetching employee details: ' . $e->getMessage()
                ]);
                exit();
            }
        }
    } catch (Exception $e) {
        error_log("AJAX Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit();
    }
}

// ============================================
// Handle form submission for saving payroll
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payroll'])) {
    try {
        $pdo->beginTransaction();

        $payroll_period = $_POST['payroll_period'] ?? $current_payroll_period;
        $payroll_cutoff = $_POST['payroll_cutoff'] ?? 'full';
        $working_days = $cutoff_ranges[$payroll_cutoff]['working_days'] ?? 22;

        error_log("=== START PAYROLL SAVE ===");
        error_log("Period: $payroll_period, Cutoff: $payroll_cutoff");

        // Check if user_id array exists and is not empty
        if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
            throw new Exception("No employees selected");
        }

        $processed_count = 0;

        // Process each employee - FIXED to avoid duplicates
        $processed_ids = [];

        foreach ($_POST['user_id'] as $index => $user_id) {
            if (empty($user_id)) {
                continue;
            }

            $user_id = intval($user_id);

            // Skip if we've already processed this user ID
            if (in_array($user_id, $processed_ids)) {
                error_log("Skipping duplicate user_id: $user_id");
                continue;
            }
            $processed_ids[] = $user_id;

            $employee_id = isset($_POST['employee_id'][$index]) ? trim($_POST['employee_id'][$index]) : '';

            if (empty($employee_id)) {
                continue;
            }

            error_log("Processing user_id: $user_id, employee_id: '$employee_id'");

            // Get values from form
            $monthly_salaries_wages = floatval($_POST['monthly_salaries_wages'][$index] ?? 0);
            $other_comp = floatval($_POST['other_comp'][$index] ?? 0);
            $gross_amount = floatval($_POST['gross_amount'][$index] ?? 0);
            $withholding_tax = floatval($_POST['withholding_tax'][$index] ?? 0);
            $sss = floatval($_POST['sss'][$index] ?? 0);
            $total_deductions = floatval($_POST['total_deduction'][$index] ?? 0);
            $net_amount = floatval($_POST['net_amount'][$index] ?? 0);
            $days_present = floatval($_POST['days_present'][$index] ?? 0);

            // Get employee data for salary
            $salary_col = getSalaryColumnName($pdo);
            $daily_rate = 0;
            $monthly_salary = 0;

            if ($salary_col) {
                $emp_stmt = $pdo->prepare("
                    SELECT $salary_col as salary_value 
                    FROM contractofservice 
                    WHERE id = ? OR employee_id = ?
                ");
                $emp_stmt->execute([$user_id, $employee_id]);
                $emp_data = $emp_stmt->fetch();

                if ($emp_data) {
                    $salary_value = floatval($emp_data['salary_value'] ?? 0);
                    if ($salary_col == 'rate_per_day' || $salary_col == 'daily_rate') {
                        $daily_rate = $salary_value;
                        $monthly_salary = $salary_value * 22;
                    } else {
                        $monthly_salary = $salary_value;
                        $daily_rate = $monthly_salary / 22;
                    }
                }
            }

            // Calculate monthly_salaries_wages if not set
            if ($monthly_salaries_wages == 0 && $daily_rate > 0) {
                $monthly_salaries_wages = $daily_rate * $days_present;
            }

            // Calculate totals if needed
            if ($total_deductions == 0 && ($withholding_tax > 0 || $sss > 0)) {
                $total_deductions = $withholding_tax + $sss;
            }

            if ($net_amount == 0 && $gross_amount > 0) {
                $net_amount = $gross_amount - $total_deductions;
                if ($net_amount < 0) $net_amount = 0;
            }

            // Check if record exists in contractual table
            $check_stmt = $pdo->prepare("
                SELECT id FROM payroll_history_contractual 
                WHERE employee_id = ? 
                AND payroll_period = ? AND payroll_cutoff = ?
            ");
            $check_stmt->execute([$employee_id, $payroll_period, $payroll_cutoff]);
            $existing = $check_stmt->fetch();

            if ($existing) {
                // UPDATE existing record
                error_log("UPDATING existing record ID: " . $existing['id'] . " for employee: $employee_id");

                $update_sql = "UPDATE payroll_history_contractual SET 
                    monthly_salaries_wages = ?,
                    monthly_salary = ?,
                    other_comp = ?,
                    gross_amount = ?,
                    earned_salary = ?,
                    withholding_tax = ?,
                    sss = ?,
                    total_deductions = ?,
                    net_amount = ?,
                    days_present = ?,
                    working_days = ?,
                    status = 'pending',
                    updated_at = NOW()
                    WHERE id = ?";

                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([
                    $monthly_salaries_wages,
                    $monthly_salary,
                    $other_comp,
                    $gross_amount,
                    $gross_amount,
                    $withholding_tax,
                    $sss,
                    $total_deductions,
                    $net_amount,
                    $days_present,
                    $working_days,
                    $existing['id']
                ]);

                // Update deductions
                $delete_deductions = $pdo->prepare("DELETE FROM payroll_deductions_contractual WHERE payroll_id = ?");
                $delete_deductions->execute([$existing['id']]);

                $deduction_types = [];
                if ($withholding_tax > 0) $deduction_types[] = ['Withholding Tax', $withholding_tax];
                if ($sss > 0) $deduction_types[] = ['SSS Contribution', $sss];

                foreach ($deduction_types as $deduction) {
                    if ($deduction[1] > 0) {
                        $deduction_stmt = $pdo->prepare("
                            INSERT INTO payroll_deductions_contractual 
                            (payroll_id, deduction_type, deduction_amount, deduction_description)
                            VALUES (?, ?, ?, ?)
                        ");
                        $deduction_stmt->execute([
                            $existing['id'],
                            $deduction[0],
                            $deduction[1],
                            $deduction[0] . ' deduction for ' . $payroll_period . ' (' . $payroll_cutoff . ')'
                        ]);
                    }
                }

                $processed_count++;
            } else {
                // INSERT new record
                error_log("INSERTING new record for employee: $employee_id");

                $insert_sql = "INSERT INTO payroll_history_contractual 
                    (employee_id, user_id, payroll_period, payroll_cutoff, 
                     monthly_salaries_wages, monthly_salary, other_comp, gross_amount, earned_salary,
                     withholding_tax, sss, total_deductions, net_amount, days_present, working_days,
                     status, processed_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute([
                    $employee_id,
                    $user_id,
                    $payroll_period,
                    $payroll_cutoff,
                    $monthly_salaries_wages,
                    $monthly_salary,
                    $other_comp,
                    $gross_amount,
                    $gross_amount,
                    $withholding_tax,
                    $sss,
                    $total_deductions,
                    $net_amount,
                    $days_present,
                    $working_days
                ]);

                $new_id = $pdo->lastInsertId();
                error_log("Insert successful for employee $employee_id, new ID: $new_id");

                // Insert deductions
                $deduction_types = [];
                if ($withholding_tax > 0) $deduction_types[] = ['Withholding Tax', $withholding_tax];
                if ($sss > 0) $deduction_types[] = ['SSS Contribution', $sss];

                foreach ($deduction_types as $deduction) {
                    if ($deduction[1] > 0) {
                        $deduction_stmt = $pdo->prepare("
                            INSERT INTO payroll_deductions_contractual 
                            (payroll_id, deduction_type, deduction_amount, deduction_description)
                            VALUES (?, ?, ?, ?)
                        ");
                        $deduction_stmt->execute([
                            $new_id,
                            $deduction[0],
                            $deduction[1],
                            $deduction[0] . ' deduction for ' . $payroll_period . ' (' . $payroll_cutoff . ')'
                        ]);
                    }
                }

                $processed_count++;
            }
        }

        $pdo->commit();
        error_log("=== PAYROLL SAVE COMPLETED SUCCESSFULLY ===");

        $_SESSION['success_message'] = "Payroll saved successfully! $processed_count records processed.";
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Payroll save error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error saving payroll: " . $e->getMessage();
    }

    // Redirect
    $redirect_url = $_SERVER['PHP_SELF'] .
        "?period=" . urlencode($_POST['payroll_period'] ?? $current_payroll_period) .
        "&cutoff=" . urlencode($_POST['payroll_cutoff'] ?? 'full') .
        "&page=" . ($_POST['current_page'] ?? 1) .
        "&per_page=" . ($_POST['records_per_page'] ?? 10) .
        "&search=" . urlencode($search_term);

    header("Location: " . $redirect_url);
    exit();
}

// Handle payroll approval
if (isset($_GET['approve_payroll']) && isset($_GET['period']) && isset($_GET['cutoff'])) {
    try {
        $pdo->beginTransaction();

        $period = $_GET['period'];
        $cutoff = $_GET['cutoff'];
        $approval_notes = $_POST['approval_notes'] ?? 'Approved via system';

        $update_sql = "UPDATE payroll_history_contractual SET 
            status = 'approved',
            approved_by = ?,
            approved_date = NOW()
            WHERE payroll_period = ? AND payroll_cutoff = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$current_user_name, $period, $cutoff]);

        // Check if payroll_approvals table exists
        $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_approvals'");
        if ($table_check->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS payroll_approvals (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    payroll_period VARCHAR(7) NOT NULL,
                    payroll_cutoff VARCHAR(20) DEFAULT 'full',
                    employee_type VARCHAR(50) DEFAULT 'contractual',
                    approved_by VARCHAR(100),
                    approval_notes TEXT,
                    status VARCHAR(20) DEFAULT 'approved',
                    approved_date DATETIME,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        $approval_stmt = $pdo->prepare("
            INSERT INTO payroll_approvals
            (payroll_period, payroll_cutoff, employee_type, approved_by, approval_notes, status, approved_date)
            VALUES (?, ?, 'contractual', ?, ?, 'approved', NOW())
        ");
        $approval_stmt->execute([$period, $cutoff, $current_user_name, $approval_notes]);

        $pdo->commit();
        $_SESSION['success_message'] = "Payroll approved successfully!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?period=" . $period . "&cutoff=" . $cutoff . "&page=" . ($_GET['page'] ?? 1) . "&per_page=" . ($_GET['per_page'] ?? 10) . "&search=" . urlencode($search_term));
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error approving payroll: " . $e->getMessage();
    }
}

// Handle payroll payment
if (isset($_GET['mark_paid']) && isset($_GET['period']) && isset($_GET['cutoff']) && isset($_GET['payroll_id'])) {
    try {
        $pdo->beginTransaction();

        $period = $_GET['period'];
        $cutoff = $_GET['cutoff'];
        $payroll_id = $_GET['payroll_id'];
        $payment_method = $_POST['payment_method'] ?? 'bank_transfer';
        $reference_number = $_POST['reference_number'] ?? 'PAY-' . time();

        $payroll_stmt = $pdo->prepare("
            SELECT * FROM payroll_history_contractual WHERE id = ?
        ");
        $payroll_stmt->execute([$payroll_id]);
        $payroll = $payroll_stmt->fetch();

        if ($payroll) {
            // Check if payroll_payments table exists
            $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_payments'");
            if ($table_check->rowCount() == 0) {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS payroll_payments (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        payroll_id INT NOT NULL,
                        payment_date DATE,
                        payment_method VARCHAR(50),
                        reference_number VARCHAR(100),
                        payment_amount DECIMAL(10,2),
                        payment_status VARCHAR(20) DEFAULT 'completed',
                        processed_by VARCHAR(100),
                        notes TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (payroll_id) REFERENCES payroll_history_contractual(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            }

            $payment_stmt = $pdo->prepare("
                INSERT INTO payroll_payments
                (payroll_id, payment_date, payment_method, reference_number, 
                payment_amount, payment_status, processed_by, notes)
                VALUES (?, CURDATE(), ?, ?, ?, 'completed', ?, ?)
            ");
            $payment_stmt->execute([
                $payroll_id,
                $payment_method,
                $reference_number,
                $payroll['net_amount'],
                $current_user_name,
                'Payment processed for ' . $period . ' (' . ($cutoff_ranges[$cutoff]['label'] ?? $cutoff) . ')'
            ]);

            $update_stmt = $pdo->prepare("
                UPDATE payroll_history_contractual 
                SET status = 'paid', updated_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([$payroll_id]);
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Payroll marked as paid successfully!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?period=" . $period . "&cutoff=" . $cutoff . "&page=" . ($_GET['page'] ?? 1) . "&per_page=" . ($_GET['per_page'] ?? 10) . "&search=" . urlencode($search_term));
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error marking payroll as paid: " . $e->getMessage();
    }
}

// Fetch tax configuration
$tax_config = [];
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'tax_configuration'");
    if ($table_check->rowCount() > 0) {
        $tax_stmt = $pdo->query("
            SELECT * FROM tax_configuration 
            WHERE tax_year = YEAR(CURDATE()) AND is_active = 1
            ORDER BY tax_bracket_min
        ");
        $tax_config = $tax_stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Tax configuration table not found: " . $e->getMessage());
    $tax_config = [];
}

// Default rates
$sss_rate = 4.50;

// Get total count of employees for pagination with search - FIXED with DISTINCT
$total_employees = 0;
try {
    $count_sql = "SELECT COUNT(DISTINCT id) FROM contractofservice WHERE status = 'active'";
    if (!empty($search_term)) {
        $count_sql .= " AND (employee_id LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR CONCAT(first_name, ' ', last_name) LIKE :search OR designation LIKE :search OR office LIKE :search)";
    }
    $count_stmt = $pdo->prepare($count_sql);
    if (!empty($search_term)) {
        $search_param = "%$search_term%";
        $count_stmt->bindParam(':search', $search_param);
    }
    $count_stmt->execute();
    $total_employees = $count_stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Error counting employees: " . $e->getMessage());
}

// Calculate total pages
$total_pages = ceil($total_employees / $records_per_page);
$offset = ($current_page - 1) * $records_per_page;

// Fetch contractual employees from contractofservice table with pagination and search - FIXED to avoid duplicates
$contractual_employees = [];

try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'contractofservice'");
    if ($table_check->rowCount() == 0) {
        throw new Exception("contractofservice table does not exist");
    }

    $sql = "
        SELECT DISTINCT
            id as user_id, 
            employee_id, 
            CONCAT(first_name, ' ', last_name) as full_name,
            first_name, 
            last_name,
            designation as position, 
            office as department
        FROM contractofservice 
        WHERE status = 'active'
    ";

    if (!empty($search_term)) {
        $sql .= " AND (employee_id LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR CONCAT(first_name, ' ', last_name) LIKE :search OR designation LIKE :search OR office LIKE :search)";
    }

    $sql .= " ORDER BY last_name, first_name LIMIT :offset, :per_page";

    $stmt = $pdo->prepare($sql);

    if (!empty($search_term)) {
        $search_param = "%$search_term%";
        $stmt->bindParam(':search', $search_param);
    }
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $employees = $stmt->fetchAll();

    // Get attendance and payroll data for each employee - FIXED to avoid reference issues
    $processed_employees = [];
    $seen_ids = [];

    foreach ($employees as $employee) {
        // Skip if we've already processed this ID
        if (in_array($employee['user_id'], $seen_ids)) {
            continue;
        }
        $seen_ids[] = $employee['user_id'];

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

        // Get salary using the salary column function
        $salary_col = getSalaryColumnName($pdo);
        $monthly_salary = 0;

        if ($salary_col) {
            $salary_stmt = $pdo->prepare("
                SELECT $salary_col as salary_value 
                FROM contractofservice 
                WHERE id = ?
            ");
            $salary_stmt->execute([$employee['user_id']]);
            $salary_data = $salary_stmt->fetch();

            if ($salary_data) {
                $salary_value = floatval($salary_data['salary_value'] ?? 0);
                if ($salary_col == 'rate_per_day' || $salary_col == 'daily_rate') {
                    $monthly_salary = $salary_value * 22;
                    $daily_rate = $salary_value;
                } else {
                    $monthly_salary = $salary_value;
                    $daily_rate = $monthly_salary / 22;
                }
            }
        } else {
            $monthly_salary = 0;
            $daily_rate = 0;
        }

        $prorated_salary = $daily_rate * $attendance_days;

        // Get payroll data from database - use employee_id string for lookup
        $payroll_data = getEmployeePayrollData($pdo, $employee['employee_id'], $employee['user_id'], $selected_period, $selected_cutoff, $prorated_salary);

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

        $processed_employees[] = $employee;
    }

    $contractual_employees = $processed_employees;
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error fetching employees: " . $e->getMessage();
    $contractual_employees = [];
}

// Calculate totals
$total_monthly_salaries_wages = 0;
$total_other_comp = 0;
$total_gross_amount = 0;
$total_withholding_tax = 0;
$total_sss = 0;
$total_deductions = 0;
$total_net_amount = 0;
$total_days_present = 0;
$employees_with_attendance = 0;

foreach ($contractual_employees as $employee) {
    $total_monthly_salaries_wages += floatval($employee['prorated_salary'] ?? 0);
    $total_other_comp += floatval($employee['other_comp'] ?? 0);
    $total_gross_amount += floatval($employee['gross_amount'] ?? 0);
    $days = floatval($employee['days_present'] ?? 0);
    $total_days_present += $days;
    if ($days > 0) $employees_with_attendance++;

    $total_withholding_tax += floatval($employee['withholding_tax'] ?? 0);
    $total_sss += floatval($employee['sss'] ?? 0);
    $total_deductions += floatval($employee['total_deductions'] ?? 0);
    $total_net_amount += floatval($employee['net_amount'] ?? 0);
}

// Get payroll summary for the period and cutoff
$payroll_summary = [];
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_summary'");
    if ($table_check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payroll_summary (
                id INT AUTO_INCREMENT PRIMARY KEY,
                payroll_period VARCHAR(7) NOT NULL,
                payroll_cutoff VARCHAR(20) DEFAULT 'full',
                employee_type VARCHAR(50) DEFAULT 'contractual',
                total_employees INT DEFAULT 0,
                total_days_present DECIMAL(10,2) DEFAULT 0,
                total_monthly_salaries_wages DECIMAL(10,2) DEFAULT 0,
                total_other_comp DECIMAL(10,2) DEFAULT 0,
                total_gross_amount DECIMAL(10,2) DEFAULT 0,
                total_withholding_tax DECIMAL(10,2) DEFAULT 0,
                total_sss DECIMAL(10,2) DEFAULT 0,
                total_deductions DECIMAL(10,2) DEFAULT 0,
                total_net_amount DECIMAL(10,2) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_period_cutoff (payroll_period, payroll_cutoff, employee_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    if ($selected_cutoff == 'full') {
        $summary_stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT employee_id) as total_employees,
                COALESCE(SUM(days_present), 0) as total_days_present,
                COALESCE(SUM(monthly_salaries_wages), 0) as total_monthly_salaries_wages,
                COALESCE(SUM(other_comp), 0) as total_other_comp,
                COALESCE(SUM(gross_amount), 0) as total_gross_amount,
                COALESCE(SUM(withholding_tax), 0) as total_withholding_tax,
                COALESCE(SUM(sss), 0) as total_sss,
                COALESCE(SUM(total_deductions), 0) as total_deductions,
                COALESCE(SUM(net_amount), 0) as total_net_amount
            FROM payroll_history_contractual 
            WHERE payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half')
        ");
        $summary_stmt->execute([$selected_period]);
        $payroll_summary = $summary_stmt->fetch();
    } else {
        $summary_stmt = $pdo->prepare("
            SELECT * FROM payroll_summary 
            WHERE payroll_period = ? AND payroll_cutoff = ? AND employee_type = 'contractual'
        ");
        $summary_stmt->execute([$selected_period, $selected_cutoff]);
        $payroll_summary = $summary_stmt->fetch();

        if (!$payroll_summary) {
            $calc_stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT employee_id) as total_employees,
                    COALESCE(SUM(days_present), 0) as total_days_present,
                    COALESCE(SUM(monthly_salaries_wages), 0) as total_monthly_salaries_wages,
                    COALESCE(SUM(other_comp), 0) as total_other_comp,
                    COALESCE(SUM(gross_amount), 0) as total_gross_amount,
                    COALESCE(SUM(withholding_tax), 0) as total_withholding_tax,
                    COALESCE(SUM(sss), 0) as total_sss,
                    COALESCE(SUM(total_deductions), 0) as total_deductions,
                    COALESCE(SUM(net_amount), 0) as total_net_amount
                FROM payroll_history_contractual 
                WHERE payroll_period = ? AND payroll_cutoff = ?
            ");
            $calc_stmt->execute([$selected_period, $selected_cutoff]);
            $payroll_summary = $calc_stmt->fetch();
        }
    }
} catch (Exception $e) {
    error_log("Payroll summary fetch error: " . $e->getMessage());
    $payroll_summary = [];
}

// Get payroll status for the period and cutoff
$payroll_status = 'pending';
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_history_contractual'");
    if ($table_check->rowCount() > 0) {
        if ($selected_cutoff == 'full') {
            $status_stmt = $pdo->prepare("
                SELECT DISTINCT status FROM payroll_history_contractual 
                WHERE payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half')
                LIMIT 1
            ");
        } else {
            $status_stmt = $pdo->prepare("
                SELECT DISTINCT status FROM payroll_history_contractual 
                WHERE payroll_period = ? AND payroll_cutoff = ?
                LIMIT 1
            ");
        }
        $status_stmt->execute([$selected_period, $selected_cutoff]);
        $status_result = $status_stmt->fetch();
        if ($status_result) {
            $payroll_status = $status_result['status'];
        }
    }
} catch (Exception $e) {
    error_log("Payroll status fetch error: " . $e->getMessage());
    $payroll_status = 'pending';
}

// Get approval history
$approval_history = [];
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_approvals'");
    if ($table_check->rowCount() > 0) {
        if ($selected_cutoff == 'full') {
            $approval_stmt = $pdo->prepare("
                SELECT * FROM payroll_approvals 
                WHERE payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half') AND employee_type = 'contractual'
                ORDER BY approved_date DESC
            ");
        } else {
            $approval_stmt = $pdo->prepare("
                SELECT * FROM payroll_approvals 
                WHERE payroll_period = ? AND payroll_cutoff = ? AND employee_type = 'contractual'
                ORDER BY approved_date DESC
            ");
        }
        $approval_stmt->execute([$selected_period, $selected_cutoff]);
        $approval_history = $approval_stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Approval history fetch error: " . $e->getMessage());
    $approval_history = [];
}

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
$info_message = $_SESSION['info_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['info_message']);

// Flush output buffer at the end
ob_end_flush();
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --primary-dark: #1e3a8a;
            --primary-light: #3b82f6;
            --secondary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #1f2937;
            --light: #f8fafc;
            --gradient-primary: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: var(--dark);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Navbar Styling - Matches Employee.php */
        .navbar {
            background: var(--gradient-primary);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
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
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .mobile-toggle {
            display: none;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
        }

        .mobile-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: translateY(-2px);
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: white;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .datetime-container {
            display: flex;
            gap: 1rem;
        }

        .datetime-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .datetime-box:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .datetime-icon {
            color: white;
            font-size: 1rem;
        }

        .datetime-text {
            display: flex;
            flex-direction: column;
        }

        .datetime-label {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .datetime-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
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

        /* Sidebar - Matches Employee.php */
        .sidebar-container {
            position: fixed;
            left: 0;
            top: 70px;
            width: 260px;
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
            z-index: 999;
            transition: transform 0.3s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }

        .sidebar {
            height: 100%;
            padding: 1.5rem 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-content {
            flex: 1;
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

        .sidebar-dropdown-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            margin-left: 2.5rem;
        }

        .sidebar-dropdown-menu.open {
            max-height: 500px;
        }

        .sidebar-dropdown-item {
            display: flex;
            align-items: center;
            padding: 0.7rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .sidebar-dropdown-item:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-dropdown-item.active {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }

        .sidebar-dropdown-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: white;
            border-radius: 0 3px 3px 0;
        }

        .sidebar-dropdown-item i {
            font-size: 0.7rem;
            margin-right: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            transition: color 0.3s ease;
        }

        .sidebar-dropdown-item:hover i,
        .sidebar-dropdown-item.active i {
            color: white;
        }

        .chevron {
            transition: transform 0.3s ease;
        }

        .chevron.rotated {
            transform: rotate(180deg);
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: white;
            font-size: 0.75rem;
        }

        /* Overlay for mobile */
        .overlay {
            position: fixed;
            top: 70px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 998;
            display: none;
        }

        .overlay.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .sidebar-container {
                transform: translateX(-100%);
            }

            .sidebar-container.active {
                transform: translateX(0);
            }

            .mobile-toggle {
                display: flex;
            }

            .datetime-container {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .brand-text {
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

            .main-content {
                padding: 0.75rem;
            }
        }

        @media (max-width: 640px) {
            .navbar {
                height: 65px;
            }

            .sidebar-container {
                top: 65px;
                height: calc(100vh - 65px);
            }

            .main-content {
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

        @media (min-width: 769px) {
            .mobile-brand {
                display: none;
            }

            .brand-text {
                display: flex;
            }
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .payroll-table {
            min-width: 1600px;
            width: 100%;
            border-collapse: collapse;
        }

        .payroll-table th {
            background: #f8fafc;
            padding: 0.75rem;
            font-weight: 600;
            color: #374151;
            border: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .payroll-table td {
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .payroll-table tbody tr:hover {
            background: #f9fafb;
        }

        /* Checkbox Styling */
        .employee-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        #select-all {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        /* Input Fields */
        .payroll-input {
            width: 100%;
            padding: 0.25rem 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            text-align: right;
            transition: all 0.2s ease;
        }

        .payroll-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .payroll-input.readonly {
            background-color: #f3f4f6;
            border-color: #e5e7eb;
            color: #374151;
            cursor: not-allowed;
        }

        .payroll-input.editable {
            background-color: #ffffff;
        }

        .payroll-input.editable:hover {
            border-color: #3b82f6;
        }

        /* Days Present Badge */
        .days-present-badge {
            display: inline-block;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.25rem;
        }

        .days-present-badge.warning {
            background: #fee2e2;
            color: #b91c1c;
        }

        .days-present-badge.success {
            background: #d1fae5;
            color: #065f46;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.25rem;
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
            transition: all 0.2s ease;
            background: #3b82f6;
            color: white;
        }

        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Print Actions Bar */
        .print-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background: #f9fafb;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
        }

        .selected-count {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--primary);
            background: #eff6ff;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
        }

        /* Search Container */
        .search-container {
            position: relative;
            max-width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        /* Pagination */
        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.5rem;
            height: 2.5rem;
            padding: 0 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #4b5563;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .pagination-btn:hover:not(:disabled):not(.active) {
            background: #f3f4f6;
            border-color: #9ca3af;
            color: #374151;
        }

        .pagination-btn.active {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Per Page Selector */
        .per-page-selector {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.25rem 0.75rem;
        }

        .per-page-selector select {
            border: none;
            background: transparent;
            font-size: 0.875rem;
            color: #374151;
            outline: none;
            cursor: pointer;
            padding: 0.25rem 0;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-paid {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1100;
            padding: 1rem;
        }

        .modal-overlay.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            border-radius: 18px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: slideUp 0.3s ease;
        }

        .modal-content.large {
            max-width: 1000px;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            padding: 1.25rem 1.5rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .close-button {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
        }

        .close-button:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            max-height: calc(90vh - 80px);
        }

        /* Employee Details Modal Styles */
        .attendance-summary {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .attendance-card {
            flex: 1;
            min-width: 120px;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 0.5rem;
            text-align: center;
        }

        .attendance-card .value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .attendance-card .label {
            font-size: 0.8rem;
            color: #6b7280;
        }

        /* Loading Spinner */
        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: #6b7280;
        }

        .spinner {
            width: 2.5rem;
            height: 2.5rem;
            border: 3px solid #e5e7eb;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
        }

        .stat-icon.blue {
            background: #dbeafe;
            color: #1e40af;
        }

        .stat-icon.green {
            background: #d1fae5;
            color: #059669;
        }

        .stat-icon.purple {
            background: #ede9fe;
            color: #7c3aed;
        }

        .stat-icon.orange {
            background: #ffedd5;
            color: #ea580c;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1f2937;
            line-height: 1;
        }

        /* Auto-save Indicator */
        .auto-save-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #1e40af;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 9999;
            animation: slideUp 0.3s ease;
        }

        .auto-save-indicator.saving {
            background: #f59e0b;
        }

        .auto-save-indicator.saved {
            background: #10b981;
        }

        .auto-save-indicator.error {
            background: #ef4444;
        }

        @keyframes slideUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .loading-overlay.active {
            display: flex;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
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
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Auto-save Indicator -->
    <div class="auto-save-indicator" id="autoSaveIndicator" style="display: none;">
        <i class="fas fa-spinner fa-spin"></i>
        <span>Saving...</span>
    </div>

    <!-- Navigation Header - Matching Employee.php -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Left Section -->
            <div class="navbar-left">
                <!-- Mobile Menu Toggle -->
                <button class="mobile-toggle" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- Logo and Brand -->
                <a href="../dashboard.php" class="navbar-brand">
                    <img class="brand-logo"
                        src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                        alt="Logo" />
                    <div class="brand-text">
                        <span class="brand-title">HR Management System</span>
                        <span class="brand-subtitle">Paluan Occidental Mindoro</span>
                    </div>
                </a>

                <!-- Mobile Brand -->
                <div class="mobile-brand lg:hidden">
                    <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
                    <div class="mobile-brand-text">
                        <span class="mobile-brand-title">HRMS</span>
                        <span class="mobile-brand-subtitle">Contractual</span>
                    </div>
                </div>
            </div>

            <!-- Right Section -->
            <div class="navbar-right">
                <!-- Date & Time -->
                <div class="datetime-container">
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

                <!-- Logout Button -->
                <a href="?logout=true" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar - Matching Employee.php with Contractual active -->
    <div class="sidebar-container" id="sidebar-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <!-- Dashboard -->
                <a href="../dashboard.php" class="sidebar-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard Analytics</span>
                </a>

                <!-- Employees -->
                <a href="../employees/Employee.php" class="sidebar-item">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>

                <!-- Attendance -->
                <a href="../attendance.php" class="sidebar-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>

                <!-- Payroll - Active and Open -->
                <a href="#" class="sidebar-item active" id="payroll-toggle">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll</span>
                    <i class="fas fa-chevron-down chevron rotated ml-auto"></i>
                </a>
                <div class="sidebar-dropdown-menu open" id="payroll-dropdown">
                    <a href="contractualpayrolltable1.php" class="sidebar-dropdown-item active">
                        <i class="fas fa-circle text-xs"></i>
                        Contractual
                    </a>
                    <a href="joboerderpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Job Order
                    </a>
                    <a href="permanentpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Permanent
                    </a>
                </div>

                <!-- Settings -->
                <a href="../settings.php" class="sidebar-item">
                    <i class="fas fa-sliders-h"></i>
                    <span>Settings</span>
                </a>

                <!-- Logout -->
                <a href="?logout=true" class="sidebar-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="text-center text-white/60 text-sm">
                    <p>HRMS v2.0</p>
                    <p class="text-xs mt-1">© 2024 Paluan LGU</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="bg-white rounded-xl shadow-lg p-4 md:p-6">
            <!-- Breadcrumb Navigation -->
            <nav class="flex mb-4 overflow-x-auto">
                <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse whitespace-nowrap">
                    <li class="inline-flex items-center">
                        <a href="contractualpayrolltable1.php"
                            class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-600">
                            <i class="fas fa-home mr-2"></i>Contractual Payroll
                        </a>
                    </li>
                    <li>
                        <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                        <a href="contractual_payroll_obligation.php" class="ms-1 text-sm font-medium hover:text-blue-600 md:ms-2">Payroll & Obligation</a>
                    </li>
                </ol>
            </nav>

            <!-- Alert Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($info_message): ?>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle mr-2"></i> <?php echo htmlspecialchars($info_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error mb-4">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Business Rules Info Banner -->
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Contractual Payroll Rules:</strong>
                            Gross amount = days present × daily rate + other compensation.
                            <span class="font-bold">Deductions include: Withholding Tax and SSS Contribution.</span>
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

            <!-- Page Header with Title and Controls -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-4">
                <!-- Left side - Title -->
                <div class="flex items-center gap-3">
                    <div class="bg-blue-100 p-2 rounded-lg">
                        <i class="fas fa-file-invoice text-blue-600 text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Contractual Payroll</h1>
                        <p class="text-xs text-gray-500">Manage contractual employee payroll</p>
                    </div>
                </div>

                <!-- Right side - Controls -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <!-- Period and Cutoff Group -->
                    <div class="flex flex-col sm:flex-row bg-white border border-gray-200 rounded-lg overflow-hidden divide-y sm:divide-y-0 sm:divide-x divide-gray-200">
                        <!-- Period Dropdown -->
                        <select id="payroll-period"
                            class="px-3 py-2 text-sm border-0 focus:ring-0 bg-transparent min-w-[140px]">
                            <?php
                            for ($i = 0; $i < 12; $i++) {
                                $date = date('Y-m', strtotime("-$i months"));
                                $display = date('F Y', strtotime("-$i months"));
                                $selected = ($date == $selected_period) ? 'selected' : '';
                                echo "<option value=\"$date\" $selected>$display</option>";
                            }
                            ?>
                        </select>

                        <!-- Cutoff Buttons Group -->
                        <div class="flex divide-x divide-gray-200">
                            <a href="?period=<?php echo $selected_period; ?>&cutoff=full&page=1&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                class="px-3 py-2 text-xs font-medium <?php echo ($selected_cutoff == 'full') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                                Full
                            </a>
                            <a href="?period=<?php echo $selected_period; ?>&cutoff=first_half&page=1&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                class="px-3 py-2 text-xs font-medium <?php echo ($selected_cutoff == 'first_half') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                                1st Half
                            </a>
                            <a href="?period=<?php echo $selected_period; ?>&cutoff=second_half&page=1&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                class="px-3 py-2 text-xs font-medium <?php echo ($selected_cutoff == 'second_half') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
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

                    <!-- Action Buttons Group -->
                    <div class="flex items-center gap-1">
                        <!-- Print Button -->
                        <button id="print-selected-btn" class="print-btn px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg transition-colors flex items-center gap-1" onclick="printSelectedPayslips()" disabled>
                            <i class="fas fa-print"></i>
                            <span class="hidden sm:inline">Print Payslips</span>
                            <span id="selected-count-badge" class="ml-1 px-1.5 py-0.5 bg-white text-green-700 rounded-full text-xs font-bold hidden">0</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Info Bar (shows only on small screens) -->
            <div class="sm:hidden flex items-center gap-2 bg-blue-50 px-3 py-2 rounded-lg mb-3 text-xs text-blue-700">
                <i class="fas fa-info-circle text-blue-500"></i>
                <span class="truncate">
                    <?php echo date('M d', strtotime($current_cutoff['start'])); ?> -
                    <?php echo date('M d, Y', strtotime($current_cutoff['end'])); ?>
                    (<?php echo $current_cutoff['working_days']; ?> days)
                    <?php if ($is_full_month): ?>
                        <span class="ml-1 px-1.5 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-[10px] font-medium">Read-Only Fields</span>
                    <?php else: ?>
                        <span class="ml-1 px-1.5 py-0.5 bg-green-100 text-green-700 rounded-full text-[10px] font-medium">Editable</span>
                    <?php endif; ?>
                </span>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-label">Total Employees</div>
                    <div class="stat-value"><?php echo $total_employees; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-label">With Attendance</div>
                    <div class="stat-value"><?php echo $employees_with_attendance; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="stat-label">Total Deductions</div>
                    <div class="stat-value" id="total-deductions-display">₱<?php echo number_format($total_deductions, 2); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-label">Net Amount</div>
                    <div class="stat-value" id="net-amount-display">₱<?php echo number_format($total_net_amount, 2); ?></div>
                </div>
            </div>

            <!-- Print Actions Bar (Shows when items are selected) -->
            <div id="print-actions-bar" class="print-actions hidden">
                <span class="selected-count">
                    <i class="fas fa-check-circle"></i>
                    <span id="selected-count-text">0</span> employee(s) selected
                </span>
                <button onclick="clearSelections()" class="text-xs text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>

            <!-- Search Bar and Per Page Selector -->
            <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="search-employees" class="search-input" placeholder="Search employees..." value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="per-page-selector">
                    <label for="per-page" class="text-sm text-gray-600">Show:</label>
                    <select id="per-page" onchange="changePerPage(this.value)">
                        <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>

            <!-- Payroll Table -->
            <form method="POST" action="" id="payroll-form">
                <input type="hidden" name="save_payroll" value="1">
                <input type="hidden" name="payroll_period" id="hidden-payroll-period" value="<?php echo $selected_period; ?>">
                <input type="hidden" name="payroll_cutoff" id="hidden-payroll-cutoff" value="<?php echo $selected_cutoff; ?>">
                <input type="hidden" name="working_days" value="<?php echo $current_cutoff['working_days']; ?>">
                <input type="hidden" name="current_page" id="current-page-input" value="<?php echo $current_page; ?>">
                <input type="hidden" name="records_per_page" id="records-per-page-input" value="<?php echo $records_per_page; ?>">

                <div class="card">
                    <div class="p-4 border-b border-gray-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <h2 class="text-lg font-semibold text-gray-900">
                            Contractual Payroll Details for <?php echo date('F Y', strtotime($selected_period . '-01')); ?>
                            (<?php echo $current_cutoff['label']; ?>)
                        </h2>
                        <?php if ($is_full_month): ?>
                            <div class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-medium">
                                <i class="fas fa-lock mr-1"></i> All deduction fields are read-only in Full Month view
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="table-container">
                        <table class="payroll-table" id="payroll-table">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="w-12">
                                        <input type="checkbox" id="select-all" class="border border-gray-500 bg-transparent rounded-sm" title="Select all employees">
                                    </th>
                                    <th rowspan="2" class="w-12">#</th>
                                    <th rowspan="2" class="min-w-[120px]">Employee ID</th>
                                    <th rowspan="2" class="min-w-[150px]">Name</th>
                                    <th rowspan="2" class="min-w-[120px]">Position</th>
                                    <th rowspan="2" class="min-w-[100px]">Department</th>
                                    <th rowspan="2" class="min-w-[100px]">Days Present</th>
                                    <th colspan="4" class="text-center compensation-header">Compensation</th>
                                    <th colspan="3" class="text-center deductions-header">Deductions</th>
                                    <th rowspan="2" class="min-w-[100px] bg-gray-100">Net Amount</th>
                                    <th rowspan="2" class="min-w-[90px]">Actions</th>
                                </tr>
                                <tr>
                                    <th class="min-w-[100px] compensation-header">Daily Rate</th>
                                    <th class="min-w-[120px] compensation-header">Monthly Salary (Base)</th>
                                    <th class="min-w-[100px] compensation-header <?php echo $is_full_month ? 'opacity-70' : ''; ?>">Other Compensation</th>
                                    <th class="min-w-[100px] compensation-header">Gross Amount Earned</th>
                                    <th class="min-w-[100px] deductions-header <?php echo $is_full_month ? 'opacity-70' : ''; ?>">Withholding Tax</th>
                                    <th class="min-w-[100px] deductions-header <?php echo $is_full_month ? 'opacity-70' : ''; ?>">SSS</th>
                                    <th class="min-w-[100px] deductions-header">Total Deductions</th>
                                </tr>
                            </thead>
                            <tbody id="payroll-tbody">
                                <?php if (empty($contractual_employees)): ?>
                                    <tr>
                                        <td colspan="17" class="text-center py-8 text-gray-500">
                                            No contractual employees found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $counter = $offset + 1; ?>
                                    <?php foreach ($contractual_employees as $employee): ?>
                                        <?php
                                        $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
                                        $daily_rate = floatval($employee['daily_rate'] ?? 0);
                                        $prorated_salary = floatval($employee['prorated_salary'] ?? 0);
                                        $other_comp = floatval($employee['other_comp'] ?? 0);
                                        $days_present = floatval($employee['days_present'] ?? 0);
                                        $gross_amount = floatval($employee['gross_amount'] ?? 0);
                                        $withholding_tax = floatval($employee['withholding_tax'] ?? 0);
                                        $sss = floatval($employee['sss'] ?? 0);
                                        $total_deductions_row = floatval($employee['total_deductions'] ?? 0);
                                        $net_amount_row = floatval($employee['net_amount'] ?? 0);
                                        $payroll_id = $employee['payroll_id'] ?? null;
                                        $payroll_exists = $employee['payroll_exists'] ?? false;

                                        // Ensure we have valid values
                                        if ($gross_amount == 0 && $prorated_salary > 0) {
                                            $gross_amount = $prorated_salary + $other_comp;
                                        }
                                        if ($total_deductions_row == 0 && ($withholding_tax > 0 || $sss > 0)) {
                                            $total_deductions_row = $withholding_tax + $sss;
                                        }
                                        if ($net_amount_row == 0 && $gross_amount > 0) {
                                            $net_amount_row = $gross_amount - $total_deductions_row;
                                            if ($net_amount_row < 0) $net_amount_row = 0;
                                        }

                                        $has_attendance = ($days_present > 0);
                                        $row_class = $has_attendance ? '' : 'no-attendance';

                                        $full_month_readonly = $is_full_month ? 'readonly disabled' : '';
                                        $full_month_style = $is_full_month ? 'background-color: #f0f0f0; color: #888; cursor: not-allowed;' : '';
                                        ?>
                                        <tr class="bg-white border-b hover:bg-gray-50 payroll-row <?php echo $row_class; ?>" data-user-id="<?php echo $employee['user_id']; ?>" data-employee-id="<?php echo htmlspecialchars($employee['employee_id']); ?>" data-payroll-id="<?php echo $payroll_id; ?>" data-payroll-exists="<?php echo $payroll_exists ? '1' : '0'; ?>">
                                            <td class="text-center">
                                                <input type="checkbox" class="employee-checkbox border border-gray-500 bg-transparent rounded-sm" value="<?php echo htmlspecialchars($employee['employee_id']); ?>" data-employee-name="<?php echo htmlspecialchars($employee['full_name']); ?>" data-user-id="<?php echo $employee['user_id']; ?>">
                                            </td>
                                            <td class="text-center"><?php echo $counter++; ?></td>
                                            <td class="font-medium"><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                            <td class="font-medium">
                                                <?php echo htmlspecialchars($employee['full_name']); ?>
                                                <input type="hidden" name="user_id[]" class="hidden-user-id" value="<?php echo $employee['user_id']; ?>">
                                                <input type="hidden" name="employee_id[]" class="hidden-employee-id" value="<?php echo htmlspecialchars($employee['employee_id']); ?>">
                                                <input type="hidden" name="days_present[]" class="hidden-days-present" value="<?php echo $days_present; ?>">
                                            </td>
                                            <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['department']); ?></td>

                                            <!-- Days Present -->
                                            <td>
                                                <span class="font-medium"><?php echo number_format($days_present, 1); ?></span>
                                                <?php if ($days_present <= 0): ?>
                                                    <span class="days-present-badge warning">No attendance</span>
                                                <?php else: ?>
                                                    <span class="days-present-badge success">Present</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Daily Rate - READONLY -->
                                            <td>
                                                <input type="number"
                                                    name="daily_rate[]"
                                                    class="payroll-input readonly disabled-field daily-rate"
                                                    value="<?php echo number_format($daily_rate, 2, '.', ''); ?>"
                                                    readonly
                                                    disabled
                                                    tabindex="-1">
                                            </td>

                                            <!-- Monthly Salary (Base) - READONLY -->
                                            <td>
                                                <input type="number"
                                                    name="monthly_salaries_wages[]"
                                                    class="payroll-input readonly disabled-field monthly-salaries-wages"
                                                    value="<?php echo number_format($monthly_salary, 2, '.', ''); ?>"
                                                    readonly
                                                    disabled
                                                    tabindex="-1">
                                            </td>

                                            <!-- Other Compensation - DISABLED in FULL MONTH, EDITABLE in half months -->
                                            <td>
                                                <input type="number"
                                                    name="other_comp[]"
                                                    class="payroll-input other-comp <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                                                    value="<?php echo number_format($other_comp, 2, '.', ''); ?>"
                                                    min="0"
                                                    step="0.01"
                                                    data-user-id="<?php echo $employee['user_id']; ?>"
                                                    data-field="other_comp"
                                                    <?php echo $full_month_readonly; ?>
                                                    <?php echo $is_full_month ? 'title="Other Compensation can only be edited in Half Month views"' : ''; ?>
                                                    style="<?php echo $full_month_style; ?>">
                                            </td>

                                            <!-- Gross Amount - FROM DATABASE, READONLY -->
                                            <td>
                                                <input type="number"
                                                    name="gross_amount[]"
                                                    class="payroll-input gross-amount readonly disabled-field <?php echo ($gross_amount <= 0) ? 'zero-amount' : ''; ?>"
                                                    value="<?php echo number_format($gross_amount, 2, '.', ''); ?>"
                                                    readonly
                                                    disabled
                                                    tabindex="-1">
                                            </td>

                                            <!-- Withholding Tax - DISABLED in FULL MONTH, EDITABLE in half months -->
                                            <td>
                                                <input type="number"
                                                    name="withholding_tax[]"
                                                    class="payroll-input withholding-tax <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                                                    value="<?php echo number_format($withholding_tax, 2, '.', ''); ?>"
                                                    min="0"
                                                    step="0.01"
                                                    data-user-id="<?php echo $employee['user_id']; ?>"
                                                    data-field="withholding_tax"
                                                    <?php echo $full_month_readonly; ?>
                                                    <?php echo $is_full_month ? 'title="Withholding Tax can only be edited in Half Month views"' : ''; ?>
                                                    style="<?php echo $full_month_style; ?>">
                                            </td>

                                            <!-- SSS Contribution - DISABLED in FULL MONTH, EDITABLE in half months -->
                                            <td>
                                                <input type="number"
                                                    name="sss[]"
                                                    class="payroll-input sss <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                                                    value="<?php echo number_format($sss, 2, '.', ''); ?>"
                                                    min="0"
                                                    step="0.01"
                                                    data-user-id="<?php echo $employee['user_id']; ?>"
                                                    data-field="sss"
                                                    <?php echo $full_month_readonly; ?>
                                                    <?php echo $is_full_month ? 'title="SSS Contribution can only be edited in Half Month views"' : ''; ?>
                                                    style="<?php echo $full_month_style; ?>">
                                            </td>

                                            <!-- Total Deduction - FROM DATABASE, READONLY -->
                                            <td>
                                                <input type="number"
                                                    name="total_deduction[]"
                                                    class="payroll-input total-deduction readonly disabled-field"
                                                    value="<?php echo number_format($total_deductions_row, 2, '.', ''); ?>"
                                                    readonly
                                                    disabled
                                                    tabindex="-1">
                                            </td>

                                            <!-- Net Amount - FROM DATABASE, READONLY -->
                                            <td>
                                                <input type="number"
                                                    name="net_amount[]"
                                                    class="payroll-input net-amount readonly disabled-field <?php echo ($net_amount_row <= 0) ? 'zero-amount' : ''; ?>"
                                                    value="<?php echo number_format($net_amount_row, 2, '.', ''); ?>"
                                                    readonly
                                                    disabled
                                                    tabindex="-1">
                                            </td>

                                            <td>
                                                <div class="action-buttons">
                                                    <button type="button" class="action-btn view-btn" onclick="viewEmployeeDetails(<?php echo $employee['user_id']; ?>, '<?php echo $selected_period; ?>', '<?php echo $selected_cutoff; ?>')">
                                                        <i class="fas fa-eye"></i> <span class="hidden md:inline">View</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <!-- Totals Row -->
                                <tr class="bg-gray-100 font-bold">
                                    <td></td>
                                    <td colspan="6" class="text-right">TOTAL AMOUNT</td>
                                    <td class="text-right">-</td>
                                    <td class="text-right" id="total-monthly-salaries-wages">₱<?php echo number_format($total_monthly_salaries_wages, 2); ?></td>
                                    <td class="text-right" id="total-other-comp">₱<?php echo number_format($total_other_comp, 2); ?></td>
                                    <td class="text-right" id="total-gross-amount">₱<?php echo number_format($total_gross_amount, 2); ?></td>
                                    <td class="text-right" id="total-withholding-tax">₱<?php echo number_format($total_withholding_tax, 2); ?></td>
                                    <td class="text-right" id="total-sss">₱<?php echo number_format($total_sss, 2); ?></td>
                                    <td class="text-right" id="total-deduction">₱<?php echo number_format($total_deductions, 2); ?></td>
                                    <td class="text-right" id="total-net-amount">₱<?php echo number_format($total_net_amount, 2); ?></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Table Footer with Pagination -->
                    <div class="table-footer" id="pagination-container">
                        <div class="text-sm text-gray-600">
                            Showing <span class="font-medium" id="showing-from"><?php echo $offset + 1; ?></span> to
                            <span class="font-medium" id="showing-to"><?php echo min($offset + $records_per_page, $total_employees); ?></span> of
                            <span class="font-medium" id="total-employees"><?php echo $total_employees; ?></span> employees
                        </div>

                        <div class="pagination-controls" id="pagination-controls">
                            <button type="button" class="pagination-btn" onclick="changePage(1)" <?php echo $current_page <= 1 ? 'disabled' : ''; ?> title="First Page">
                                <i class="fas fa-angle-double-left"></i>
                            </button>
                            <button type="button" class="pagination-btn" onclick="changePage(<?php echo $current_page - 1; ?>)" <?php echo $current_page <= 1 ? 'disabled' : ''; ?> title="Previous Page">
                                <i class="fas fa-angle-left"></i>
                            </button>

                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            if ($start_page > 1) {
                                echo '<button type="button" class="pagination-btn" onclick="changePage(1)">1</button>';
                                if ($start_page > 2) {
                                    echo '<span class="pagination-ellipsis">...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active_class = ($i == $current_page) ? 'active' : '';
                                echo "<button type=\"button\" class=\"pagination-btn $active_class\" onclick=\"changePage($i)\">$i</button>";
                            }

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="pagination-ellipsis">...</span>';
                                }
                                echo "<button type=\"button\" class=\"pagination-btn\" onclick=\"changePage($total_pages)\">$total_pages</button>";
                            }
                            ?>

                            <button type="button" class="pagination-btn" onclick="changePage(<?php echo $current_page + 1; ?>)" <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?> title="Next Page">
                                <i class="fas fa-angle-right"></i>
                            </button>
                            <button type="button" class="pagination-btn" onclick="changePage(<?php echo $total_pages; ?>)" <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?> title="Last Page">
                                <i class="fas fa-angle-double-right"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($payroll_status == 'pending' || $payroll_status == 'draft'): ?>
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="px-4 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 flex items-center justify-center">
                            <i class="fas fa-save mr-2"></i> Save Payroll (<?php echo $current_cutoff['label']; ?>)
                        </button>
                    </div>
                <?php elseif ($payroll_status == 'approved'): ?>
                    <div class="mt-6 flex justify-end">
                        <button type="button" onclick="showApproveModal()" class="px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-check-circle mr-2"></i> Approve Payroll
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </main>

    <!-- View Employee Modal -->
    <div class="modal-overlay" id="viewEmployeeModal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3 class="text-lg font-semibold">Employee Details</h3>
                <button class="close-button" id="closeViewModalBtn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="viewEmployeeModalBody">
                <!-- Content will be loaded dynamically -->
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Loading employee details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Payroll Modal -->
    <div class="modal-overlay" id="approveModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-lg font-semibold">Approve Payroll</h3>
                <button class="close-button" id="closeApproveModalBtn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="?approve_payroll=1&period=<?php echo $selected_period; ?>&cutoff=<?php echo $selected_cutoff; ?>&page=<?php echo $current_page; ?>&per_page=<?php echo $records_per_page; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" method="POST">
                <div class="modal-body">
                    <p class="mb-4">Are you sure you want to approve the payroll for <?php echo date('F Y', strtotime($selected_period . '-01')); ?> (<?php echo $current_cutoff['label']; ?>)?</p>
                    <p class="mb-4 text-sm text-gray-600">This action cannot be undone.</p>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Approval Notes (Optional)</label>
                        <textarea name="approval_notes" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                            placeholder="Enter any notes about this approval..."></textarea>
                    </div>

                    <div class="bg-yellow-50 p-3 rounded-lg">
                        <p class="text-sm text-yellow-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            Once approved, payroll data will be locked and cannot be edited.
                        </p>
                    </div>
                </div>
                <div class="modal-footer p-4 border-t border-gray-200 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('approveModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                        Confirm Approval
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // ============================================
        // SIDEBAR FUNCTIONALITY - Matching Employee.php
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarContainer = document.getElementById('sidebar-container');
            const overlay = document.getElementById('overlay');
            const payrollToggle = document.getElementById('payroll-toggle');
            const payrollDropdown = document.getElementById('payroll-dropdown');

            // Ensure payroll dropdown is open by default on this page
            if (payrollToggle && payrollDropdown) {
                // Make sure dropdown is open and chevron is rotated
                payrollDropdown.classList.add('open');
                const chevron = payrollToggle.querySelector('.chevron');
                if (chevron) {
                    chevron.classList.add('rotated');
                }

                // Keep the toggle functionality but preserve open state
                payrollToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Toggle the dropdown
                    payrollDropdown.classList.toggle('open');

                    // Toggle chevron rotation
                    if (chevron) {
                        chevron.classList.toggle('rotated');
                    }
                });
            }

            // Toggle sidebar
            if (sidebarToggle && sidebarContainer && overlay) {
                sidebarToggle.addEventListener('click', function() {
                    sidebarContainer.classList.toggle('active');
                    overlay.classList.toggle('active');
                });

                overlay.addEventListener('click', function() {
                    sidebarContainer.classList.remove('active');
                    overlay.classList.remove('active');
                });
            }

            // Close sidebar on window resize if open
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024 && sidebarContainer.classList.contains('active')) {
                    sidebarContainer.classList.remove('active');
                    overlay.classList.remove('active');
                }
            });

            // Initialize date/time
            updateDateTime();
            setInterval(updateDateTime, 1000);

            // Initialize other functionality
            initCheckboxHandlers();
            initAutoSave();
            loadSelectionsFromStorage();
            syncCheckboxesWithSelections();

            // Modal close handlers
            const closeViewModalBtn = document.getElementById('closeViewModalBtn');
            if (closeViewModalBtn) {
                closeViewModalBtn.addEventListener('click', function() {
                    closeModal('viewEmployeeModal');
                });
            }

            const closeApproveModalBtn = document.getElementById('closeApproveModalBtn');
            if (closeApproveModalBtn) {
                closeApproveModalBtn.addEventListener('click', function() {
                    closeModal('approveModal');
                });
            }

            // Click outside to close modals
            window.addEventListener('click', function(e) {
                if (e.target.classList.contains('modal-overlay')) {
                    e.target.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // ============================================
        // DATE/TIME FUNCTIONS
        // ============================================
        function updateDateTime() {
            const now = new Date();
            const optionsDate = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            const dateString = now.toLocaleDateString('en-US', optionsDate);

            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;

            const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;

            document.getElementById('current-date').textContent = dateString;
            document.getElementById('current-time').textContent = timeString;
        }

        // ============================================
        // GLOBAL SELECTION FUNCTIONS
        // ============================================
        let selectedEmployeesMap = new Map();
        const storageKey = `contractual_selected_<?php echo $selected_period; ?>_<?php echo $selected_cutoff; ?>`;

        function loadSelectionsFromStorage() {
            try {
                const saved = sessionStorage.getItem(storageKey);
                if (saved) {
                    const selections = JSON.parse(saved);
                    selectedEmployeesMap.clear();

                    const uniqueSelections = new Map();
                    selections.forEach(item => {
                        if (item && item.id && item.id !== 'undefined' && item.id !== '') {
                            uniqueSelections.set(String(item.id), {
                                id: String(item.id),
                                name: item.name || 'Employee',
                                user_id: item.user_id || ''
                            });
                        }
                    });

                    selectedEmployeesMap = uniqueSelections;
                }
            } catch (e) {
                console.error('Error loading selections:', e);
                selectedEmployeesMap.clear();
            }
        }

        function saveSelectionsToStorage() {
            try {
                const selections = Array.from(selectedEmployeesMap.values())
                    .filter(item => item && item.id && item.id !== 'undefined' && item.id !== '');
                sessionStorage.setItem(storageKey, JSON.stringify(selections));
            } catch (e) {
                console.error('Error saving selections:', e);
            }
        }

        function syncCheckboxesWithSelections() {
            const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');
            let syncedCount = 0;

            employeeCheckboxes.forEach(checkbox => {
                const employeeId = String(checkbox.value);

                if (!employeeId || employeeId === 'undefined' || employeeId === '') {
                    return;
                }

                if (selectedEmployeesMap.has(employeeId)) {
                    checkbox.checked = true;
                    syncedCount++;
                } else {
                    checkbox.checked = false;
                }
            });

            updateSelectAllCheckbox();
            updateSelectedUI();
        }

        function updateSelectAllCheckbox() {
            const selectAllCheckbox = document.getElementById('select-all');
            const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');

            if (!selectAllCheckbox) return;

            const totalCheckboxes = employeeCheckboxes.length;
            const checkedCheckboxes = Array.from(employeeCheckboxes).filter(cb => cb.checked).length;

            if (totalCheckboxes === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCheckboxes === totalCheckboxes) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCheckboxes === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }
        }

        function updateSelectedUI() {
            const printBtn = document.getElementById('print-selected-btn');
            const printActionsBar = document.getElementById('print-actions-bar');
            const selectedCountBadge = document.getElementById('selected-count-badge');
            const selectedCountText = document.getElementById('selected-count-text');

            const validSelections = Array.from(selectedEmployeesMap.values())
                .filter(item => item && item.id && item.id !== 'undefined' && item.id !== '');

            const count = validSelections.length;

            if (count !== selectedEmployeesMap.size) {
                selectedEmployeesMap.clear();
                validSelections.forEach(item => {
                    selectedEmployeesMap.set(item.id, item);
                });
            }

            if (printBtn) {
                printBtn.disabled = count === 0;
                if (count > 0) {
                    printBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                    printBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                } else {
                    printBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                    printBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                }
            }

            if (selectedCountBadge) {
                selectedCountBadge.textContent = count;
                selectedCountBadge.classList.toggle('hidden', count === 0);
            }

            if (selectedCountText) {
                selectedCountText.textContent = count;
            }

            if (printActionsBar) {
                printActionsBar.classList.toggle('hidden', count === 0);
            }

            saveSelectionsToStorage();
        }

        // ============================================
        // CHECKBOX HANDLERS
        // ============================================
        function initCheckboxHandlers() {
            const selectAllCheckbox = document.getElementById('select-all');
            const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');

            employeeCheckboxes.forEach(checkbox => {
                checkbox.removeEventListener('change', handleCheckboxChange);
                checkbox.addEventListener('change', handleCheckboxChange);
            });

            if (selectAllCheckbox) {
                selectAllCheckbox.removeEventListener('change', handleSelectAllChange);
                selectAllCheckbox.addEventListener('change', handleSelectAllChange);
            }
        }

        function handleCheckboxChange(e) {
            const checkbox = e.currentTarget;
            const employeeId = String(checkbox.value);

            if (!employeeId || employeeId === 'undefined' || employeeId === '') {
                checkbox.checked = false;
                return;
            }

            const employeeName = checkbox.dataset.employeeName || 'Employee';
            const userId = checkbox.dataset.userId || '';

            if (checkbox.checked) {
                selectedEmployeesMap.set(employeeId, {
                    id: employeeId,
                    name: employeeName,
                    user_id: userId
                });
            } else {
                selectedEmployeesMap.delete(employeeId);
            }

            updateSelectAllCheckbox();
            updateSelectedUI();
        }

        function handleSelectAllChange(e) {
            const isChecked = e.currentTarget.checked;
            const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');

            employeeCheckboxes.forEach(checkbox => {
                const employeeId = String(checkbox.value);

                if (!employeeId || employeeId === 'undefined' || employeeId === '') {
                    checkbox.checked = false;
                    return;
                }

                const employeeName = checkbox.dataset.employeeName || 'Employee';
                const userId = checkbox.dataset.userId || '';

                if (isChecked) {
                    selectedEmployeesMap.set(employeeId, {
                        id: employeeId,
                        name: employeeName,
                        user_id: userId
                    });
                } else {
                    selectedEmployeesMap.delete(employeeId);
                }

                checkbox.checked = isChecked;
            });

            updateSelectedUI();
        }

        window.clearSelections = function() {
            selectedEmployeesMap.clear();

            const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');
            employeeCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });

            const selectAllCheckbox = document.getElementById('select-all');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            }

            updateSelectedUI();
            saveSelectionsToStorage();
        };

        window.printSelectedPayslips = function() {
            const validSelections = Array.from(selectedEmployeesMap.values())
                .filter(item => item && item.id && item.id !== 'undefined' && item.id !== '');

            if (validSelections.length === 0) {
                alert('Please select at least one employee to print payslips.');
                return;
            }

            const employeeIds = validSelections.map(item => item.id).join(',');
            const period = document.getElementById('payroll-period').value;
            const cutoff = document.getElementById('hidden-payroll-cutoff').value;

            window.open(`print_multiple_payslips_contractual.php?employees=${encodeURIComponent(employeeIds)}&period=${encodeURIComponent(period)}&cutoff=${encodeURIComponent(cutoff)}`, '_blank');
        };

        // ============================================
        // PAGINATION FUNCTIONS
        // ============================================
        let currentPage = <?php echo $current_page; ?>;
        let totalPages = <?php echo $total_pages; ?>;
        let recordsPerPage = <?php echo $records_per_page; ?>;
        let searchTerm = '<?php echo addslashes($search_term); ?>';
        let searchTimeout;

        window.changePage = function(page) {
            if (page < 1 || page > totalPages) return;

            saveSelectionsToStorage();

            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            url.searchParams.set('per_page', recordsPerPage);
            if (searchTerm) {
                url.searchParams.set('search', searchTerm);
            }
            window.location.href = url.toString();
        };

        window.changePerPage = function(perPage) {
            const newPerPage = parseInt(perPage);
            if (isNaN(newPerPage) || newPerPage < 1) return;

            saveSelectionsToStorage();

            const url = new URL(window.location.href);
            url.searchParams.set('per_page', newPerPage);
            url.searchParams.set('page', '1');
            if (searchTerm) {
                url.searchParams.set('search', searchTerm);
            }
            window.location.href = url.toString();
        };

        // ============================================
        // SEARCH FUNCTION
        // ============================================
        function initSearch() {
            const searchInput = document.getElementById('search-employees');
            if (searchInput) {
                searchInput.value = searchTerm;
                searchInput.addEventListener('input', function(e) {
                    searchTerm = e.target.value.trim();
                    if (searchTimeout) clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        saveSelectionsToStorage();

                        const url = new URL(window.location.href);
                        url.searchParams.set('page', '1');
                        if (searchTerm) {
                            url.searchParams.set('search', searchTerm);
                        } else {
                            url.searchParams.delete('search');
                        }
                        window.location.href = url.toString();
                    }, 500);
                });
            }
        }

        initSearch();

        // Period change handler
        const periodSelect = document.getElementById('payroll-period');
        if (periodSelect) {
            periodSelect.addEventListener('change', function() {
                saveSelectionsToStorage();

                const period = this.value;
                const url = new URL(window.location.href);
                url.searchParams.set('period', period);
                url.searchParams.set('page', '1');
                window.location.href = url.toString();
            });
        }

        // ============================================
        // AUTO-SAVE FUNCTIONALITY
        // ============================================
        let activeSaves = 0;
        let saveTimeout;

        function initAutoSave() {
            document.querySelectorAll('.auto-save-field').forEach(field => {
                field.removeEventListener('blur', handleAutoSave);
                field.removeEventListener('keypress', handleAutoSaveKeypress);
                field.addEventListener('blur', handleAutoSave);
                field.addEventListener('keypress', handleAutoSaveKeypress);
            });
        }

        function handleAutoSaveKeypress(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleAutoSave.call(this, e);
            }
        }

        function handleAutoSave(e) {
            const field = e.currentTarget;
            if (!field) return;

            if (field.readonly || field.disabled || field.classList.contains('full-month-disabled')) return;

            if (saveTimeout) clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                saveField(field);
            }, 300);
        }

        async function saveField(field) {
            if (!field) {
                showAutoSaveIndicator('error', 'Field not found');
                return;
            }

            const userId = field.dataset.userId;
            const fieldName = field.dataset.field;
            const value = parseFloat(field.value) || 0;

            const period = document.getElementById('payroll-period').value;
            const cutoff = document.getElementById('hidden-payroll-cutoff').value;

            if (cutoff === 'full') {
                showAutoSaveIndicator('error', 'Cannot edit in Full Month');
                field.classList.add('error');
                setTimeout(() => field.classList.remove('error'), 2000);
                return;
            }

            if (!userId || !fieldName) {
                showAutoSaveIndicator('error', 'Missing data');
                return;
            }

            showAutoSaveIndicator('saving');

            try {
                const row = field.closest('.payroll-row');
                if (!row) {
                    showAutoSaveIndicator('error', 'Row not found');
                    return;
                }

                // Calculate row values
                await calculateRow(row);

                // Get all current values from the row after calculation
                const otherCompField = row.querySelector('.other-comp');
                const withholdingTaxField = row.querySelector('.withholding-tax');
                const sssField = row.querySelector('.sss');
                const grossAmountField = row.querySelector('.gross-amount');
                const totalDeductionField = row.querySelector('.total-deduction');
                const netAmountField = row.querySelector('.net-amount');
                const monthlySalaryField = row.querySelector('.monthly-salaries-wages');
                const daysPresentField = row.querySelector('.hidden-days-present');

                const otherComp = otherCompField ? parseFloat(otherCompField.value) || 0 : 0;
                const withholdingTax = withholdingTaxField ? parseFloat(withholdingTaxField.value) || 0 : 0;
                const sss = sssField ? parseFloat(sssField.value) || 0 : 0;
                const grossAmount = grossAmountField ? parseFloat(grossAmountField.value) || 0 : 0;
                const totalDeductions = totalDeductionField ? parseFloat(totalDeductionField.value) || 0 : 0;
                const netAmount = netAmountField ? parseFloat(netAmountField.value) || 0 : 0;
                const monthlySalary = monthlySalaryField ? parseFloat(monthlySalaryField.value) || 0 : 0;
                const daysPresent = daysPresentField ? parseFloat(daysPresentField.value) || 0 : 0;

                const formData = new FormData();
                formData.append('ajax_action', 'save_deductions');
                formData.append('employee_id', userId);
                formData.append('period', period);
                formData.append('cutoff', cutoff);
                formData.append('other_comp', otherComp);
                formData.append('withholding_tax', withholdingTax);
                formData.append('sss', sss);
                formData.append('gross_amount', grossAmount);
                formData.append('total_deductions', totalDeductions);
                formData.append('net_amount', netAmount);
                formData.append('monthly_salary', monthlySalary);
                formData.append('days_present', daysPresent);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const responseText = await response.text();
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    console.error('Failed to parse JSON response:', responseText);
                    throw new Error('Invalid JSON response from server');
                }

                if (result.success) {
                    showAutoSaveIndicator('saved');

                    if (result.gross_amount !== undefined && grossAmountField) {
                        grossAmountField.value = result.gross_amount.toFixed(2);
                    }
                    if (result.total_deductions !== undefined && totalDeductionField) {
                        totalDeductionField.value = result.total_deductions.toFixed(2);
                    }
                    if (result.net_amount !== undefined && netAmountField) {
                        netAmountField.value = result.net_amount.toFixed(2);
                    }

                    row.setAttribute('data-payroll-exists', '1');
                    if (result.id) {
                        row.setAttribute('data-payroll-id', result.id);
                    }

                    await calculateAll();
                } else {
                    showAutoSaveIndicator('error', result.error || 'Save failed');
                    field.classList.add('error');
                    setTimeout(() => field.classList.remove('error'), 2000);
                }
            } catch (error) {
                console.error('Error saving field:', error);
                showAutoSaveIndicator('error', 'Network error: ' + error.message);
                field.classList.add('error');
                setTimeout(() => field.classList.remove('error'), 2000);
            }
        }

        function showAutoSaveIndicator(status, message = '') {
            const indicator = document.getElementById('autoSaveIndicator');
            if (!indicator) return;

            if (status === 'saving') {
                activeSaves++;
                indicator.className = 'auto-save-indicator saving';
                indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Saving...</span>';
                indicator.style.display = 'flex';
            } else if (status === 'saved') {
                activeSaves = Math.max(0, activeSaves - 1);
                if (activeSaves === 0) {
                    indicator.className = 'auto-save-indicator saved';
                    indicator.innerHTML = '<i class="fas fa-check-circle"></i><span>Saved to Database</span>';
                    setTimeout(() => {
                        indicator.style.display = 'none';
                    }, 2000);
                }
            } else if (status === 'error') {
                activeSaves = Math.max(0, activeSaves - 1);
                indicator.className = 'auto-save-indicator error';
                indicator.innerHTML = `<i class="fas fa-exclamation-circle"></i><span>${message || 'Save failed'}</span>`;

                setTimeout(() => {
                    if (activeSaves === 0) {
                        indicator.style.display = 'none';
                    }
                }, 3000);
            }
        }

        async function calculateRow(row) {
            if (!row) return null;

            const otherCompField = row.querySelector('.other-comp');
            const withholdingTaxField = row.querySelector('.withholding-tax');
            const sssField = row.querySelector('.sss');
            const monthlySalaryField = row.querySelector('.monthly-salaries-wages');
            const daysPresentField = row.querySelector('.hidden-days-present');
            const dailyRateField = row.querySelector('.daily-rate');
            const grossAmountField = row.querySelector('.gross-amount');
            const totalDeductionField = row.querySelector('.total-deduction');
            const netAmountField = row.querySelector('.net-amount');

            const otherComp = otherCompField ? parseFloat(otherCompField.value) || 0 : 0;
            const withholdingTax = withholdingTaxField ? parseFloat(withholdingTaxField.value) || 0 : 0;
            const sss = sssField ? parseFloat(sssField.value) || 0 : 0;
            const monthlySalary = monthlySalaryField ? parseFloat(monthlySalaryField.value) || 0 : 0;
            const daysPresent = daysPresentField ? parseFloat(daysPresentField.value) || 0 : 0;

            const dailyRate = monthlySalary / 22;
            if (dailyRateField) dailyRateField.value = dailyRate.toFixed(2);

            const proratedSalary = dailyRate * daysPresent;
            let grossAmount = proratedSalary + otherComp;

            if (grossAmountField) grossAmountField.value = grossAmount.toFixed(2);

            const totalDeduction = withholdingTax + sss;
            if (totalDeductionField) totalDeductionField.value = totalDeduction.toFixed(2);

            let netAmount = grossAmount - totalDeduction;
            if (netAmount < 0) netAmount = 0;

            if (netAmountField) netAmountField.value = netAmount.toFixed(2);

            if (daysPresent <= 0) {
                row.classList.add('no-attendance');
            } else {
                row.classList.remove('no-attendance');
            }

            return {
                grossAmount,
                totalDeduction,
                netAmount,
                otherComp,
                withholdingTax,
                sss
            };
        }

        async function calculateAll() {
            let totalMonthlySalariesWages = 0;
            let totalOtherComp = 0;
            let totalGross = 0;
            let totalWithholdingTax = 0;
            let totalSss = 0;
            let totalDeduction = 0;
            let totalNetAmount = 0;

            const rows = document.querySelectorAll('.payroll-row');

            for (const row of rows) {
                const monthlySalaryField = row.querySelector('.monthly-salaries-wages');
                const otherCompField = row.querySelector('.other-comp');
                const daysPresentField = row.querySelector('.hidden-days-present');

                const monthlySalary = monthlySalaryField ? parseFloat(monthlySalaryField.value) || 0 : 0;
                const otherComp = otherCompField ? parseFloat(otherCompField.value) || 0 : 0;
                const daysPresent = daysPresentField ? parseFloat(daysPresentField.value) || 0 : 0;

                totalMonthlySalariesWages += monthlySalary * (daysPresent / 22);
                totalOtherComp += otherComp;

                const result = await calculateRow(row);

                if (result) {
                    totalGross += result.grossAmount;
                    totalWithholdingTax += result.withholdingTax;
                    totalSss += result.sss;
                    totalDeduction += result.totalDeduction;
                    totalNetAmount += result.netAmount;
                }
            }

            document.getElementById('total-monthly-salaries-wages').textContent = '₱' + totalMonthlySalariesWages.toFixed(2);
            document.getElementById('total-other-comp').textContent = '₱' + totalOtherComp.toFixed(2);
            document.getElementById('total-gross-amount').textContent = '₱' + totalGross.toFixed(2);
            document.getElementById('total-withholding-tax').textContent = '₱' + totalWithholdingTax.toFixed(2);
            document.getElementById('total-sss').textContent = '₱' + totalSss.toFixed(2);
            document.getElementById('total-deduction').textContent = '₱' + totalDeduction.toFixed(2);
            document.getElementById('total-net-amount').textContent = '₱' + totalNetAmount.toFixed(2);
            document.getElementById('total-deductions-display').textContent = '₱' + totalDeduction.toFixed(2);
            document.getElementById('net-amount-display').textContent = '₱' + totalNetAmount.toFixed(2);
        }

        // ============================================
        // EMPLOYEE DETAILS MODAL
        // ============================================
        window.viewEmployeeDetails = async function(employeeId, period, cutoff) {
            showLoading();

            try {
                const formData = new FormData();
                formData.append('ajax_action', 'get_employee_details');
                formData.append('employee_id', employeeId);
                formData.append('period', period);
                formData.append('cutoff', cutoff);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    displayEmployeeDetails(result);
                } else {
                    alert('Error: ' + (result.error || 'Failed to load employee details'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error loading employee details');
            } finally {
                hideLoading();
            }
        };

        function displayEmployeeDetails(data) {
            const employee = data.employee || {};
            const attendance = data.attendance || {};
            const payrollHistory = data.payroll_history || [];
            const calculations = data.calculations || {};
            const cutoff = data.cutoff || {
                type: 'full',
                label: 'Full Month',
                working_days: 22
            };

            const modalBody = document.getElementById('viewEmployeeModalBody');
            if (!modalBody) return;

            const safeNumber = (value, decimals = 2) => {
                const num = parseFloat(value);
                return isNaN(num) ? '0.00' : num.toFixed(decimals);
            };

            const safeCurrency = (value) => '₱' + safeNumber(value);
            const safeText = (value) => value ?? 'N/A';

            const hasAttendance = (attendance.days_present > 0);

            let html = `
        <div class="space-y-4">
            <div class="bg-blue-50 p-4 rounded-lg border-l-4 border-blue-600">
                <h4 class="font-bold text-lg mb-2">Payroll Period: ${cutoff.label}</h4>
                <p class="text-sm">Period: ${cutoff.start} to ${cutoff.end}</p>
                <p class="text-sm">Working Days: ${cutoff.working_days} days</p>
            </div>
            
            <div class="bg-primary-50 p-4 rounded-lg">
                <h4 class="font-bold text-lg mb-2">${safeText(employee.full_name)}</h4>
                <p class="text-sm">Employee ID: ${safeText(employee.employee_id)}</p>
                <p class="text-sm">Position: ${safeText(employee.position)}</p>
                <p class="text-sm">Department: ${safeText(employee.department)}</p>
            </div>
            
            <div class="border p-4 rounded-lg">
                <h4 class="font-semibold mb-2">Contact Information</h4>
                <p class="text-sm">Email: ${safeText(employee.email_address)}</p>
                <p class="text-sm">Mobile: ${safeText(employee.mobile_number)}</p>
            </div>
            
            <div class="border p-4 rounded-lg">
                <h4 class="font-semibold mb-2">Attendance Summary (${cutoff.label})</h4>
                <div class="attendance-summary">
                    <div class="attendance-card">
                        <div class="value">${safeNumber(attendance.days_present, 1)}</div>
                        <div class="label">Days Present</div>
                    </div>
                    <div class="attendance-card">
                        <div class="value">${cutoff.working_days}</div>
                        <div class="label">Working Days</div>
                    </div>
                    <div class="attendance-card">
                        <div class="value">${((attendance.days_present / cutoff.working_days) * 100).toFixed(1)}%</div>
                        <div class="label">Attendance Rate</div>
                    </div>
                </div>
            </div>
            
            <div class="border p-4 rounded-lg">
                <h4 class="font-semibold mb-2">Compensation & Deductions</h4>
                <p class="text-sm">Monthly Salary (Base): ${safeCurrency(calculations.monthly_salary)}</p>
                <p class="text-sm">Daily Rate: ${safeCurrency(calculations.daily_rate)}</p>
                <p class="text-sm">Other Compensation: ${safeCurrency(calculations.other_comp)}</p>
                <p class="text-sm">Withholding Tax: ${safeCurrency(calculations.withholding_tax)}</p>
                <p class="text-sm">SSS Contribution: ${safeCurrency(calculations.sss)}</p>
                <p class="text-sm">Total Deductions: ${safeCurrency(calculations.total_deductions)}</p>
                <p class="text-sm font-bold ${hasAttendance ? 'text-green-600' : 'text-gray-400'} mt-2">Gross Amount Earned: ${safeCurrency(calculations.gross_amount)}</p>
                <p class="text-sm font-bold text-blue-600">Net Amount: ${safeCurrency(calculations.net_amount)}</p>
            </div>
    `;

            if (payrollHistory && payrollHistory.length > 0) {
                html += `
            <div class="border p-4 rounded-lg">
                <h4 class="font-semibold mb-2">Recent Payroll History</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="p-2">Period</th>
                                <th class="p-2">Cutoff</th>
                                <th class="p-2">Days</th>
                                <th class="p-2">Gross</th>
                                <th class="p-2">Deductions</th>
                                <th class="p-2">Net</th>
                                <th class="p-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

                payrollHistory.forEach(payroll => {
                    html += `
                <tr class="border-b">
                    <td class="p-2">${safeText(payroll.payroll_period)}</td>
                    <td class="p-2">${payroll.payroll_cutoff}</td>
                    <td class="p-2">${payroll.days_present ? safeNumber(payroll.days_present, 1) : '-'}</td>
                    <td class="p-2">${safeCurrency(payroll.gross_amount)}</td>
                    <td class="p-2">${safeCurrency(payroll.total_deductions)}</td>
                    <td class="p-2">${safeCurrency(payroll.net_amount)}</td>
                    <td class="p-2"><span class="status-badge status-${payroll.status || 'pending'}">${safeText(payroll.status)}</span></td>
                </tr>
            `;
                });

                html += `</tbody></table></div></div>`;
            }

            html += `</div>`;

            modalBody.innerHTML = html;
            openModal('viewEmployeeModal');
        }

        // ============================================
        // MODAL FUNCTIONS
        // ============================================
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        window.closeModal = function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        };

        window.showApproveModal = function() {
            openModal('approveModal');
        };

        // ============================================
        // LOADING FUNCTIONS
        // ============================================
        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.add('active');
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.remove('active');
        }
    </script>
</body>

</html>