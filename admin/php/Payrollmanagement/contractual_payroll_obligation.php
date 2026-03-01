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
$per_page = 10;
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
$is_full_month = ($selected_cutoff == 'full');

// Logout functionality
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('remember_user', '', time() - 3600, "/");
    header('Location: login.php');
    exit();
}

// Helper function to get salary column name
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

// Function to get employee's payroll data
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

// Fetch contractual employees
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
    $columns_query = $pdo->query("SHOW COLUMNS FROM contractofservice");
    $existing_columns = $columns_query->fetchAll(PDO::FETCH_COLUMN);

    $select_fields = "id as user_id, employee_id, CONCAT(first_name, ' ', last_name) as full_name, first_name, last_name, designation as position, office as department, period_from, period_to, status";

    if (in_array('address', $existing_columns)) {
        $select_fields .= ", address";
    } else {
        $select_fields .= ", 'Paluan Occ. Mdo.' as address";
    }

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

    foreach ($employees as &$employee) {
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

        $payroll_data = getEmployeePayrollData($pdo, $employee['user_id'], $selected_period, $selected_cutoff, $prorated_salary);
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
$period_display = date('F d, Y', strtotime($current_cutoff['start'])) . ' - ' . date('F d, Y', strtotime($current_cutoff['end']));

// OBLIGATION REQUEST SECTION - EXACT MATCH TO PROVIDED STRUCTURE
$start_month = date('F', strtotime($current_cutoff['start']));
$start_day = date('d', strtotime($current_cutoff['start']));
$end_day = date('d', strtotime($current_cutoff['end']));
$end_year = date('Y', strtotime($current_cutoff['end']));
$wages_period_display = "WAGES " . $start_month . " " . $start_day . " - " . $end_day . ", " . $end_year;

$ors_number = "CON-" . date('Ymd', strtotime($selected_period . '-01')) . "-" . strtoupper(substr($selected_cutoff, 0, 1));

// IMPORTANT FIX: Calculate page total instead of full total
$page_total_amount = 0;
foreach ($contractual_employees as $employee) {
    $page_total_amount += $employee['net_amount'];
}

// If page total is 0 but we have employees with data, use their net_amount
if ($page_total_amount == 0 && !empty($contractual_employees)) {
    // Check if any employee has non-zero values
    $has_values = false;
    foreach ($contractual_employees as $employee) {
        if ($employee['net_amount'] > 0 || $employee['gross_amount'] > 0 || $employee['monthly_salary'] > 0) {
            $has_values = true;
            break;
        }
    }

    // If no values found, keep as 0 (which matches the example where all are 0.00)
    // Otherwise use the calculated total
    if (!$has_values) {
        $page_total_amount = 0;
    }
}

// Get the first employee from the CURRENT PAGE (Row #1)
$first_employee_on_page = '';
$employee_office_on_page = '';

try {
    $sql = "
        SELECT 
            CONCAT(first_name, ' ', last_name) as full_name,
            COALESCE(office, department, '') as office_name,
            designation as position
        FROM contractofservice 
        WHERE status = 'active'
        ORDER BY last_name, first_name 
        LIMIT :offset, 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $first_employee_data = $stmt->fetch();

    if ($first_employee_data && !empty($first_employee_data['full_name'])) {
        $first_employee_on_page = $first_employee_data['full_name'];
        $employee_position = $first_employee_data['position'] ?? '';
        $employee_office_on_page = !empty($first_employee_data['office_name'])
            ? $first_employee_data['office_name']
            : ($employee_position ? "Office of the Nurse I" : 'Office of the Municipal Mayor');
    } else {
        $first_employee_on_page = 'No employees on this page';
        $employee_office_on_page = 'Office of the Municipal Mayor';
    }
} catch (Exception $e) {
    error_log("Error fetching first employee from current page: " . $e->getMessage());
    $first_employee_on_page = 'EMPLOYEE NAME';
    $employee_office_on_page = 'Office of the Municipal Mayor';
}

// Handle form submission for saving/updating obligation request
$save_message = '';
$save_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_obligation'])) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS obligation_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ors_serial VARCHAR(50) NOT NULL,
                ors_date DATE NOT NULL,
                fund_cluster VARCHAR(100) NOT NULL,
                payee VARCHAR(255) NOT NULL,
                office VARCHAR(255) NOT NULL,
                address TEXT,
                responsibility_center VARCHAR(100),
                particulars TEXT,
                mfo_pap VARCHAR(50),
                uacs_object_code VARCHAR(50),
                amount DECIMAL(15,2) DEFAULT 0,
                payroll_period VARCHAR(7) NOT NULL,
                payroll_cutoff VARCHAR(20) NOT NULL,
                page_number INT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_period (payroll_period, payroll_cutoff, page_number)
            )
        ");

        $ors_serial = $_POST['ors_serial'] ?? $ors_number;
        $ors_date = $_POST['ors_date'] ?? date('Y-m-d');
        $fund_cluster_input = $_POST['fund_cluster'] ?? $fund_cluster;
        $payee = $_POST['payee'] ?? $first_employee_on_page;
        $office = $_POST['office'] ?? $employee_office_on_page;
        $address = $_POST['address'] ?? $entity_info['address'];
        $responsibility_center = $_POST['responsibility_center'] ?? '';
        $particulars = $_POST['particulars'] ?? $wages_period_display;
        $mfo_pap = $_POST['mfo_pap'] ?? '';
        $uacs_object_code = $_POST['uacs_object_code'] ?? '';
        $amount = floatval($_POST['amount'] ?? $page_total_amount);

        $check_stmt = $pdo->prepare("
            SELECT id FROM obligation_requests 
            WHERE payroll_period = ? AND payroll_cutoff = ? AND page_number = ?
        ");
        $check_stmt->execute([$selected_period, $selected_cutoff, $page]);
        $existing = $check_stmt->fetch();

        if ($existing) {
            $update_stmt = $pdo->prepare("
                UPDATE obligation_requests SET
                    ors_serial = ?,
                    ors_date = ?,
                    fund_cluster = ?,
                    payee = ?,
                    office = ?,
                    address = ?,
                    responsibility_center = ?,
                    particulars = ?,
                    mfo_pap = ?,
                    uacs_object_code = ?,
                    amount = ?
                WHERE id = ?
            ");
            $update_stmt->execute([
                $ors_serial,
                $ors_date,
                $fund_cluster_input,
                $payee,
                $office,
                $address,
                $responsibility_center,
                $particulars,
                $mfo_pap,
                $uacs_object_code,
                $amount,
                $existing['id']
            ]);
            $save_message = "Obligation request updated successfully!";
        } else {
            $insert_stmt = $pdo->prepare("
                INSERT INTO obligation_requests (
                    ors_serial, ors_date, fund_cluster, payee, office, address,
                    responsibility_center, particulars, mfo_pap, uacs_object_code,
                    amount, payroll_period, payroll_cutoff, page_number
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_stmt->execute([
                $ors_serial,
                $ors_date,
                $fund_cluster_input,
                $payee,
                $office,
                $address,
                $responsibility_center,
                $particulars,
                $mfo_pap,
                $uacs_object_code,
                $amount,
                $selected_period,
                $selected_cutoff,
                $page
            ]);
            $save_message = "Obligation request saved successfully!";
        }
    } catch (Exception $e) {
        $save_error = "Error saving obligation request: " . $e->getMessage();
        error_log($save_error);
    }
}

// Load existing obligation request data for current page
$existing_obligation = null;
try {
    $load_stmt = $pdo->prepare("
        SELECT * FROM obligation_requests 
        WHERE payroll_period = ? AND payroll_cutoff = ? AND page_number = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $load_stmt->execute([$selected_period, $selected_cutoff, $page]);
    $existing_obligation = $load_stmt->fetch();
} catch (Exception $e) {
    error_log("Error loading obligation request: " . $e->getMessage());
}

// Set default values from existing data or use page total
$default_ors_serial = $existing_obligation['ors_serial'] ?? $ors_number;
$default_ors_date = $existing_obligation['ors_date'] ?? date('Y-m-d');
$default_fund_cluster = $existing_obligation['fund_cluster'] ?? $fund_cluster;
$default_payee = $existing_obligation['payee'] ?? strtoupper($first_employee_on_page);
$default_office = $existing_obligation['office'] ?? $employee_office_on_page;
$default_address = $existing_obligation['address'] ?? $entity_info['address'];
$default_responsibility_center = $existing_obligation['responsibility_center'] ?? '';
$default_particulars = $existing_obligation['particulars'] ?? $wages_period_display;
$default_mfo_pap = $existing_obligation['mfo_pap'] ?? '';
$default_uacs_object_code = $existing_obligation['uacs_object_code'] ?? '';
$default_amount = $existing_obligation['amount'] ?? $page_total_amount;

// Create save_personnel_config.php handler
if (!file_exists('save_personnel_config.php')) {
    $save_config_content = '<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    echo json_encode(["success" => false, "error" => "Not authenticated"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["success" => false, "error" => "Invalid data"]);
    exit();
}

$config_file = __DIR__ . "/config/payroll_personnel.json";
$config_dir = dirname($config_file);

if (!is_dir($config_dir)) {
    mkdir($config_dir, 0755, true);
}

try {
    file_put_contents($config_file, json_encode($data, JSON_PRETTY_PRINT));
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
';
    file_put_contents('save_personnel_config.php', $save_config_content);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contractual Payroll & Obligation Request</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
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

        /* Scrollbar Styling - ADDED FROM contractualpayrolltable1.php */
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

        /* NAVBAR STYLES */
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

        /* Payroll Table Styles - Optimized for 100% Print Fit */
        .payroll-section {
            width: 100%;
            max-width: 100%;
            margin: 0 auto 30px auto;
            background: white;
            padding: 15px;
            border: 1px solid #ddd;
            font-family: 'Inter', sans-serif;
        }

        .payroll-header {
            position: relative;
            margin-bottom: 15px;
        }

        .appendix {
            position: absolute;
            right: 0;
            top: 0;
            font-size: 10px;
            font-weight: 500;
        }

        .payroll-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .period-container {
            display: flex;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 11px;
        }

        .period-label {
            white-space: nowrap;
        }

        .period-value {
            position: relative;
            margin-left: 5px;
            text-transform: uppercase;
        }

        .period-value:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            border-bottom: 1px solid #000;
        }

        .entity-info {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 10px;
        }

        .entity-left {
            flex: 1;
        }

        .entity-row {
            display: flex;
            align-items: center;
            margin-bottom: 3px;
        }

        .entity-label {
            font-weight: bold;
            margin-right: 8px;
            white-space: nowrap;
        }

        .entity-value {
            position: relative;
            min-width: 180px;
            padding-bottom: 2px;
        }

        .entity-value:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            border-bottom: 1px solid #000;
        }

        .entity-right {
            text-align: right;
        }

        .payroll-no-row,
        .sheet-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 3px;
        }

        .payroll-no-label,
        .sheet-label {
            font-weight: bold;
            margin-right: 8px;
        }

        .payroll-no-value {
            position: relative;
            width: 100px;
            text-align: left;
            padding-bottom: 2px;
        }

        .payroll-no-value:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            border-bottom: 1px solid #000;
        }

        .sheet-value {
            display: flex;
            align-items: center;
        }

        .sheet-number {
            position: relative;
            width: 40px;
            text-align: center;
            padding-bottom: 2px;
        }

        .sheet-number:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            border-bottom: 1px solid #000;
        }

        .sheet-text {
            margin: 0 3px;
        }

        .acknowledgment {
            font-size: 11px;
            font-style: italic;
            margin-bottom: 10px;
        }

        .payroll-table-container {
            width: 100%;
            overflow-x: visible;
            margin-bottom: 15px;
        }

        .payroll-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            table-layout: fixed;
        }

        /* Define exact column widths for 100% fit on landscape */
        .payroll-table th:nth-child(1) {
            width: 3%;
        }

        /* # */
        .payroll-table th:nth-child(2) {
            width: 12%;
        }

        /* Name */
        .payroll-table th:nth-child(3) {
            width: 10%;
        }

        /* Position */
        .payroll-table th:nth-child(4) {
            width: 10%;
        }

        /* Address */
        .payroll-table th:nth-child(5) {
            width: 6%;
        }

        /* Monthly Salary */
        .payroll-table th:nth-child(6) {
            width: 5%;
        }

        /* Other Comp */
        .payroll-table th:nth-child(7) {
            width: 6%;
        }

        /* Gross Amount */
        .payroll-table th:nth-child(8) {
            width: 5%;
        }

        /* Withholding Tax */
        .payroll-table th:nth-child(9) {
            width: 5%;
        }

        /* SSS */
        .payroll-table th:nth-child(10) {
            width: 5%;
        }

        /* Total Deductions */
        .payroll-table th:nth-child(11) {
            width: 6%;
        }

        /* Community Tax Number */
        .payroll-table th:nth-child(12) {
            width: 6%;
        }

        /* Community Tax Date */
        .payroll-table th:nth-child(13) {
            width: 6%;
        }

        /* Net Amount Due */
        .payroll-table th:nth-child(14) {
            width: 15%;
        }

        /* Signature */

        .payroll-table th,
        .payroll-table td {
            border: 1px solid #000;
            padding: 3px 2px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow: hidden;
        }

        .payroll-table thead th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            font-size: 8px;
            line-height: 1.2;
        }

        .payroll-table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }

        .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .certification-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            font-size: 9px;
            margin-top: 15px;
        }

        .certification-box {
            border: 1px solid #ccc;
            padding: 6px;
            min-height: 90px;
        }

        .certification-box p {
            margin-bottom: 3px;
        }

        .officer-signature {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 8px;
        }

        .officer-info {
            text-align: center;
        }

        .officer-name {
            font-weight: bold;
            font-size: 9px;
        }

        .officer-title {
            font-style: italic;
            font-size: 8px;
        }

        .date-field {
            display: flex;
            align-items: center;
        }

        .date-label {
            margin-right: 3px;
            font-size: 8px;
        }

        .date-line {
            border-bottom: 1px solid #000;
            width: 50px;
            height: 1px;
        }

        .amount-field {
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .amount-line {
            border-bottom: 1px solid #000;
            min-width: 50px;
            text-align: right;
            margin-left: 3px;
            padding-bottom: 1px;
        }

        /* OBLIGATION REQUEST STYLES - EXACT MATCH TO PROVIDED STRUCTURE */
        .obligation-container {
            max-width: 900px;
            margin: 40px auto;
            background-color: white;
            font-size: 12px;
            border: 2px solid #000;
            font-family: 'Inter', sans-serif;
        }

        .obligation-header {
            display: flex;
            border-bottom: 2px solid #000;
        }

        .obligation-title {
            width: 600px;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            padding: 8px 0;
        }

        .obligation-title p {
            margin: 0;
            line-height: 1.4;
        }

        .obligation-meta {
            width: 300px;
            border-left: 2px solid #000;
            font-weight: 600;
            font-size: 11px;
            padding: 8px;
        }

        .obligation-meta div {
            margin-bottom: 2px;
            white-space: nowrap;
        }

        .obligation-meta span {
            border-bottom: 1px solid #000;
            display: inline-block;
            margin-left: 5px;
            min-width: 160px;
        }

        .obligation-table {
            width: 100%;
            border-collapse: collapse;
        }

        .obligation-table th,
        .obligation-table td {
            border: 2px solid #000;
            border-top: 0;
            padding: 8px 10px;
            line-height: 1.3;
            height: 40px;
            vertical-align: middle;
        }

        .obligation-table .header-cell {
            text-align: center;
            vertical-align: middle;
            font-weight: bold;
            background-color: #f5f5f5;
            width: 13.5%;
        }

        .obligation-table input {
            width: 100%;
            border: none;
            background: transparent;
            padding: 2px;
        }

        .obligation-table input:focus {
            outline: none;
            background-color: #f0f9ff;
        }

        .amount-column {
            height: 300px;
            position: relative;
        }

        .amount-input-container {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .amount-input-container input {
            text-align: right;
            font-weight: bold;
        }

        .amount-total {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
        }

        .certification-row {
            display: flex;
            border-top: 0;
            border-left: 2px solid #000;
            border-right: 2px solid #000;
            border-bottom: 2px solid #000;
        }

        .certification-box-ob {
            width: 50%;
            padding: 10px;
            font-size: 12px;
        }

        .certification-box-ob:first-child {
            border-right: 2px solid #000;
        }

        .certification-box-ob p {
            margin-bottom: 16px;
            font-weight: 500;
        }

        .certification-box-ob .signature-line {
            display: flex;
            flex-direction: column;
            font-weight: 600;
        }

        .certification-box-ob .signature-row {
            margin-bottom: -3px;
        }

        .certification-box-ob .signature-row span {
            border-bottom: 1px solid #000;
            display: inline-block;
            width: 360px;
        }

        .certification-box-ob .printed-name {
            text-transform: uppercase;
            font-weight: bold;
        }

        .certification-box-ob .date-row {
            margin-top: 12px;
            font-weight: 600;
        }

        .certification-box-ob .date-row span {
            border-bottom: 1px solid #000;
            display: inline-block;
            width: 350px;
            margin-left: 40px;
        }

        .status-section {
            border: 1px solid #000;
            border-top: 0;
        }

        .status-title {
            font-weight: bold;
            padding: 4px 8px;
            border-bottom: 2px solid #000;
        }

        .status-table {
            width: 100%;
            border-collapse: collapse;
        }

        .status-table th,
        .status-table td {
            border: 1px solid #000;
            border-top: 0;
            padding: 8px 4px;
            text-align: center;
            font-size: 12px;
        }

        .status-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .status-table td {
            height: 32px;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }

        .period-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: #dbeafe;
            color: #1e40af;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .breadcrumb-container {
            margin-top: 1rem;
            margin-bottom: 1.5rem;
        }

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

            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .action-buttons button,
            .action-buttons a {
                width: 100%;
            }

            .obligation-header {
                flex-direction: column;
            }

            .obligation-title,
            .obligation-meta {
                width: 100%;
            }

            .obligation-meta {
                border-left: 0;
                border-top: 2px solid #000;
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

        .message-container {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 0.5rem;
        }

        .message-success {
            background-color: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .message-error {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
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

        /* Print Styles - Optimized for 100% fit */
        @media print {
            .print-hide {
                display: none !important;
            }

            /* Payroll Print - 100% Fit with exact column widths */
            #payroll-section {
                page: landscape !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0.15in !important;
                border: 1px solid #000 !important;
                box-sizing: border-box !important;
            }

            .payroll-table {
                width: 100% !important;
                font-size: 7.5px !important;
                table-layout: fixed !important;
                border-collapse: collapse !important;
            }

            .payroll-table th,
            .payroll-table td {
                border: 0.5px solid #000 !important;
                padding: 2px 1px !important;
                font-size: 7.5px !important;
                word-wrap: break-word !important;
                overflow: hidden !important;
                line-height: 1.2 !important;
            }

            .payroll-table thead th {
                background-color: #f0f0f0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                font-size: 7px !important;
                padding: 2px 1px !important;
            }

            /* Keep exact column widths */
            .payroll-table th:nth-child(1) {
                width: 3% !important;
            }

            .payroll-table th:nth-child(2) {
                width: 12% !important;
            }

            .payroll-table th:nth-child(3) {
                width: 10% !important;
            }

            .payroll-table th:nth-child(4) {
                width: 10% !important;
            }

            .payroll-table th:nth-child(5) {
                width: 6% !important;
            }

            .payroll-table th:nth-child(6) {
                width: 5% !important;
            }

            .payroll-table th:nth-child(7) {
                width: 6% !important;
            }

            .payroll-table th:nth-child(8) {
                width: 5% !important;
            }

            .payroll-table th:nth-child(9) {
                width: 5% !important;
            }

            .payroll-table th:nth-child(10) {
                width: 5% !important;
            }

            .payroll-table th:nth-child(11) {
                width: 6% !important;
            }

            .payroll-table th:nth-child(12) {
                width: 6% !important;
            }

            .payroll-table th:nth-child(13) {
                width: 6% !important;
            }

            .payroll-table th:nth-child(14) {
                width: 15% !important;
            }

            .certification-grid {
                font-size: 8px !important;
                gap: 5px !important;
                margin-top: 10px !important;
            }

            .certification-box {
                border: 0.5px solid #000 !important;
                padding: 4px !important;
                min-height: auto !important;
            }

            .officer-name,
            .officer-title {
                font-size: 8px !important;
            }

            .date-line,
            .amount-line {
                border-bottom: 0.5px solid #000 !important;
                width: 40px !important;
            }

            /* Obligation Print - 100% Fit */
            #obligation-section {
                page: portrait !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                page-break-before: always !important;
            }

            .obligation-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0.2in !important;
                border: 2px solid #000 !important;
                box-shadow: none !important;
                box-sizing: border-box !important;
            }

            .obligation-table th,
            .obligation-table td {
                border: 2px solid #000 !important;
                padding: 6px 8px !important;
                font-size: 11px !important;
            }

            .obligation-table input {
                font-size: 11px !important;
                padding: 2px !important;
            }

            .certification-box-ob {
                border: 2px solid #000 !important;
                font-size: 11px !important;
                padding: 8px !important;
            }

            .status-table th,
            .status-table td {
                border: 2px solid #000 !important;
                font-size: 11px !important;
                padding: 4px !important;
            }

            /* Ensure all elements fit within page */
            * {
                box-sizing: border-box !important;
                max-width: 100% !important;
            }
        }

        #editPersonnelModal {
            z-index: 99999;
        }

        /* Mobile hide class */
        .mobile-hide {
            display: table-cell;
        }

        @media (max-width: 640px) {
            .mobile-hide {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Navigation Header -->
    <nav class="navbar print-hide">
        <div class="navbar-container">
            <div class="navbar-left">
                <button class="mobile-toggle" id="mobile-menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>

                <a href="../dashboard.php" class="navbar-brand hidden lg:flex">
                    <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
                    <div class="brand-text">
                        <span class="brand-title">HR Management System</span>
                        <span class="brand-subtitle">Paluan Occidental Mindoro</span>
                    </div>
                </a>

                <div class="mobile-brand lg:hidden">
                    <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
                    <div class="mobile-brand-text">
                        <span class="mobile-brand-title">HRMS</span>
                        <span class="mobile-brand-subtitle">Dashboard</span>
                    </div>
                </div>
            </div>

            <div class="navbar-right">
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
                                <i class="fas fa-user-circle"></i> My Profile
                            </a>
                            <a href="../settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <hr class="my-2 border-gray-200">
                            <a href="?logout=true" class="dropdown-item text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt"></i> Logout
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
                        <a href="../Payrollmanagement/contractualpayrolltable1.php" class="submenu-item active">
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
                            <span class="ml-1 text-sm font-medium text-primary-600 md:ml-2">Payroll & Obligation Request</span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-4 print-hide">
            <div class="flex items-center gap-3">
                <div class="bg-primary-100 p-2 rounded-lg">
                    <i class="fas fa-file-invoice text-primary-600 text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Contractual Payroll & Obligation Request</h1>
                    <p class="text-xs text-gray-500">Generate and manage payroll with obligation request</p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <div class="flex flex-col sm:flex-row bg-white border border-gray-200 rounded-lg overflow-hidden divide-y sm:divide-y-0 sm:divide-x divide-gray-200">
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

                    <div class="flex divide-x divide-gray-200">
                        <a href="?period=<?php echo $selected_period; ?>&cutoff=full&page=1" class="px-3 py-2 text-xs font-medium <?php echo ($selected_cutoff == 'full') ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-gray-50'; ?>">Full</a>
                        <a href="?period=<?php echo $selected_period; ?>&cutoff=first_half&page=1" class="px-3 py-2 text-xs font-medium <?php echo ($selected_cutoff == 'first_half') ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-gray-50'; ?>">1st Half</a>
                        <a href="?period=<?php echo $selected_period; ?>&cutoff=second_half&page=1" class="px-3 py-2 text-xs font-medium <?php echo ($selected_cutoff == 'second_half') ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-gray-50'; ?>">2nd Half</a>
                    </div>

                    <div class="hidden sm:flex items-center px-3 bg-gray-50">
                        <span class="text-xs text-gray-600 whitespace-nowrap">
                            <i class="fas fa-calendar text-gray-400 mr-1"></i>
                            <?php echo date('M d', strtotime($current_cutoff['start'])); ?> - <?php echo date('d', strtotime($current_cutoff['end'])); ?> (<?php echo $current_cutoff['working_days']; ?>d)
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-blue-50 border-l-4 border-blue-400 p-3 mb-4 flex flex-wrap items-center justify-between print-hide">
            <div class="flex items-center gap-3">
                <span class="period-badge"><i class="fas fa-calendar-alt mr-1"></i> <?php echo date('F Y', strtotime($selected_period . '-01')); ?></span>
                <span class="period-badge"><i class="fas fa-cut mr-1"></i> <?php echo $current_cutoff['label']; ?></span>
                <span class="text-sm text-gray-600"><i class="fas fa-calendar-week mr-1"></i> <?php echo date('M d', strtotime($current_cutoff['start'])); ?> - <?php echo date('M d, Y', strtotime($current_cutoff['end'])); ?> (<?php echo $current_cutoff['working_days']; ?> working days)</span>
            </div>
            <div class="text-sm text-gray-600"><i class="fas fa-users mr-1"></i> Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> | Total: <?php echo $total_employees; ?> employees</div>
        </div>

        <?php if ($save_message): ?>
            <div class="message-container message-success print-hide"><i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($save_message); ?></div>
        <?php endif; ?>
        <?php if ($save_error): ?>
            <div class="message-container message-error print-hide"><i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($save_error); ?></div>
        <?php endif; ?>

        <!-- PAYROLL SECTION -->
        <div id="payroll-section" class="payroll-section">
            <div class="appendix">Appendix 33</div>
            <div class="payroll-title">PAYROLL</div>

            <div class="period-container">
                <span class="period-label">For the period:</span>
                <div class="period-value"><?php echo $period_display; ?></div>
            </div>

            <div class="entity-info">
                <div class="entity-left">
                    <div class="entity-row">
                        <span class="entity-label">Entity Name:</span>
                        <div class="entity-value"><?php echo $entity_name; ?></div>
                    </div>
                    <div class="entity-row">
                        <span class="entity-label">Fund/Cluster:</span>
                        <div class="entity-value"><?php echo $fund_cluster; ?></div>
                    </div>
                </div>
                <div class="entity-right">
                    <div class="payroll-no-row">
                        <span class="payroll-no-label">Payroll No. :</span>
                        <div class="payroll-no-value"><?php echo $payroll_number; ?></div>
                    </div>
                    <div class="sheet-row">
                        <span class="sheet-label">Sheet:</span>
                        <div class="sheet-value">
                            <div class="sheet-number"><?php echo $page; ?></div>
                            <span class="sheet-text">of</span>
                            <div class="sheet-number"><?php echo $total_pages; ?></div>
                            <span class="sheet-text">Sheets</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="acknowledgment">
                We acknowledge receipt of cash shown opposite our names as full compensation for services rendered for the period covered.
            </div>

            <div class="payroll-table-container">
                <table class="payroll-table">
                    <thead>
                        <tr>
                            <th rowspan="2">#</th>
                            <th rowspan="2">Name</th>
                            <th rowspan="2">Position</th>
                            <th rowspan="2">Address</th>
                            <th colspan="3">Compensation</th>
                            <th colspan="3">Deductions</th>
                            <th colspan="2">Community Tax Certificate</th>
                            <th rowspan="2">Net Amount Due</th>
                            <th rowspan="2">Signature</th>
                        </tr>
                        <tr>
                            <th>Monthly Salaries and Wages</th>
                            <th>Other Compensation</th>
                            <th>Gross Amount Earned</th>
                            <th>Withholding Tax</th>
                            <th>SSS Contribution</th>
                            <th>Total Deductions</th>
                            <th>Number</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contractual_employees)): ?>
                            <tr>
                                <td colspan="14" class="text-center py-4 text-gray-500">No employees found for this period.</td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = $offset + 1; ?>
                            <?php foreach ($contractual_employees as $employee): ?>
                                <tr>
                                    <td class="text-center"><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($employee['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['address'] ?? 'Paluan Occ. Mdo.'); ?></td>
                                    <td class="text-right"><?php echo number_format($employee['prorated_salary'], 2); ?></td>
                                    <td class="text-right"><?php echo $employee['other_comp'] > 0 ? number_format($employee['other_comp'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo number_format($employee['gross_amount'], 2); ?></td>
                                    <td class="text-right"><?php echo $employee['withholding_tax'] > 0 ? number_format($employee['withholding_tax'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo $employee['sss'] > 0 ? number_format($employee['sss'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo number_format($employee['total_deductions'], 2); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($employee['community_tax_number']); ?></td>
                                    <td class="text-center"><?php echo $employee['community_tax_date'] ? date('m/d/Y', strtotime($employee['community_tax_date'])) : ''; ?></td>
                                    <td class="text-right font-bold"><?php echo number_format($employee['net_amount'], 2); ?></td>
                                    <td></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <tr class="total-row">
                            <td colspan="4" class="text-right">TOTAL AMOUNT</td>
                            <td class="text-right"><?php echo number_format($totals['monthly_salaries'], 2); ?></td>
                            <td class="text-right"><?php echo number_format($totals['other_comp'], 2); ?></td>
                            <td class="text-right"><?php echo number_format($totals['gross_amount'], 2); ?></td>
                            <td class="text-right"><?php echo number_format($totals['withholding_tax'], 2); ?></td>
                            <td class="text-right"><?php echo number_format($totals['sss'], 2); ?></td>
                            <td class="text-right"><?php echo number_format($totals['total_deductions'], 2); ?></td>
                            <td></td>
                            <td></td>
                            <td class="text-right"><?php echo number_format($totals['net_amount'], 2); ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination print-hide mt-4">
                    <button class="pagination-btn" onclick="goToPage(1)" <?php echo $page <= 1 ? 'disabled' : ''; ?>><i class="fas fa-angle-double-left"></i></button>
                    <button class="pagination-btn" onclick="goToPage(<?php echo $page - 1; ?>)" <?php echo $page <= 1 ? 'disabled' : ''; ?>><i class="fas fa-angle-left"></i></button>
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <button class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>" onclick="goToPage(<?php echo $i; ?>)"><?php echo $i; ?></button>
                    <?php endfor; ?>
                    <button class="pagination-btn" onclick="goToPage(<?php echo $page + 1; ?>)" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>><i class="fas fa-angle-right"></i></button>
                    <button class="pagination-btn" onclick="goToPage(<?php echo $total_pages; ?>)" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>><i class="fas fa-angle-double-right"></i></button>
                </div>
            <?php endif; ?>

            <div class="certification-grid">
                <div class="certification-box">
                    <p class="font-bold">A. CERTIFIED: Services duly rendered as stated.</p>
                    <div class="officer-signature">
                        <div class="officer-info">
                            <div class="officer-name"><?php echo htmlspecialchars($officer_A['name']); ?></div>
                            <div class="officer-title"><?php echo htmlspecialchars($officer_A['title']); ?></div>
                        </div>
                        <div class="date-field">
                            <span class="date-label">Date</span>
                            <div class="date-line"></div>
                        </div>
                    </div>
                </div>

                <div class="certification-box">
                    <div class="flex justify-between">
                        <p class="font-bold">C. CERTIFIED: Cash available in the amount of</p>
                        <div class="amount-field">
                            <span>₱</span>
                            <div class="amount-line"><?php echo number_format($totals['net_amount'], 2); ?></div>
                        </div>
                    </div>
                    <div class="officer-signature" style="margin-top: 15px;">
                        <div></div>
                        <div class="officer-info">
                            <div class="officer-name"><?php echo htmlspecialchars($officer_C['name']); ?></div>
                            <div class="officer-title"><?php echo htmlspecialchars($officer_C['title']); ?></div>
                        </div>
                        <div class="date-field">
                            <span class="date-label">Date</span>
                            <div class="date-line"></div>
                        </div>
                    </div>
                </div>

                <div class="certification-box">
                    <p class="font-bold">F. CERTIFIED: Each employee whose name appears on the payroll has been paid the amount as indicated opposite his/her name.</p>
                    <div class="officer-info" style="margin-top: 10px;">
                        <div class="officer-name"><?php echo htmlspecialchars($officer_F['name']); ?></div>
                        <div class="officer-title"><?php echo htmlspecialchars($officer_F['title']); ?></div>
                    </div>
                </div>

                <div class="certification-box">
                    <p class="font-bold">B. CERTIFIED: Supporting documents complete and proper.</p>
                    <div class="officer-signature">
                        <div class="officer-info">
                            <div class="officer-name"><?php echo htmlspecialchars($officer_B['name']); ?></div>
                            <div class="officer-title"><?php echo htmlspecialchars($officer_B['title']); ?></div>
                        </div>
                        <div class="date-field">
                            <span class="date-label">Date</span>
                            <div class="date-line"></div>
                        </div>
                    </div>
                </div>

                <div class="certification-box">
                    <p class="font-bold">D. APPROVED: For payment</p>
                    <div class="officer-signature">
                        <div class="officer-info">
                            <div class="officer-name"><?php echo htmlspecialchars($officer_D['name']); ?></div>
                            <div class="officer-title"><?php echo htmlspecialchars($officer_D['title']); ?></div>
                        </div>
                        <div class="date-field">
                            <span class="date-label">Date</span>
                            <div class="date-line"></div>
                        </div>
                    </div>
                </div>

                <div class="certification-box">
                    <p class="font-bold">E.</p>
                    <div class="mt-1">
                        <div class="flex justify-between text-xs"><span>ORS/BURS No. :</span><span class="border-b border-black w-3/5"></span></div>
                        <div class="flex justify-between text-xs"><span>Date</span><span class="border-b border-black w-3/5"></span></div>
                        <div class="flex justify-between text-xs"><span>Jev No. :</span><span class="border-b border-black w-3/5"></span></div>
                        <div class="flex justify-between text-xs"><span>Date</span><span class="border-b border-black w-3/5"></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- OBLIGATION REQUEST SECTION - EXACT MATCH TO PROVIDED STRUCTURE -->
        <div id="obligation-section" class="obligation-container">
            <form method="POST" action="" id="obligation-form">
                <input type="hidden" name="save_obligation" value="1">

                <!-- HEADER -->
                <div class="obligation-header">
                    <div class="obligation-title">
                        <p>OBLIGATION REQUEST AND STATUS</p>
                        <p>LOCAL GOVERNMENT UNIT OF PALUAN</p>
                    </div>
                    <div class="obligation-meta">
                        <div>Serial No.: <span><?php echo htmlspecialchars($default_ors_serial); ?></span></div>
                        <div>Date: <span><?php echo date('Y-m-d', strtotime($default_ors_date)); ?></span></div>
                        <div>Fund Cluster: <span><?php echo htmlspecialchars($default_fund_cluster); ?></span></div>
                    </div>
                </div>

                <!-- PAYEE INFORMATION TABLE -->
                <table class="obligation-table">
                    <thead>
                        <tr>
                            <td class="header-cell">Payee</td>
                            <td colspan="4" class="font-bold uppercase">
                                <input type="text" name="payee" value="<?php echo htmlspecialchars($default_payee); ?>" class="w-full font-bold uppercase bg-transparent border-0" id="payee-field">
                            </td>
                        </tr>
                        <tr>
                            <td class="header-cell">Office</td>
                            <td colspan="4">
                                <input type="text" name="office" value="<?php echo htmlspecialchars($default_office); ?>" class="w-full bg-transparent border-0" id="office-field">
                            </td>
                        </tr>
                        <tr>
                            <td class="header-cell">Address</td>
                            <td colspan="4">
                                <input type="text" name="address" value="<?php echo htmlspecialchars($default_address); ?>" class="w-full bg-transparent border-0">
                            </td>
                        </tr>
                        <tr class="border-t-2 border-black">
                            <td class="header-cell">Responsibility Center</td>
                            <td class="text-center" style="width:30%">Particulars</td>
                            <td class="text-center" style="width:7%">MFO/PAP</td>
                            <td class="text-center" style="width:10%">UACS Object Code</td>
                            <td class="text-center" style="width:20%">Amount</td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-center">
                                <input type="text" name="responsibility_center" value="<?php echo htmlspecialchars($default_responsibility_center); ?>" class="w-full text-center bg-transparent border-0">
                            </td>
                            <td class="font-bold text-center">
                                <div class="amount-column">
                                    <div class="amount-input-container">
                                        <div>
                                            <input type="text" name="particulars" value="<?php echo htmlspecialchars($default_particulars); ?>" class="w-full text-center font-bold bg-transparent border-0">
                                        </div>
                                        <div class="amount-total">
                                            <span>Total</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <input type="text" name="mfo_pap" value="<?php echo htmlspecialchars($default_mfo_pap); ?>" class="w-full text-center bg-transparent border-0">
                            </td>
                            <td class="text-center">
                                <input type="text" name="uacs_object_code" value="<?php echo htmlspecialchars($default_uacs_object_code); ?>" class="w-full text-center bg-transparent border-0">
                            </td>
                            <td class="text-right font-bold">
                                <div class="amount-column">
                                    <div class="amount-input-container">
                                        <div>
                                            <input type="number" step="0.01" name="amount" value="<?php echo htmlspecialchars($default_amount); ?>" class="w-full text-right font-bold bg-transparent border-0" id="amount-input">
                                        </div>
                                        <div class="amount-total">
                                            <span>₱</span>
                                            <span id="total-display"><?php echo number_format($default_amount, 2); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- CERTIFICATION SECTION A & B -->
                <div class="flex flex-row w-full border-t-0 border border-black">
                    <!-- Set A  -->
                    <div class="certification-box w-[740px]">
                        <p class="mb-4 font-medium"><span class="font-bold">A. Certified:</span> Charges to appropriation/allotment are necessary, lawful and under my direct supervision; and supporting documents valid, proper and legal</p>

                        <div class="flex flex-col font-semibold w-full">
                            <div class="w-full mb-[-3px]">Signature<span class="ml-[10px] mr-1">:</span><span class="border-b border-black w-[360px] inline-block"></span></div>
                            <div class="w-full mb-[-3px]">Printed Name: <span class="uppercase font-bold ml-[120px]"><?php echo htmlspecialchars($officer_A['name']); ?></span></div>
                            <div class="w-full">Position <span class="ml-[27px]">:</span><span class="font-bold ml-[80px]"><?php echo htmlspecialchars($officer_A['title']); ?></span></div>
                        </div>
                        <div class="mt-3 text-center">Head, Requesting Office/Authorized Representative</div>
                        <div class="w-full font-semibold mt-2 mb-2">Date<span class="ml-[40px] mr-3">:</span><span class="border-b border-black w-[350px] inline-block"></span></div>
                    </div>
                    <!-- Set B  -->
                    <div class="certification-box w-[685px]">
                        <p class="mb-4 font-medium"><span class="font-bold">B. Certified:</span> Allotment available and obligated for the purpose/adjustment necessary as indicated above</p>

                        <div class="flex flex-col font-semibold w-full">
                            <div class="w-full mb-[-3px]">Signature<span class="ml-[10px] mr-1">:</span><span class="border-b border-black w-[330px] inline-block"></span></div>
                            <div class="w-full mb-[-3px]">Printed Name: <span class="uppercase font-bold ml-[70px]"><?php echo htmlspecialchars($officer_B['name']); ?></span></div>
                            <div class="w-full">Position <span class="ml-[27px]">:</span><span class="font-bold ml-[100px]"><?php echo htmlspecialchars($officer_B['title']); ?></span></div>
                        </div>
                        <div class="w-full font-semibold mt-9 mb-2">Date<span class="ml-[40px] mr-3">:</span><span class="border-b border-black w-[316px] inline-block"></span></div>
                    </div>
                </div>

                <!-- STATUS OF OBLIGATION SECTION -->
                <div class="status-section">
                    <div class="status-title">C. STATUS OF OBLIGATION</div>
                    <table class="status-table">
                        <thead>
                            <tr>
                                <th colspan="3">Reference</th>
                                <th colspan="5">Amount</th>
                            </tr>
                            <tr>
                                <th rowspan="2">Date</th>
                                <th rowspan="2">Particulars</th>
                                <th rowspan="2">ORS/JEV/Check/ADA/TRA No.</th>
                                <th colspan="3">Amount</th>
                                <th colspan="2">Balance</th>
                            </tr>
                            <tr>
                                <th>Obligation<br>(a)</th>
                                <th>Payable<br>(b)</th>
                                <th>Payment<br>(c)</th>
                                <th>Not Yet Due<br>(a-b)</th>
                                <th>Due and Demandable<br>(b-c)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>

        <div class="action-buttons print-hide">
            <button onclick="printPayrollOnly()" class="text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5">
                <i class="fas fa-print mr-2"></i> Print Payroll
            </button>
            <button onclick="printObligationOnly()" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5">
                <i class="fas fa-print mr-2"></i> Print Obligation
            </button>
            <!-- <button type="submit" form="obligation-form" class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5">
                <i class="fas fa-save mr-2"></i> Save Obligation Data
            </button> -->
        </div>

        <!-- <div class="flex justify-center mb-4 print-hide">
            <button onclick="syncPayeeWithFirstEmployee()" class="text-sm text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-4 py-2 rounded-lg transition-colors duration-200">
                <i class="fas fa-sync-alt mr-2"></i> Sync Payee with First Employee on Current Page
            </button>
        </div> -->

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
                    <button onclick="toggleEditModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times text-xl"></i></button>
                </div>

                <form id="personnelEditForm" onsubmit="savePersonnelChanges(event)">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="border p-3 rounded">
                            <h4 class="font-bold mb-2 text-blue-800">Officer A (HRMO)</h4>
                            <div class="mb-2"><label class="block text-sm font-medium text-gray-700">Name</label><input type="text" name="officer_A_name" value="<?php echo htmlspecialchars($officer_A['name']); ?>" class="w-full border rounded p-2 text-sm" required></div>
                            <div class="mb-2"><label class="block text-sm font-medium text-gray-700">Title</label><input type="text" name="officer_A_title" value="<?php echo htmlspecialchars($officer_A['title']); ?>" class="w-full border rounded p-2 text-sm" required></div>
                        </div>
                        <div class="border p-3 rounded">
                            <h4 class="font-bold mb-2 text-blue-800">Officer B (Accountant)</h4>
                            <div class="mb-2"><label class="block text-sm font-medium text-gray-700">Name</label><input type="text" name="officer_B_name" value="<?php echo htmlspecialchars($officer_B['name']); ?>" class="w-full border rounded p-2 text-sm" required></div>
                            <div class="mb-2"><label class="block text-sm font-medium text-gray-700">Title</label><input type="text" name="officer_B_title" value="<?php echo htmlspecialchars($officer_B['title']); ?>" class="w-full border rounded p-2 text-sm" required></div>
                        </div>
                        <div class="border p-3 rounded">
                            <h4 class="font-bold mb-2 text-blue-800">Officer C (Treasurer)</h4>
                            <div class="mb-2"><label class="block text-sm font-medium text-gray-700">Name</label><input type="text" name="officer_C_name" value="<?php echo htmlspecialchars($officer_C['name']); ?>" class="w-full border rounded p-2 text-sm" required></div>
                            <div class="mb-2"><label class="block text-sm font-medium text-gray-700">Title</label><input type="text" name="officer_C_title" value="<?php echo htmlspecialchars($officer_C['title']); ?>" class="w-full border rounded p-2 text-sm" required></div>
                        </div>
                        <div class="border p-3 rounded">
                            <h4 class="font-bold mb-2 text-blue-800">Officer D (Mayor)</h4>
                            <div class="mb-2"><label class="block text-sm font-medium text-gray-700">Name</label><input type="text" name="officer_D_name" value="<?php echo htmlspecialchars($officer_D['name']); ?>" class="w-full border rounded p-2 text-sm" required></div>
                            <div class="mb-2"><label class="block text-sm font-medium text-gray-700">Title</label><input type="text" name="officer_D_title" value="<?php echo htmlspecialchars($officer_D['title']); ?>" class="w-full border rounded p-2 text-sm" required></div>
                        </div>
                        <div class="border p-3 rounded col-span-2">
                            <h4 class="font-bold mb-2 text-blue-800">Officer F (Disbursing)</h4>
                            <div class="grid grid-cols-2 gap-2">
                                <div><label class="block text-sm font-medium text-gray-700">Name</label><input type="text" name="officer_F_name" value="<?php echo htmlspecialchars($officer_F['name']); ?>" class="w-full border rounded p-2 text-sm" required></div>
                                <div><label class="block text-sm font-medium text-gray-700">Title</label><input type="text" name="officer_F_title" value="<?php echo htmlspecialchars($officer_F['title']); ?>" class="w-full border rounded p-2 text-sm" required></div>
                            </div>
                        </div>
                        <div class="border p-3 rounded col-span-2">
                            <h4 class="font-bold mb-2 text-green-800">Entity Information</h4>
                            <div class="grid grid-cols-2 gap-2">
                                <div><label class="block text-sm font-medium text-gray-700">Entity Name</label><input type="text" name="entity_name" value="<?php echo htmlspecialchars($entity_name); ?>" class="w-full border rounded p-2 text-sm" required></div>
                                <div><label class="block text-sm font-medium text-gray-700">Fund Cluster</label><input type="text" name="fund_cluster" value="<?php echo htmlspecialchars($fund_cluster); ?>" class="w-full border rounded p-2 text-sm" required></div>
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

                if (dateElement) dateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
                if (timeElement) timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
            }

            updateDateTime();
            setInterval(updateDateTime, 1000);

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

            // Sidebar functionality
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

            // Payroll dropdown functionality
            const payrollToggle = document.getElementById('payroll-toggle');
            const payrollDropdown = document.getElementById('payroll-submenu');

            if (payrollToggle && payrollDropdown) {
                payrollDropdown.classList.add('open');
                const chevron = payrollToggle.querySelector('.chevron');
                if (chevron) chevron.classList.add('rotated');

                payrollToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    payrollDropdown.classList.toggle('open');
                    const chevron = this.querySelector('.chevron');
                    if (chevron) chevron.classList.toggle('rotated');
                });
            }

            const amountInput = document.getElementById('amount-input');
            const totalDisplay = document.getElementById('total-display');

            if (amountInput && totalDisplay) {
                amountInput.addEventListener('input', function() {
                    const value = parseFloat(this.value) || 0;
                    totalDisplay.textContent = value.toFixed(2);
                });
            }

            setTimeout(syncPayeeWithFirstEmployee, 100);
        });

        function goToPage(page) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            window.location.href = url.toString();
        }

        function syncPayeeWithFirstEmployee() {
            const firstRowName = document.querySelector('.payroll-table tbody tr:first-child td:nth-child(2)');
            const firstRowPosition = document.querySelector('.payroll-table tbody tr:first-child td:nth-child(3)');

            if (firstRowName) {
                const employeeName = firstRowName.textContent.trim();
                const payeeInput = document.getElementById('payee-field');
                const officeInput = document.getElementById('office-field');

                if (payeeInput && employeeName) {
                    payeeInput.value = employeeName.toUpperCase();
                    if (firstRowPosition && officeInput) {
                        const position = firstRowPosition.textContent.trim();
                        if (position) officeInput.value = `Office of the ${position}`;
                    }
                    firstRowName.style.backgroundColor = '#fef9c3';
                    setTimeout(() => firstRowName.style.backgroundColor = '', 500);
                    return true;
                }
            }
            return false;
        }

        function printPayrollOnly() {
            const printContainer = document.createElement('div');
            printContainer.id = 'print-payroll-container';
            printContainer.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:99999;background:white;padding:0;margin:0;overflow:visible;';

            const payrollSection = document.getElementById('payroll-section');
            if (!payrollSection) {
                alert('Could not find payroll section');
                return;
            }

            const payrollClone = payrollSection.cloneNode(true);
            payrollClone.querySelectorAll('.print-hide, .fixed, .pagination, button, a[href*="logout"]').forEach(el => el.remove());

            // Ensure 100% fit for printing
            payrollClone.style.cssText = 'max-width:100%;margin:0;padding:0.15in;background:white;border:1px solid #000;box-sizing:border-box;';

            printContainer.appendChild(payrollClone);
            document.body.appendChild(printContainer);

            const allElements = document.body.children;
            for (let element of allElements) {
                if (element.id !== 'print-payroll-container') {
                    element.style.visibility = 'hidden';
                    element.style.display = 'none';
                }
            }

            const style = document.createElement('style');
            style.innerHTML = '@page { size: landscape; margin: 0.15in; } @media print { body { background:white; } }';
            document.head.appendChild(style);

            setTimeout(() => {
                window.print();
                setTimeout(() => {
                    document.body.removeChild(printContainer);
                    style.remove();
                    for (let element of allElements) {
                        element.style.visibility = '';
                        element.style.display = '';
                    }
                }, 500);
            }, 100);
        }

        function printObligationOnly() {
            const printContainer = document.createElement('div');
            printContainer.id = 'print-obligation-container';
            printContainer.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:99999;background:white;padding:0;margin:0;overflow:visible;';

            const obligationSection = document.getElementById('obligation-section');
            if (!obligationSection) {
                alert('Could not find obligation section');
                return;
            }

            const obligationClone = obligationSection.cloneNode(true);
            obligationClone.querySelectorAll('.print-hide, .fixed, button:not([type="submit"]), a[href*="logout"]').forEach(el => el.remove());

            // Ensure 100% fit for printing
            obligationClone.style.cssText = 'max-width:100%;margin:0;padding:0.2in;background:white;border:2px solid #000;box-sizing:border-box;';

            printContainer.appendChild(obligationClone);
            document.body.appendChild(printContainer);

            const allElements = document.body.children;
            for (let element of allElements) {
                if (element.id !== 'print-obligation-container') {
                    element.style.visibility = 'hidden';
                    element.style.display = 'none';
                }
            }

            const style = document.createElement('style');
            style.innerHTML = '@page { size: portrait; margin: 0.2in; } @media print { body { background:white; } }';
            document.head.appendChild(style);

            setTimeout(() => {
                window.print();
                setTimeout(() => {
                    document.body.removeChild(printContainer);
                    style.remove();
                    for (let element of allElements) {
                        element.style.visibility = '';
                        element.style.display = '';
                    }
                }, 500);
            }, 100);
        }

        function toggleEditModal() {
            const modal = document.getElementById('editPersonnelModal');
            modal.classList.toggle('hidden');
            modal.classList.toggle('flex');
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

            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Saving...';
            submitBtn.disabled = true;

            fetch('save_personnel_config.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
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

        window.onclick = function(event) {
            const modal = document.getElementById('editPersonnelModal');
            if (event.target == modal) toggleEditModal();
        }
    </script>
</body>

</html>