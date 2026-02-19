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
$selected_cutoff = isset($_GET['cutoff']) ? $_GET['cutoff'] : 'full'; // full, first_half, second_half

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
        // Monday to Friday are working days (1-5)
        if ($day_of_week <= 5) {
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

// Function to ensure payroll_history table has all required columns
function ensurePayrollHistoryTable($pdo)
{
    // First, check if table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_history'");
    if ($table_check->rowCount() == 0) {
        // Create table with all columns
        $pdo->exec("
            CREATE TABLE payroll_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                employee_type VARCHAR(50) DEFAULT 'contractual',
                payroll_period VARCHAR(7) NOT NULL,
                payroll_cutoff VARCHAR(20) DEFAULT 'full',
                monthly_salaries_wages DECIMAL(10,2) DEFAULT 0,
                other_comp DECIMAL(10,2) DEFAULT 0,
                gross_amount DECIMAL(10,2) DEFAULT 0,
                withholding_tax DECIMAL(10,2) DEFAULT 0,
                sss DECIMAL(10,2) DEFAULT 0,
                total_deductions DECIMAL(10,2) DEFAULT 0,
                net_amount DECIMAL(10,2) DEFAULT 0,
                days_present DECIMAL(5,2) DEFAULT 0,
                working_days INT DEFAULT 22,
                status VARCHAR(20) DEFAULT 'draft',
                approved_by VARCHAR(100),
                approved_date DATETIME,
                processed_date DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_employee_period_cutoff (employee_id, payroll_period, payroll_cutoff)
            )
        ");
        return true;
    } else {
        // Get existing columns
        $columns = $pdo->query("SHOW COLUMNS FROM payroll_history")->fetchAll(PDO::FETCH_COLUMN);

        // Required columns that should exist
        $required_columns = [
            'withholding_tax' => "ALTER TABLE payroll_history ADD COLUMN withholding_tax DECIMAL(10,2) DEFAULT 0",
            'sss' => "ALTER TABLE payroll_history ADD COLUMN sss DECIMAL(10,2) DEFAULT 0",
            'other_comp' => "ALTER TABLE payroll_history ADD COLUMN other_comp DECIMAL(10,2) DEFAULT 0",
            'monthly_salaries_wages' => "ALTER TABLE payroll_history ADD COLUMN monthly_salaries_wages DECIMAL(10,2) DEFAULT 0",
            'gross_amount' => "ALTER TABLE payroll_history ADD COLUMN gross_amount DECIMAL(10,2) DEFAULT 0",
            'total_deductions' => "ALTER TABLE payroll_history ADD COLUMN total_deductions DECIMAL(10,2) DEFAULT 0",
            'net_amount' => "ALTER TABLE payroll_history ADD COLUMN net_amount DECIMAL(10,2) DEFAULT 0",
            'days_present' => "ALTER TABLE payroll_history ADD COLUMN days_present DECIMAL(5,2) DEFAULT 0",
            'working_days' => "ALTER TABLE payroll_history ADD COLUMN working_days INT DEFAULT 22"
        ];

        // Add missing columns
        foreach ($required_columns as $column => $alter_sql) {
            if (!in_array($column, $columns)) {
                try {
                    $pdo->exec($alter_sql);
                    error_log("Added missing column: $column to payroll_history table");
                } catch (Exception $e) {
                    error_log("Error adding column $column: " . $e->getMessage());
                }
            }
        }

        // Check for unique constraint
        try {
            $constraints = $pdo->query("SHOW INDEX FROM payroll_history WHERE Key_name = 'unique_employee_period_cutoff'");
            if ($constraints->rowCount() == 0) {
                $pdo->exec("CREATE UNIQUE INDEX unique_employee_period_cutoff ON payroll_history(employee_id, payroll_period, payroll_cutoff)");
            }
        } catch (Exception $e) {
            error_log("Error creating unique index: " . $e->getMessage());
        }

        return true;
    }
}

// Ensure payroll_deductions table exists
function ensurePayrollDeductionsTable($pdo)
{
    $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_deductions'");
    if ($table_check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE payroll_deductions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                payroll_id INT NOT NULL,
                deduction_type VARCHAR(50) NOT NULL,
                deduction_amount DECIMAL(10,2) DEFAULT 0,
                deduction_description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (payroll_id) REFERENCES payroll_history(id) ON DELETE CASCADE
            )
        ");
    }
}

// Call these functions at the start
ensurePayrollHistoryTable($pdo);
ensurePayrollDeductionsTable($pdo);

// Get payroll_history columns for dynamic queries
$payroll_columns = $pdo->query("SHOW COLUMNS FROM payroll_history")->fetchAll(PDO::FETCH_COLUMN);

