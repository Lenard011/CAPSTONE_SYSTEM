<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

// ===============================================
// ENHANCED LOGOUT FUNCTIONALITY - From contractofservice.php
// ===============================================
if (isset($_GET['logout'])) {
    // Optional: Log the logout activity if you have an activity manager
    // This would require the ActivityManager class to be available

    // Clear session data
    $_SESSION = array();

    // Destroy session cookie if using cookies
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy session
    session_destroy();

    // Clear remember me cookie
    setcookie('remember_user', '', time() - 3600, "/", "", true, true);

    // Redirect to login page
    header('Location: ../login.php');
    exit();
}

// Database configuration
$host = 'localhost';
$dbname = 'u420482914_hrms_paluan';
$username = 'u420482914_paluan_hrms';
$password = 'Hrms_Paluan01';

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
    $config_file = __DIR__ . '/config/payroll_personnel_permanent.json';
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

// Function to get employee's payroll data from permanent tables
function getEmployeePayrollData($pdo, $employee_id, $period, $cutoff, $prorated_salary = 0)
{
    $data = [
        'other_comp' => 0,
        'withholding_tax' => 0,
        'pagibig_loan_mpl' => 0,
        'corso_loan' => 0,
        'policy_loan' => 0,
        'philhealth_ps' => 0,
        'uef_retirement' => 0,
        'emergency_loan' => 0,
        'gfal' => 0,
        'lbp_loan' => 0,
        'mpl' => 0,
        'mpl_lite' => 0,
        'sss_contribution' => 0,
        'pagibig_cont' => 0,
        'state_ins_gs' => 0,
        'total_deductions' => 0,
        'gross_amount' => $prorated_salary,
        'amount_due' => $prorated_salary,
        'net_amount' => $prorated_salary,
        'amount_accrued' => $prorated_salary,
        'days_present' => 0,
        'signature_status' => '',
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
                    COALESCE(SUM(pagibig_loan_mpl), 0) as pagibig_loan_mpl,
                    COALESCE(SUM(corso_loan), 0) as corso_loan,
                    COALESCE(SUM(policy_loan), 0) as policy_loan,
                    COALESCE(SUM(philhealth_ps), 0) as philhealth_ps,
                    COALESCE(SUM(uef_retirement), 0) as uef_retirement,
                    COALESCE(SUM(emergency_loan), 0) as emergency_loan,
                    COALESCE(SUM(gfal), 0) as gfal,
                    COALESCE(SUM(lbp_loan), 0) as lbp_loan,
                    COALESCE(SUM(mpl), 0) as mpl,
                    COALESCE(SUM(mpl_lite), 0) as mpl_lite,
                    COALESCE(SUM(sss_contribution), 0) as sss_contribution,
                    COALESCE(SUM(pagibig_cont), 0) as pagibig_cont,
                    COALESCE(SUM(state_ins_gs), 0) as state_ins_gs,
                    COALESCE(SUM(total_deductions), 0) as total_deductions,
                    COALESCE(SUM(gross_amount), 0) as gross_amount,
                    COALESCE(SUM(amount_due), 0) as amount_due,
                    COALESCE(SUM(net_amount), 0) as net_amount,
                    COALESCE(SUM(amount_accrued), 0) as amount_accrued,
                    COALESCE(SUM(days_present), 0) as days_present,
                    COUNT(DISTINCT id) as record_count
                FROM payroll_history_permanent 
                WHERE employee_id = ? 
                    AND payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half')
            ");
            $stmt->execute([$employee_id, $period]);
            $result = $stmt->fetch();

            if ($result && $result['record_count'] > 0) {
                $data['other_comp'] = floatval($result['other_comp']);
                $data['withholding_tax'] = floatval($result['withholding_tax']);
                $data['pagibig_loan_mpl'] = floatval($result['pagibig_loan_mpl']);
                $data['corso_loan'] = floatval($result['corso_loan']);
                $data['policy_loan'] = floatval($result['policy_loan']);
                $data['philhealth_ps'] = floatval($result['philhealth_ps']);
                $data['uef_retirement'] = floatval($result['uef_retirement']);
                $data['emergency_loan'] = floatval($result['emergency_loan']);
                $data['gfal'] = floatval($result['gfal']);
                $data['lbp_loan'] = floatval($result['lbp_loan']);
                $data['mpl'] = floatval($result['mpl']);
                $data['mpl_lite'] = floatval($result['mpl_lite']);
                $data['sss_contribution'] = floatval($result['sss_contribution']);
                $data['pagibig_cont'] = floatval($result['pagibig_cont']);
                $data['state_ins_gs'] = floatval($result['state_ins_gs']);
                $data['total_deductions'] = floatval($result['total_deductions']);
                $data['gross_amount'] = floatval($result['gross_amount']);
                $data['amount_due'] = floatval($result['amount_due']);
                $data['net_amount'] = floatval($result['net_amount']);
                $data['amount_accrued'] = floatval($result['amount_accrued']);
                $data['days_present'] = floatval($result['days_present']);
                $data['exists'] = true;
            }
        } else {
            $stmt = $pdo->prepare("
                SELECT id, other_comp, withholding_tax, pagibig_loan_mpl, corso_loan, policy_loan,
                       philhealth_ps, uef_retirement, emergency_loan, gfal, lbp_loan, mpl,
                       mpl_lite, sss_contribution, pagibig_cont, state_ins_gs,
                       total_deductions, gross_amount, amount_due, net_amount, amount_accrued, days_present,
                       signature_status, status
                FROM payroll_history_permanent 
                WHERE employee_id = ? 
                    AND payroll_period = ? AND payroll_cutoff = ?
                LIMIT 1
            ");
            $stmt->execute([$employee_id, $period, $cutoff]);
            $result = $stmt->fetch();

            if ($result) {
                $data['other_comp'] = floatval($result['other_comp'] ?? 0);
                $data['withholding_tax'] = floatval($result['withholding_tax'] ?? 0);
                $data['pagibig_loan_mpl'] = floatval($result['pagibig_loan_mpl'] ?? 0);
                $data['corso_loan'] = floatval($result['corso_loan'] ?? 0);
                $data['policy_loan'] = floatval($result['policy_loan'] ?? 0);
                $data['philhealth_ps'] = floatval($result['philhealth_ps'] ?? 0);
                $data['uef_retirement'] = floatval($result['uef_retirement'] ?? 0);
                $data['emergency_loan'] = floatval($result['emergency_loan'] ?? 0);
                $data['gfal'] = floatval($result['gfal'] ?? 0);
                $data['lbp_loan'] = floatval($result['lbp_loan'] ?? 0);
                $data['mpl'] = floatval($result['mpl'] ?? 0);
                $data['mpl_lite'] = floatval($result['mpl_lite'] ?? 0);
                $data['sss_contribution'] = floatval($result['sss_contribution'] ?? 0);
                $data['pagibig_cont'] = floatval($result['pagibig_cont'] ?? 0);
                $data['state_ins_gs'] = floatval($result['state_ins_gs'] ?? 0);
                $data['total_deductions'] = floatval($result['total_deductions'] ?? 0);
                $data['gross_amount'] = floatval($result['gross_amount'] ?? 0);
                $data['amount_due'] = floatval($result['amount_due'] ?? 0);
                $data['net_amount'] = floatval($result['net_amount'] ?? 0);
                $data['amount_accrued'] = floatval($result['amount_accrued'] ?? 0);
                $data['days_present'] = floatval($result['days_present'] ?? 0);
                $data['signature_status'] = $result['signature_status'] ?? '';
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
function getCommunityTaxCertificate($pdo, $employee_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT ctc_number, ctc_date 
            FROM permanent 
            WHERE employee_id = ? 
            ORDER BY ctc_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$employee_id]);
        $result = $stmt->fetch();

        if ($result) {
            return [
                'number' => $result['ctc_number'] ?? '',
                'date' => $result['ctc_date'] ?? ''
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
    $count_sql = "SELECT COUNT(*) FROM permanent WHERE (status = 'Active' OR status IS NULL)";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute();
    $total_employees = $count_stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Error counting employees: " . $e->getMessage());
    $total_employees = 0;
}

$total_pages = max(1, ceil($total_employees / $per_page));

// Fetch permanent employees
$permanent_employees = [];
$totals = [
    'monthly_salary' => 0,
    'amount_accrued' => 0,
    'other_comp' => 0,
    'gross_amount' => 0,
    'withholding_tax' => 0,
    'pagibig_loan_mpl' => 0,
    'corso_loan' => 0,
    'policy_loan' => 0,
    'philhealth_ps' => 0,
    'uef_retirement' => 0,
    'emergency_loan' => 0,
    'gfal' => 0,
    'lbp_loan' => 0,
    'mpl' => 0,
    'mpl_lite' => 0,
    'sss_contribution' => 0,
    'pagibig_cont' => 0,
    'state_ins_gs' => 0,
    'total_deductions' => 0,
    'amount_due' => 0
];

try {
    $sql = "
        SELECT 
            id as user_id, 
            employee_id, 
            CONCAT(
                COALESCE(first_name, ''), 
                ' ', 
                COALESCE(middle, ''), 
                ' ', 
                COALESCE(last_name, '')
            ) as full_name,
            position, 
            office as department,
            monthly_salary,
            ctc_number,
            ctc_date
        FROM permanent 
        WHERE (status = 'Active' OR status IS NULL)
        ORDER BY full_name 
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

        // Attendance fetching logic would go here
        // This is a placeholder - implement based on your attendance system
        $attendance_days = $current_cutoff['working_days']; // Default to full working days

        $employee['days_present'] = $attendance_days;
        $employee['total_hours'] = $total_hours;

        $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
        $working_days_in_month = 22; // Standard working days
        $prorated_salary = ($monthly_salary / $working_days_in_month) * $attendance_days;
        $amount_accrued = $prorated_salary;

        $payroll_data = getEmployeePayrollData($pdo, $employee['employee_id'], $selected_period, $selected_cutoff, $prorated_salary);
        $cedula = getCommunityTaxCertificate($pdo, $employee['employee_id']);

        // Calculate total deductions
        $total_deductions =
            ($payroll_data['withholding_tax'] ?? 0) +
            ($payroll_data['pagibig_loan_mpl'] ?? 0) +
            ($payroll_data['corso_loan'] ?? 0) +
            ($payroll_data['policy_loan'] ?? 0) +
            ($payroll_data['philhealth_ps'] ?? 0) +
            ($payroll_data['uef_retirement'] ?? 0) +
            ($payroll_data['emergency_loan'] ?? 0) +
            ($payroll_data['gfal'] ?? 0) +
            ($payroll_data['lbp_loan'] ?? 0) +
            ($payroll_data['mpl'] ?? 0) +
            ($payroll_data['mpl_lite'] ?? 0) +
            ($payroll_data['sss_contribution'] ?? 0) +
            ($payroll_data['pagibig_cont'] ?? 0) +
            ($payroll_data['state_ins_gs'] ?? 0);

        // Assign all payroll data to employee
        $employee['other_comp'] = $payroll_data['other_comp'] ?? 0;
        $employee['withholding_tax'] = $payroll_data['withholding_tax'] ?? 0;
        $employee['pagibig_loan_mpl'] = $payroll_data['pagibig_loan_mpl'] ?? 0;
        $employee['corso_loan'] = $payroll_data['corso_loan'] ?? 0;
        $employee['policy_loan'] = $payroll_data['policy_loan'] ?? 0;
        $employee['philhealth_ps'] = $payroll_data['philhealth_ps'] ?? 0;
        $employee['uef_retirement'] = $payroll_data['uef_retirement'] ?? 0;
        $employee['emergency_loan'] = $payroll_data['emergency_loan'] ?? 0;
        $employee['gfal'] = $payroll_data['gfal'] ?? 0;
        $employee['lbp_loan'] = $payroll_data['lbp_loan'] ?? 0;
        $employee['mpl'] = $payroll_data['mpl'] ?? 0;
        $employee['mpl_lite'] = $payroll_data['mpl_lite'] ?? 0;
        $employee['sss_contribution'] = $payroll_data['sss_contribution'] ?? 0;
        $employee['pagibig_cont'] = $payroll_data['pagibig_cont'] ?? 0;
        $employee['state_ins_gs'] = $payroll_data['state_ins_gs'] ?? 0;
        $employee['total_deductions'] = $total_deductions;

        // Calculate amount due
        $employee['amount_due'] = ($payroll_data['amount_due'] ?? 0) > 0 ? $payroll_data['amount_due'] : ($amount_accrued + ($payroll_data['other_comp'] ?? 0) - $total_deductions);
        $employee['amount_accrued'] = ($payroll_data['amount_accrued'] ?? 0) > 0 ? $payroll_data['amount_accrued'] : $amount_accrued;
        $employee['gross_amount'] = ($payroll_data['gross_amount'] ?? 0) > 0 ? $payroll_data['gross_amount'] : $amount_accrued + ($payroll_data['other_comp'] ?? 0);
        $employee['monthly_salary'] = $monthly_salary;
        $employee['prorated_salary'] = $prorated_salary;
        $employee['payroll_status'] = $payroll_data['status'] ?? 'draft';
        $employee['payroll_id'] = $payroll_data['payroll_id'] ?? null;
        $employee['payroll_exists'] = $payroll_data['exists'] ?? false;
        $employee['signature_status'] = $payroll_data['signature_status'] ?? '';
        $employee['community_tax_number'] = $cedula['number'] ?? '';
        $employee['community_tax_date'] = $cedula['date'] ?? '';

        // Add to totals
        $totals['monthly_salary'] += $monthly_salary;
        $totals['amount_accrued'] += $employee['amount_accrued'];
        $totals['other_comp'] += $payroll_data['other_comp'] ?? 0;
        $totals['gross_amount'] += $employee['gross_amount'];
        $totals['withholding_tax'] += $payroll_data['withholding_tax'] ?? 0;
        $totals['pagibig_loan_mpl'] += $payroll_data['pagibig_loan_mpl'] ?? 0;
        $totals['corso_loan'] += $payroll_data['corso_loan'] ?? 0;
        $totals['policy_loan'] += $payroll_data['policy_loan'] ?? 0;
        $totals['philhealth_ps'] += $payroll_data['philhealth_ps'] ?? 0;
        $totals['uef_retirement'] += $payroll_data['uef_retirement'] ?? 0;
        $totals['emergency_loan'] += $payroll_data['emergency_loan'] ?? 0;
        $totals['gfal'] += $payroll_data['gfal'] ?? 0;
        $totals['lbp_loan'] += $payroll_data['lbp_loan'] ?? 0;
        $totals['mpl'] += $payroll_data['mpl'] ?? 0;
        $totals['mpl_lite'] += $payroll_data['mpl_lite'] ?? 0;
        $totals['sss_contribution'] += $payroll_data['sss_contribution'] ?? 0;
        $totals['pagibig_cont'] += $payroll_data['pagibig_cont'] ?? 0;
        $totals['state_ins_gs'] += $payroll_data['state_ins_gs'] ?? 0;
        $totals['total_deductions'] += $total_deductions;
        $totals['amount_due'] += $employee['amount_due'];
    }

    $permanent_employees = $employees;
} catch (Exception $e) {
    error_log("Error fetching employees: " . $e->getMessage());
    $permanent_employees = [];
}

// Get entity information
$payroll_number = "PERM-" . date('Ymd', strtotime($selected_period . '-01')) . "-" . strtoupper(substr($selected_cutoff, 0, 1));
$period_display = date('F d, Y', strtotime($current_cutoff['start'])) . ' - ' . date('F d, Y', strtotime($current_cutoff['end']));

// OBLIGATION REQUEST SECTION
$start_month = date('F', strtotime($current_cutoff['start']));
$start_day = date('d', strtotime($current_cutoff['start']));
$end_day = date('d', strtotime($current_cutoff['end']));
$end_year = date('Y', strtotime($current_cutoff['end']));
$wages_period_display = "WAGES " . $start_month . " " . $start_day . " - " . $end_day . ", " . $end_year;

$ors_number = "PERM-" . date('Ymd', strtotime($selected_period . '-01')) . "-" . strtoupper(substr($selected_cutoff, 0, 1));

// Calculate page total amount
$page_total_amount = $totals['amount_due'];

// Get the first employee from the CURRENT PAGE
$first_employee_on_page = '';
$employee_office_on_page = '';

try {
    $sql = "
        SELECT 
            CONCAT(
                COALESCE(first_name, ''), 
                ' ', 
                COALESCE(middle, ''), 
                ' ', 
                COALESCE(last_name, '')
            ) as full_name,
            office as office_name,
            position
        FROM permanent 
        WHERE (status = 'Active' OR status IS NULL)
        ORDER BY full_name 
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
            : ($employee_position ? "Office of the " . $employee_position : 'Office of the Municipal Mayor');
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
            CREATE TABLE IF NOT EXISTS obligation_requests_permanent (
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
            SELECT id FROM obligation_requests_permanent 
            WHERE payroll_period = ? AND payroll_cutoff = ? AND page_number = ?
        ");
        $check_stmt->execute([$selected_period, $selected_cutoff, $page]);
        $existing = $check_stmt->fetch();

        if ($existing) {
            $update_stmt = $pdo->prepare("
                UPDATE obligation_requests_permanent SET
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
                INSERT INTO obligation_requests_permanent (
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
        SELECT * FROM obligation_requests_permanent 
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

// Create save_personnel_config.php handler for permanent
if (!file_exists('save_personnel_config_permanent.php')) {
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

$config_file = __DIR__ . "/config/payroll_personnel_permanent.json";
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
    file_put_contents('save_personnel_config_permanent.php', $save_config_content);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permanent Payroll & Obligation Request</title>
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

        /* Navbar Styling - UPDATED to match other payroll pages */
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

        /* Logout Button - New */
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

        /* Sidebar Styles - UPDATED to match other payroll pages */
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

        /* ========== EXISTING STYLES BELOW - UNCHANGED ========== */
        /* Payroll Table Styles */
        .payroll-section {
            width: 100%;
            max-width: 100%;
            margin: 0 auto 30px auto;
            background: white;
            padding: 20px 15px;
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
            overflow-x: auto;
            margin-bottom: 15px;
        }

        .payroll-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            min-width: 2200px;
        }

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

        /* OBLIGATION REQUEST STYLES */
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

            .sidebar-container {
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

        /* PRINT STYLES - ONLY AFFECTS PRINT PREVIEW */
        @media print {

            /* Hide all non-payroll elements when printing */
            .print-hide {
                display: none !important;
            }

            body {
                background: white;
                margin: 0;
                padding: 0;
                width: 100%;
            }

            /* Payroll Section Print Styles - Long Bond Paper Landscape */
            #payroll-section {
                page-break-after: always;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0.2in !important;
                border: 1px solid #000 !important;
                box-sizing: border-box !important;
                background: white !important;
                font-family: 'Inter', sans-serif !important;
            }

            /* Long Bond Paper dimensions: 8.5 x 13 inches landscape */
            @page {
                size: 8.5in 13in landscape;
                margin: 0.2in;
            }

            .payroll-table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 8px !important;
                page-break-inside: auto !important;
            }

            .payroll-table th,
            .payroll-table td {
                border: 0.5px solid #000 !important;
                padding: 3px 2px !important;
                font-size: 7.5px !important;
                word-wrap: break-word !important;
                overflow: hidden !important;
                line-height: 1.2 !important;
            }

            .payroll-table thead th {
                background-color: #f0f0f0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                font-weight: bold !important;
            }

            /* Prevent row splitting across pages */
            .payroll-table tbody tr {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }

            .payroll-table thead {
                display: table-header-group !important;
            }

            .payroll-table tfoot {
                display: table-footer-group !important;
            }

            /* Certification grid styling for print - only on first page */
            .certification-grid {
                font-size: 8px !important;
                gap: 5px !important;
                margin-top: 10px !important;
                page-break-inside: avoid !important;
                display: grid !important;
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

            /* Page indicator */
            .page-indicator {
                text-align: right !important;
                font-size: 8px !important;
                margin-top: 5px !important;
                font-style: italic !important;
            }

            /* Obligation Print - Portrait */
            #obligation-section {
                page: portrait;
                page-break-before: always !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0.2in !important;
                border: 2px solid #000 !important;
                box-sizing: border-box !important;
                background: white !important;
            }

            .obligation-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
            }

            .obligation-table th,
            .obligation-table td {
                border: 2px solid #000 !important;
                padding: 4px 6px !important;
                font-size: 10px !important;
            }

            .certification-box-ob {
                border: 2px solid #000 !important;
                font-size: 10px !important;
                padding: 6px !important;
            }

            .status-table th,
            .status-table td {
                border: 2px solid #000 !important;
                font-size: 10px !important;
                padding: 3px !important;
            }

            * {
                box-sizing: border-box !important;
                max-width: 100% !important;
            }

            .payroll-table-container {
                overflow: visible !important;
                width: 100% !important;
            }
        }

        #editPersonnelModal {
            z-index: 99999;
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

<body class="bg-gray-50">
    <!-- Navigation Header - UPDATED to match other payroll pages -->
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
                        <span class="mobile-brand-subtitle">Permanent</span>
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
            </div>
        </div>
    </nav>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar - UPDATED to match other payroll pages with Permanent active -->
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
                    <a href="contractualpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Contractual
                    </a>
                    <a href="joboerderpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Job Order
                    </a>
                    <a href="permanentpayrolltable1.php" class="sidebar-dropdown-item active">
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

    <main class="main-content">
        <div class="breadcrumb-container print-hide">
            <nav class="mt-4 flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-2">
                    <li class="inline-flex items-center">
                        <a href="permanentpayrolltable1.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-primary-600 md:ml-2">
                            <i class="fas fa-home mr-2"></i> Permanent Payroll
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
                    <h1 class="text-xl font-bold text-gray-900">Permanent Payroll & Obligation Request</h1>
                    <p class="text-xs text-gray-500">Generate and manage permanent employee payroll with obligation request</p>
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

        <div class="bg-primary-50 border-l-4 border-primary-400 p-3 mb-4 flex flex-wrap items-center justify-between print-hide">
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
            <div class="appendix">Appendix 33-B</div>
            <div class="payroll-title">PAYROLL (PERMANENT)</div>

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
                <table class="payroll-table" id="print-payroll-table">
                    <thead>
                        <tr>
                            <th rowspan="2">#</th>
                            <th rowspan="2">Employee ID</th>
                            <th rowspan="2">Name</th>
                            <th rowspan="2">Position</th>
                            <th rowspan="2">Department</th>
                            <th rowspan="2">Days Present</th>
                            <th rowspan="2">Monthly Salary</th>
                            <th rowspan="2">Amount Accrued</th>
                            <th colspan="14" class="text-center">DEDUCTIONS</th>
                            <th colspan="3" class="text-center">ADDITIONAL</th>
                            <th rowspan="2">Signature</th>
                        </tr>
                        <tr>
                            <th>Withholding Tax</th>
                            <th>PAG-IBIG LOAN - MPL</th>
                            <th>Corso Loan</th>
                            <th>Policy Loan</th>
                            <th>PhilHealth P.S.</th>
                            <th>UEF/Retirement</th>
                            <th>Emergency Loan</th>
                            <th>GFAL</th>
                            <th>LBP Loan</th>
                            <th>MPL</th>
                            <th>MPL Lite</th>
                            <th>SSS Contribution</th>
                            <th>PAG-IBIG CONT.</th>
                            <th>STATE INS. G.S.</th>
                            <th>Total Deductions</th>
                            <th>Amount Due</th>
                            <th>No.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($permanent_employees)): ?>
                            <tr>
                                <td colspan="29" class="text-center py-8 text-gray-500">
                                    No permanent employees found for this period.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = $offset + 1; ?>
                            <?php foreach ($permanent_employees as $employee): ?>
                                <?php
                                // Calculate total deductions
                                $total_deductions =
                                    ($employee['withholding_tax'] ?? 0) +
                                    ($employee['pagibig_loan_mpl'] ?? 0) +
                                    ($employee['corso_loan'] ?? 0) +
                                    ($employee['policy_loan'] ?? 0) +
                                    ($employee['philhealth_ps'] ?? 0) +
                                    ($employee['uef_retirement'] ?? 0) +
                                    ($employee['emergency_loan'] ?? 0) +
                                    ($employee['gfal'] ?? 0) +
                                    ($employee['lbp_loan'] ?? 0) +
                                    ($employee['mpl'] ?? 0) +
                                    ($employee['mpl_lite'] ?? 0) +
                                    ($employee['sss_contribution'] ?? 0) +
                                    ($employee['pagibig_cont'] ?? 0) +
                                    ($employee['state_ins_gs'] ?? 0);

                                $amount_due = ($employee['amount_due'] ?? 0) > 0 ? $employee['amount_due'] : (($employee['amount_accrued'] ?? 0) + ($employee['other_comp'] ?? 0) - $total_deductions);
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $counter; ?></td>
                                    <td class="font-medium"><?php echo htmlspecialchars($employee['employee_id'] ?? ''); ?></td>
                                    <td class="font-medium"><?php echo htmlspecialchars($employee['full_name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($employee['position'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($employee['department'] ?? ''); ?></td>
                                    <td class="text-center"><?php echo number_format($employee['days_present'] ?? 0, 1); ?></td>
                                    <td class="text-right"><?php echo number_format($employee['monthly_salary'] ?? 0, 2); ?></td>
                                    <td class="text-right"><?php echo number_format($employee['amount_accrued'] ?? 0, 2); ?></td>
                                    <td class="text-right"><?php echo ($employee['withholding_tax'] ?? 0) > 0 ? number_format($employee['withholding_tax'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo ($employee['pagibig_loan_mpl'] ?? 0) > 0 ? number_format($employee['pagibig_loan_mpl'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo ($employee['corso_loan'] ?? 0) > 0 ? number_format($employee['corso_loan'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo ($employee['policy_loan'] ?? 0) > 0 ? number_format($employee['policy_loan'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo ($employee['philhealth_ps'] ?? 0) > 0 ? number_format($employee['philhealth_ps'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo ($employee['uef_retirement'] ?? 0) > 0 ? number_format($employee['uef_retirement'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo ($employee['emergency_loan'] ?? 0) > 0 ? number_format($employee['emergency_loan'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo ($employee['gfal'] ?? 0) > 0 ? number_format($employee['gfal'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo ($employee['lbp_loan'] ?? 0) > 0 ? number_format($employee['lbp_loan'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo ($employee['mpl'] ?? 0) > 0 ? number_format($employee['mpl'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo ($employee['mpl_lite'] ?? 0) > 0 ? number_format($employee['mpl_lite'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo ($employee['sss_contribution'] ?? 0) > 0 ? number_format($employee['sss_contribution'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo ($employee['pagibig_cont'] ?? 0) > 0 ? number_format($employee['pagibig_cont'], 2) : ''; ?></td>
                                    <td class="text-right"><?php echo ($employee['state_ins_gs'] ?? 0) > 0 ? number_format($employee['state_ins_gs'], 2) : ''; ?></td>
                                    <td class="text-right font-bold"><?php echo number_format($total_deductions, 2); ?></td>
                                    <td class="text-right font-bold"><?php echo number_format($amount_due, 2); ?></td>
                                    <td class="text-center"><?php echo $counter; ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($employee['signature_status'] ?? ''); ?></td>
                                </tr>
                                <?php $counter++; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Totals Row -->
                        <tr class="total-row">
                            <td colspan="7" class="text-right font-bold">TOTAL AMOUNT</td>
                            <td class="text-right font-bold"><?php echo number_format($totals['amount_accrued'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['withholding_tax'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['pagibig_loan_mpl'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['corso_loan'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['policy_loan'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['philhealth_ps'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['uef_retirement'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['emergency_loan'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['gfal'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['lbp_loan'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['mpl'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['mpl_lite'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['sss_contribution'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['pagibig_cont'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['state_ins_gs'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['total_deductions'] ?? 0, 2); ?></td>
                            <td class="text-right font-bold"><?php echo number_format($totals['amount_due'] ?? 0, 2); ?></td>
                            <td colspan="2"></td>
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
                            <div class="amount-line"><?php echo number_format($totals['amount_due'] ?? 0, 2); ?></div>
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

        <!-- OBLIGATION REQUEST SECTION -->
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
                    <!-- Set A -->
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
                    <!-- Set B -->
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
            <button onclick="document.getElementById('obligation-form').submit()" class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5">
                <i class="fas fa-save mr-2"></i> Save Obligation
            </button>
        </div>

        <div class="fixed bottom-4 right-4 z-50 print-hide">
            <button onclick="toggleEditModal()" class="bg-blue-600 text-white p-3 rounded-full shadow-lg hover:bg-blue-700 flex items-center justify-center" style="width: 50px; height: 50px;">
                <i class="fas fa-edit text-xl"></i>
            </button>
        </div>

        <!-- Edit Personnel Modal -->
        <div id="editPersonnelModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center" style="z-index: 99999;">
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold">Edit Certifying Officers (Permanent)</h3>
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
            // ============================================
            // SIDEBAR FUNCTIONALITY - UPDATED to match other payroll pages
            // ============================================
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
                    document.body.style.overflow = sidebarContainer.classList.contains('active') ? 'hidden' : '';
                });

                overlay.addEventListener('click', function() {
                    sidebarContainer.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }

            // Close sidebar on window resize if open
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024 && sidebarContainer.classList.contains('active')) {
                    sidebarContainer.classList.remove('active');
                    overlay.classList.remove('active');
                }
            });

            // ============================================
            // DATE/TIME FUNCTIONS (preserved)
            // ============================================
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
            const firstRowName = document.querySelector('.payroll-table tbody tr:first-child td:nth-child(3)');
            const firstRowPosition = document.querySelector('.payroll-table tbody tr:first-child td:nth-child(4)');

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
            // Create a new window for printing
            const printWindow = window.open('', '_blank');

            // Get the payroll section
            const payrollSection = document.getElementById('payroll-section');
            if (!payrollSection) {
                alert('Could not find payroll section');
                return;
            }

            // Clone the payroll section
            const payrollClone = payrollSection.cloneNode(true);

            // Remove all print-hide elements from the clone
            payrollClone.querySelectorAll('.print-hide, .fixed, .pagination, button, a[href*="logout"]').forEach(el => el.remove());

            // Generate HTML for both pages
            let html = `
            <html>
            <head>
                <title>Permanent Payroll - <?php echo $period_display; ?></title>
                <style>
                    body { margin: 0; padding: 0; background: white; font-family: 'Inter', sans-serif; }
                    @page { size: 8.5in 13in landscape; margin: 0.2in; }
                    .payroll-section { max-width: 100%; margin: 0; padding: 0.2in; border: 1px solid #000; background: white; page-break-after: always; }
                    .payroll-header { position: relative; margin-bottom: 15px; }
                    .appendix { position: absolute; right: 0; top: 0; font-size: 10px; }
                    .payroll-title { text-align: center; font-size: 16px; font-weight: bold; margin-bottom: 5px; }
                    .period-container { display: flex; justify-content: center; font-weight: bold; margin-bottom: 15px; font-size: 11px; }
                    .period-value { position: relative; margin-left: 5px; border-bottom: 1px solid #000; }
                    .entity-info { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 10px; }
                    .entity-row { display: flex; align-items: center; margin-bottom: 3px; }
                    .entity-label { font-weight: bold; margin-right: 8px; }
                    .entity-value { border-bottom: 1px solid #000; min-width: 180px; }
                    .acknowledgment { font-size: 11px; font-style: italic; margin-bottom: 10px; }
                    .payroll-table { width: 100%; border-collapse: collapse; font-size: 8px; }
                    .payroll-table th, .payroll-table td { border: 0.5px solid #000; padding: 3px 2px; vertical-align: middle; }
                    .payroll-table thead th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
                    .text-right { text-align: right; }
                    .text-center { text-align: center; }
                    .font-bold { font-weight: bold; }
                    .total-row { background-color: #f0f0f0; font-weight: bold; }
                    .certification-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; font-size: 9px; margin-top: 15px; }
                    .certification-box { border: 0.5px solid #000; padding: 6px; }
                    .officer-signature { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 8px; }
                    .officer-info { text-align: center; }
                    .officer-name { font-weight: bold; font-size: 9px; }
                    .officer-title { font-style: italic; font-size: 8px; }
                    .date-field { display: flex; align-items: center; }
                    .date-line { border-bottom: 1px solid #000; width: 50px; }
                    .amount-field { display: flex; align-items: center; justify-content: flex-end; }
                    .amount-line { border-bottom: 1px solid #000; min-width: 50px; text-align: right; margin-left: 3px; }
                    .page-break { page-break-before: always; }
                </style>
                <script>
                    // Auto-close the window when print is canceled or completed
                    window.onafterprint = function() {
                        window.close();
                    };
                    
                    // Also close if the user navigates away from the print dialog
                    setTimeout(function() {
                        if (document.hasFocus()) {
                            window.close();
                        }
                    }, 1000);
                <\/script>
            </head>
            <body onload="window.print();">
            `;

            // PAGE 1: Show columns 1-17
            html += `<div class="payroll-section">`;
            html += payrollClone.querySelector('.appendix').outerHTML;
            html += `<div class="payroll-title">PAYROLL (PERMANENT)</div>`;
            html += `<div class="period-container"><span class="period-label">For the period:</span><div class="period-value"><?php echo $period_display; ?></div></div>`;
            html += `<div class="entity-info">${payrollClone.querySelector('.entity-info').innerHTML}</div>`;
            html += `<div class="acknowledgment">We acknowledge receipt of cash shown opposite our names as full compensation for services rendered for the period covered.</div>`;

            // Create table for page 1 (columns 1-17)
            html += `<table class="payroll-table">`;

            // Headers for page 1
            html += `<thead>`;
            html += `<tr>`;
            html += `<th rowspan="2">#</th>`;
            html += `<th rowspan="2">Employee ID</th>`;
            html += `<th rowspan="2">Name</th>`;
            html += `<th rowspan="2">Position</th>`;
            html += `<th rowspan="2">Department</th>`;
            html += `<th rowspan="2">Days Present</th>`;
            html += `<th rowspan="2">Monthly Salary</th>`;
            html += `<th rowspan="2">Amount Accrued</th>`;
            html += `<th colspan="9" class="text-center">DEDUCTIONS (Part 1)</th>`;
            html += `</tr>`;
            html += `<tr>`;
            html += `<th>Withholding Tax</th>`;
            html += `<th>PAG-IBIG LOAN - MPL</th>`;
            html += `<th>Corso Loan</th>`;
            html += `<th>Policy Loan</th>`;
            html += `<th>PhilHealth P.S.</th>`;
            html += `<th>UEF/Retirement</th>`;
            html += `<th>Emergency Loan</th>`;
            html += `<th>GFAL</th>`;
            html += `<th>LBP Loan</th>`;
            html += `</tr>`;
            html += `</thead>`;
            html += `<tbody>`;

            // Data rows for page 1 (columns 1-17)
            <?php $counter = $offset + 1; ?>
            <?php foreach ($permanent_employees as $employee): ?>
                <?php
                $total_deductions_part1 = ($employee['withholding_tax'] ?? 0) + ($employee['pagibig_loan_mpl'] ?? 0) + ($employee['corso_loan'] ?? 0) + ($employee['policy_loan'] ?? 0) + ($employee['philhealth_ps'] ?? 0) + ($employee['uef_retirement'] ?? 0) + ($employee['emergency_loan'] ?? 0) + ($employee['gfal'] ?? 0) + ($employee['lbp_loan'] ?? 0);
                $amount_due = ($employee['amount_due'] ?? 0) > 0 ? $employee['amount_due'] : (($employee['amount_accrued'] ?? 0) - $total_deductions_part1);
                ?>
                html += `<tr>`;
                html += `<td class="text-center"><?php echo $counter; ?></td>`;
                html += `<td><?php echo htmlspecialchars($employee['employee_id'] ?? ''); ?></td>`;
                html += `<td><?php echo htmlspecialchars($employee['full_name'] ?? ''); ?></td>`;
                html += `<td><?php echo htmlspecialchars($employee['position'] ?? ''); ?></td>`;
                html += `<td><?php echo htmlspecialchars($employee['department'] ?? ''); ?></td>`;
                html += `<td class="text-center"><?php echo number_format($employee['days_present'] ?? 0, 1); ?></td>`;
                html += `<td class="text-right"><?php echo number_format($employee['monthly_salary'] ?? 0, 2); ?></td>`;
                html += `<td class="text-right"><?php echo number_format($employee['amount_accrued'] ?? 0, 2); ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['withholding_tax'] ?? 0) ? number_format($employee['withholding_tax'], 2) : ''; ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['pagibig_loan_mpl'] ?? 0) ? number_format($employee['pagibig_loan_mpl'], 2) : ''; ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['corso_loan'] ?? 0) ? number_format($employee['corso_loan'], 2) : ''; ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['policy_loan'] ?? 0) ? number_format($employee['policy_loan'], 2) : ''; ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['philhealth_ps'] ?? 0) ? number_format($employee['philhealth_ps'], 2) : ''; ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['uef_retirement'] ?? 0) ? number_format($employee['uef_retirement'], 2) : ''; ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['emergency_loan'] ?? 0) ? number_format($employee['emergency_loan'], 2) : ''; ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['gfal'] ?? 0) ? number_format($employee['gfal'], 2) : ''; ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['lbp_loan'] ?? 0) ? number_format($employee['lbp_loan'], 2) : ''; ?></td>`;
                html += `</tr>`;
                <?php $counter++; ?>
            <?php endforeach; ?>

            // Totals for page 1
            html += `<tr class="total-row">`;
            html += `<td colspan="7" class="text-right font-bold">TOTAL AMOUNT</td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['amount_accrued'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['withholding_tax'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['pagibig_loan_mpl'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['corso_loan'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['policy_loan'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['philhealth_ps'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['uef_retirement'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['emergency_loan'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['gfal'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['lbp_loan'] ?? 0, 2); ?></td>`;
            html += `</tr>`;
            html += `</tbody>`;
            html += `</table>`;

            // Add certification grid on first page
            html += payrollClone.querySelector('.certification-grid').outerHTML;

            // Add page indicator
            html += `<div style="text-align: right; font-size: 8px; margin-top: 5px; font-style: italic;">Page 1 of 2 (Columns 1-17: # through LBP Loan)</div>`;
            html += `</div>`; // Close first page

            // PAGE 2: Show columns 18-29
            html += `<div class="payroll-section page-break">`;
            html += payrollClone.querySelector('.appendix').outerHTML;
            html += `<div class="payroll-title">PAYROLL (PERMANENT) - CONTINUATION</div>`;
            html += `<div class="period-container"><span class="period-label">For the period:</span><div class="period-value"><?php echo $period_display; ?></div></div>`;
            html += `<div class="entity-info">${payrollClone.querySelector('.entity-info').innerHTML}</div>`;
            html += `<div class="acknowledgment">We acknowledge receipt of cash shown opposite our names as full compensation for services rendered for the period covered. (Continued)</div>`;

            // Create table for page 2 (columns 18-29)
            html += `<table class="payroll-table">`;

            // Headers for page 2
            html += `<thead>`;
            html += `<tr>`;
            html += `<th rowspan="2">#</th>`;
            html += `<th rowspan="2">Employee ID</th>`;
            html += `<th rowspan="2">Name</th>`;
            html += `<th colspan="8" class="text-center">DEDUCTIONS (Part 2)</th>`;
            html += `<th colspan="3" class="text-center">ADDITIONAL</th>`;
            html += `<th rowspan="2">Signature</th>`;
            html += `</tr>`;
            html += `<tr>`;
            html += `<th>MPL</th>`;
            html += `<th>MPL Lite</th>`;
            html += `<th>SSS Contribution</th>`;
            html += `<th>PAG-IBIG CONT.</th>`;
            html += `<th>STATE INS. G.S.</th>`;
            html += `<th>Total Deductions</th>`;
            html += `<th>Amount Due</th>`;
            html += `<th>No.</th>`;
            html += `</tr>`;
            html += `</thead>`;
            html += `<tbody>`;

            // Data rows for page 2 (columns 18-29)
            <?php $counter = $offset + 1; ?>
            <?php foreach ($permanent_employees as $employee): ?>
                <?php
                $total_deductions_part2 = ($employee['mpl'] ?? 0) + ($employee['mpl_lite'] ?? 0) + ($employee['sss_contribution'] ?? 0) + ($employee['pagibig_cont'] ?? 0) + ($employee['state_ins_gs'] ?? 0);
                $total_deductions_all = ($employee['total_deductions'] ?? 0);
                $amount_due = ($employee['amount_due'] ?? 0);
                ?>
                html += `<tr>`;
                html += `<td class="text-center"><?php echo $counter; ?></td>`;
                html += `<td><?php echo htmlspecialchars($employee['employee_id'] ?? ''); ?></td>`;
                html += `<td><?php echo htmlspecialchars($employee['full_name'] ?? ''); ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['mpl'] ?? 0) ? number_format($employee['mpl'], 2) : ''; ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['mpl_lite'] ?? 0) ? number_format($employee['mpl_lite'], 2) : ''; ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['sss_contribution'] ?? 0) ? number_format($employee['sss_contribution'], 2) : ''; ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['pagibig_cont'] ?? 0) ? number_format($employee['pagibig_cont'], 2) : ''; ?></td>`;
                html += `<td class="text-right"><?php echo ($employee['state_ins_gs'] ?? 0) ? number_format($employee['state_ins_gs'], 2) : ''; ?></td>`;
                html += `<td class="text-right font-bold"><?php echo number_format($total_deductions_all, 2); ?></td>`;
                html += `<td class="text-right font-bold"><?php echo number_format($amount_due, 2); ?></td>`;
                html += `<td class="text-center"><?php echo $counter; ?></td>`;
                html += `<td class="text-center"><?php echo htmlspecialchars($employee['signature_status'] ?? ''); ?></td>`;
                html += `</tr>`;
                <?php $counter++; ?>
            <?php endforeach; ?>

            // Totals for page 2
            html += `<tr class="total-row">`;
            html += `<td colspan="3" class="text-right font-bold">TOTAL AMOUNT</td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['mpl'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['mpl_lite'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['sss_contribution'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['pagibig_cont'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['state_ins_gs'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['total_deductions'] ?? 0, 2); ?></td>`;
            html += `<td class="text-right font-bold"><?php echo number_format($totals['amount_due'] ?? 0, 2); ?></td>`;
            html += `<td colspan="2"></td>`;
            html += `</tr>`;
            html += `</tbody>`;
            html += `</table>`;

            // Add page indicator
            html += `<div style="text-align: right; font-size: 8px; margin-top: 5px; font-style: italic;">Page 2 of 2 (Columns 18-29: MPL through Signature)</div>`;
            html += `</div>`; // Close second page

            html += `</body></html>`;

            // Write to print window and print
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
        }

        function printObligationOnly() {
            // Create a new window for printing
            const printWindow = window.open('', '_blank');

            const obligationSection = document.getElementById('obligation-section');
            if (!obligationSection) {
                alert('Could not find obligation section');
                return;
            }

            // Clone the obligation section
            const obligationClone = obligationSection.cloneNode(true);

            // Remove all print-hide elements from the clone
            obligationClone.querySelectorAll('.print-hide, .fixed, button:not([type="submit"]), a[href*="logout"]').forEach(el => el.remove());

            // Generate HTML
            let html = `
            <html>
            <head>
                <title>Obligation Request - Permanent Payroll</title>
                <style>
                    body { margin: 0; padding: 0; background: white; font-family: 'Inter', sans-serif; }
                    @page { size: portrait; margin: 0.2in; }
                    .obligation-container { max-width: 100%; margin: 0; padding: 0.2in; background: white; border: 2px solid #000; box-sizing: border-box; }
                    .obligation-header { display: flex; border-bottom: 2px solid #000; }
                    .obligation-title { width: 600px; text-align: center; font-weight: bold; font-size: 14px; padding: 8px 0; }
                    .obligation-meta { width: 300px; border-left: 2px solid #000; font-weight: 600; font-size: 11px; padding: 8px; }
                    .obligation-table { width: 100%; border-collapse: collapse; }
                    .obligation-table th, .obligation-table td { border: 2px solid #000; border-top: 0; padding: 8px 10px; vertical-align: middle; }
                    .certification-box-ob { border: 2px solid #000; padding: 10px; font-size: 12px; }
                    .status-section { border: 1px solid #000; border-top: 0; }
                    .status-table { width: 100%; border-collapse: collapse; }
                    .status-table th, .status-table td { border: 1px solid #000; padding: 8px 4px; text-align: center; }
                </style>
                <script>
                    // Auto-close the window when print is canceled or completed
                    window.onafterprint = function() {
                        window.close();
                    };
                    
                    // Also close if the user navigates away from the print dialog
                    setTimeout(function() {
                        if (document.hasFocus()) {
                            window.close();
                        }
                    }, 1000);
                <\/script>
            </head>
            <body onload="window.print();">
                ${obligationClone.outerHTML}
            </body>
            </html>
            `;

            // Write to print window
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
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

            fetch('save_personnel_config_permanent.php', {
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