// Handle AJAX requests for real-time calculations and saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    try {
        if ($_POST['ajax_action'] === 'calculate_row') {
            $employee_id = $_POST['employee_id'];
            $monthly_salaries_wages = floatval($_POST['monthly_salaries_wages']);
            $other_comp = floatval($_POST['other_comp']);
            $withholding_tax = floatval($_POST['withholding_tax']);
            $sss = floatval($_POST['sss']);
            $days_present = floatval($_POST['days_present'] ?? 0);
            $working_days = floatval($_POST['working_days'] ?? 22);

            // Calculate daily rate from monthly wages (based on 22 working days per month)
            $daily_rate = $monthly_salaries_wages / 22;

            // Calculate gross amount based on days present
            $prorated_salary = $daily_rate * $days_present;
            $gross_amount = $prorated_salary + $other_comp;

            // Calculate total deductions (always calculate even if gross is 0)
            $total_deductions = $withholding_tax + $sss;

            // Calculate net amount
            $net_amount = $gross_amount - $total_deductions;
            if ($net_amount < 0) $net_amount = 0;

            echo json_encode([
                'success' => true,
                'gross_amount' => number_format($gross_amount, 2),
                'prorated_salary' => number_format($prorated_salary, 2),
                'total_deductions' => number_format($total_deductions, 2),
                'net_amount' => number_format($net_amount, 2),
                'daily_rate' => number_format($daily_rate, 2),
                'days_present' => $days_present,
                'has_attendance' => ($days_present > 0)
            ]);
            exit();
        }

        // Save Other Compensation
        if ($_POST['ajax_action'] === 'save_other_comp') {
            $employee_id = intval($_POST['employee_id']);
            $other_comp = floatval($_POST['other_comp']);
            $period = $_POST['period'];
            $cutoff = $_POST['cutoff'];

            // Check if record exists
            $check_stmt = $pdo->prepare("
                SELECT id FROM payroll_history 
                WHERE employee_id = ? AND employee_type = 'contractual' 
                    AND payroll_period = ? AND payroll_cutoff = ?
            ");
            $check_stmt->execute([$employee_id, $period, $cutoff]);
            $existing_id = $check_stmt->fetchColumn();

            if ($existing_id) {
                // Update existing record - only update other_comp if column exists
                if (in_array('other_comp', $payroll_columns)) {
                    $update_stmt = $pdo->prepare("
                        UPDATE payroll_history 
                        SET other_comp = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$other_comp, $existing_id]);
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Other compensation updated successfully',
                    'id' => $existing_id
                ]);
            } else {
                // Insert new record - only include columns that exist
                $insert_fields = ['employee_id', 'employee_type', 'payroll_period', 'payroll_cutoff', 'status'];
                $insert_values = [$employee_id, 'contractual', $period, $cutoff, 'draft'];

                if (in_array('other_comp', $payroll_columns)) {
                    $insert_fields[] = 'other_comp';
                    $insert_values[] = $other_comp;
                }

                $placeholders = array_fill(0, count($insert_fields), '?');

                $insert_sql = "INSERT INTO payroll_history (" . implode(", ", $insert_fields) . ") 
                              VALUES (" . implode(", ", $placeholders) . ")";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute($insert_values);

                $new_id = $pdo->lastInsertId();

                echo json_encode([
                    'success' => true,
                    'message' => 'Other compensation saved successfully',
                    'id' => $new_id
                ]);
            }
            exit();
        }

        // Save Deductions
        if ($_POST['ajax_action'] === 'save_deductions') {
            $employee_id = intval($_POST['employee_id']);
            $withholding_tax = floatval($_POST['withholding_tax']);
            $sss = floatval($_POST['sss']);
            $period = $_POST['period'];
            $cutoff = $_POST['cutoff'];

            // Check if record exists
            $check_stmt = $pdo->prepare("
                SELECT id FROM payroll_history 
                WHERE employee_id = ? AND employee_type = 'contractual' 
                    AND payroll_period = ? AND payroll_cutoff = ?
            ");
            $check_stmt->execute([$employee_id, $period, $cutoff]);
            $existing_id = $check_stmt->fetchColumn();

            if ($existing_id) {
                // Build dynamic update query based on existing columns
                $update_fields = [];
                $update_values = [];

                if (in_array('withholding_tax', $payroll_columns)) {
                    $update_fields[] = "withholding_tax = ?";
                    $update_values[] = $withholding_tax;
                }
                if (in_array('sss', $payroll_columns)) {
                    $update_fields[] = "sss = ?";
                    $update_values[] = $sss;
                }

                $update_fields[] = "updated_at = NOW()";
                $update_values[] = $existing_id;

                if (!empty($update_fields)) {
                    $update_sql = "UPDATE payroll_history SET " . implode(", ", $update_fields) . " WHERE id = ?";
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->execute($update_values);
                }

                // Update payroll_deductions table
                $delete_deductions = $pdo->prepare("DELETE FROM payroll_deductions WHERE payroll_id = ?");
                $delete_deductions->execute([$existing_id]);

                $deduction_types = [
                    ['Withholding Tax', $withholding_tax],
                    ['SSS Contribution', $sss]
                ];

                foreach ($deduction_types as $deduction) {
                    if ($deduction[1] > 0) {
                        $deduction_stmt = $pdo->prepare("
                            INSERT INTO payroll_deductions 
                            (payroll_id, deduction_type, deduction_amount, deduction_description)
                            VALUES (?, ?, ?, ?)
                        ");
                        $deduction_stmt->execute([
                            $existing_id,
                            $deduction[0],
                            $deduction[1],
                            $deduction[0] . ' deduction for ' . $period . ' (' . $cutoff . ')'
                        ]);
                    }
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Deductions updated successfully',
                    'id' => $existing_id
                ]);
            } else {
                // Insert new record - only include columns that exist
                $insert_fields = ['employee_id', 'employee_type', 'payroll_period', 'payroll_cutoff', 'status'];
                $insert_values = [$employee_id, 'contractual', $period, $cutoff, 'draft'];

                if (in_array('withholding_tax', $payroll_columns)) {
                    $insert_fields[] = 'withholding_tax';
                    $insert_values[] = $withholding_tax;
                }
                if (in_array('sss', $payroll_columns)) {
                    $insert_fields[] = 'sss';
                    $insert_values[] = $sss;
                }

                $placeholders = array_fill(0, count($insert_fields), '?');

                $insert_sql = "INSERT INTO payroll_history (" . implode(", ", $insert_fields) . ") 
                              VALUES (" . implode(", ", $placeholders) . ")";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute($insert_values);

                $new_id = $pdo->lastInsertId();

                // Insert into payroll_deductions
                $deduction_types = [
                    ['Withholding Tax', $withholding_tax],
                    ['SSS Contribution', $sss]
                ];

                foreach ($deduction_types as $deduction) {
                    if ($deduction[1] > 0) {
                        $deduction_stmt = $pdo->prepare("
                            INSERT INTO payroll_deductions 
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
                    'message' => 'Deductions saved successfully',
                    'id' => $new_id
                ]);
            }
            exit();
        }

        // Get employee details with cutoff filtering
        if ($_POST['ajax_action'] === 'get_employee_details') {
            $employee_id = $_POST['employee_id'];
            $period = $_POST['period'] ?? date('Y-m');
            $cutoff = $_POST['cutoff'] ?? 'full';

            // First, check what columns exist in the table
            $columns_query = $pdo->query("SHOW COLUMNS FROM contractofservice");
            $existing_columns = $columns_query->fetchAll(PDO::FETCH_COLUMN);

            // Build SELECT query based on existing columns
            $select_fields = "id as user_id, employee_id, CONCAT(first_name, ' ', last_name) as full_name, first_name, last_name, designation as position, office as department, period_from, period_to, status, email_address, mobile_number";

            if (in_array('contribution', $existing_columns)) {
                $select_fields .= ", contribution";
            }

            // Get wages/rate column
            if (in_array('wages', $existing_columns)) {
                $select_fields .= ", wages as monthly_salary";
            } elseif (in_array('monthly_salary', $existing_columns)) {
                $select_fields .= ", monthly_salary";
            } elseif (in_array('rate_per_day', $existing_columns)) {
                $select_fields .= ", rate_per_day * 22 as monthly_salary";
            } else {
                $select_fields .= ", 0 as monthly_salary";
            }

            $stmt = $pdo->prepare("
                SELECT $select_fields
                FROM contractofservice 
                WHERE id = ? OR employee_id = ?
            ");
            $stmt->execute([$employee_id, $employee_id]);
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
                // Check if attendance table exists
                $table_check = $pdo->query("SHOW TABLES LIKE 'attendance'");
                if ($table_check->rowCount() > 0) {
                    // Check columns in attendance table
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
                    } else {
                        // Fallback query without CASE
                        $attendance_stmt = $pdo->prepare("
                            SELECT 
                                COUNT(*) as total_records,
                                SUM(total_hours) as total_hours
                            FROM attendance 
                            WHERE employee_id = ? 
                                AND date BETWEEN ? AND ?
                        ");
                        $attendance_stmt->execute([$employee['employee_id'], $date_range['start'], $date_range['end']]);
                        $attendance_data = $attendance_stmt->fetch();
                        $attendance_data['days_present'] = $attendance_data['total_hours'] ? floor($attendance_data['total_hours'] / 8) : 0;
                    }
                }
            } catch (Exception $e) {
                error_log("Attendance query error: " . $e->getMessage());
                $attendance_data = ['total_records' => 0, 'days_present' => 0, 'total_hours' => 0];
            }

            // Get data from payroll_history if exists
            $other_comp = 0;
            $withholding_tax = 0;
            $sss = 0;

            try {
                $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_history'");
                if ($table_check->rowCount() > 0) {
                    $payroll_columns_local = $pdo->query("SHOW COLUMNS FROM payroll_history")->fetchAll(PDO::FETCH_COLUMN);

                    $select_payroll = [];
                    if (in_array('other_comp', $payroll_columns_local)) {
                        $select_payroll[] = 'other_comp';
                    }
                    if (in_array('withholding_tax', $payroll_columns_local)) {
                        $select_payroll[] = 'withholding_tax';
                    }
                    if (in_array('sss', $payroll_columns_local)) {
                        $select_payroll[] = 'sss';
                    }

                    if (!empty($select_payroll)) {
                        $select_sql = implode(', ', $select_payroll);
                        $payroll_stmt = $pdo->prepare("
                            SELECT $select_sql FROM payroll_history 
                            WHERE employee_id = ? AND employee_type = 'contractual' 
                                AND payroll_period = ? AND payroll_cutoff = ?
                        ");
                        $payroll_stmt->execute([$employee['user_id'], $period, $cutoff]);
                        $payroll_data = $payroll_stmt->fetch();

                        if ($payroll_data) {
                            $other_comp = floatval($payroll_data['other_comp'] ?? 0);
                            $withholding_tax = floatval($payroll_data['withholding_tax'] ?? 0);
                            $sss = floatval($payroll_data['sss'] ?? 0);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Payroll history query error: " . $e->getMessage());
            }

            // Get payroll history
            $payroll_history = [];
            try {
                $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_history'");
                if ($table_check->rowCount() > 0) {
                    $payroll_stmt = $pdo->prepare("
                        SELECT * FROM payroll_history 
                        WHERE employee_id = ? AND employee_type = 'contractual'
                        ORDER BY payroll_period DESC, payroll_cutoff DESC
                        LIMIT 6
                    ");
                    $payroll_stmt->execute([$employee['user_id']]);
                    $payroll_history = $payroll_stmt->fetchAll();
                }
            } catch (Exception $e) {
                error_log("Payroll history fetch error: " . $e->getMessage());
                $payroll_history = [];
            }

            // Calculate current month values based on cutoff
            $days_present = floatval($attendance_data['days_present'] ?? 0);
            $monthly_salary = floatval($employee['monthly_salary'] ?? 0);

            // Calculate daily rate
            $daily_rate = $monthly_salary / 22; // Base on standard 22 working days per month

            // Calculate prorated salary based on days present
            $prorated_salary = $daily_rate * $days_present;
            $gross_amount = $prorated_salary + $other_comp;

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
                    'days_present' => floatval($days_present),
                    'total_hours' => floatval($attendance_data['total_hours'] ?? 0)
                ],
                'payroll_history' => $payroll_history,
                'calculations' => [
                    'days_present' => floatval($days_present),
                    'monthly_salary' => floatval($monthly_salary),
                    'daily_rate' => floatval($daily_rate),
                    'prorated_salary' => floatval($prorated_salary),
                    'other_comp' => floatval($other_comp),
                    'withholding_tax' => floatval($withholding_tax),
                    'sss' => floatval($sss),
                    'gross_amount' => floatval($gross_amount),
                    'total_hours' => floatval($attendance_data['total_hours'] ?? 0)
                ]
            ]);
            exit();
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

// Handle form submission for saving payroll - FIXED VERSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payroll'])) {
    try {
        $pdo->beginTransaction();

        $payroll_period = $_POST['payroll_period'] ?? $current_payroll_period;
        $payroll_cutoff = $_POST['payroll_cutoff'] ?? 'full';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Get cutoff working days
        $working_days = $cutoff_ranges[$payroll_cutoff]['working_days'] ?? 22;

        // Refresh payroll_columns
        $payroll_columns = $pdo->query("SHOW COLUMNS FROM payroll_history")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($_POST['employee_id'] as $index => $employee_id) {
            $monthly_salaries_wages = floatval($_POST['monthly_salaries_wages'][$index] ?? 0);
            $other_comp = floatval($_POST['other_comp'][$index] ?? 0);
            $gross_amount = floatval($_POST['gross_amount'][$index] ?? 0);
            $withholding_tax = floatval($_POST['withholding_tax'][$index] ?? 0);
            $sss = floatval($_POST['sss'][$index] ?? 0);
            $total_deductions = floatval($_POST['total_deduction'][$index] ?? 0);
            $net_amount = floatval($_POST['net_amount'][$index] ?? 0);
            $days_present = floatval($_POST['days_present'][$index] ?? 0);

            // Check if payroll record exists
            $check_stmt = $pdo->prepare("
                SELECT id FROM payroll_history 
                WHERE employee_id = ? AND employee_type = 'contractual' 
                    AND payroll_period = ? AND payroll_cutoff = ?
            ");
            $check_stmt->execute([$employee_id, $payroll_period, $payroll_cutoff]);
            $existing_id = $check_stmt->fetchColumn();

            if ($existing_id) {
                // Build dynamic update query
                $update_fields = [];
                $update_values = [];

                if (in_array('monthly_salaries_wages', $payroll_columns)) {
                    $update_fields[] = "monthly_salaries_wages = ?";
                    $update_values[] = $monthly_salaries_wages;
                }
                if (in_array('other_comp', $payroll_columns)) {
                    $update_fields[] = "other_comp = ?";
                    $update_values[] = $other_comp;
                }
                if (in_array('gross_amount', $payroll_columns)) {
                    $update_fields[] = "gross_amount = ?";
                    $update_values[] = $gross_amount;
                }
                if (in_array('withholding_tax', $payroll_columns)) {
                    $update_fields[] = "withholding_tax = ?";
                    $update_values[] = $withholding_tax;
                }
                if (in_array('sss', $payroll_columns)) {
                    $update_fields[] = "sss = ?";
                    $update_values[] = $sss;
                }
                if (in_array('total_deductions', $payroll_columns)) {
                    $update_fields[] = "total_deductions = ?";
                    $update_values[] = $total_deductions;
                }
                if (in_array('net_amount', $payroll_columns)) {
                    $update_fields[] = "net_amount = ?";
                    $update_values[] = $net_amount;
                }
                if (in_array('days_present', $payroll_columns)) {
                    $update_fields[] = "days_present = ?";
                    $update_values[] = $days_present;
                }
                if (in_array('working_days', $payroll_columns)) {
                    $update_fields[] = "working_days = ?";
                    $update_values[] = $working_days;
                }
                if (in_array('status', $payroll_columns)) {
                    $update_fields[] = "status = ?";
                    $update_values[] = 'pending';
                }
                if (in_array('updated_at', $payroll_columns)) {
                    $update_fields[] = "updated_at = NOW()";
                }

                if (!empty($update_fields)) {
                    $update_values[] = $existing_id;
                    $update_sql = "UPDATE payroll_history SET " . implode(", ", $update_fields) . " WHERE id = ?";
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->execute($update_values);
                }

                // Update payroll_deductions
                $delete_deductions = $pdo->prepare("DELETE FROM payroll_deductions WHERE payroll_id = ?");
                $delete_deductions->execute([$existing_id]);

                $deduction_types = [
                    ['Withholding Tax', $withholding_tax],
                    ['SSS Contribution', $sss]
                ];

                foreach ($deduction_types as $deduction) {
                    if ($deduction[1] > 0) {
                        $deduction_stmt = $pdo->prepare("
                            INSERT INTO payroll_deductions 
                            (payroll_id, deduction_type, deduction_amount, deduction_description)
                            VALUES (?, ?, ?, ?)
                        ");
                        $deduction_stmt->execute([
                            $existing_id,
                            $deduction[0],
                            $deduction[1],
                            $deduction[0] . ' deduction for ' . $payroll_period . ' (' . $payroll_cutoff . ')'
                        ]);
                    }
                }
            } else {
                // Build dynamic insert query
                $insert_fields = ['employee_id', 'employee_type', 'payroll_period', 'payroll_cutoff'];
                $insert_values = [$employee_id, 'contractual', $payroll_period, $payroll_cutoff];

                if (in_array('monthly_salaries_wages', $payroll_columns)) {
                    $insert_fields[] = 'monthly_salaries_wages';
                    $insert_values[] = $monthly_salaries_wages;
                }
                if (in_array('other_comp', $payroll_columns)) {
                    $insert_fields[] = 'other_comp';
                    $insert_values[] = $other_comp;
                }
                if (in_array('gross_amount', $payroll_columns)) {
                    $insert_fields[] = 'gross_amount';
                    $insert_values[] = $gross_amount;
                }
                if (in_array('withholding_tax', $payroll_columns)) {
                    $insert_fields[] = 'withholding_tax';
                    $insert_values[] = $withholding_tax;
                }
                if (in_array('sss', $payroll_columns)) {
                    $insert_fields[] = 'sss';
                    $insert_values[] = $sss;
                }
                if (in_array('total_deductions', $payroll_columns)) {
                    $insert_fields[] = 'total_deductions';
                    $insert_values[] = $total_deductions;
                }
                if (in_array('net_amount', $payroll_columns)) {
                    $insert_fields[] = 'net_amount';
                    $insert_values[] = $net_amount;
                }
                if (in_array('days_present', $payroll_columns)) {
                    $insert_fields[] = 'days_present';
                    $insert_values[] = $days_present;
                }
                if (in_array('working_days', $payroll_columns)) {
                    $insert_fields[] = 'working_days';
                    $insert_values[] = $working_days;
                }
                if (in_array('status', $payroll_columns)) {
                    $insert_fields[] = 'status';
                    $insert_values[] = 'pending';
                }
                if (in_array('processed_date', $payroll_columns)) {
                    $insert_fields[] = 'processed_date';
                    $insert_values[] = date('Y-m-d H:i:s');
                }

                $placeholders = array_fill(0, count($insert_fields), '?');

                $insert_sql = "INSERT INTO payroll_history (" . implode(", ", $insert_fields) . ") 
                              VALUES (" . implode(", ", $placeholders) . ")";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute($insert_values);

                $new_id = $pdo->lastInsertId();

                // Insert into payroll_deductions
                $deduction_types = [
                    ['Withholding Tax', $withholding_tax],
                    ['SSS Contribution', $sss]
                ];

                foreach ($deduction_types as $deduction) {
                    if ($deduction[1] > 0) {
                        $deduction_stmt = $pdo->prepare("
                            INSERT INTO payroll_deductions 
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
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Payroll saved successfully for " . $cutoff_ranges[$payroll_cutoff]['label'] . "!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?period=" . $payroll_period . "&cutoff=" . $payroll_cutoff);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error saving payroll: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF'] . "?period=" . ($_POST['payroll_period'] ?? $current_payroll_period) . "&cutoff=" . ($_POST['payroll_cutoff'] ?? 'full'));
        exit();
    }
}

// Handle payroll approval
if (isset($_GET['approve_payroll']) && isset($_GET['period']) && isset($_GET['cutoff'])) {
    try {
        $pdo->beginTransaction();

        $period = $_GET['period'];
        $cutoff = $_GET['cutoff'];
        $approval_notes = $_POST['approval_notes'] ?? 'Approved via system';

        $payroll_columns = $pdo->query("SHOW COLUMNS FROM payroll_history")->fetchAll(PDO::FETCH_COLUMN);

        $update_fields = [];
        $update_values = [];

        if (in_array('status', $payroll_columns)) {
            $update_fields[] = "status = ?";
            $update_values[] = 'approved';
        }
        if (in_array('approved_by', $payroll_columns)) {
            $update_fields[] = "approved_by = ?";
            $update_values[] = $current_user_name;
        }
        if (in_array('approved_date', $payroll_columns)) {
            $update_fields[] = "approved_date = NOW()";
        }

        $update_values[] = $period;
        $update_values[] = $cutoff;

        if (!empty($update_fields)) {
            $update_sql = "UPDATE payroll_history SET " . implode(", ", $update_fields) . " 
                          WHERE payroll_period = ? AND payroll_cutoff = ? AND employee_type = 'contractual'";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute($update_values);
        }

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
                )
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
        header("Location: " . $_SERVER['PHP_SELF'] . "?period=" . $period . "&cutoff=" . $cutoff);
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
            SELECT * FROM payroll_history WHERE id = ?
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
                        FOREIGN KEY (payroll_id) REFERENCES payroll_history(id) ON DELETE CASCADE
                    )
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

            $payroll_columns = $pdo->query("SHOW COLUMNS FROM payroll_history")->fetchAll(PDO::FETCH_COLUMN);

            if (in_array('status', $payroll_columns)) {
                $update_stmt = $pdo->prepare("
                    UPDATE payroll_history 
                    SET status = 'paid', updated_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([$payroll_id]);
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Payroll marked as paid successfully!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?period=" . $period . "&cutoff=" . $cutoff);
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

// Default SSS rate
$sss_rate = 4.50;

// Fetch contractual employees from contractofservice table
$contractual_employees = [];

try {
    // Check if contractofservice table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'contractofservice'");
    if ($table_check->rowCount() == 0) {
        throw new Exception("contractofservice table does not exist");
    }

    // First, check what columns exist in the table
    $columns_query = $pdo->query("SHOW COLUMNS FROM contractofservice");
    $existing_columns = $columns_query->fetchAll(PDO::FETCH_COLUMN);

    // Build SELECT query based on existing columns
    $select_fields = "id as user_id, employee_id, CONCAT(first_name, ' ', last_name) as full_name, first_name, last_name, designation as position, office as department, period_from, period_to, status, email_address, mobile_number";

    if (in_array('contribution', $existing_columns)) {
        $select_fields .= ", contribution";
    }

    // Get wages/rate column
    if (in_array('wages', $existing_columns)) {
        $select_fields .= ", wages as monthly_salary";
    } elseif (in_array('monthly_salary', $existing_columns)) {
        $select_fields .= ", monthly_salary";
    } elseif (in_array('rate_per_day', $existing_columns)) {
        $select_fields .= ", rate_per_day * 22 as monthly_salary";
    } else {
        $select_fields .= ", 0 as monthly_salary";
    }

    $stmt = $pdo->prepare("
        SELECT $select_fields
        FROM contractofservice 
        WHERE status = 'active'
        ORDER BY last_name, first_name
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll();

    // Get attendance and other compensation for each employee based on cutoff
    foreach ($employees as &$employee) {
        // Get attendance records for the selected cutoff period
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

        // Calculate days present
        $employee['days_present'] = $attendance_days;
        $employee['total_hours'] = $total_hours;

        // Calculate daily rate (based on 22 working days per month)
        $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
        $daily_rate = $monthly_salary / 22;

        // Calculate prorated salary based on days present
        $prorated_salary = $daily_rate * $attendance_days;

        // Get data from payroll_history if exists - FOR FULL MONTH, WE NEED TO COMBINE DATA FROM BOTH HALVES
        $other_comp = 0;
        $withholding_tax = 0;
        $sss = 0;

        try {
            $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_history'");
            if ($table_check->rowCount() > 0) {
                $payroll_columns_local = $pdo->query("SHOW COLUMNS FROM payroll_history")->fetchAll(PDO::FETCH_COLUMN);

                $select_payroll = [];
                if (in_array('other_comp', $payroll_columns_local)) {
                    $select_payroll[] = 'SUM(other_comp) as other_comp';
                } else {
                    $select_payroll[] = '0 as other_comp';
                }
                if (in_array('withholding_tax', $payroll_columns_local)) {
                    $select_payroll[] = 'SUM(withholding_tax) as withholding_tax';
                } else {
                    $select_payroll[] = '0 as withholding_tax';
                }
                if (in_array('sss', $payroll_columns_local)) {
                    $select_payroll[] = 'SUM(sss) as sss';
                } else {
                    $select_payroll[] = '0 as sss';
                }
                if (in_array('gross_amount', $payroll_columns_local)) {
                    $select_payroll[] = 'SUM(gross_amount) as gross_amount';
                } else {
                    $select_payroll[] = '0 as gross_amount';
                }
                if (in_array('days_present', $payroll_columns_local)) {
                    $select_payroll[] = 'SUM(days_present) as total_days_present';
                } else {
                    $select_payroll[] = '0 as total_days_present';
                }

                $select_sql = implode(', ', $select_payroll);

                // For full month, we need to get data from both halves
                if ($selected_cutoff == 'full') {
                    // Get data from both first_half and second_half
                    $payroll_stmt = $pdo->prepare("
                        SELECT $select_sql
                        FROM payroll_history 
                        WHERE employee_id = ? AND employee_type = 'contractual' 
                            AND payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half')
                    ");
                    $payroll_stmt->execute([$employee['user_id'], $selected_period]);
                    $payroll_data = $payroll_stmt->fetch();

                    if ($payroll_data) {
                        $other_comp = floatval($payroll_data['other_comp'] ?? 0);
                        $withholding_tax = floatval($payroll_data['withholding_tax'] ?? 0);
                        $sss = floatval($payroll_data['sss'] ?? 0);
                        $total_gross = floatval($payroll_data['gross_amount'] ?? 0);
                        $total_days = floatval($payroll_data['total_days_present'] ?? 0);

                        // For full month, we want to show the combined gross amount
                        if ($total_gross > 0) {
                            $employee['gross_amount'] = $total_gross;
                        }
                        if ($total_days > 0) {
                            $employee['days_present'] = $total_days;
                        }
                    }
                } else {
                    // For specific half, get just that half's data
                    $select_sql = str_replace('SUM(', '', $select_sql);
                    $select_sql = str_replace(') as', ' as', $select_sql);

                    $payroll_stmt = $pdo->prepare("
                        SELECT $select_sql
                        FROM payroll_history 
                        WHERE employee_id = ? AND employee_type = 'contractual' 
                            AND payroll_period = ? AND payroll_cutoff = ?
                    ");
                    $payroll_stmt->execute([$employee['user_id'], $selected_period, $selected_cutoff]);
                    $payroll_data = $payroll_stmt->fetch();

                    if ($payroll_data) {
                        $other_comp = floatval($payroll_data['other_comp'] ?? 0);
                        $withholding_tax = floatval($payroll_data['withholding_tax'] ?? 0);
                        $sss = floatval($payroll_data['sss'] ?? 0);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Payroll data fetch error: " . $e->getMessage());
        }

        $employee['other_comp'] = $other_comp;
        $employee['withholding_tax'] = $withholding_tax;
        $employee['sss'] = $sss;
        $employee['monthly_salary'] = $monthly_salary;
        $employee['daily_rate'] = $daily_rate;
        $employee['prorated_salary'] = $prorated_salary;

        // If gross amount wasn't set by the combined data, calculate it
        if (!isset($employee['gross_amount']) || $employee['gross_amount'] == 0) {
            $employee['gross_amount'] = $prorated_salary + $other_comp;
        }

        // Check if payroll already exists for this period and cutoff
        $payroll_data = null;
        try {
            $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_history'");
            if ($table_check->rowCount() > 0) {
                if ($selected_cutoff == 'full') {
                    // For full month, we need to check if there are records for both halves
                    $payroll_stmt = $pdo->prepare("
                        SELECT COUNT(*) as record_count, 
                               SUM(withholding_tax) as total_withholding_tax,
                               SUM(sss) as total_sss,
                               SUM(other_comp) as total_other_comp,
                               SUM(gross_amount) as total_gross_amount
                        FROM payroll_history 
                        WHERE employee_id = ? AND employee_type = 'contractual' 
                            AND payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half')
                    ");
                    $payroll_stmt->execute([$employee['user_id'], $selected_period]);
                    $payroll_summary_data = $payroll_stmt->fetch();

                    if ($payroll_summary_data && $payroll_summary_data['record_count'] > 0) {
                        $payroll_data = [
                            'id' => null,
                            'withholding_tax' => $payroll_summary_data['total_withholding_tax'],
                            'sss' => $payroll_summary_data['total_sss'],
                            'other_comp' => $payroll_summary_data['total_other_comp'],
                            'gross_amount' => $payroll_summary_data['total_gross_amount'],
                            'total_deductions' => ($payroll_summary_data['total_withholding_tax'] ?? 0) + ($payroll_summary_data['total_sss'] ?? 0)
                        ];
                    }
                } else {
                    $payroll_stmt = $pdo->prepare("
                        SELECT * FROM payroll_history 
                        WHERE employee_id = ? AND employee_type = 'contractual' 
                            AND payroll_period = ? AND payroll_cutoff = ?
                    ");
                    $payroll_stmt->execute([$employee['user_id'], $selected_period, $selected_cutoff]);
                    $payroll_data = $payroll_stmt->fetch();
                }
            }
        } catch (Exception $e) {
            error_log("Payroll check error: " . $e->getMessage());
        }

        if ($payroll_data) {
            $employee['payroll_data'] = $payroll_data;
            $employee['other_comp'] = floatval($payroll_data['other_comp'] ?? $other_comp);
            $employee['withholding_tax'] = floatval($payroll_data['withholding_tax'] ?? $withholding_tax);
            $employee['sss'] = floatval($payroll_data['sss'] ?? $sss);
            $employee['gross_amount'] = floatval($payroll_data['gross_amount'] ?? $employee['gross_amount']);

            // Get detailed deductions
            $deductions_detail = [];
            try {
                $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_deductions'");
                if ($table_check->rowCount() > 0) {
                    if ($selected_cutoff == 'full' && $payroll_data['id'] === null) {
                        // For full month with combined data, we need to get deductions from both halves
                        $deductions_stmt = $pdo->prepare("
                            SELECT deduction_type, SUM(deduction_amount) as deduction_amount
                            FROM payroll_deductions pd
                            JOIN payroll_history ph ON pd.payroll_id = ph.id
                            WHERE ph.employee_id = ? AND ph.employee_type = 'contractual' 
                                AND ph.payroll_period = ? AND ph.payroll_cutoff IN ('first_half', 'second_half')
                            GROUP BY deduction_type
                        ");
                        $deductions_stmt->execute([$employee['user_id'], $selected_period]);
                    } else {
                        $deductions_stmt = $pdo->prepare("
                            SELECT deduction_type, deduction_amount 
                            FROM payroll_deductions 
                            WHERE payroll_id = ?
                        ");
                        $deductions_stmt->execute([$payroll_data['id']]);
                    }

                    $deductions = $deductions_stmt->fetchAll();

                    foreach ($deductions as $ded) {
                        $deductions_detail[$ded['deduction_type']] = $ded['deduction_amount'];
                    }
                }
            } catch (Exception $e) {
                error_log("Deductions fetch error: " . $e->getMessage());
            }

            $employee['deductions_detail'] = $deductions_detail;
        } else {
            $employee['payroll_data'] = null;
            $employee['deductions_detail'] = [];

            // Calculate default deductions for display (ALWAYS calculate regardless of gross amount)
            $gross = $employee['gross_amount'];

            $calc_withholding_tax = 0;
            $calc_sss = 0;

            // Withholding tax calculation based on tax config
            foreach ($tax_config as $bracket) {
                if (
                    $gross > $bracket['tax_bracket_min'] &&
                    ($bracket['tax_bracket_max'] === null || $gross <= $bracket['tax_bracket_max'])
                ) {
                    $calc_withholding_tax = ($gross - $bracket['tax_bracket_min']) * ($bracket['tax_rate'] / 100);
                    break;
                }
            }

            // If no tax config found, use a simple default calculation
            if ($calc_withholding_tax == 0 && empty($tax_config)) {
                if ($gross > 20000) {
                    $calc_withholding_tax = ($gross - 20000) * 0.15;
                } elseif ($gross > 10000) {
                    $calc_withholding_tax = ($gross - 10000) * 0.10;
                }
            }

            // SSS calculation using default rate (always calculate)
            $calc_sss = $gross * ($sss_rate / 100);

            $employee['default_deductions'] = [
                'withholding_tax' => $calc_withholding_tax,
                'sss' => $calc_sss,
                'total' => $calc_withholding_tax + $calc_sss
            ];
        }
    }

    $contractual_employees = $employees;
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

    if (isset($employee['payroll_data']) && $employee['payroll_data']) {
        $total_withholding_tax += floatval($employee['payroll_data']['withholding_tax'] ?? 0);
        $total_sss += floatval($employee['payroll_data']['sss'] ?? 0);
        $total_deductions += floatval($employee['payroll_data']['total_deductions'] ?? 0);
        $total_net_amount += floatval($employee['payroll_data']['net_amount'] ?? 0);
    } else {
        $gross = floatval($employee['gross_amount'] ?? 0);
        $deductions = $employee['default_deductions'] ?? [];

        $total_withholding_tax += $deductions['withholding_tax'] ?? 0;
        $total_sss += $deductions['sss'] ?? 0;
        $total_deductions += $deductions['total'] ?? 0;
        $total_net_amount += ($gross - ($deductions['total'] ?? 0));
    }
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
            )
        ");
    }

    if ($selected_cutoff == 'full') {
        // For full month, we need to aggregate from both halves
        $summary_stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT employee_id) as total_employees,
                SUM(days_present) as total_days_present,
                SUM(monthly_salaries_wages) as total_monthly_salaries_wages,
                SUM(other_comp) as total_other_comp,
                SUM(gross_amount) as total_gross_amount,
                SUM(withholding_tax) as total_withholding_tax,
                SUM(sss) as total_sss,
                SUM(total_deductions) as total_deductions,
                SUM(net_amount) as total_net_amount
            FROM payroll_history 
            WHERE payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half') AND employee_type = 'contractual'
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
    }
} catch (Exception $e) {
    error_log("Payroll summary fetch error: " . $e->getMessage());
    $payroll_summary = [];
}

// Get payroll status for the period and cutoff
$payroll_status = 'pending';
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_history'");
    if ($table_check->rowCount() > 0) {
        if ($selected_cutoff == 'full') {
            // For full month, check status from both halves
            $status_stmt = $pdo->prepare("
                SELECT DISTINCT status FROM payroll_history 
                WHERE employee_type = 'contractual' AND payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half')
                LIMIT 1
            ");
        } else {
            $status_stmt = $pdo->prepare("
                SELECT DISTINCT status FROM payroll_history 
                WHERE employee_type = 'contractual' AND payroll_period = ? AND payroll_cutoff = ?
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
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Function to calculate deductions for a row (IMPROVED - Always calculates deductions)
function calculateRowDeductions($employee, $tax_config, $sss_rate, $cutoff = 'full')
{
    $gross = floatval($employee['gross_amount'] ?? 0);

    // If payroll data exists, use it
    if (isset($employee['payroll_data']) && $employee['payroll_data']) {
        return [
            'withholding_tax' => floatval($employee['payroll_data']['withholding_tax'] ?? 0),
            'sss' => floatval($employee['payroll_data']['sss'] ?? 0),
            'total' => floatval($employee['payroll_data']['total_deductions'] ?? 0)
        ];
    }

    // Use stored values from the employee array (from database)
    if (isset($employee['withholding_tax']) && isset($employee['sss'])) {
        return [
            'withholding_tax' => floatval($employee['withholding_tax']),
            'sss' => floatval($employee['sss']),
            'total' => floatval($employee['withholding_tax'] + $employee['sss'])
        ];
    }

    // Use default deductions (always calculated even if gross is 0)
    if (isset($employee['default_deductions'])) {
        return $employee['default_deductions'];
    }

    // Always calculate deductions based on gross amount
    // If gross is 0, deductions will be 0
    $withholding_tax = 0;

    // Only calculate withholding tax if there's gross amount
    if ($gross > 0) {
        foreach ($tax_config as $bracket) {
            if (
                $gross > $bracket['tax_bracket_min'] &&
                ($bracket['tax_bracket_max'] === null || $gross <= $bracket['tax_bracket_max'])
            ) {
                $withholding_tax = ($gross - $bracket['tax_bracket_min']) * ($bracket['tax_rate'] / 100);
                break;
            }
        }

        if ($withholding_tax == 0 && empty($tax_config)) {
            if ($gross > 20000) {
                $withholding_tax = ($gross - 20000) * 0.15;
            } elseif ($gross > 10000) {
                $withholding_tax = ($gross - 10000) * 0.10;
            }
        }
    }

    // Always calculate SSS based on gross (will be 0 if gross is 0)
    $sss = $gross * ($sss_rate / 100);

    return [
        'withholding_tax' => $withholding_tax,
        'sss' => $sss,
        'total' => $withholding_tax + $sss
    ];
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

        /* Highlight rows with no attendance */
        .payroll-row.no-attendance {
            background-color: #fff3f3;
        }

        .payroll-row.no-attendance .gross-amount,
        .payroll-row.no-attendance .net-amount {
            color: #999;
            font-style: italic;
        }

        /* Info badge for attendance rules */
        .attendance-info-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            background-color: #f3f4f6;
            color: #4b5563;
        }

        .attendance-info-badge.warning {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .attendance-info-badge.success {
            background-color: #d1fae5;
            color: #065f46;
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
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .action-btn.paid-btn {
            background-color: #10b981;
            color: white;
        }

        .action-btn.paid-btn:hover {
            background-color: #059669;
        }

        .action-btn.view-btn {
            background-color: #3b82f6;
            color: white;
        }

        .action-btn.view-btn:hover {
            background-color: #2563eb;
        }

        /* Card design */
        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
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

        .payroll-input.editable:focus {
            border-color: #3b82f6;
        }

        .payroll-input.error {
            border-color: #ef4444;
            background-color: #fef2f2;
        }

        /* Zero amount styling */
        .zero-amount {
            color: #9ca3af;
            font-style: italic;
        }

        /* Alert messages */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
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

        /* Payroll period selector */
        .period-selector {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-paid {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-draft {
            background-color: #e5e7eb;
            color: #374151;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 0.5rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        .modal-content.large {
            max-width: 800px;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .modal-header button {
            color: white;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-header button:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        /* Employee details styles */
        .detail-section {
            margin-bottom: 1.5rem;
        }

        .detail-section h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-item.full-width {
            grid-column: span 2;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .detail-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: #1f2937;
        }

        .detail-value.highlight {
            color: #059669;
            font-weight: 600;
        }

        .attendance-summary {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
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

        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .history-table th {
            background: #f9fafb;
            padding: 0.5rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
        }

        .history-table td {
            padding: 0.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        /* Summary cards */
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
        }

        .summary-card .amount {
            font-size: 2rem;
            font-weight: bold;
            margin-top: 0.5rem;
        }

        /* Chart container */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Tab navigation */
        .tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 1rem;
        }

        .tab {
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .tab:hover {
            color: var(--primary);
        }

        .tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
            font-weight: 500;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Loading spinner */
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltip-text {
            visibility: hidden;
            width: 120px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Export dropdown */
        .export-dropdown {
            position: relative;
            display: inline-block;
        }

        .export-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            z-index: 1;
            border-radius: 0.375rem;
            overflow: hidden;
        }

        .export-dropdown:hover .export-dropdown-content {
            display: block;
        }

        .export-dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.2s;
        }

        .export-dropdown-content a:hover {
            background-color: #f3f4f6;
        }

        /* Loading overlay */
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

        /* Cutoff selector styles */
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

        /* Disabled field styling */
        .disabled-field {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: 500;
        }

        /* Compensation header background */
        .compensation-header {
            background-color: #e6f3ff;
        }

        /* Deductions header background */
        .deductions-header {
            background-color: #fff3e6;
        }

        /* Days present indicator */
        .days-present-badge {
            background-color: #e0f2fe;
            color: #0369a1;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
        }

        .days-present-badge.warning {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .days-present-badge.success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .days-present-badge.info {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        /* Stats cards for attendance summary */
        .attendance-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .attendance-stat-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1rem;
            flex: 1;
            min-width: 150px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .attendance-stat-card .label {
            font-size: 0.8rem;
            color: #6b7280;
        }

        .attendance-stat-card .value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }

        /* Auto-save indicator */
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

        .auto-save-indicator.saving {
            background: #f59e0b;
        }

        .auto-save-indicator.saved {
            background: #10b981;
        }

        .auto-save-indicator.error {
            background: #ef4444;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Auto-save Indicator -->
    <div class="auto-save-indicator" id="autoSaveIndicator" style="display: none;">
        <i class="fas fa-spinner fa-spin"></i>
        <span>Saving...</span>
    </div>

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

                <!-- Logout Button -->
                <a href="?logout=true" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
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
        <?php if ($success_message): ?>
            <div class="alert alert-success mb-4">
                <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Attendance Summary Stats -->
        <div class="attendance-stats mb-4">
            <div class="attendance-stat-card">
                <div class="label">Total Employees</div>
                <div class="value"><?php echo count($contractual_employees); ?></div>
            </div>
            <div class="attendance-stat-card">
                <div class="label">Employees with Attendance</div>
                <div class="value"><?php echo $employees_with_attendance; ?></div>
            </div>
            <div class="attendance-stat-card">
                <div class="label">Employees without Attendance</div>
                <div class="value"><?php echo count($contractual_employees) - $employees_with_attendance; ?></div>
            </div>
            <div class="attendance-stat-card">
                <div class="label">Total Days Present</div>
                <div class="value"><?php echo number_format($total_days_present, 1); ?></div>
            </div>
        </div>

        <!-- Business Rules Info Banner -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>Payroll Calculation Rules:</strong>
                        Gross amount is calculated based on days present × daily rate + other compensation.
                        <span class="font-bold text-green-700">For Full Month view, deductions are combined from both First and Second Half periods.</span>
                        <?php if ($selected_cutoff != 'full'): ?>
                            You are viewing <strong><?php echo $current_cutoff['label']; ?></strong>.
                            For Full Month view, data from both halves will be combined.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

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

        <!-- Tabs Navigation -->
        <div class="tabs">
            <div class="tab active" data-tab="payroll">Payroll Details</div>
            <div class="tab" data-tab="summary">Payroll Summary</div>
            <div class="tab" data-tab="approvals">Approval History</div>
        </div>

        <!-- Payroll Details Tab -->
        <div class="tab-content active" id="tab-payroll">
            <!-- IMPROVED PAGE HEADER SECTION - FIXED ALIGNMENT AND DESIGN -->
            <div class="mb-6 mt-4">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                    <!-- Left side - Title -->
                    <div class="flex-shrink-0">
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900">Contractual Payroll</h1>
                        <p class="text-gray-600 mt-1 text-sm md:text-base">Manage and process contractual employee payroll</p>
                    </div>

                    <!-- Right side - Controls (Stacked vertically on large screens, side by side on smaller) -->
                    <div class="flex flex-col w-full lg:w-auto lg:min-w-[400px] gap-3">
                        <!-- Period Selector and Cutoff Buttons - Compact and aligned -->
                        <div class="flex flex-col gap-2 bg-white p-3 rounded-lg border border-gray-200 shadow-sm">
                            <!-- Period Dropdown -->
                            <div class="period-selector w-full">
                                <select id="payroll-period" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
                                    <?php
                                    // Generate last 12 months
                                    for ($i = 0; $i < 12; $i++) {
                                        $date = date('Y-m', strtotime("-$i months"));
                                        $display = date('F Y', strtotime("-$i months"));
                                        $selected = ($date == $selected_period) ? 'selected' : '';
                                        echo "<option value=\"$date\" $selected>$display</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Cutoff Buttons - Horizontal scroll on mobile -->
                            <div class="cutoff-selector flex flex-nowrap overflow-x-auto pb-1 gap-1 scrollbar-thin">
                                <a href="?period=<?php echo $selected_period; ?>&cutoff=full"
                                    class="cutoff-btn whitespace-nowrap <?php echo ($selected_cutoff == 'full') ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-alt"></i> Full Month (Combined)
                                </a>
                                <a href="?period=<?php echo $selected_period; ?>&cutoff=first_half"
                                    class="cutoff-btn whitespace-nowrap <?php echo ($selected_cutoff == 'first_half') ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-week"></i> First Half (1-15)
                                </a>
                                <a href="?period=<?php echo $selected_period; ?>&cutoff=second_half"
                                    class="cutoff-btn whitespace-nowrap <?php echo ($selected_cutoff == 'second_half') ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-week"></i> Second Half (16-<?php echo date('t', strtotime($selected_period . '-01')); ?>)
                                </a>
                            </div>

                            <!-- Cutoff Info - Compact -->
                            <div class="cutoff-info text-xs bg-gray-50 p-2 rounded border border-gray-100">
                                <i class="fas fa-info-circle text-primary-600 mr-1"></i>
                                Period: <?php echo date('M d', strtotime($current_cutoff['start'])); ?> - <?php echo date('M d, Y', strtotime($current_cutoff['end'])); ?>
                                (<?php echo $current_cutoff['working_days']; ?> working days)
                                <?php if ($selected_cutoff == 'full'): ?>
                                    <span class="font-bold text-green-600 ml-1">(Combined from both halves)</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Action Buttons - Right aligned -->
                        <div class="flex flex-wrap gap-2 justify-end">
                            <?php if ($payroll_status == 'pending' || $payroll_status == 'draft'): ?>
                                <button id="calculate-all-btn" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-3 rounded-lg flex items-center justify-center text-sm">
                                    <i class="fas fa-calculator mr-1"></i> Calculate All
                                </button>
                            <?php endif; ?>

                            <!-- Export Dropdown -->
                            <div class="export-dropdown">
                                <button class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-3 rounded-lg flex items-center justify-center text-sm">
                                    <i class="fas fa-download mr-1"></i> Export
                                </button>
                                <div class="export-dropdown-content">
                                    <a href="#" onclick="exportData('pdf')"><i class="fas fa-file-pdf mr-2"></i> Export as PDF</a>
                                    <a href="#" onclick="exportData('excel')"><i class="fas fa-file-excel mr-2"></i> Export as Excel</a>
                                    <a href="#" onclick="exportData('csv')"><i class="fas fa-file-csv mr-2"></i> Export as CSV</a>
                                </div>
                            </div>
                        </div>
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
                            <i class="fas fa-calendar-check text-lg"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Days Present</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900"><?php echo number_format($total_days_present, 1); ?></p>
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
                            <p class="text-xl md:text-2xl font-bold text-gray-900" id="net-amount-display">₱<?php echo number_format($total_net_amount, 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payroll Table -->
            <form method="POST" action="" id="payroll-form">
                <input type="hidden" name="save_payroll" value="1">
                <input type="hidden" name="payroll_period" id="hidden-payroll-period" value="<?php echo $selected_period; ?>">
                <input type="hidden" name="payroll_cutoff" id="hidden-payroll-cutoff" value="<?php echo $selected_cutoff; ?>">
                <input type="hidden" name="working_days" value="<?php echo $current_cutoff['working_days']; ?>">

                <div class="card">
                    <div class="p-4 border-b border-gray-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <h2 class="text-lg font-semibold text-gray-900">
                            Employee Payroll Details for <?php echo date('F Y', strtotime($selected_period . '-01')); ?>
                            (<?php echo $current_cutoff['label']; ?>)
                        </h2>
                        <div class="flex items-center space-x-2 w-full md:w-auto">
                            <div class="relative flex-1 md:flex-none">
                                <input type="text" id="search-employees" placeholder="Search employees..." class="pl-9 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 w-full text-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="payroll-table">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="w-12">#</th>
                                    <th rowspan="2" class="min-w-[120px]">Employee ID</th>
                                    <th rowspan="2" class="min-w-[150px]">Name</th>
                                    <th rowspan="2" class="min-w-[120px]">Position</th>
                                    <th rowspan="2" class="min-w-[100px]">Department</th>
                                    <th rowspan="2" class="min-w-[100px]">Days Present</th>
                                    <th colspan="4" class="text-center compensation-header">Compensation</th>
                                    <th colspan="3" class="text-center deductions-header">Deductions</th>
                                    <th rowspan="2" class="min-w-[100px] bg-gray-100">Net Amount</th>
                                    <th rowspan="2" class="min-w-[180px]">Actions</th>
                                </tr>
                                <tr>
                                    <th class="min-w-[100px] compensation-header">Daily Rate</th>
                                    <th class="min-w-[120px] compensation-header">Monthly Salary (Base)</th>
                                    <th class="min-w-[100px] compensation-header">Other Compensation</th>
                                    <th class="min-w-[100px] compensation-header">Gross Amount Earned</th>
                                    <th class="min-w-[100px] deductions-header">Withholding Tax</th>
                                    <th class="min-w-[100px] deductions-header">SSS Contribution</th>
                                    <th class="min-w-[100px] deductions-header">Total Deductions</th>
                                </tr>
                            </thead>
                            <tbody id="payroll-tbody">
                                <?php if (empty($contractual_employees)): ?>
                                    <tr>
                                        <td colspan="16" class="text-center py-8 text-gray-500">
                                            No contractual employees found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($contractual_employees as $employee): ?>
                                        <?php
                                        $deductions = calculateRowDeductions($employee, $tax_config, $sss_rate, $selected_cutoff);
                                        $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
                                        $daily_rate = floatval($employee['daily_rate'] ?? 0);
                                        $prorated_salary = floatval($employee['prorated_salary'] ?? 0);
                                        $other_comp = floatval($employee['other_comp'] ?? 0);
                                        $days_present = floatval($employee['days_present'] ?? 0);
                                        $gross_amount = floatval($employee['gross_amount'] ?? 0);
                                        $net_amount_row = $gross_amount - $deductions['total'];
                                        if ($net_amount_row < 0) $net_amount_row = 0;

                                        $payroll_data = $employee['payroll_data'] ?? null;
                                        $payroll_id = $payroll_data['id'] ?? null;

                                        // Determine if row has attendance
                                        $has_attendance = ($days_present > 0);
                                        $row_class = $has_attendance ? '' : 'no-attendance';
                                        ?>
                                        <tr class="bg-white border-b hover:bg-gray-50 payroll-row <?php echo $row_class; ?>" data-employee-id="<?php echo $employee['user_id']; ?>" data-payroll-id="<?php echo $payroll_id; ?>">
                                            <td class="text-center"><?php echo $counter++; ?></td>
                                            <td class="font-medium"><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                            <td class="font-medium">
                                                <?php echo htmlspecialchars($employee['full_name']); ?>
                                                <input type="hidden" name="employee_id[]" value="<?php echo $employee['user_id']; ?>">
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
                                                    value="<?php echo $daily_rate; ?>"
                                                    readonly
                                                    disabled
                                                    tabindex="-1">
                                            </td>

                                            <!-- Monthly Salary (Base) - READONLY -->
                                            <td>
                                                <input type="number"
                                                    name="monthly_salaries_wages[]"
                                                    class="payroll-input readonly disabled-field monthly-salaries-wages"
                                                    value="<?php echo $monthly_salary; ?>"
                                                    readonly
                                                    disabled
                                                    tabindex="-1">
                                            </td>

                                            <!-- Other Compensation - EDITABLE with auto-save -->
                                            <td>
                                                <input type="number"
                                                    name="other_comp[]"
                                                    class="payroll-input editable other-comp auto-save-field"
                                                    value="<?php echo $other_comp; ?>"
                                                    min="0"
                                                    step="0.01"
                                                    data-employee-id="<?php echo $employee['user_id']; ?>"
                                                    data-field="other_comp"
                                                    <?php echo ($payroll_status != 'pending' && $payroll_status != 'draft') ? 'readonly disabled' : ''; ?>>
                                            </td>

                                            <!-- Gross Amount - AUTO-CALCULATED, READONLY -->
                                            <td>
                                                <input type="number"
                                                    name="gross_amount[]"
                                                    class="payroll-input gross-amount readonly disabled-field <?php echo ($gross_amount <= 0) ? 'zero-amount' : ''; ?>"
                                                    value="<?php echo $gross_amount; ?>"
                                                    readonly
                                                    disabled
                                                    tabindex="-1">
                                            </td>

                                            <!-- Deductions - EDITABLE with auto-save -->
                                            <td>
                                                <input type="number"
                                                    name="withholding_tax[]"
                                                    class="payroll-input editable withholding-tax auto-save-field <?php echo ($payroll_status != 'pending' && $payroll_status != 'draft') ? 'readonly' : ''; ?>"
                                                    value="<?php echo $deductions['withholding_tax']; ?>"
                                                    min="0"
                                                    step="0.01"
                                                    data-employee-id="<?php echo $employee['user_id']; ?>"
                                                    data-field="withholding_tax"
                                                    <?php echo ($payroll_status != 'pending' && $payroll_status != 'draft') ? 'readonly disabled' : ''; ?>>
                                            </td>
                                            <td>
                                                <input type="number"
                                                    name="sss[]"
                                                    class="payroll-input editable sss auto-save-field <?php echo ($payroll_status != 'pending' && $payroll_status != 'draft') ? 'readonly' : ''; ?>"
                                                    value="<?php echo $deductions['sss']; ?>"
                                                    min="0"
                                                    step="0.01"
                                                    data-employee-id="<?php echo $employee['user_id']; ?>"
                                                    data-field="sss"
                                                    <?php echo ($payroll_status != 'pending' && $payroll_status != 'draft') ? 'readonly disabled' : ''; ?>>
                                            </td>

                                            <!-- Total Deduction - AUTO-CALCULATED, READONLY -->
                                            <td>
                                                <input type="number"
                                                    name="total_deduction[]"
                                                    class="payroll-input total-deduction readonly disabled-field"
                                                    value="<?php echo $deductions['total']; ?>"
                                                    readonly
                                                    disabled
                                                    tabindex="-1">
                                            </td>

                                            <!-- Net Amount - AUTO-CALCULATED, READONLY -->
                                            <td>
                                                <input type="number"
                                                    name="net_amount[]"
                                                    class="payroll-input net-amount readonly disabled-field <?php echo ($net_amount_row <= 0) ? 'zero-amount' : ''; ?>"
                                                    value="<?php echo $net_amount_row; ?>"
                                                    readonly
                                                    disabled
                                                    tabindex="-1">
                                            </td>

                                            <td>
                                                <div class="action-buttons">
                                                    <!-- View Button with enhanced modal -->
                                                    <button type="button" class="action-btn view-btn" onclick="viewEmployeeDetails(<?php echo $employee['user_id']; ?>, '<?php echo $selected_period; ?>', '<?php echo $selected_cutoff; ?>')">
                                                        <i class="fas fa-eye"></i> <span class="hidden md:inline">View</span>
                                                    </button>

                                                    <?php if ($payroll_status == 'pending' || $payroll_status == 'draft'): ?>
                                                        <button type="button" class="action-btn bg-green-500 text-white hover:bg-green-600 calculate-row" onclick="calculateSingleRow(this)">
                                                            <i class="fas fa-calculator"></i> <span class="hidden md:inline">Calc</span>
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if ($payroll_status == 'approved' && $payroll_id): ?>
                                                        <button type="button" class="action-btn paid-btn" onclick="showPaymentModal(<?php echo $payroll_id; ?>)">
                                                            <i class="fas fa-check-double"></i> <span class="hidden md:inline">Pay</span>
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if ($payroll_id): ?>
                                                        <button type="button" class="action-btn bg-purple-500 text-white hover:bg-purple-600" onclick="viewDeductions(<?php echo $payroll_id; ?>)">
                                                            <i class="fas fa-chart-pie"></i> <span class="hidden md:inline">Deductions</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <!-- Totals Row -->
                                <tr class="bg-gray-100 font-bold">
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

                    <!-- Table Footer -->
                    <div class="p-4 border-t border-gray-200 flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="text-sm text-gray-600">
                            Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($contractual_employees); ?></span> of <span class="font-medium"><?php echo count($contractual_employees); ?></span> employees
                            for <?php echo $current_cutoff['label']; ?>
                        </div>
                        <div class="text-sm text-blue-600 bg-blue-50 px-3 py-1 rounded">
                            <i class="fas fa-info-circle mr-1"></i>
                            <strong>Calculation:</strong> Gross = (Daily Rate × Days Present) + Other Compensation | Daily Rate = Monthly Salary / 22 days
                            <?php if ($selected_cutoff == 'full'): ?>
                                | <span class="font-bold">Full Month combines data from both halves</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <?php if ($payroll_status == 'pending' || $payroll_status == 'draft'): ?>
                    <div class="mt-6 flex flex-col sm:flex-row justify-end gap-3">
                        <button type="submit" class="px-4 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 flex items-center justify-center">
                            <i class="fas fa-save mr-2"></i> Save Payroll (<?php echo $current_cutoff['label']; ?>)
                        </button>
                        <button type="button" onclick="generateObligationRequest()" class="px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 flex items-center justify-center">
                            Generate Obligation Request <i class="fas fa-arrow-right ml-2"></i>
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

        <!-- Payroll Summary Tab -->
        <div class="tab-content" id="tab-summary">
            <div class="card p-6">
                <h2 class="text-xl font-bold mb-4">
                    Payroll Summary for <?php echo date('F Y', strtotime($selected_period . '-01')); ?>
                    (<?php echo $current_cutoff['label']; ?>)
                </h2>

                <?php if ($payroll_summary): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="summary-card">
                                <h3 class="text-lg opacity-90">Total Net Amount</h3>
                                <div class="amount">₱<?php echo number_format($payroll_summary['total_net_amount'], 2); ?></div>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <span class="font-medium">Total Employees:</span>
                                    <span class="font-bold"><?php echo $payroll_summary['total_employees']; ?></span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <span class="font-medium">Total Days Present:</span>
                                    <span class="font-bold"><?php echo number_format($payroll_summary['total_days_present'] ?? 0, 1); ?></span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <span class="font-medium">Total Monthly Salaries (Base):</span>
                                    <span class="font-bold">₱<?php echo number_format($payroll_summary['total_monthly_salaries_wages'] ?? 0, 2); ?></span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <span class="font-medium">Total Other Compensation:</span>
                                    <span class="font-bold">₱<?php echo number_format($payroll_summary['total_other_comp'] ?? 0, 2); ?></span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <span class="font-medium">Total Gross Amount:</span>
                                    <span class="font-bold">₱<?php echo number_format($payroll_summary['total_gross_amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold mb-3">Deductions Breakdown</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                                    <span class="font-medium">Withholding Tax:</span>
                                    <span class="font-bold text-red-600">₱<?php echo number_format($payroll_summary['total_withholding_tax'], 2); ?></span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                                    <span class="font-medium">SSS Contribution:</span>
                                    <span class="font-bold text-blue-600">₱<?php echo number_format($payroll_summary['total_sss'], 2); ?></span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-purple-50 rounded">
                                    <span class="font-medium">Total Deductions:</span>
                                    <span class="font-bold text-purple-600">₱<?php echo number_format($payroll_summary['total_deductions'], 2); ?></span>
                                </div>
                            </div>

                            <div class="mt-6 chart-container">
                                <canvas id="deductionsChart"></canvas>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-8">No payroll summary available for this period and cutoff.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Approval History Tab -->
        <div class="tab-content" id="tab-approvals">
            <div class="card p-6">
                <h2 class="text-xl font-bold mb-4">
                    Approval History for <?php echo date('F Y', strtotime($selected_period . '-01')); ?>
                    (<?php echo $current_cutoff['label']; ?>)
                </h2>

                <?php if ($approval_history): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved By</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($approval_history as $approval): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M d, Y H:i', strtotime($approval['approved_date'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($approval['approved_by']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="status-badge status-<?php echo $approval['status']; ?>">
                                                <?php echo ucfirst($approval['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($approval['approval_notes'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-8">No approval history found for this period and cutoff.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Enhanced View Employee Modal -->
        <div class="modal" id="viewEmployeeModal">
            <div class="modal-content large">
                <div class="modal-header">
                    <h3 class="text-lg font-semibold">Employee Details</h3>
                    <button onclick="closeModal('viewEmployeeModal')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" id="viewEmployeeModalBody">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center py-4">
                        <div class="spinner mx-auto"></div>
                        <p class="mt-2 text-gray-600">Loading employee details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('viewEmployeeModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                        Close
                    </button>
                    <button type="button" onclick="printEmployeeDetails()" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                </div>
            </div>
        </div>

        <!-- Approve Payroll Modal -->
        <div class="modal" id="approveModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="text-lg font-semibold">Approve Payroll</h3>
                    <button onclick="closeModal('approveModal')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="?approve_payroll=1&period=<?php echo $selected_period; ?>&cutoff=<?php echo $selected_cutoff; ?>" method="POST">
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
                    <div class="modal-footer">
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

        <!-- Payment Modal -->
        <div class="modal" id="paymentModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="text-lg font-semibold">Process Payment</h3>
                    <button onclick="closeModal('paymentModal')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="" method="POST" id="paymentForm">
                    <div class="modal-body">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                            <select name="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500">
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="check">Check</option>
                                <option value="cash">Cash</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number</label>
                            <input type="text" name="reference_number"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                                placeholder="Enter reference number">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Notes</label>
                            <textarea name="payment_notes" rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                                placeholder="Enter any notes about this payment..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="payroll_id" id="payment_payroll_id">
                        <button type="button" onclick="closeModal('paymentModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                            Process Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Deductions Detail Modal -->
        <div class="modal" id="deductionsModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="text-lg font-semibold">Deductions Details</h3>
                    <button onclick="closeModal('deductionsModal')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" id="deductionsModalBody">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script>
        // Initialize states
        let sidebarOpen = false;
        let payrollMenuOpen = true;
        let activeSaves = 0;
        let saveTimeout;

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

            // Initialize charts
            initializeCharts();

            // Set hidden cutoff value
            document.getElementById('hidden-payroll-cutoff').value = '<?php echo $selected_cutoff; ?>';

            // Make sure disabled fields don't interfere with form submission
            const payrollForm = document.getElementById('payroll-form');
            if (payrollForm) {
                payrollForm.addEventListener('submit', function(e) {
                    // Temporarily enable disabled fields to ensure their values are submitted
                    const disabledFields = this.querySelectorAll('input[disabled]');
                    disabledFields.forEach(field => {
                        field.disabled = false;
                    });
                });
            }

            // Initialize row highlighting based on attendance
            highlightRowsByAttendance();

            // Initialize auto-save for editable fields
            initAutoSave();
        });

        // Initialize auto-save functionality
        function initAutoSave() {
            const autoSaveFields = document.querySelectorAll('.auto-save-field');

            autoSaveFields.forEach(field => {
                // Save on Enter key press
                field.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        saveField(this);
                    }
                });

                // Save on blur (when field loses focus)
                field.addEventListener('blur', function() {
                    // Clear any pending timeout
                    if (saveTimeout) {
                        clearTimeout(saveTimeout);
                    }
                    // Debounce save to avoid multiple saves
                    saveTimeout = setTimeout(() => {
                        saveField(this);
                    }, 300);
                });
            });
        }

        // Save field via AJAX
        async function saveField(field) {
            if (field.readonly || field.disabled) return;

            const employeeId = field.dataset.employeeId;
            const fieldName = field.dataset.field;
            const value = parseFloat(field.value) || 0;
            const period = document.getElementById('payroll-period').value;
            const cutoff = document.getElementById('hidden-payroll-cutoff').value;

            if (!employeeId || !fieldName) {
                console.error('Missing employeeId or fieldName');
                return;
            }

            // Show saving indicator
            showAutoSaveIndicator('saving');

            try {
                const formData = new FormData();

                if (fieldName === 'other_comp') {
                    formData.append('ajax_action', 'save_other_comp');
                    formData.append('other_comp', value);
                } else {
                    formData.append('ajax_action', 'save_deductions');

                    // For deductions, get both values from the same row
                    const row = field.closest('.payroll-row');
                    const withholdingTax = parseFloat(row.querySelector('.withholding-tax').value) || 0;
                    const sss = parseFloat(row.querySelector('.sss').value) || 0;

                    formData.append('withholding_tax', withholdingTax);
                    formData.append('sss', sss);
                }

                formData.append('employee_id', employeeId);
                formData.append('period', period);
                formData.append('cutoff', cutoff);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    showAutoSaveIndicator('saved');

                    // Recalculate the row
                    const row = field.closest('.payroll-row');
                    await calculateRow(row);
                    await calculateAll();

                    console.log('Save successful:', result.message);
                } else {
                    showAutoSaveIndicator('error');
                    console.error('Auto-save error:', result.error);
                    showNotification('Error saving: ' + (result.error || 'Unknown error'), 'error');
                }
            } catch (error) {
                showAutoSaveIndicator('error');
                console.error('Auto-save error:', error);
                showNotification('Error connecting to server', 'error');
            }
        }

        // Show auto-save indicator
        function showAutoSaveIndicator(status) {
            const indicator = document.getElementById('autoSaveIndicator');

            if (status === 'saving') {
                activeSaves++;
                indicator.className = 'auto-save-indicator saving';
                indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Saving...</span>';
                indicator.style.display = 'flex';
            } else if (status === 'saved') {
                activeSaves = Math.max(0, activeSaves - 1);

                if (activeSaves === 0) {
                    indicator.className = 'auto-save-indicator saved';
                    indicator.innerHTML = '<i class="fas fa-check-circle"></i><span>Saved</span>';

                    setTimeout(() => {
                        indicator.style.display = 'none';
                    }, 2000);
                }
            } else if (status === 'error') {
                activeSaves = Math.max(0, activeSaves - 1);

                indicator.className = 'auto-save-indicator error';
                indicator.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Save failed</span>';

                setTimeout(() => {
                    indicator.style.display = 'none';
                }, 3000);
            }
        }

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

        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and tab contents
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                // Add active class to clicked tab
                this.classList.add('active');

                // Show corresponding tab content
                const tabId = this.dataset.tab;
                document.getElementById(`tab-${tabId}`).classList.add('active');
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

        // Show/hide loading overlay
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        // Highlight rows based on attendance
        function highlightRowsByAttendance() {
            const rows = document.querySelectorAll('.payroll-row');
            rows.forEach(row => {
                const daysPresentInput = row.querySelector('.hidden-days-present');
                if (daysPresentInput) {
                    const daysPresent = parseFloat(daysPresentInput.value) || 0;
                    if (daysPresent <= 0) {
                        row.classList.add('no-attendance');
                    } else {
                        row.classList.remove('no-attendance');
                    }
                }
            });
        }

        // View employee details with cutoff
        window.viewEmployeeDetails = async function(employeeId, period, cutoff) {
            console.log('=== View Employee Details Debug ===');
            console.log('Employee ID:', employeeId);
            console.log('Period:', period);
            console.log('Cutoff:', cutoff);

            // Show loading
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

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log('Response data:', result);

                if (result.success) {
                    // Ensure all numeric values are properly converted
                    if (result.calculations) {
                        result.calculations.monthly_salary = parseFloat(result.calculations.monthly_salary) || 0;
                        result.calculations.daily_rate = parseFloat(result.calculations.daily_rate) || 0;
                        result.calculations.prorated_salary = parseFloat(result.calculations.prorated_salary) || 0;
                        result.calculations.other_comp = parseFloat(result.calculations.other_comp) || 0;
                        result.calculations.gross_amount = parseFloat(result.calculations.gross_amount) || 0;
                        result.calculations.total_hours = parseFloat(result.calculations.total_hours) || 0;
                    }

                    if (result.attendance) {
                        result.attendance.total_days = parseInt(result.attendance.total_days) || 0;
                        result.attendance.days_present = parseFloat(result.attendance.days_present) || 0;
                        result.attendance.total_hours = parseFloat(result.attendance.total_hours) || 0;
                    }

                    displayEmployeeDetails(result);
                } else {
                    console.error('Server returned error:', result);
                    showNotification('Error: ' + (result.error || 'Unknown error'), 'error');
                }
            } catch (error) {
                console.error('Connection error details:', error);
                showNotification('Error: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        };

        // Display employee details with cutoff information
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
            if (!modalBody) {
                console.error('Modal body element not found!');
                return;
            }

            // Helper function to safely format numbers
            const safeNumber = (value, decimals = 2) => {
                if (value === null || value === undefined || value === '') return '0.00';
                const num = parseFloat(value);
                return isNaN(num) ? '0.00' : num.toFixed(decimals);
            };

            // Helper function to safely format currency
            const safeCurrency = (value) => {
                return '₱' + safeNumber(value);
            };

            // Helper function to safely get text
            const safeText = (value) => {
                if (value === null || value === undefined) return 'N/A';
                return String(value);
            };

            // Determine if employee has attendance
            const hasAttendance = (attendance.days_present > 0);
            const grossDisplayClass = hasAttendance ? 'text-green-600 font-bold' : 'text-gray-400';

            let html = `
                <div class="space-y-4">
                    <!-- Cutoff Info -->
                    <div class="bg-primary-50 p-4 rounded-lg border-l-4 border-primary-600">
                        <h4 class="font-bold text-lg mb-2">Payroll Period: ${cutoff.label}</h4>
                        <p class="text-sm">Period: ${cutoff.start} to ${cutoff.end}</p>
                        <p class="text-sm">Working Days: ${cutoff.working_days} days</p>
                        ${cutoff.type == 'full' ? '<p class="text-sm font-bold text-green-600 mt-1">Combined from both halves</p>' : ''}
                    </div>
                    
                    <!-- Basic Info -->
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h4 class="font-bold text-lg mb-2">${safeText(employee.full_name)}</h4>
                        <p class="text-sm">Employee ID: ${safeText(employee.employee_id)}</p>
                        <p class="text-sm">Position: ${safeText(employee.position)}</p>
                        <p class="text-sm">Department: ${safeText(employee.department)}</p>
                    </div>
                    
                    <!-- Contact Info -->
                    <div class="border p-4 rounded-lg">
                        <h4 class="font-semibold mb-2">Contact Information</h4>
                        <p class="text-sm">Email: ${safeText(employee.email_address)}</p>
                        <p class="text-sm">Mobile: ${safeText(employee.mobile_number)}</p>
                    </div>
                    
                    <!-- Attendance Summary -->
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
                        <p class="text-sm text-gray-600 mt-2">Total Hours: ${safeNumber(attendance.total_hours, 1)} hours</p>
                        ${!hasAttendance ? '<p class="text-sm text-red-600 mt-2"><i class="fas fa-exclamation-circle"></i> No attendance recorded for this period</p>' : ''}
                    </div>
                    
                    <!-- Compensation Info -->
                    <div class="border p-4 rounded-lg">
                        <h4 class="font-semibold mb-2">Compensation Information</h4>
                        <p class="text-sm">Monthly Salary (Base): ${safeCurrency(calculations.monthly_salary)}</p>
                        <p class="text-sm">Daily Rate: ${safeCurrency(calculations.daily_rate)}</p>
                        <p class="text-sm">Prorated Salary (${safeNumber(attendance.days_present, 1)} days × ₱${safeNumber(calculations.daily_rate)}): ${safeCurrency(calculations.prorated_salary)}</p>
                        <p class="text-sm">Other Compensation: ${safeCurrency(calculations.other_comp)}</p>
                        <p class="text-sm">Withholding Tax: ${safeCurrency(calculations.withholding_tax)}</p>
                        <p class="text-sm">SSS Contribution: ${safeCurrency(calculations.sss)}</p>
                        <p class="text-sm font-bold ${grossDisplayClass} mt-2">Gross Amount Earned: ${safeCurrency(calculations.gross_amount)}</p>
                        ${!hasAttendance ? '<p class="text-xs text-gray-500 mt-1">Gross amount is 0 due to no attendance, deductions are 0</p>' : ''}
                        ${cutoff.type == 'full' ? '<p class="text-xs text-green-600 mt-1">Note: Deductions are combined from both First and Second Half periods</p>' : ''}
                    </div>
                    
                    <!-- Contract Info -->
                    <div class="border p-4 rounded-lg">
                        <h4 class="font-semibold mb-2">Contract Information</h4>
                        <p class="text-sm">Period: ${safeText(employee.period_from)} to ${safeText(employee.period_to)}</p>
                        <p class="text-sm">Status: <span class="status-badge status-${employee.status || 'active'}">${safeText(employee.status)}</span></p>
                        <p class="text-sm">Contribution: ${safeText(employee.contribution)}</p>
                    </div>
            `;

            // Payroll History
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
                            <td class="p-2">${payroll.payroll_cutoff == 'full' ? 'Full (Combined)' : (payroll.payroll_cutoff == 'first_half' ? '1st Half' : '2nd Half')}</td>
                            <td class="p-2">${payroll.days_present ? safeNumber(payroll.days_present, 1) : '-'}</td>
                            <td class="p-2">${safeCurrency(payroll.gross_amount)}</td>
                            <td class="p-2">${safeCurrency(payroll.net_amount)}</td>
                            <td class="p-2"><span class="status-badge status-${payroll.status || 'pending'}">${safeText(payroll.status)}</span></td>
                        </tr>
                    `;
                });

                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="border p-4 rounded-lg">
                        <h4 class="font-semibold mb-2">Recent Payroll History</h4>
                        <p class="text-gray-500 text-center py-2">No payroll history found.</p>
                    </div>
                `;
            }

            html += `</div>`;

            modalBody.innerHTML = html;
            openModal('viewEmployeeModal');
        }

        // Print employee details
        function printEmployeeDetails() {
            const modalContent = document.getElementById('viewEmployeeModalBody').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Employee Details</title>
                    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
                    <style>
                        body { padding: 20px; font-family: Arial, sans-serif; }
                        .detail-section { margin-bottom: 20px; }
                        .detail-section h4 { font-size: 16px; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
                        .attendance-summary { display: flex; gap: 10px; margin-bottom: 10px; }
                        .attendance-card { flex: 1; padding: 10px; background: #f5f5f5; border-radius: 5px; text-align: center; }
                        .attendance-card .value { font-size: 18px; font-weight: bold; color: #1e40af; }
                        .history-table { width: 100%; border-collapse: collapse; }
                        .history-table th, .history-table td { padding: 8px; border: 1px solid #ddd; text-align: left; }
                    </style>
                </head>
                <body>
                    <h1 class="text-2xl font-bold mb-4">Employee Details</h1>
                    ${modalContent}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Payroll Calculation Functions with AJAX
        async function calculateRow(row) {
            // Get values from editable fields
            const otherComp = parseFloat(row.querySelector('.other-comp').value) || 0;
            const withholdingTax = parseFloat(row.querySelector('.withholding-tax').value) || 0;
            const sss = parseFloat(row.querySelector('.sss').value) || 0;

            // Get readonly values
            const monthlySalaryInput = row.querySelector('.monthly-salaries-wages');
            const daysPresent = parseFloat(row.querySelector('.hidden-days-present')?.value || 0);
            const workingDays = 22; // Standard 22 working days per month
            const monthlySalary = parseFloat(monthlySalaryInput ? monthlySalaryInput.value : 0) || 0;

            // Calculate daily rate
            const dailyRate = monthlySalary / 22;

            // Update daily rate field (disabled)
            const dailyRateField = row.querySelector('.daily-rate');
            if (dailyRateField) {
                dailyRateField.value = dailyRate.toFixed(2);
            }

            // Calculate gross amount based on days present
            const proratedSalary = dailyRate * daysPresent;
            let grossAmount = proratedSalary + otherComp;

            // Update gross amount field (disabled)
            const grossAmountField = row.querySelector('.gross-amount');
            if (grossAmountField) {
                grossAmountField.value = grossAmount.toFixed(2);
            }

            // Calculate total deductions (always calculate)
            const totalDeduction = withholdingTax + sss;
            const totalDeductionField = row.querySelector('.total-deduction');
            if (totalDeductionField) {
                totalDeductionField.value = totalDeduction.toFixed(2);
            }

            // Calculate net amount
            let netAmount = grossAmount - totalDeduction;
            if (netAmount < 0) netAmount = 0;

            const netAmountField = row.querySelector('.net-amount');
            if (netAmountField) {
                netAmountField.value = netAmount.toFixed(2);
            }

            // Update row highlighting based on attendance
            if (daysPresent <= 0) {
                row.classList.add('no-attendance');
            } else {
                row.classList.remove('no-attendance');
            }

            const employeeId = row.dataset.employeeId;

            try {
                // Make AJAX call to calculate
                const formData = new FormData();
                formData.append('ajax_action', 'calculate_row');
                formData.append('employee_id', employeeId);
                formData.append('monthly_salaries_wages', monthlySalary);
                formData.append('other_comp', otherComp);
                formData.append('withholding_tax', withholdingTax);
                formData.append('sss', sss);
                formData.append('days_present', daysPresent);
                formData.append('working_days', workingDays);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Update row values with server-calculated values
                    if (grossAmountField) {
                        grossAmountField.value = parseFloat(result.gross_amount.replace(/[₱,]/g, ''));
                    }
                    if (totalDeductionField) {
                        totalDeductionField.value = parseFloat(result.total_deductions);
                    }
                    if (netAmountField) {
                        netAmountField.value = parseFloat(result.net_amount.replace(/[₱,]/g, ''));
                    }

                    return {
                        grossAmount: parseFloat(result.gross_amount.replace(/[₱,]/g, '')),
                        totalDeduction: parseFloat(result.total_deductions),
                        netAmount: parseFloat(result.net_amount.replace(/[₱,]/g, '')),
                        otherComp,
                        withholdingTax,
                        sss
                    };
                } else {
                    // Use client-side calculations as fallback
                    return {
                        grossAmount: grossAmount,
                        totalDeduction: totalDeduction,
                        netAmount: netAmount,
                        otherComp,
                        withholdingTax,
                        sss
                    };
                }
            } catch (error) {
                console.log('Using client-side calculation');
                // Use client-side calculations if server fails
                return {
                    grossAmount: grossAmount,
                    totalDeduction: totalDeduction,
                    netAmount: netAmount,
                    otherComp,
                    withholdingTax,
                    sss
                };
            }
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

            // Show loading indicator
            showNotification('Calculating all rows...', 'info');

            for (const row of rows) {
                const monthlySalaryInput = row.querySelector('.monthly-salaries-wages');
                const otherCompInput = row.querySelector('.other-comp');
                const daysPresent = parseFloat(row.querySelector('.hidden-days-present')?.value || 0);

                const monthlySalary = parseFloat(monthlySalaryInput ? monthlySalaryInput.value : 0) || 0;
                const otherComp = parseFloat(otherCompInput ? otherCompInput.value : 0) || 0;

                // Add to totals (prorated)
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

            // Update total displays
            document.getElementById('total-monthly-salaries-wages').textContent = '₱' + totalMonthlySalariesWages.toFixed(2);
            document.getElementById('total-other-comp').textContent = '₱' + totalOtherComp.toFixed(2);
            document.getElementById('total-gross-amount').textContent = '₱' + totalGross.toFixed(2);
            document.getElementById('total-withholding-tax').textContent = '₱' + totalWithholdingTax.toFixed(2);
            document.getElementById('total-sss').textContent = '₱' + totalSss.toFixed(2);
            document.getElementById('total-deduction').textContent = '₱' + totalDeduction.toFixed(2);
            document.getElementById('total-net-amount').textContent = '₱' + totalNetAmount.toFixed(2);

            // Update stat cards
            document.getElementById('total-deductions-display').textContent = '₱' + totalDeduction.toFixed(2);
            document.getElementById('net-amount-display').textContent = '₱' + totalNetAmount.toFixed(2);

            // Highlight rows by attendance
            highlightRowsByAttendance();

            showNotification('All calculations completed!', 'success');

            return {
                totalGross,
                totalDeduction,
                totalNetAmount
            };
        }

        // Calculate all button
        const calculateAllBtn = document.getElementById('calculate-all-btn');
        if (calculateAllBtn) {
            calculateAllBtn.addEventListener('click', function() {
                calculateAll();
            });
        }

        // Calculate single row
        window.calculateSingleRow = async function(button) {
            const row = button.closest('.payroll-row');
            await calculateRow(row);
            await calculateAll();
            showNotification('Row calculation updated!', 'success');
        };

        // View deductions
        window.viewDeductions = function(payrollId) {
            if (!payrollId) {
                showNotification('No payroll record found for this employee', 'error');
                return;
            }

            // Fetch deduction details via AJAX
            fetch(`get_deductions.php?payroll_id=${payrollId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<div class="space-y-3">';
                        data.deductions.forEach(ded => {
                            html += `
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <span class="font-medium">${ded.deduction_type}:</span>
                                    <span class="font-bold">₱${parseFloat(ded.deduction_amount).toFixed(2)}</span>
                                </div>
                            `;
                        });
                        html += '</div>';

                        document.getElementById('deductionsModalBody').innerHTML = html;
                        openModal('deductionsModal');
                    } else {
                        showNotification('Error loading deductions', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error connecting to server', 'error');
                });
        };

        // Search functionality
        const searchInput = document.getElementById('search-employees');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('.payroll-row');

                rows.forEach(row => {
                    const name = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                    const empId = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    if (name.includes(searchTerm) || empId.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        // Payroll period change
        document.getElementById('payroll-period').addEventListener('change', function() {
            const period = this.value;
            const cutoff = document.getElementById('hidden-payroll-cutoff').value;
            window.location.href = '?period=' + period + '&cutoff=' + cutoff;
        });

        // Form submission
        const payrollForm = document.getElementById('payroll-form');
        if (payrollForm) {
            payrollForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Validate only editable inputs
                let isValid = true;
                document.querySelectorAll('.payroll-input.editable:not(.readonly)').forEach(input => {
                    if (input.value === '' || isNaN(parseFloat(input.value)) || parseFloat(input.value) < 0) {
                        isValid = false;
                        input.classList.add('error');
                    } else {
                        input.classList.remove('error');
                    }
                });

                if (!isValid) {
                    showNotification('Please check all deduction inputs. They must be valid positive numbers.', 'error');
                    return;
                }

                // Calculate final totals
                await calculateAll();

                // Show confirmation
                if (confirm('Are you sure you want to save the payroll data for ' +
                        document.querySelector('.cutoff-btn.active').innerText.trim() + '?')) {

                    // Temporarily enable disabled fields to ensure their values are submitted
                    const disabledFields = this.querySelectorAll('input[disabled]');
                    disabledFields.forEach(field => {
                        field.disabled = false;
                    });

                    // Show loading
                    showNotification('Saving payroll data...', 'info');
                    this.submit();
                }
            });
        }

        // Modal functions
        window.showApproveModal = function() {
            openModal('approveModal');
        };

        window.showPaymentModal = function(payrollId) {
            document.getElementById('payment_payroll_id').value = payrollId;
            openModal('paymentModal');
        };

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        window.closeModal = function(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
        };

        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // Export functions
        window.exportData = function(format) {
            const period = document.getElementById('payroll-period').value;
            const cutoff = document.getElementById('hidden-payroll-cutoff').value;
            window.location.href = `export_payroll.php?period=${period}&cutoff=${cutoff}&format=${format}&type=contractual`;
        };

        // Generate obligation request
        window.generateObligationRequest = function() {
            const period = document.getElementById('payroll-period').value;
            const cutoff = document.getElementById('hidden-payroll-cutoff').value;
            window.location.href = `contractualobligationrequest.php?period=${period}&cutoff=${cutoff}`;
        };

        // Initialize charts
        function initializeCharts() {
            <?php if ($payroll_summary): ?>
                const ctx = document.getElementById('deductionsChart').getContext('2d');
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: ['Withholding Tax', 'SSS Contribution', 'Net Pay'],
                        datasets: [{
                            data: [
                                <?php echo $payroll_summary['total_withholding_tax'] ?? 0; ?>,
                                <?php echo $payroll_summary['total_sss'] ?? 0; ?>,
                                <?php echo $payroll_summary['total_net_amount'] ?? 0; ?>
                            ],
                            backgroundColor: [
                                '#ef4444',
                                '#3b82f6',
                                '#10b981'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            <?php endif; ?>
        }

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