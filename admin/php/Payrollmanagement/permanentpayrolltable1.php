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

// Function to ensure Permanent payroll tables exist
function ensurePermanentPayrollTables($pdo)
{
  // Check if payroll_history_permanent table exists
  $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_history_permanent'");
  if ($table_check->rowCount() == 0) {
    // Create the table with fields matching the permanent payroll display
    $pdo->exec("
            CREATE TABLE IF NOT EXISTS payroll_history_permanent (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id VARCHAR(50) NOT NULL,
                user_id INT,
                employee_type VARCHAR(50) DEFAULT 'permanent',
                payroll_period VARCHAR(7) NOT NULL,
                payroll_cutoff ENUM('full', 'first_half', 'second_half') DEFAULT 'full',
                
                -- Employee information
                full_name VARCHAR(255),
                position VARCHAR(255),
                office VARCHAR(255),
                
                -- Compensation fields
                monthly_salary DECIMAL(12,2) DEFAULT 0.00,
                amount_accrued DECIMAL(12,2) DEFAULT 0.00,
                other_comp DECIMAL(12,2) DEFAULT 0.00,
                gross_amount DECIMAL(12,2) DEFAULT 0.00,
                
                -- Deduction fields - matching the table columns
                withholding_tax DECIMAL(12,2) DEFAULT 0.00,
                pagibig_loan_mpl DECIMAL(12,2) DEFAULT 0.00,
                corso_loan DECIMAL(12,2) DEFAULT 0.00,
                policy_loan DECIMAL(12,2) DEFAULT 0.00,
                philhealth_ps DECIMAL(12,2) DEFAULT 0.00,
                uef_retirement DECIMAL(12,2) DEFAULT 0.00,
                emergency_loan DECIMAL(12,2) DEFAULT 0.00,
                gfal DECIMAL(12,2) DEFAULT 0.00,
                lbp_loan DECIMAL(12,2) DEFAULT 0.00,
                mpl DECIMAL(12,2) DEFAULT 0.00,
                mpl_lite DECIMAL(12,2) DEFAULT 0.00,
                sss_contribution DECIMAL(12,2) DEFAULT 0.00,
                
                -- Additional fields
                pagibig_cont DECIMAL(12,2) DEFAULT 0.00,
                state_ins_gs DECIMAL(12,2) DEFAULT 0.00,
                
                -- Calculated fields
                total_deductions DECIMAL(12,2) DEFAULT 0.00,
                amount_due DECIMAL(12,2) DEFAULT 0.00,
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
                
                -- Signature field
                signature_status VARCHAR(50) DEFAULT '(Not signed)',
                
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

  // Check if payroll_deductions_permanent table exists
  $deductions_check = $pdo->query("SHOW TABLES LIKE 'payroll_deductions_permanent'");
  if ($deductions_check->rowCount() == 0) {
    $pdo->exec("
            CREATE TABLE IF NOT EXISTS payroll_deductions_permanent (
                id INT AUTO_INCREMENT PRIMARY KEY,
                payroll_id INT NOT NULL,
                deduction_type VARCHAR(50) NOT NULL,
                deduction_amount DECIMAL(12,2) DEFAULT 0.00,
                deduction_description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (payroll_id) REFERENCES payroll_history_permanent(id) ON DELETE CASCADE,
                INDEX idx_payroll_id (payroll_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
  }
}

// Ensure tables exist
ensurePermanentPayrollTables($pdo);

// Function to get employee's payroll data from permanent table - FIXED to avoid duplicates
function getEmployeePayrollData($pdo, $employee_id, $user_id, $period, $cutoff, $amount_accrued = 0)
{
  $data = [
    'full_name' => '',
    'position' => '',
    'office' => '',
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
    'gross_amount' => $amount_accrued,
    'amount_due' => $amount_accrued,
    'net_amount' => $amount_accrued,
    'amount_accrued' => $amount_accrued,
    'monthly_salary' => 0,
    'days_present' => 0,
    'signature_status' => '(Not signed)',
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
      // For specific half, get just that half's data
      $stmt = $pdo->prepare("
                SELECT id, full_name, position, office, monthly_salary, amount_accrued,
                       other_comp, withholding_tax, pagibig_loan_mpl, corso_loan, policy_loan,
                       philhealth_ps, uef_retirement, emergency_loan, gfal, lbp_loan, mpl,
                       mpl_lite, sss_contribution, pagibig_cont, state_ins_gs,
                       total_deductions, gross_amount, amount_due, net_amount, days_present,
                       signature_status, status
                FROM payroll_history_permanent 
                WHERE employee_id = ? 
                    AND payroll_period = ? AND payroll_cutoff = ?
                LIMIT 1
            ");
      $stmt->execute([$employee_id, $period, $cutoff]);
      $result = $stmt->fetch();

      if ($result) {
        $data['full_name'] = $result['full_name'] ?? '';
        $data['position'] = $result['position'] ?? '';
        $data['office'] = $result['office'] ?? '';
        $data['monthly_salary'] = floatval($result['monthly_salary'] ?? 0);
        $data['amount_accrued'] = floatval($result['amount_accrued'] ?? 0);
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
        $data['days_present'] = floatval($result['days_present'] ?? 0);
        $data['signature_status'] = $result['signature_status'] ?? '(Not signed)';
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
  // Clean any output buffers to ensure clean JSON response
  while (ob_get_level()) {
    ob_end_clean();
  }
  header('Content-Type: application/json');

  try {
    // GET ALL EMPLOYEES FOR SELECTION ACROSS PAGES
    if ($_POST['ajax_action'] === 'get_all_employees_for_selection') {
      $period = $_POST['period'] ?? $selected_period;
      $cutoff = $_POST['cutoff'] ?? $selected_cutoff;
      $search = $_POST['search'] ?? '';

      try {
        // Build query to get all employees (without pagination)
        $sql = "
                    SELECT DISTINCT
                        id as user_id, 
                        employee_id, 
                        full_name,
                        position, 
                        office as department
                    FROM permanent 
                    WHERE (status = 'Active' OR status IS NULL)
                ";

        if (!empty($search)) {
          $sql .= " AND (employee_id LIKE :search OR full_name LIKE :search OR position LIKE :search OR office LIKE :search)";
        }

        $sql .= " ORDER BY full_name";

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

    // PAGINATION AJAX HANDLER
    if ($_POST['ajax_action'] === 'get_paginated_employees') {
      $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
      $per_page = isset($_POST['per_page']) ? (int)$_POST['per_page'] : 10;
      $period = $_POST['period'] ?? $selected_period;
      $cutoff = $_POST['cutoff'] ?? $selected_cutoff;
      $search = isset($_POST['search']) ? trim($_POST['search']) : '';

      $offset = ($page - 1) * $per_page;

      $permanent_employees_paginated = [];
      $total_employees = 0;

      try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'permanent'");
        if ($table_check->rowCount() == 0) {
          throw new Exception("permanent table does not exist");
        }

        // Count total distinct employees
        $count_sql = "SELECT COUNT(DISTINCT id) as total FROM permanent WHERE (status = 'Active' OR status IS NULL)";
        if (!empty($search)) {
          $count_sql .= " AND (employee_id LIKE :search OR full_name LIKE :search OR position LIKE :search OR office LIKE :search)";
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
                        full_name,
                        position, 
                        office as department,
                        monthly_salary,
                        mobile_number,
                        email_address,
                        joining_date,
                        eligibility
                    FROM permanent 
                    WHERE (status = 'Active' OR status IS NULL)
                ";

        if (!empty($search)) {
          $sql .= " AND (employee_id LIKE :search OR full_name LIKE :search OR position LIKE :search OR office LIKE :search)";
        }

        $sql .= " ORDER BY full_name LIMIT :offset, :per_page";

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

        // Process each employee
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

          $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
          $prorated_salary = ($monthly_salary / 22) * $attendance_days;

          // Get payroll data from database
          $payroll_data = getEmployeePayrollData($pdo, $employee['employee_id'], $employee['user_id'], $period, $cutoff, $prorated_salary);

          $employee['other_comp'] = $payroll_data['other_comp'];
          $employee['withholding_tax'] = $payroll_data['withholding_tax'];
          $employee['pagibig_loan_mpl'] = $payroll_data['pagibig_loan_mpl'];
          $employee['corso_loan'] = $payroll_data['corso_loan'];
          $employee['policy_loan'] = $payroll_data['policy_loan'];
          $employee['philhealth_ps'] = $payroll_data['philhealth_ps'];
          $employee['uef_retirement'] = $payroll_data['uef_retirement'];
          $employee['emergency_loan'] = $payroll_data['emergency_loan'];
          $employee['gfal'] = $payroll_data['gfal'];
          $employee['lbp_loan'] = $payroll_data['lbp_loan'];
          $employee['mpl'] = $payroll_data['mpl'];
          $employee['mpl_lite'] = $payroll_data['mpl_lite'];
          $employee['sss_contribution'] = $payroll_data['sss_contribution'];
          $employee['pagibig_cont'] = $payroll_data['pagibig_cont'];
          $employee['state_ins_gs'] = $payroll_data['state_ins_gs'];
          $employee['total_deductions'] = $payroll_data['total_deductions'];
          $employee['net_amount'] = $payroll_data['net_amount'];
          $employee['amount_due'] = $payroll_data['amount_due'];
          $employee['gross_amount'] = $payroll_data['gross_amount'] > 0 ? $payroll_data['gross_amount'] : $prorated_salary + $payroll_data['other_comp'];
          $employee['amount_accrued'] = $payroll_data['amount_accrued'] > 0 ? $payroll_data['amount_accrued'] : $prorated_salary;
          $employee['monthly_salary'] = $monthly_salary;
          $employee['daily_rate'] = $monthly_salary / 22;
          $employee['prorated_salary'] = $prorated_salary;
          $employee['payroll_status'] = $payroll_data['status'];
          $employee['payroll_id'] = $payroll_data['payroll_id'];
          $employee['payroll_exists'] = $payroll_data['exists'];
          $employee['signature_status'] = $payroll_data['signature_status'];

          $processed_employees[] = $employee;
        }

        $permanent_employees_paginated = $processed_employees;
      } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        $permanent_employees_paginated = [];
        $total_employees = 0;
      }

      // Calculate totals
      $total_amount_accrued = 0;
      $total_other_comp = 0;
      $total_gross_amount = 0;
      $total_withholding_tax = 0;
      $total_pagibig_loan_mpl = 0;
      $total_corso_loan = 0;
      $total_policy_loan = 0;
      $total_philhealth_ps = 0;
      $total_uef_retirement = 0;
      $total_emergency_loan = 0;
      $total_gfal = 0;
      $total_lbp_loan = 0;
      $total_mpl = 0;
      $total_mpl_lite = 0;
      $total_sss_contribution = 0;
      $total_pagibig_cont = 0;
      $total_state_ins_gs = 0;
      $total_deductions = 0;
      $total_amount_due = 0;

      foreach ($permanent_employees_paginated as $employee) {
        $total_amount_accrued += floatval($employee['amount_accrued'] ?? 0);
        $total_other_comp += floatval($employee['other_comp'] ?? 0);
        $total_gross_amount += floatval($employee['gross_amount'] ?? 0);
        $total_withholding_tax += floatval($employee['withholding_tax'] ?? 0);
        $total_pagibig_loan_mpl += floatval($employee['pagibig_loan_mpl'] ?? 0);
        $total_corso_loan += floatval($employee['corso_loan'] ?? 0);
        $total_policy_loan += floatval($employee['policy_loan'] ?? 0);
        $total_philhealth_ps += floatval($employee['philhealth_ps'] ?? 0);
        $total_uef_retirement += floatval($employee['uef_retirement'] ?? 0);
        $total_emergency_loan += floatval($employee['emergency_loan'] ?? 0);
        $total_gfal += floatval($employee['gfal'] ?? 0);
        $total_lbp_loan += floatval($employee['lbp_loan'] ?? 0);
        $total_mpl += floatval($employee['mpl'] ?? 0);
        $total_mpl_lite += floatval($employee['mpl_lite'] ?? 0);
        $total_sss_contribution += floatval($employee['sss_contribution'] ?? 0);
        $total_pagibig_cont += floatval($employee['pagibig_cont'] ?? 0);
        $total_state_ins_gs += floatval($employee['state_ins_gs'] ?? 0);
        $total_deductions += floatval($employee['total_deductions'] ?? 0);
        $total_amount_due += floatval($employee['amount_due'] ?? 0);
      }

      ob_start();
      $counter = $offset + 1;
      $is_full_month_ajax = ($cutoff == 'full');

      foreach ($permanent_employees_paginated as $employee):
        $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
        $daily_rate = floatval($employee['daily_rate'] ?? 0);
        $prorated_salary = floatval($employee['prorated_salary'] ?? 0);
        $amount_accrued = floatval($employee['amount_accrued'] ?? 0);
        $other_comp = floatval($employee['other_comp'] ?? 0);
        $days_present = floatval($employee['days_present'] ?? 0);
        $gross_amount = floatval($employee['gross_amount'] ?? 0);

        // Deduction fields
        $withholding_tax = floatval($employee['withholding_tax'] ?? 0);
        $pagibig_loan_mpl = floatval($employee['pagibig_loan_mpl'] ?? 0);
        $corso_loan = floatval($employee['corso_loan'] ?? 0);
        $policy_loan = floatval($employee['policy_loan'] ?? 0);
        $philhealth_ps = floatval($employee['philhealth_ps'] ?? 0);
        $uef_retirement = floatval($employee['uef_retirement'] ?? 0);
        $emergency_loan = floatval($employee['emergency_loan'] ?? 0);
        $gfal = floatval($employee['gfal'] ?? 0);
        $lbp_loan = floatval($employee['lbp_loan'] ?? 0);
        $mpl = floatval($employee['mpl'] ?? 0);
        $mpl_lite = floatval($employee['mpl_lite'] ?? 0);
        $sss_contribution = floatval($employee['sss_contribution'] ?? 0);

        // Additional fields
        $pagibig_cont = floatval($employee['pagibig_cont'] ?? 0);
        $state_ins_gs = floatval($employee['state_ins_gs'] ?? 0);

        $total_deductions_row = floatval($employee['total_deductions'] ?? 0);
        $amount_due_row = floatval($employee['amount_due'] ?? 0);
        $payroll_id = $employee['payroll_id'] ?? null;
        $payroll_exists = $employee['payroll_exists'] ?? false;
        $signature_status = $employee['signature_status'] ?? '(Not signed)';

        $has_attendance = ($days_present > 0);
        $row_class = $has_attendance ? '' : 'no-attendance';

        $full_month_disabled_class = $is_full_month_ajax ? 'full-month-disabled' : '';
        $full_month_readonly = $is_full_month_ajax ? 'readonly disabled' : '';
        $full_month_style = $is_full_month_ajax ? 'background-color: #f0f0f0; color: #888; cursor: not-allowed;' : '';
?>
        <tr class="bg-white border-b hover:bg-gray-50 payroll-row <?php echo $row_class; ?>"
          data-user-id="<?php echo $employee['user_id']; ?>"
          data-employee-id="<?php echo htmlspecialchars($employee['employee_id']); ?>"
          data-payroll-id="<?php echo $payroll_id; ?>"
          data-payroll-exists="<?php echo $payroll_exists ? '1' : '0'; ?>">

          <td class="text-center">
            <input type="checkbox" class="employee-checkbox" value="<?php echo htmlspecialchars($employee['employee_id']); ?>"
              data-employee-name="<?php echo htmlspecialchars($employee['full_name']); ?>"
              data-user-id="<?php echo $employee['user_id']; ?>">
          </td>
          <td class="text-center"><?php echo $counter++; ?></td>
          <td class="font-medium"><?php echo htmlspecialchars($employee['employee_id']); ?></td>
          <td class="text-left pl-2 font-medium">
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
          <td class="text-right currency-cell monthly-salary">
            <input type="number"
              name="monthly_salary[]"
              class="payroll-input readonly disabled-field"
              value="<?php echo number_format($monthly_salary, 2, '.', ''); ?>"
              readonly disabled tabindex="-1">
          </td>
          <td class="text-right currency-cell amount-accrued">
            <input type="number"
              name="amount_accrued[]"
              class="payroll-input readonly disabled-field"
              value="<?php echo number_format($amount_accrued, 2, '.', ''); ?>"
              readonly disabled tabindex="-1">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="withholding_tax[]"
              class="payroll-input withholding-tax <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($withholding_tax, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="withholding_tax"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="pagibig_loan_mpl[]"
              class="payroll-input pagibig-loan-mpl <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($pagibig_loan_mpl, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="pagibig_loan_mpl"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="corso_loan[]"
              class="payroll-input corso-loan <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($corso_loan, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="corso_loan"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="policy_loan[]"
              class="payroll-input policy-loan <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($policy_loan, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="policy_loan"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="philhealth_ps[]"
              class="payroll-input philhealth-ps <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($philhealth_ps, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="philhealth_ps"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="uef_retirement[]"
              class="payroll-input uef-retirement <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($uef_retirement, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="uef_retirement"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="emergency_loan[]"
              class="payroll-input emergency-loan <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($emergency_loan, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="emergency_loan"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="gfal[]"
              class="payroll-input gfal <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($gfal, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="gfal"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="lbp_loan[]"
              class="payroll-input lbp-loan <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($lbp_loan, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="lbp_loan"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="mpl[]"
              class="payroll-input mpl <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($mpl, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="mpl"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="mpl_lite[]"
              class="payroll-input mpl-lite <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($mpl_lite, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="mpl_lite"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="sss_contribution[]"
              class="payroll-input sss-contribution <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($sss_contribution, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="sss_contribution"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="pagibig_cont[]"
              class="payroll-input pagibig-cont <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($pagibig_cont, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="pagibig_cont"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell">
            <input type="number"
              name="state_ins_gs[]"
              class="payroll-input state-ins-gs <?php echo $is_full_month_ajax ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
              value="<?php echo number_format($state_ins_gs, 2, '.', ''); ?>"
              min="0" step="0.01"
              data-user-id="<?php echo $employee['user_id']; ?>"
              data-field="state_ins_gs"
              <?php echo $full_month_readonly; ?>
              style="<?php echo $full_month_style; ?>">
          </td>
          <td class="text-right currency-cell total-deduction-field">
            <input type="number"
              name="total_deduction[]"
              class="payroll-input total-deduction readonly disabled-field"
              value="<?php echo number_format($total_deductions_row, 2, '.', ''); ?>"
              readonly disabled tabindex="-1">
          </td>
          <td class="text-right currency-cell net-amount-field important-column">
            <input type="number"
              name="net_amount[]"
              class="payroll-input net-amount readonly disabled-field <?php echo ($amount_due_row <= 0) ? 'zero-amount' : ''; ?>"
              value="<?php echo number_format($amount_due_row, 2, '.', ''); ?>"
              readonly disabled tabindex="-1">
          </td>
          <td class="italic text-gray-500 signature-status"><?php echo htmlspecialchars($signature_status); ?></td>
          <td class="text-center action-cell">
            <div class="action-buttons">
              <button type="button" class="action-btn view-btn" onclick="viewEmployeeData(<?php echo $employee['user_id']; ?>, '<?php echo $selected_period; ?>', '<?php echo $selected_cutoff; ?>')">
                <i class="fas fa-eye"></i> <span class="hidden md:inline">View</span>
              </button>
              <?php if (!$is_full_month_ajax && ($payroll_status == 'pending' || $payroll_status == 'draft')): ?>
                <button type="button" class="action-btn bg-green-500 text-white hover:bg-green-600 calculate-row" onclick="calculateSingleRow(this)">
                  <i class="fas fa-calculator"></i> <span class="hidden md:inline">Calc</span>
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
          'total_amount_accrued' => number_format($total_amount_accrued, 2),
          'total_other_comp' => number_format($total_other_comp, 2),
          'total_gross_amount' => number_format($total_gross_amount, 2),
          'total_withholding_tax' => number_format($total_withholding_tax, 2),
          'total_pagibig_loan_mpl' => number_format($total_pagibig_loan_mpl, 2),
          'total_corso_loan' => number_format($total_corso_loan, 2),
          'total_policy_loan' => number_format($total_policy_loan, 2),
          'total_philhealth_ps' => number_format($total_philhealth_ps, 2),
          'total_uef_retirement' => number_format($total_uef_retirement, 2),
          'total_emergency_loan' => number_format($total_emergency_loan, 2),
          'total_gfal' => number_format($total_gfal, 2),
          'total_lbp_loan' => number_format($total_lbp_loan, 2),
          'total_mpl' => number_format($total_mpl, 2),
          'total_mpl_lite' => number_format($total_mpl_lite, 2),
          'total_sss_contribution' => number_format($total_sss_contribution, 2),
          'total_pagibig_cont' => number_format($total_pagibig_cont, 2),
          'total_state_ins_gs' => number_format($total_state_ins_gs, 2),
          'total_deductions' => number_format($total_deductions, 2),
          'total_amount_due' => number_format($total_amount_due, 2)
        ]
      ]);
      exit();
    }

    // Save Deductions (Handles ALL fields at once - called by auto-save)
    // FIXED: Corrected the number of bound variables to match the query
    if ($_POST['ajax_action'] === 'save_deductions') {
      $user_id = intval($_POST['employee_id']);

      // Parse all deduction fields
      $withholding_tax = floatval($_POST['withholding_tax'] ?? 0);
      $pagibig_loan_mpl = floatval($_POST['pagibig_loan_mpl'] ?? 0);
      $corso_loan = floatval($_POST['corso_loan'] ?? 0);
      $policy_loan = floatval($_POST['policy_loan'] ?? 0);
      $philhealth_ps = floatval($_POST['philhealth_ps'] ?? 0);
      $uef_retirement = floatval($_POST['uef_retirement'] ?? 0);
      $emergency_loan = floatval($_POST['emergency_loan'] ?? 0);
      $gfal = floatval($_POST['gfal'] ?? 0);
      $lbp_loan = floatval($_POST['lbp_loan'] ?? 0);
      $mpl = floatval($_POST['mpl'] ?? 0);
      $mpl_lite = floatval($_POST['mpl_lite'] ?? 0);
      $sss_contribution = floatval($_POST['sss_contribution'] ?? 0);

      $other_comp = floatval($_POST['other_comp'] ?? 0);
      $pagibig_cont = floatval($_POST['pagibig_cont'] ?? 0);
      $state_ins_gs = floatval($_POST['state_ins_gs'] ?? 0);

      $amount_accrued = floatval($_POST['amount_accrued'] ?? 0);
      $gross_amount = floatval($_POST['gross_amount'] ?? 0);
      $total_deductions = floatval($_POST['total_deductions'] ?? 0);
      $amount_due = floatval($_POST['amount_due'] ?? 0);

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
        // Get the actual employee_id string from permanent table
        $emp_stmt = $pdo->prepare("
                    SELECT employee_id, full_name, position, office, monthly_salary
                    FROM permanent 
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
        $full_name = $employee['full_name'];
        $position = $employee['position'];
        $office = $employee['office'];

        // Calculate monthly salary and amount accrued
        $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
        $daily_rate = $monthly_salary / 22;
        $prorated_salary = $daily_rate * $days_present;

        if ($amount_accrued == 0) {
          $amount_accrued = $prorated_salary;
        }

        if ($gross_amount == 0) {
          $gross_amount = $amount_accrued + $other_comp;
        }

        // Ensure calculations are consistent
        if ($total_deductions == 0) {
          $total_deductions = $withholding_tax + $pagibig_loan_mpl + $corso_loan + $policy_loan +
            $philhealth_ps + $uef_retirement + $emergency_loan + $gfal +
            $lbp_loan + $mpl + $mpl_lite + $sss_contribution +
            $pagibig_cont + $state_ins_gs;
        }

        if ($amount_due == 0 && $gross_amount > 0) {
          $amount_due = $gross_amount - $total_deductions;
          if ($amount_due < 0) $amount_due = 0;
        }

        // Check if record exists in permanent table
        $check_stmt = $pdo->prepare("
                    SELECT id FROM payroll_history_permanent 
                    WHERE employee_id = ? 
                        AND payroll_period = ? AND payroll_cutoff = ?
                ");
        $check_stmt->execute([$employee_id, $period, $cutoff]);
        $existing = $check_stmt->fetch();

        if ($existing) {
          // UPDATE existing record
          $update_sql = "UPDATE payroll_history_permanent SET 
                        full_name = ?,
                        position = ?,
                        office = ?,
                        monthly_salary = ?,
                        amount_accrued = ?,
                        other_comp = ?,
                        gross_amount = ?,
                        withholding_tax = ?,
                        pagibig_loan_mpl = ?,
                        corso_loan = ?,
                        policy_loan = ?,
                        philhealth_ps = ?,
                        uef_retirement = ?,
                        emergency_loan = ?,
                        gfal = ?,
                        lbp_loan = ?,
                        mpl = ?,
                        mpl_lite = ?,
                        sss_contribution = ?,
                        pagibig_cont = ?,
                        state_ins_gs = ?,
                        total_deductions = ?,
                        amount_due = ?,
                        net_amount = ?,
                        days_present = ?,
                        updated_at = NOW()
                        WHERE id = ?";

          $update_stmt = $pdo->prepare($update_sql);
          $update_stmt->execute([
            $full_name,
            $position,
            $office,
            $monthly_salary,
            $amount_accrued,
            $other_comp,
            $gross_amount,
            $withholding_tax,
            $pagibig_loan_mpl,
            $corso_loan,
            $policy_loan,
            $philhealth_ps,
            $uef_retirement,
            $emergency_loan,
            $gfal,
            $lbp_loan,
            $mpl,
            $mpl_lite,
            $sss_contribution,
            $pagibig_cont,
            $state_ins_gs,
            $total_deductions,
            $amount_due,
            $amount_due,
            $days_present,
            $existing['id']
          ]);

          // Update deductions - delete old and insert new
          $delete_deductions = $pdo->prepare("DELETE FROM payroll_deductions_permanent WHERE payroll_id = ?");
          $delete_deductions->execute([$existing['id']]);

          $deduction_types = [];
          if ($withholding_tax > 0) $deduction_types[] = ['Withholding Tax', $withholding_tax];
          if ($pagibig_loan_mpl > 0) $deduction_types[] = ['PAG-IBIG LOAN - MPL', $pagibig_loan_mpl];
          if ($corso_loan > 0) $deduction_types[] = ['Corso Loan', $corso_loan];
          if ($policy_loan > 0) $deduction_types[] = ['Policy Loan', $policy_loan];
          if ($philhealth_ps > 0) $deduction_types[] = ['PhilHealth P.S.', $philhealth_ps];
          if ($uef_retirement > 0) $deduction_types[] = ['UEF / Retirement', $uef_retirement];
          if ($emergency_loan > 0) $deduction_types[] = ['Emergency Loan', $emergency_loan];
          if ($gfal > 0) $deduction_types[] = ['GFAL', $gfal];
          if ($lbp_loan > 0) $deduction_types[] = ['LBP Loan', $lbp_loan];
          if ($mpl > 0) $deduction_types[] = ['MPL', $mpl];
          if ($mpl_lite > 0) $deduction_types[] = ['MPL Lite', $mpl_lite];
          if ($sss_contribution > 0) $deduction_types[] = ['SSS Contribution', $sss_contribution];
          if ($pagibig_cont > 0) $deduction_types[] = ['PAG-IBIG CONT.', $pagibig_cont];
          if ($state_ins_gs > 0) $deduction_types[] = ['STATE INS. G.S.', $state_ins_gs];

          foreach ($deduction_types as $deduction) {
            if ($deduction[1] > 0) {
              $deduction_stmt = $pdo->prepare("
                                INSERT INTO payroll_deductions_permanent 
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
            'amount_due' => $amount_due
          ]);
        } else {
          // INSERT new record - FIXED: Corrected number of placeholders to match execute array
          $insert_sql = "INSERT INTO payroll_history_permanent 
    (employee_id, user_id, employee_type, full_name, position, office, 
     payroll_period, payroll_cutoff,
     monthly_salary, amount_accrued, other_comp, gross_amount,
     withholding_tax, pagibig_loan_mpl, corso_loan, policy_loan,
     philhealth_ps, uef_retirement, emergency_loan, gfal,
     lbp_loan, mpl, mpl_lite, sss_contribution,
     pagibig_cont, state_ins_gs, total_deductions, amount_due, net_amount, 
     days_present, working_days, status, processed_date,
     obligation_status, signature_status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

          // Count: 36 fields, 36 placeholders
          $insert_stmt = $pdo->prepare($insert_sql);
          $insert_stmt->execute([
            $employee_id,           // 1
            $user_id,               // 2
            'permanent',            // 3 - employee_type
            $full_name,             // 4
            $position,              // 5
            $office,                // 6
            $period,                // 7 - payroll_period
            $cutoff,                // 8 - payroll_cutoff
            $monthly_salary,        // 9
            $amount_accrued,        // 10
            $other_comp,            // 11
            $gross_amount,          // 12
            $withholding_tax,       // 13
            $pagibig_loan_mpl,      // 14
            $corso_loan,            // 15
            $policy_loan,           // 16
            $philhealth_ps,         // 17
            $uef_retirement,        // 18
            $emergency_loan,        // 19
            $gfal,                  // 20
            $lbp_loan,              // 21
            $mpl,                   // 22
            $mpl_lite,              // 23
            $sss_contribution,      // 24
            $pagibig_cont,          // 25
            $state_ins_gs,          // 26
            $total_deductions,      // 27
            $amount_due,            // 28
            $amount_due,            // 29 - net_amount
            $days_present,          // 30
            22,                     // 31 - working_days
            'pending',              // 32 - status
            date('Y-m-d H:i:s'),    // 33 - processed_date
            'pending',              // 34 - obligation_status
            '(Not signed)'          // 35 - signature_status
            // NOT included: created_at, updated_at (auto-generated)
            // NOT included: approved_by, approved_date (null by default)
            // NOT included: obligation_id (null by default)
          ]);

          $new_id = $pdo->lastInsertId();

          // Insert deductions
          $deduction_types = [];
          if ($withholding_tax > 0) $deduction_types[] = ['Withholding Tax', $withholding_tax];
          if ($pagibig_loan_mpl > 0) $deduction_types[] = ['PAG-IBIG LOAN - MPL', $pagibig_loan_mpl];
          if ($corso_loan > 0) $deduction_types[] = ['Corso Loan', $corso_loan];
          if ($policy_loan > 0) $deduction_types[] = ['Policy Loan', $policy_loan];
          if ($philhealth_ps > 0) $deduction_types[] = ['PhilHealth P.S.', $philhealth_ps];
          if ($uef_retirement > 0) $deduction_types[] = ['UEF / Retirement', $uef_retirement];
          if ($emergency_loan > 0) $deduction_types[] = ['Emergency Loan', $emergency_loan];
          if ($gfal > 0) $deduction_types[] = ['GFAL', $gfal];
          if ($lbp_loan > 0) $deduction_types[] = ['LBP Loan', $lbp_loan];
          if ($mpl > 0) $deduction_types[] = ['MPL', $mpl];
          if ($mpl_lite > 0) $deduction_types[] = ['MPL Lite', $mpl_lite];
          if ($sss_contribution > 0) $deduction_types[] = ['SSS Contribution', $sss_contribution];
          if ($pagibig_cont > 0) $deduction_types[] = ['PAG-IBIG CONT.', $pagibig_cont];
          if ($state_ins_gs > 0) $deduction_types[] = ['STATE INS. G.S.', $state_ins_gs];

          foreach ($deduction_types as $deduction) {
            if ($deduction[1] > 0) {
              $deduction_stmt = $pdo->prepare("
                                INSERT INTO payroll_deductions_permanent 
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
            'amount_due' => $amount_due
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
        $stmt = $pdo->prepare("
                    SELECT 
                        id as user_id, 
                        employee_id, 
                        full_name,
                        position, 
                        office as department,
                        monthly_salary,
                        ctc_number,
                        ctc_date,
                        place_of_issue,
                        mobile_number,
                        email_address,
                        joining_date,
                        eligibility
                    FROM permanent 
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

        // Get data from payroll_history_permanent
        $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
        $daily_rate = $monthly_salary / 22;
        $prorated_salary = $daily_rate * floatval($attendance_data['days_present'] ?? 0);

        $payroll_data = getEmployeePayrollData($pdo, $employee['employee_id'], $employee['user_id'], $period, $cutoff, $prorated_salary);

        // Get payroll history from permanent table
        $payroll_history = [];
        try {
          $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_history_permanent'");
          if ($table_check->rowCount() > 0) {
            $payroll_stmt = $pdo->prepare("
                            SELECT * FROM payroll_history_permanent 
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
            'amount_accrued' => $payroll_data['amount_accrued'],
            'other_comp' => $payroll_data['other_comp'],
            'withholding_tax' => $payroll_data['withholding_tax'],
            'pagibig_loan_mpl' => $payroll_data['pagibig_loan_mpl'],
            'corso_loan' => $payroll_data['corso_loan'],
            'policy_loan' => $payroll_data['policy_loan'],
            'philhealth_ps' => $payroll_data['philhealth_ps'],
            'uef_retirement' => $payroll_data['uef_retirement'],
            'emergency_loan' => $payroll_data['emergency_loan'],
            'gfal' => $payroll_data['gfal'],
            'lbp_loan' => $payroll_data['lbp_loan'],
            'mpl' => $payroll_data['mpl'],
            'mpl_lite' => $payroll_data['mpl_lite'],
            'sss_contribution' => $payroll_data['sss_contribution'],
            'pagibig_cont' => $payroll_data['pagibig_cont'],
            'state_ins_gs' => $payroll_data['state_ins_gs'],
            'total_deductions' => $payroll_data['total_deductions'],
            'amount_due' => $payroll_data['amount_due'],
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

    // View deductions details
    if ($_POST['ajax_action'] === 'view_deductions') {
      $payroll_id = intval($_POST['payroll_id']);

      try {
        $stmt = $pdo->prepare("
                    SELECT deduction_type, deduction_amount, deduction_description, created_at
                    FROM payroll_deductions_permanent
                    WHERE payroll_id = ?
                    ORDER BY deduction_type
                ");
        $stmt->execute([$payroll_id]);
        $deductions = $stmt->fetchAll();

        echo json_encode([
          'success' => true,
          'deductions' => $deductions
        ]);
        exit();
      } catch (Exception $e) {
        error_log("View deductions error: " . $e->getMessage());
        echo json_encode([
          'success' => false,
          'error' => $e->getMessage()
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

    // Process each employee
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
      $monthly_salary = floatval($_POST['monthly_salary'][$index] ?? 0);
      $amount_accrued = floatval($_POST['amount_accrued'][$index] ?? 0);
      $withholding_tax = floatval($_POST['withholding_tax'][$index] ?? 0);
      $pagibig_loan_mpl = floatval($_POST['pagibig_loan_mpl'][$index] ?? 0);
      $corso_loan = floatval($_POST['corso_loan'][$index] ?? 0);
      $policy_loan = floatval($_POST['policy_loan'][$index] ?? 0);
      $philhealth_ps = floatval($_POST['philhealth_ps'][$index] ?? 0);
      $uef_retirement = floatval($_POST['uef_retirement'][$index] ?? 0);
      $emergency_loan = floatval($_POST['emergency_loan'][$index] ?? 0);
      $gfal = floatval($_POST['gfal'][$index] ?? 0);
      $lbp_loan = floatval($_POST['lbp_loan'][$index] ?? 0);
      $mpl = floatval($_POST['mpl'][$index] ?? 0);
      $mpl_lite = floatval($_POST['mpl_lite'][$index] ?? 0);
      $sss_contribution = floatval($_POST['sss_contribution'][$index] ?? 0);
      $pagibig_cont = floatval($_POST['pagibig_cont'][$index] ?? 0);
      $state_ins_gs = floatval($_POST['state_ins_gs'][$index] ?? 0);
      $other_comp = floatval($_POST['other_comp'][$index] ?? 0);
      $gross_amount = floatval($_POST['gross_amount'][$index] ?? 0);
      $total_deductions = floatval($_POST['total_deduction'][$index] ?? 0);
      $amount_due = floatval($_POST['net_amount'][$index] ?? 0);
      $days_present = floatval($_POST['days_present'][$index] ?? 0);

      // Get employee data
      $emp_stmt = $pdo->prepare("SELECT full_name, position, office FROM permanent WHERE id = ? OR employee_id = ?");
      $emp_stmt->execute([$user_id, $employee_id]);
      $emp_data = $emp_stmt->fetch();

      $full_name = $emp_data['full_name'] ?? '';
      $position = $emp_data['position'] ?? '';
      $office = $emp_data['office'] ?? '';

      // Calculate totals if needed
      if ($total_deductions == 0) {
        $total_deductions = $withholding_tax + $pagibig_loan_mpl + $corso_loan + $policy_loan +
          $philhealth_ps + $uef_retirement + $emergency_loan + $gfal +
          $lbp_loan + $mpl + $mpl_lite + $sss_contribution +
          $pagibig_cont + $state_ins_gs;
      }

      if ($gross_amount == 0) {
        $gross_amount = $amount_accrued + $other_comp;
      }

      if ($amount_due == 0 && $gross_amount > 0) {
        $amount_due = $gross_amount - $total_deductions;
        if ($amount_due < 0) $amount_due = 0;
      }

      // Check if record exists in permanent table
      $check_stmt = $pdo->prepare("
                SELECT id FROM payroll_history_permanent 
                WHERE employee_id = ? 
                AND payroll_period = ? AND payroll_cutoff = ?
            ");
      $check_stmt->execute([$employee_id, $payroll_period, $payroll_cutoff]);
      $existing = $check_stmt->fetch();

      if ($existing) {
        // UPDATE existing record
        error_log("UPDATING existing record ID: " . $existing['id'] . " for employee: $employee_id");

        $update_sql = "UPDATE payroll_history_permanent SET 
                    full_name = ?,
                    position = ?,
                    office = ?,
                    monthly_salary = ?,
                    amount_accrued = ?,
                    other_comp = ?,
                    gross_amount = ?,
                    withholding_tax = ?,
                    pagibig_loan_mpl = ?,
                    corso_loan = ?,
                    policy_loan = ?,
                    philhealth_ps = ?,
                    uef_retirement = ?,
                    emergency_loan = ?,
                    gfal = ?,
                    lbp_loan = ?,
                    mpl = ?,
                    mpl_lite = ?,
                    sss_contribution = ?,
                    pagibig_cont = ?,
                    state_ins_gs = ?,
                    total_deductions = ?,
                    amount_due = ?,
                    net_amount = ?,
                    days_present = ?,
                    working_days = ?,
                    status = 'pending',
                    updated_at = NOW()
                    WHERE id = ?";

        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
          $full_name,
          $position,
          $office,
          $monthly_salary,
          $amount_accrued,
          $other_comp,
          $gross_amount,
          $withholding_tax,
          $pagibig_loan_mpl,
          $corso_loan,
          $policy_loan,
          $philhealth_ps,
          $uef_retirement,
          $emergency_loan,
          $gfal,
          $lbp_loan,
          $mpl,
          $mpl_lite,
          $sss_contribution,
          $pagibig_cont,
          $state_ins_gs,
          $total_deductions,
          $amount_due,
          $amount_due,
          $days_present,
          $working_days,
          $existing['id']
        ]);

        // Update deductions
        $delete_deductions = $pdo->prepare("DELETE FROM payroll_deductions_permanent WHERE payroll_id = ?");
        $delete_deductions->execute([$existing['id']]);

        $deduction_types = [];
        if ($withholding_tax > 0) $deduction_types[] = ['Withholding Tax', $withholding_tax];
        if ($pagibig_loan_mpl > 0) $deduction_types[] = ['PAG-IBIG LOAN - MPL', $pagibig_loan_mpl];
        if ($corso_loan > 0) $deduction_types[] = ['Corso Loan', $corso_loan];
        if ($policy_loan > 0) $deduction_types[] = ['Policy Loan', $policy_loan];
        if ($philhealth_ps > 0) $deduction_types[] = ['PhilHealth P.S.', $philhealth_ps];
        if ($uef_retirement > 0) $deduction_types[] = ['UEF / Retirement', $uef_retirement];
        if ($emergency_loan > 0) $deduction_types[] = ['Emergency Loan', $emergency_loan];
        if ($gfal > 0) $deduction_types[] = ['GFAL', $gfal];
        if ($lbp_loan > 0) $deduction_types[] = ['LBP Loan', $lbp_loan];
        if ($mpl > 0) $deduction_types[] = ['MPL', $mpl];
        if ($mpl_lite > 0) $deduction_types[] = ['MPL Lite', $mpl_lite];
        if ($sss_contribution > 0) $deduction_types[] = ['SSS Contribution', $sss_contribution];
        if ($pagibig_cont > 0) $deduction_types[] = ['PAG-IBIG CONT.', $pagibig_cont];
        if ($state_ins_gs > 0) $deduction_types[] = ['STATE INS. G.S.', $state_ins_gs];

        foreach ($deduction_types as $deduction) {
          if ($deduction[1] > 0) {
            $deduction_stmt = $pdo->prepare("
                            INSERT INTO payroll_deductions_permanent 
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
        $insert_sql = "INSERT INTO payroll_history_permanent 
    (employee_id, user_id, employee_type, full_name, position, office, 
     payroll_period, payroll_cutoff,
     monthly_salary, amount_accrued, other_comp, gross_amount,
     withholding_tax, pagibig_loan_mpl, corso_loan, policy_loan,
     philhealth_ps, uef_retirement, emergency_loan, gfal,
     lbp_loan, mpl, mpl_lite, sss_contribution,
     pagibig_cont, state_ins_gs, total_deductions, amount_due, net_amount, 
     days_present, working_days, status, processed_date,
     obligation_status, signature_status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([
          $employee_id,           // 1
          $user_id,               // 2
          'permanent',            // 3
          $full_name,             // 4
          $position,              // 5
          $office,                // 6
          $payroll_period,        // 7
          $payroll_cutoff,        // 8
          $monthly_salary,        // 9
          $amount_accrued,        // 10
          $other_comp,            // 11
          $gross_amount,          // 12
          $withholding_tax,       // 13
          $pagibig_loan_mpl,      // 14
          $corso_loan,            // 15
          $policy_loan,           // 16
          $philhealth_ps,         // 17
          $uef_retirement,        // 18
          $emergency_loan,        // 19
          $gfal,                  // 20
          $lbp_loan,              // 21
          $mpl,                   // 22
          $mpl_lite,              // 23
          $sss_contribution,      // 24
          $pagibig_cont,          // 25
          $state_ins_gs,          // 26
          $total_deductions,      // 27
          $amount_due,            // 28
          $amount_due,            // 29
          $days_present,          // 30
          $working_days,          // 31
          'pending',              // 32
          date('Y-m-d H:i:s'),    // 33
          'pending',              // 34
          '(Not signed)'          // 35
        ]);

        $new_id = $pdo->lastInsertId();
        error_log("Insert successful for employee $employee_id, new ID: $new_id");

        // Insert deductions
        $deduction_types = [];
        if ($withholding_tax > 0) $deduction_types[] = ['Withholding Tax', $withholding_tax];
        if ($pagibig_loan_mpl > 0) $deduction_types[] = ['PAG-IBIG LOAN - MPL', $pagibig_loan_mpl];
        if ($corso_loan > 0) $deduction_types[] = ['Corso Loan', $corso_loan];
        if ($policy_loan > 0) $deduction_types[] = ['Policy Loan', $policy_loan];
        if ($philhealth_ps > 0) $deduction_types[] = ['PhilHealth P.S.', $philhealth_ps];
        if ($uef_retirement > 0) $deduction_types[] = ['UEF / Retirement', $uef_retirement];
        if ($emergency_loan > 0) $deduction_types[] = ['Emergency Loan', $emergency_loan];
        if ($gfal > 0) $deduction_types[] = ['GFAL', $gfal];
        if ($lbp_loan > 0) $deduction_types[] = ['LBP Loan', $lbp_loan];
        if ($mpl > 0) $deduction_types[] = ['MPL', $mpl];
        if ($mpl_lite > 0) $deduction_types[] = ['MPL Lite', $mpl_lite];
        if ($sss_contribution > 0) $deduction_types[] = ['SSS Contribution', $sss_contribution];
        if ($pagibig_cont > 0) $deduction_types[] = ['PAG-IBIG CONT.', $pagibig_cont];
        if ($state_ins_gs > 0) $deduction_types[] = ['STATE INS. G.S.', $state_ins_gs];

        foreach ($deduction_types as $deduction) {
          if ($deduction[1] > 0) {
            $deduction_stmt = $pdo->prepare("
                            INSERT INTO payroll_deductions_permanent 
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

    $update_sql = "UPDATE payroll_history_permanent SET 
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
                    employee_type VARCHAR(50) DEFAULT 'permanent',
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
            VALUES (?, ?, 'permanent', ?, ?, 'approved', NOW())
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
            SELECT * FROM payroll_history_permanent WHERE id = ?
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
                        FOREIGN KEY (payroll_id) REFERENCES payroll_history_permanent(id) ON DELETE CASCADE
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
        $payroll['amount_due'],
        $current_user_name,
        'Payment processed for ' . $period . ' (' . ($cutoff_ranges[$cutoff]['label'] ?? $cutoff) . ')'
      ]);

      $update_stmt = $pdo->prepare("
                UPDATE payroll_history_permanent 
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

// Get total count of employees for pagination with search
$total_employees = 0;
try {
  $count_sql = "SELECT COUNT(DISTINCT id) FROM permanent WHERE (status = 'Active' OR status IS NULL)";
  if (!empty($search_term)) {
    $count_sql .= " AND (employee_id LIKE :search OR full_name LIKE :search OR position LIKE :search OR office LIKE :search)";
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

// Fetch permanent employees from permanent table with pagination and search
$permanent_employees = [];

try {
  $table_check = $pdo->query("SHOW TABLES LIKE 'permanent'");
  if ($table_check->rowCount() == 0) {
    throw new Exception("permanent table does not exist");
  }

  $sql = "
        SELECT DISTINCT
            id as user_id, 
            employee_id, 
            full_name,
            position, 
            office as department,
            monthly_salary,
            mobile_number,
            email_address,
            joining_date,
            eligibility
        FROM permanent 
        WHERE (status = 'Active' OR status IS NULL)
    ";

  if (!empty($search_term)) {
    $sql .= " AND (employee_id LIKE :search OR full_name LIKE :search OR position LIKE :search OR office LIKE :search)";
  }

  $sql .= " ORDER BY full_name LIMIT :offset, :per_page";

  $stmt = $pdo->prepare($sql);

  if (!empty($search_term)) {
    $search_param = "%$search_term%";
    $stmt->bindParam(':search', $search_param);
  }
  $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
  $stmt->bindParam(':per_page', $records_per_page, PDO::PARAM_INT);
  $stmt->execute();
  $employees = $stmt->fetchAll();

  // Get attendance and payroll data for each employee
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

    $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
    $prorated_salary = ($monthly_salary / 22) * $attendance_days;

    // Get payroll data from database
    $payroll_data = getEmployeePayrollData($pdo, $employee['employee_id'], $employee['user_id'], $selected_period, $selected_cutoff, $prorated_salary);

    $employee['other_comp'] = $payroll_data['other_comp'];
    $employee['withholding_tax'] = $payroll_data['withholding_tax'];
    $employee['pagibig_loan_mpl'] = $payroll_data['pagibig_loan_mpl'];
    $employee['corso_loan'] = $payroll_data['corso_loan'];
    $employee['policy_loan'] = $payroll_data['policy_loan'];
    $employee['philhealth_ps'] = $payroll_data['philhealth_ps'];
    $employee['uef_retirement'] = $payroll_data['uef_retirement'];
    $employee['emergency_loan'] = $payroll_data['emergency_loan'];
    $employee['gfal'] = $payroll_data['gfal'];
    $employee['lbp_loan'] = $payroll_data['lbp_loan'];
    $employee['mpl'] = $payroll_data['mpl'];
    $employee['mpl_lite'] = $payroll_data['mpl_lite'];
    $employee['sss_contribution'] = $payroll_data['sss_contribution'];
    $employee['pagibig_cont'] = $payroll_data['pagibig_cont'];
    $employee['state_ins_gs'] = $payroll_data['state_ins_gs'];
    $employee['total_deductions'] = $payroll_data['total_deductions'];
    $employee['net_amount'] = $payroll_data['net_amount'];
    $employee['amount_due'] = $payroll_data['amount_due'];
    $employee['gross_amount'] = $payroll_data['gross_amount'] > 0 ? $payroll_data['gross_amount'] : $prorated_salary + $payroll_data['other_comp'];
    $employee['amount_accrued'] = $payroll_data['amount_accrued'] > 0 ? $payroll_data['amount_accrued'] : $prorated_salary;
    $employee['monthly_salary'] = $monthly_salary;
    $employee['daily_rate'] = $monthly_salary / 22;
    $employee['prorated_salary'] = $prorated_salary;
    $employee['payroll_status'] = $payroll_data['status'];
    $employee['payroll_id'] = $payroll_data['payroll_id'];
    $employee['payroll_exists'] = $payroll_data['exists'];
    $employee['signature_status'] = $payroll_data['signature_status'];

    $processed_employees[] = $employee;
  }

  $permanent_employees = $processed_employees;
} catch (Exception $e) {
  error_log("Database error: " . $e->getMessage());
  $_SESSION['error_message'] = "Error fetching employees: " . $e->getMessage();
  $permanent_employees = [];
}

// Calculate totals
$total_amount_accrued = 0;
$total_other_comp = 0;
$total_gross_amount = 0;
$total_withholding_tax = 0;
$total_pagibig_loan_mpl = 0;
$total_corso_loan = 0;
$total_policy_loan = 0;
$total_philhealth_ps = 0;
$total_uef_retirement = 0;
$total_emergency_loan = 0;
$total_gfal = 0;
$total_lbp_loan = 0;
$total_mpl = 0;
$total_mpl_lite = 0;
$total_sss_contribution = 0;
$total_pagibig_cont = 0;
$total_state_ins_gs = 0;
$total_deductions = 0;
$total_amount_due = 0;
$total_days_present = 0;
$employees_with_attendance = 0;

foreach ($permanent_employees as $employee) {
  $total_amount_accrued += floatval($employee['amount_accrued'] ?? 0);
  $total_other_comp += floatval($employee['other_comp'] ?? 0);
  $total_gross_amount += floatval($employee['gross_amount'] ?? 0);
  $days = floatval($employee['days_present'] ?? 0);
  $total_days_present += $days;
  if ($days > 0) $employees_with_attendance++;

  $total_withholding_tax += floatval($employee['withholding_tax'] ?? 0);
  $total_pagibig_loan_mpl += floatval($employee['pagibig_loan_mpl'] ?? 0);
  $total_corso_loan += floatval($employee['corso_loan'] ?? 0);
  $total_policy_loan += floatval($employee['policy_loan'] ?? 0);
  $total_philhealth_ps += floatval($employee['philhealth_ps'] ?? 0);
  $total_uef_retirement += floatval($employee['uef_retirement'] ?? 0);
  $total_emergency_loan += floatval($employee['emergency_loan'] ?? 0);
  $total_gfal += floatval($employee['gfal'] ?? 0);
  $total_lbp_loan += floatval($employee['lbp_loan'] ?? 0);
  $total_mpl += floatval($employee['mpl'] ?? 0);
  $total_mpl_lite += floatval($employee['mpl_lite'] ?? 0);
  $total_sss_contribution += floatval($employee['sss_contribution'] ?? 0);
  $total_pagibig_cont += floatval($employee['pagibig_cont'] ?? 0);
  $total_state_ins_gs += floatval($employee['state_ins_gs'] ?? 0);
  $total_deductions += floatval($employee['total_deductions'] ?? 0);
  $total_amount_due += floatval($employee['amount_due'] ?? 0);
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
                employee_type VARCHAR(50) DEFAULT 'permanent',
                total_employees INT DEFAULT 0,
                total_days_present DECIMAL(10,2) DEFAULT 0,
                total_amount_accrued DECIMAL(10,2) DEFAULT 0,
                total_other_comp DECIMAL(10,2) DEFAULT 0,
                total_gross_amount DECIMAL(10,2) DEFAULT 0,
                total_withholding_tax DECIMAL(10,2) DEFAULT 0,
                total_pagibig_loan_mpl DECIMAL(10,2) DEFAULT 0,
                total_corso_loan DECIMAL(10,2) DEFAULT 0,
                total_policy_loan DECIMAL(10,2) DEFAULT 0,
                total_philhealth_ps DECIMAL(10,2) DEFAULT 0,
                total_uef_retirement DECIMAL(10,2) DEFAULT 0,
                total_emergency_loan DECIMAL(10,2) DEFAULT 0,
                total_gfal DECIMAL(10,2) DEFAULT 0,
                total_lbp_loan DECIMAL(10,2) DEFAULT 0,
                total_mpl DECIMAL(10,2) DEFAULT 0,
                total_mpl_lite DECIMAL(10,2) DEFAULT 0,
                total_sss_contribution DECIMAL(10,2) DEFAULT 0,
                total_pagibig_cont DECIMAL(10,2) DEFAULT 0,
                total_state_ins_gs DECIMAL(10,2) DEFAULT 0,
                total_deductions DECIMAL(10,2) DEFAULT 0,
                total_amount_due DECIMAL(10,2) DEFAULT 0,
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
                COALESCE(SUM(amount_accrued), 0) as total_amount_accrued,
                COALESCE(SUM(other_comp), 0) as total_other_comp,
                COALESCE(SUM(gross_amount), 0) as total_gross_amount,
                COALESCE(SUM(withholding_tax), 0) as total_withholding_tax,
                COALESCE(SUM(pagibig_loan_mpl), 0) as total_pagibig_loan_mpl,
                COALESCE(SUM(corso_loan), 0) as total_corso_loan,
                COALESCE(SUM(policy_loan), 0) as total_policy_loan,
                COALESCE(SUM(philhealth_ps), 0) as total_philhealth_ps,
                COALESCE(SUM(uef_retirement), 0) as total_uef_retirement,
                COALESCE(SUM(emergency_loan), 0) as total_emergency_loan,
                COALESCE(SUM(gfal), 0) as total_gfal,
                COALESCE(SUM(lbp_loan), 0) as total_lbp_loan,
                COALESCE(SUM(mpl), 0) as total_mpl,
                COALESCE(SUM(mpl_lite), 0) as total_mpl_lite,
                COALESCE(SUM(sss_contribution), 0) as total_sss_contribution,
                COALESCE(SUM(pagibig_cont), 0) as total_pagibig_cont,
                COALESCE(SUM(state_ins_gs), 0) as total_state_ins_gs,
                COALESCE(SUM(total_deductions), 0) as total_deductions,
                COALESCE(SUM(amount_due), 0) as total_amount_due
            FROM payroll_history_permanent 
            WHERE payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half')
        ");
    $summary_stmt->execute([$selected_period]);
    $payroll_summary = $summary_stmt->fetch();
  } else {
    $summary_stmt = $pdo->prepare("
            SELECT * FROM payroll_summary 
            WHERE payroll_period = ? AND payroll_cutoff = ? AND employee_type = 'permanent'
        ");
    $summary_stmt->execute([$selected_period, $selected_cutoff]);
    $payroll_summary = $summary_stmt->fetch();

    if (!$payroll_summary) {
      $calc_stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT employee_id) as total_employees,
                    COALESCE(SUM(days_present), 0) as total_days_present,
                    COALESCE(SUM(amount_accrued), 0) as total_amount_accrued,
                    COALESCE(SUM(other_comp), 0) as total_other_comp,
                    COALESCE(SUM(gross_amount), 0) as total_gross_amount,
                    COALESCE(SUM(withholding_tax), 0) as total_withholding_tax,
                    COALESCE(SUM(pagibig_loan_mpl), 0) as total_pagibig_loan_mpl,
                    COALESCE(SUM(corso_loan), 0) as total_corso_loan,
                    COALESCE(SUM(policy_loan), 0) as total_policy_loan,
                    COALESCE(SUM(philhealth_ps), 0) as total_philhealth_ps,
                    COALESCE(SUM(uef_retirement), 0) as total_uef_retirement,
                    COALESCE(SUM(emergency_loan), 0) as total_emergency_loan,
                    COALESCE(SUM(gfal), 0) as total_gfal,
                    COALESCE(SUM(lbp_loan), 0) as total_lbp_loan,
                    COALESCE(SUM(mpl), 0) as total_mpl,
                    COALESCE(SUM(mpl_lite), 0) as total_mpl_lite,
                    COALESCE(SUM(sss_contribution), 0) as total_sss_contribution,
                    COALESCE(SUM(pagibig_cont), 0) as total_pagibig_cont,
                    COALESCE(SUM(state_ins_gs), 0) as total_state_ins_gs,
                    COALESCE(SUM(total_deductions), 0) as total_deductions,
                    COALESCE(SUM(amount_due), 0) as total_amount_due
                FROM payroll_history_permanent 
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
  $table_check = $pdo->query("SHOW TABLES LIKE 'payroll_history_permanent'");
  if ($table_check->rowCount() > 0) {
    if ($selected_cutoff == 'full') {
      $status_stmt = $pdo->prepare("
                SELECT DISTINCT status FROM payroll_history_permanent 
                WHERE payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half')
                LIMIT 1
            ");
    } else {
      $status_stmt = $pdo->prepare("
                SELECT DISTINCT status FROM payroll_history_permanent 
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
                WHERE payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half') AND employee_type = 'permanent'
                ORDER BY approved_date DESC
            ");
    } else {
      $approval_stmt = $pdo->prepare("
                SELECT * FROM payroll_approvals 
                WHERE payroll_period = ? AND payroll_cutoff = ? AND employee_type = 'permanent'
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
  <title>Permanent Payroll | HR Management System</title>
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
      min-width: 2000px;
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

    /* Checkbox styling */
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

    /* Print actions bar */
    .print-actions {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
      padding: 0.5rem;
      background-color: #f9fafb;
      border-radius: 0.5rem;
      border: 1px solid #e5e7eb;
    }

    .selected-count {
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--primary);
      background-color: #eff6ff;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
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

    /* Full month disabled field styling */
    .full-month-disabled {
      background-color: #f0f0f0 !important;
      color: #888 !important;
      cursor: not-allowed !important;
      border-color: #e0e0e0 !important;
    }

    .full-month-disabled:hover {
      border-color: #e0e0e0 !important;
    }

    .full-month-disabled:focus {
      outline: none !important;
      box-shadow: none !important;
      border-color: #e0e0e0 !important;
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

    .alert-info {
      background-color: #dbeafe;
      color: #1e40af;
      border: 1px solid #bfdbfe;
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

    /* Print button styles */
    .print-btn {
      background-color: #10b981;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .print-btn:hover {
      background-color: #059669;
      transform: translateY(-1px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .print-btn:disabled {
      background-color: #9ca3af;
      cursor: not-allowed;
      transform: none;
    }

    .print-btn i {
      font-size: 1rem;
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
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .stat-card {
      background: white;
      border-radius: 0.5rem;
      padding: 1rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      display: flex;
      align-items: center;
      gap: 1rem;
      transition: all 0.3s ease;
    }

    .stat-card:hover {
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      transform: translateY(-2px);
    }

    .stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
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

    .stat-content {
      flex: 1;
    }

    .stat-label {
      font-size: 0.75rem;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      font-weight: 600;
    }

    .stat-value {
      font-size: 1.25rem;
      font-weight: 700;
      color: #1f2937;
      line-height: 1.2;
    }

    .stat-desc {
      font-size: 0.7rem;
      color: #9ca3af;
      margin-top: 0.25rem;
    }

    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
      }
    }

    @media (max-width: 480px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
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

    /* Header action bar */
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

    .action-buttons-wrapper {
      display: flex;
      gap: 0.5rem;
      justify-content: flex-end;
    }

    /* PAGINATION STYLES */
    .pagination-container {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      background: white;
      border-top: 1px solid #e5e7eb;
    }

    @media (min-width: 640px) {
      .pagination-container {
        flex-direction: row;
      }
    }

    .pagination-info {
      font-size: 0.875rem;
      color: #6b7280;
    }

    .pagination-controls {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

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
      background-color: white;
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      transition: all 0.2s ease;
      cursor: pointer;
      text-decoration: none;
    }

    .pagination-btn:hover:not(:disabled):not(.active) {
      background-color: #f3f4f6;
      border-color: #9ca3af;
      color: #374151;
    }

    .pagination-btn.active {
      background-color: #3b82f6;
      border-color: #3b82f6;
      color: white;
    }

    .pagination-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .pagination-ellipsis {
      border: none;
      background: none;
      cursor: default;
      min-width: auto;
      padding: 0 0.25rem;
    }

    .pagination-ellipsis:hover {
      background: none;
      transform: none;
    }

    /* Records per page selector */
    .per-page-selector {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      background-color: white;
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

    .per-page-selector select:focus {
      ring: none;
    }

    /* Search container */
    .search-container {
      position: relative;
      width: 100%;
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
      pointer-events: none;
    }

    /* Table footer */
    .table-footer {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      background: #f9fafb;
      border-top: 1px solid #e5e7eb;
    }

    @media (min-width: 768px) {
      .table-footer {
        flex-direction: row;
      }
    }

    /* Permanent specific colors */
    .bg-permanent {
      background-color: #e6f0fa;
    }

    .text-permanent {
      color: #1e3a8a;
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
            <span class="mobile-brand-subtitle">Permanent</span>
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
          <a href="#" class="sidebar-item" id="payroll-toggle">
            <i class="fas fa-money-bill-wave"></i>
            <span>Payroll</span>
            <i class="fas fa-chevron-down chevron text-xs ml-auto"></i>
          </a>
          <div class="submenu" id="payroll-submenu">
            <a href="../Payrollmanagement/contractualpayrolltable1.php" class="submenu-item">
              <i class="fas fa-circle text-xs"></i>
              Contractual
            </a>
            <a href="../Payrollmanagement/joborderpayrolltable1.php" class="submenu-item">
              <i class="fas fa-circle text-xs"></i>
              Job Order
            </a>
            <a href="permanentpayrolltable1.php" class="submenu-item active">
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

  <!-- Main Content -->
  <main>
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
            <strong>Permanent Payroll Rules:</strong>
            Amount Due = Amount Accrued - Total Deductions.
            <span class="font-bold">Deductions include: Withholding Tax, PAG-IBIG Loan, Corso Loan, Policy Loan, PhilHealth, UEF/Retirement, Emergency Loan, GFAL, LBP Loan, MPL, MPL Lite, SSS Contribution, PAG-IBIG Cont., and State Ins. GS.</span>
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

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-2">
        <li class="inline-flex items-center">
          <a href="permanentpayrolltable1.php" class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-700">
            <i class="fas fa-home mr-2"></i> Permanent Payroll
          </a>
        </li>
        <li>
          <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
            <a href="permanent_payroll_obligation.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-primary-600 md:ml-2">Payroll & Obligation Request</a>
          </div>
        </li>
      </ol>
    </nav>

    <!-- Page Header with Title and Controls -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-4">
      <!-- Left side - Title -->
      <div class="flex items-center gap-3">
        <div class="bg-blue-100 p-2 rounded-lg">
          <i class="fas fa-file-invoice text-blue-600 text-lg"></i>
        </div>
        <div>
          <h1 class="text-xl font-bold text-gray-900">Permanent Payroll</h1>
          <p class="text-xs text-gray-500">Manage permanent employee payroll</p>
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

        <!-- Print Button -->
        <div class="flex items-center gap-1">
          <button id="print-selected-btn" class="print-btn" onclick="printSelectedPayslips()" disabled>
            <i class="fas fa-print"></i>
            <span class="hidden sm:inline">Print Payslips</span>
            <span id="selected-count-badge" class="ml-1 px-1.5 py-0.5 bg-white text-blue-700 rounded-full text-xs font-bold hidden">0</span>
          </button>
        </div>
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
        <div class="stat-content">
          <div class="stat-label">Total Employees</div>
          <div class="stat-value"><?php echo $total_employees; ?></div>
          <div class="stat-desc">Active permanent</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon green">
          <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
          <div class="stat-label">With Attendance</div>
          <div class="stat-value"><?php echo $employees_with_attendance; ?></div>
          <div class="stat-desc"><?php echo $total_employees - $employees_with_attendance; ?> without</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon purple">
          <i class="fas fa-hand-holding-usd"></i>
        </div>
        <div class="stat-content">
          <div class="stat-label">Total Deductions</div>
          <div class="stat-value" id="total-deductions-display">₱<?php echo number_format($total_deductions, 2); ?></div>
          <div class="stat-desc">All deduction types</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon orange">
          <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-content">
          <div class="stat-label">Amount Due</div>
          <div class="stat-value" id="amount-due-display">₱<?php echo number_format($total_amount_due, 2); ?></div>
          <div class="stat-desc">Total net pay</div>
        </div>
      </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="tabs">
      <div class="tab active" data-tab="payroll">Payroll Details</div>
      <div class="tab" data-tab="summary">Payroll Summary</div>
    </div>

    <!-- Payroll Details Tab -->
    <div class="tab-content active" id="tab-payroll">
      <!-- Search Bar -->
      <div class="mb-4 flex justify-between items-center">
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
              Permanent Payroll Details for <?php echo date('F Y', strtotime($selected_period . '-01')); ?>
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
                  <th rowspan="2" class="min-w-[100px]">Monthly Salary</th>
                  <th rowspan="2" class="min-w-[100px]">Amount Accrued</th>
                  <th colspan="14" class="text-center bg-yellow-50">DEDUCTIONS</th>
                  <th colspan="4" class="text-center bg-green-50">ADDITIONAL</th>
                  <th rowspan="2" class="min-w-[100px] bg-gray-100">Net Amount</th>
                  <th rowspan="2" class="min-w-[120px]">Signature of Payee</th>
                  <th rowspan="2" class="min-w-[180px]">Actions</th>
                </tr>
                <tr>
                  <th class="min-w-[90px] bg-yellow-50">Withholding Tax</th>
                  <th class="min-w-[90px] bg-yellow-50">PAG-IBIG LOAN - MPL</th>
                  <th class="min-w-[90px] bg-yellow-50">Corso Loan</th>
                  <th class="min-w-[90px] bg-yellow-50">Policy Loan</th>
                  <th class="min-w-[90px] bg-yellow-50">PhilHealth P.S.</th>
                  <th class="min-w-[90px] bg-yellow-50">UEF / Retirement</th>
                  <th class="min-w-[90px] bg-yellow-50">Emergency Loan</th>
                  <th class="min-w-[90px] bg-yellow-50">GFAL</th>
                  <th class="min-w-[90px] bg-yellow-50">LBP Loan</th>
                  <th class="min-w-[90px] bg-yellow-50">MPL</th>
                  <th class="min-w-[90px] bg-yellow-50">MPL Lite</th>
                  <th class="min-w-[90px] bg-yellow-50">SSS Contribution</th>
                  <th class="min-w-[90px] bg-green-50">PAG-IBIG CONT.</th>
                  <th class="min-w-[90px] bg-green-50">STATE INS. G.S.</th>
                  <th class="min-w-[90px] bg-yellow-50">Total Deductions</th>
                  <th class="min-w-[90px] bg-green-50">Amount Due</th>
                  <th class="min-w-[90px] bg-green-50">No.</th>
                </tr>
              </thead>
              <tbody id="payroll-tbody">
                <?php if (empty($permanent_employees)): ?>
                  <tr>
                    <td colspan="27" class="text-center py-8 text-gray-500">
                      No permanent employees found.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php $counter = $offset + 1; ?>
                  <?php foreach ($permanent_employees as $employee): ?>
                    <?php
                    $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
                    $amount_accrued = floatval($employee['amount_accrued'] ?? 0);
                    $other_comp = floatval($employee['other_comp'] ?? 0);
                    $days_present = floatval($employee['days_present'] ?? 0);

                    // Deduction fields
                    $withholding_tax = floatval($employee['withholding_tax'] ?? 0);
                    $pagibig_loan_mpl = floatval($employee['pagibig_loan_mpl'] ?? 0);
                    $corso_loan = floatval($employee['corso_loan'] ?? 0);
                    $policy_loan = floatval($employee['policy_loan'] ?? 0);
                    $philhealth_ps = floatval($employee['philhealth_ps'] ?? 0);
                    $uef_retirement = floatval($employee['uef_retirement'] ?? 0);
                    $emergency_loan = floatval($employee['emergency_loan'] ?? 0);
                    $gfal = floatval($employee['gfal'] ?? 0);
                    $lbp_loan = floatval($employee['lbp_loan'] ?? 0);
                    $mpl = floatval($employee['mpl'] ?? 0);
                    $mpl_lite = floatval($employee['mpl_lite'] ?? 0);
                    $sss_contribution = floatval($employee['sss_contribution'] ?? 0);

                    // Additional fields
                    $pagibig_cont = floatval($employee['pagibig_cont'] ?? 0);
                    $state_ins_gs = floatval($employee['state_ins_gs'] ?? 0);

                    $total_deductions_row = floatval($employee['total_deductions'] ?? 0);
                    $amount_due_row = floatval($employee['amount_due'] ?? 0);
                    $payroll_id = $employee['payroll_id'] ?? null;
                    $payroll_exists = $employee['payroll_exists'] ?? false;
                    $signature_status = $employee['signature_status'] ?? '(Not signed)';

                    $has_attendance = ($days_present > 0);
                    $row_class = $has_attendance ? '' : 'no-attendance';

                    $full_month_readonly = $is_full_month ? 'readonly disabled' : '';
                    $full_month_style = $is_full_month ? 'background-color: #f0f0f0; color: #888; cursor: not-allowed;' : '';
                    ?>
                    <tr class="bg-white border-b hover:bg-gray-50 payroll-row <?php echo $row_class; ?>"
                      data-user-id="<?php echo $employee['user_id']; ?>"
                      data-employee-id="<?php echo htmlspecialchars($employee['employee_id']); ?>"
                      data-payroll-id="<?php echo $payroll_id; ?>"
                      data-payroll-exists="<?php echo $payroll_exists ? '1' : '0'; ?>">

                      <td class="text-center">
                        <input type="checkbox" class="employee-checkbox" value="<?php echo htmlspecialchars($employee['employee_id']); ?>"
                          data-employee-name="<?php echo htmlspecialchars($employee['full_name']); ?>"
                          data-user-id="<?php echo $employee['user_id']; ?>">
                      </td>
                      <td class="text-center"><?php echo $counter; ?></td>
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
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="monthly_salary[]"
                          class="payroll-input readonly disabled-field"
                          value="<?php echo number_format($monthly_salary, 2, '.', ''); ?>"
                          readonly disabled tabindex="-1">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="amount_accrued[]"
                          class="payroll-input readonly disabled-field"
                          value="<?php echo number_format($amount_accrued, 2, '.', ''); ?>"
                          readonly disabled tabindex="-1">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="withholding_tax[]"
                          class="payroll-input withholding-tax <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($withholding_tax, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="withholding_tax"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="pagibig_loan_mpl[]"
                          class="payroll-input pagibig-loan-mpl <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($pagibig_loan_mpl, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="pagibig_loan_mpl"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="corso_loan[]"
                          class="payroll-input corso-loan <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($corso_loan, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="corso_loan"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="policy_loan[]"
                          class="payroll-input policy-loan <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($policy_loan, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="policy_loan"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="philhealth_ps[]"
                          class="payroll-input philhealth-ps <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($philhealth_ps, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="philhealth_ps"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="uef_retirement[]"
                          class="payroll-input uef-retirement <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($uef_retirement, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="uef_retirement"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="emergency_loan[]"
                          class="payroll-input emergency-loan <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($emergency_loan, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="emergency_loan"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="gfal[]"
                          class="payroll-input gfal <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($gfal, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="gfal"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="lbp_loan[]"
                          class="payroll-input lbp-loan <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($lbp_loan, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="lbp_loan"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="mpl[]"
                          class="payroll-input mpl <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($mpl, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="mpl"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="mpl_lite[]"
                          class="payroll-input mpl-lite <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($mpl_lite, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="mpl_lite"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="sss_contribution[]"
                          class="payroll-input sss-contribution <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($sss_contribution, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="sss_contribution"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="pagibig_cont[]"
                          class="payroll-input pagibig-cont <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($pagibig_cont, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="pagibig_cont"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="state_ins_gs[]"
                          class="payroll-input state-ins-gs <?php echo $is_full_month ? 'readonly disabled-field full-month-disabled' : 'editable auto-save-field'; ?>"
                          value="<?php echo number_format($state_ins_gs, 2, '.', ''); ?>"
                          min="0" step="0.01"
                          data-user-id="<?php echo $employee['user_id']; ?>"
                          data-field="state_ins_gs"
                          <?php echo $full_month_readonly; ?>
                          style="<?php echo $full_month_style; ?>">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="total_deduction[]"
                          class="payroll-input total-deduction readonly disabled-field"
                          value="<?php echo number_format($total_deductions_row, 2, '.', ''); ?>"
                          readonly disabled tabindex="-1">
                      </td>
                      <td class="text-right currency-cell">
                        <input type="number"
                          name="amount_due[]"
                          class="payroll-input amount-due readonly disabled-field"
                          value="<?php echo number_format($amount_due_row, 2, '.', ''); ?>"
                          readonly disabled tabindex="-1">
                      </td>
                      <td class="text-center"><?php echo $counter; ?></td>
                      <td class="text-right currency-cell net-amount-field important-column">
                        <input type="number"
                          name="net_amount[]"
                          class="payroll-input net-amount readonly disabled-field <?php echo ($amount_due_row <= 0) ? 'zero-amount' : ''; ?>"
                          value="<?php echo number_format($amount_due_row, 2, '.', ''); ?>"
                          readonly disabled tabindex="-1">
                      </td>
                      <td class="italic text-gray-500 signature-status"><?php echo htmlspecialchars($signature_status); ?></td>
                      <td class="text-center action-cell">
                        <div class="action-buttons">
                          <button type="button" class="action-btn view-btn" onclick="viewEmployeeData(<?php echo $employee['user_id']; ?>, '<?php echo $selected_period; ?>', '<?php echo $selected_cutoff; ?>')">
                            <i class="fas fa-eye"></i> <span class="hidden md:inline">View</span>
                          </button>
                          <?php if (!$is_full_month && ($payroll_status == 'pending' || $payroll_status == 'draft')): ?>
                            <button type="button" class="action-btn bg-green-500 text-white hover:bg-green-600 calculate-row" onclick="calculateSingleRow(this)">
                              <i class="fas fa-calculator"></i> <span class="hidden md:inline">Calc</span>
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
                    <?php $counter++; ?>
                  <?php endforeach; ?>
                <?php endif; ?>

                <!-- Totals Row -->
                <tr class="bg-gray-100 font-bold">
                  <td></td>
                  <td colspan="9" class="text-right">TOTAL AMOUNT</td>
                  <td class="text-right" id="total-withholding-tax">₱<?php echo number_format($total_withholding_tax, 2); ?></td>
                  <td class="text-right" id="total-pagibig-loan-mpl">₱<?php echo number_format($total_pagibig_loan_mpl, 2); ?></td>
                  <td class="text-right" id="total-corso-loan">₱<?php echo number_format($total_corso_loan, 2); ?></td>
                  <td class="text-right" id="total-policy-loan">₱<?php echo number_format($total_policy_loan, 2); ?></td>
                  <td class="text-right" id="total-philhealth-ps">₱<?php echo number_format($total_philhealth_ps, 2); ?></td>
                  <td class="text-right" id="total-uef-retirement">₱<?php echo number_format($total_uef_retirement, 2); ?></td>
                  <td class="text-right" id="total-emergency-loan">₱<?php echo number_format($total_emergency_loan, 2); ?></td>
                  <td class="text-right" id="total-gfal">₱<?php echo number_format($total_gfal, 2); ?></td>
                  <td class="text-right" id="total-lbp-loan">₱<?php echo number_format($total_lbp_loan, 2); ?></td>
                  <td class="text-right" id="total-mpl">₱<?php echo number_format($total_mpl, 2); ?></td>
                  <td class="text-right" id="total-mpl-lite">₱<?php echo number_format($total_mpl_lite, 2); ?></td>
                  <td class="text-right" id="total-sss-contribution">₱<?php echo number_format($total_sss_contribution, 2); ?></td>
                  <td class="text-right" id="total-pagibig-cont">₱<?php echo number_format($total_pagibig_cont, 2); ?></td>
                  <td class="text-right" id="total-state-ins-gs">₱<?php echo number_format($total_state_ins_gs, 2); ?></td>
                  <td class="text-right" id="total-deduction">₱<?php echo number_format($total_deductions, 2); ?></td>
                  <td class="text-right" id="total-amount-due">₱<?php echo number_format($total_amount_due, 2); ?></td>
                  <td colspan="2"></td>
                  <td class="text-right important-column" id="total-net-amount">₱<?php echo number_format($total_amount_due, 2); ?></td>
                  <td colspan="2"></td>
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

        <?php if ($payroll_summary && ($payroll_summary['total_gross_amount'] > 0 || $payroll_summary['total_amount_due'] > 0)): ?>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <div class="summary-card">
                <h3 class="text-lg opacity-90">Total Amount Due</h3>
                <div class="amount">₱<?php echo number_format($payroll_summary['total_amount_due'] ?? 0, 2); ?></div>
              </div>

              <div class="mt-4 space-y-3">
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                  <span class="font-medium">Total Employees:</span>
                  <span class="font-bold"><?php echo $payroll_summary['total_employees'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                  <span class="font-medium">Total Days Present:</span>
                  <span class="font-bold"><?php echo number_format($payroll_summary['total_days_present'] ?? 0, 1); ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                  <span class="font-medium">Total Amount Accrued:</span>
                  <span class="font-bold">₱<?php echo number_format($payroll_summary['total_amount_accrued'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                  <span class="font-medium">Total Other Compensation:</span>
                  <span class="font-bold">₱<?php echo number_format($payroll_summary['total_other_comp'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                  <span class="font-medium">Total Gross Amount:</span>
                  <span class="font-bold">₱<?php echo number_format($payroll_summary['total_gross_amount'] ?? 0, 2); ?></span>
                </div>
              </div>
            </div>

            <div>
              <h3 class="text-lg font-semibold mb-3">Deductions Breakdown</h3>
              <div class="space-y-2">
                <div class="flex justify-between items-center p-2 bg-red-50 rounded text-sm">
                  <span class="font-medium">Withholding Tax:</span>
                  <span class="font-bold text-red-600">₱<?php echo number_format($payroll_summary['total_withholding_tax'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 bg-blue-50 rounded text-sm">
                  <span class="font-medium">PAG-IBIG LOAN - MPL:</span>
                  <span class="font-bold text-blue-600">₱<?php echo number_format($payroll_summary['total_pagibig_loan_mpl'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 bg-purple-50 rounded text-sm">
                  <span class="font-medium">Corso Loan:</span>
                  <span class="font-bold text-purple-600">₱<?php echo number_format($payroll_summary['total_corso_loan'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 bg-indigo-50 rounded text-sm">
                  <span class="font-medium">Policy Loan:</span>
                  <span class="font-bold text-indigo-600">₱<?php echo number_format($payroll_summary['total_policy_loan'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 bg-green-50 rounded text-sm">
                  <span class="font-medium">PhilHealth P.S.:</span>
                  <span class="font-bold text-green-600">₱<?php echo number_format($payroll_summary['total_philhealth_ps'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 bg-yellow-50 rounded text-sm">
                  <span class="font-medium">UEF / Retirement:</span>
                  <span class="font-bold text-yellow-600">₱<?php echo number_format($payroll_summary['total_uef_retirement'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 bg-orange-50 rounded text-sm">
                  <span class="font-medium">Emergency Loan:</span>
                  <span class="font-bold text-orange-600">₱<?php echo number_format($payroll_summary['total_emergency_loan'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 bg-pink-50 rounded text-sm">
                  <span class="font-medium">GFAL:</span>
                  <span class="font-bold text-pink-600">₱<?php echo number_format($payroll_summary['total_gfal'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 bg-teal-50 rounded text-sm">
                  <span class="font-medium">LBP Loan:</span>
                  <span class="font-bold text-teal-600">₱<?php echo number_format($payroll_summary['total_lbp_loan'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 bg-gray-50 rounded text-sm">
                  <span class="font-medium">MPL:</span>
                  <span class="font-bold text-gray-600">₱<?php echo number_format($payroll_summary['total_mpl'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 bg-gray-100 rounded text-sm">
                  <span class="font-medium">MPL Lite:</span>
                  <span class="font-bold">₱<?php echo number_format($payroll_summary['total_mpl_lite'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 bg-blue-100 rounded text-sm">
                  <span class="font-medium">SSS Contribution:</span>
                  <span class="font-bold">₱<?php echo number_format($payroll_summary['total_sss_contribution'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 bg-green-100 rounded text-sm">
                  <span class="font-medium">PAG-IBIG CONT.:</span>
                  <span class="font-bold">₱<?php echo number_format($payroll_summary['total_pagibig_cont'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 bg-purple-100 rounded text-sm">
                  <span class="font-medium">STATE INS. G.S.:</span>
                  <span class="font-bold">₱<?php echo number_format($payroll_summary['total_state_ins_gs'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-orange-100 rounded font-bold mt-2">
                  <span class="font-bold">Total Deductions:</span>
                  <span class="font-bold text-orange-700">₱<?php echo number_format($payroll_summary['total_deductions'] ?? 0, 2); ?></span>
                </div>
              </div>
            </div>
          </div>
        <?php else: ?>
          <p class="text-gray-500 text-center py-8">No payroll summary available for this period and cutoff.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- View Employee Modal -->
    <div class="modal" id="viewModal">
      <div class="modal-content large">
        <div class="modal-header">
          <h3 class="text-lg font-semibold">Employee Payroll Details</h3>
          <button onclick="closeModal('viewModal')" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body" id="viewModalBody">
          <!-- Content will be loaded dynamically -->
          <div class="text-center py-4">
            <div class="spinner mx-auto"></div>
            <p class="mt-2 text-gray-600">Loading employee details...</p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" onclick="closeModal('viewModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
            Close
          </button>
          <button type="button" onclick="printEmployeeDetails()" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
            <i class="fas fa-print mr-2"></i> Print
          </button>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal" id="approveModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3 class="text-lg font-semibold">Approve Payroll</h3>
          <button onclick="closeModal('approveModal')" class="text-gray-500 hover:text-gray-700">
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

  <!-- JavaScript - COMPLETE VERSION WITH FIXES FOR DUPLICATE ISSUES -->
  <script>
    // ============================================
    // GLOBAL VARIABLES AND SELECTION FUNCTIONS
    // ============================================

    // Global selections storage - Map to ensure uniqueness
    let selectedEmployeesMap = new Map(); // Store all selected employees across pages
    const storageKey = `permanent_selected_<?php echo $selected_period; ?>_<?php echo $selected_cutoff; ?>`;

    // Pagination variables
    let currentPage = <?php echo $current_page; ?>;
    let totalPages = <?php echo $total_pages; ?>;
    let recordsPerPage = <?php echo $records_per_page; ?>;
    let totalEmployees = <?php echo $total_employees; ?>;
    let searchTerm = '<?php echo addslashes($search_term); ?>';
    let isLoading = false;
    let sidebarOpen = false;
    let payrollMenuOpen = true;
    let activeSaves = 0;
    let saveTimeout;
    let searchTimeout;

    // DOM Ready
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOM loaded, total employees:', totalEmployees);

      // Initialize sidebar
      initSidebar();

      // Initialize payroll dropdown
      const payrollDropdown = document.getElementById('payroll-submenu');
      const payrollChevron = document.getElementById('payroll-toggle')?.querySelector('.chevron');
      if (payrollDropdown) {
        payrollDropdown.classList.add('open');
        if (payrollChevron) payrollChevron.classList.add('rotated');
      }

      // Set hidden cutoff value
      const hiddenCutoff = document.getElementById('hidden-payroll-cutoff');
      if (hiddenCutoff) {
        hiddenCutoff.value = '<?php echo $selected_cutoff; ?>';
      }

      // Load saved selections from storage
      loadSelectionsFromStorage();

      // Initialize components
      initAutoSave();
      initSearch();
      initPaginationEvents();
      highlightRowsByAttendance();
      updateDateTime();
      setInterval(updateDateTime, 1000);

      // Initialize tabs
      initTabs();

      // Initialize pagination buttons on initial load
      setupPaginationButtons();

      // Initialize checkbox functionality
      initCheckboxHandlers();

      // Sync checkboxes with stored selections
      syncCheckboxesWithSelections();

      console.log('Page loaded with global selection across pages');
    });

    // ============================================
    // GLOBAL SELECTION FUNCTIONS
    // ============================================

    function loadSelectionsFromStorage() {
      try {
        const saved = sessionStorage.getItem(storageKey);
        if (saved) {
          const selections = JSON.parse(saved);
          selectedEmployeesMap.clear();

          // Convert array back to Map - filter out invalid entries and duplicates
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

          // Convert back to our map
          selectedEmployeesMap = uniqueSelections;

          console.log(`Loaded ${selectedEmployeesMap.size} valid unique selections from storage`);
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
        console.log(`Saved ${selections.length} unique selections to storage`);
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

      console.log(`Synced ${syncedCount} checkboxes with selections`);
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

      createSelectAllPagesButton();
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

    function createSelectAllPagesButton() {
      if (document.getElementById('select-all-pages-btn')) return;

      const searchContainer = document.querySelector('.mb-4.flex.justify-between.items-center');
      if (!searchContainer) return;

      const selectAllPagesBtn = document.createElement('button');
      selectAllPagesBtn.id = 'select-all-pages-btn';
      selectAllPagesBtn.className = 'ml-2 px-3 py-2 bg-primary-600 hover:bg-primary-700 text-white text-xs font-medium rounded-lg transition-colors flex items-center gap-1';
      selectAllPagesBtn.innerHTML = '<i class="fas fa-check-double"></i> Select All (All Pages)';
      selectAllPagesBtn.onclick = selectAllAcrossPages;

      const rightSide = document.createElement('div');
      rightSide.className = 'flex items-center';
      rightSide.appendChild(selectAllPagesBtn);

      const perPageSelector = document.querySelector('.per-page-selector');
      if (perPageSelector && perPageSelector.parentNode) {
        perPageSelector.parentNode.appendChild(rightSide);
      }
    }

    async function selectAllAcrossPages() {
      showLoading();

      try {
        const response = await fetch(window.location.href, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            'ajax_action': 'get_all_employees_for_selection',
            'period': document.getElementById('payroll-period').value,
            'cutoff': document.getElementById('hidden-payroll-cutoff').value,
            'search': searchTerm
          })
        });

        const result = await response.json();

        if (result.success && result.employees) {
          selectedEmployeesMap.clear();

          result.employees.forEach(emp => {
            if (emp.employee_id) {
              selectedEmployeesMap.set(String(emp.employee_id), {
                id: String(emp.employee_id),
                name: emp.full_name || 'Employee',
                user_id: emp.user_id || ''
              });
            }
          });

          const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');
          employeeCheckboxes.forEach(checkbox => {
            const employeeId = String(checkbox.value);
            checkbox.checked = selectedEmployeesMap.has(employeeId);
          });

          updateSelectAllCheckbox();
          updateSelectedUI();

          showNotification(`Selected ${selectedEmployeesMap.size} unique employees across all pages`, 'success');
        }
      } catch (error) {
        console.error('Error selecting all employees:', error);
        showNotification('Error selecting all employees', 'error');
      } finally {
        hideLoading();
      }
    }

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

      window.open(`print_multiple_payslips_permanent.php?employees=${encodeURIComponent(employeeIds)}&period=${encodeURIComponent(period)}&cutoff=${encodeURIComponent(cutoff)}`, '_blank');
    };

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
      showNotification('All selections cleared', 'info');
    };

    // ============================================
    // PAGINATION FUNCTIONS
    // ============================================

    window.changePage = function(page) {
      if (page < 1 || page > totalPages || isLoading) return;

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
      if (isNaN(newPerPage) || newPerPage < 1 || isLoading) return;

      saveSelectionsToStorage();

      const url = new URL(window.location.href);
      url.searchParams.set('per_page', newPerPage);
      url.searchParams.set('page', '1');
      if (searchTerm) {
        url.searchParams.set('search', searchTerm);
      }
      window.location.href = url.toString();
    };

    function setupPaginationButtons() {
      document.querySelectorAll('.pagination-btn:not([disabled])').forEach(btn => {
        btn.removeEventListener('click', handlePaginationClick);
        btn.addEventListener('click', handlePaginationClick);
      });
    }

    function handlePaginationClick(e) {
      e.preventDefault();
      const btn = e.currentTarget;

      saveSelectionsToStorage();

      const pageText = btn.textContent.trim();
      const pageNum = parseInt(pageText);

      if (!isNaN(pageNum) && pageText === pageNum.toString()) {
        changePage(pageNum);
      } else if (btn.innerHTML.includes('angle-double-left')) {
        changePage(1);
      } else if (btn.innerHTML.includes('angle-double-right')) {
        changePage(totalPages);
      } else if (btn.innerHTML.includes('angle-left') || btn.textContent.includes('Previous')) {
        changePage(currentPage - 1);
      } else if (btn.innerHTML.includes('angle-right') || btn.textContent.includes('Next')) {
        changePage(currentPage + 1);
      }
    }

    function initPaginationEvents() {
      setupPaginationButtons();
    }

    function initSearch() {
      const searchInput = document.getElementById('search-employees');
      if (searchInput) {
        searchInput.value = searchTerm;
        searchInput.addEventListener('input', function(e) {
          searchTerm = e.target.value.trim();
          if (searchTimeout) clearTimeout(searchTimeout);
          searchTimeout = setTimeout(() => {
            if (!isLoading) {
              saveSelectionsToStorage();
              const url = new URL(window.location.href);
              url.searchParams.set('page', '1');
              if (searchTerm) {
                url.searchParams.set('search', searchTerm);
              } else {
                url.searchParams.delete('search');
              }
              window.location.href = url.toString();
            }
          }, 500);
        });
      }
    }

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
    // AUTO-SAVE FUNCTIONS
    // ============================================

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

      if (field.readonly || field.disabled) return;
      if (field.classList.contains('full-month-disabled')) return;

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

      const periodSelect = document.getElementById('payroll-period');
      const period = periodSelect ? periodSelect.value : '';

      const hiddenCutoff = document.getElementById('hidden-payroll-cutoff');
      const cutoff = hiddenCutoff ? hiddenCutoff.value : '';

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

        await calculateRow(row);

        // Get all current values from the row
        const withholdingTaxField = row.querySelector('.withholding-tax');
        const pagibigLoanField = row.querySelector('.pagibig-loan-mpl');
        const corsoLoanField = row.querySelector('.corso-loan');
        const policyLoanField = row.querySelector('.policy-loan');
        const philhealthField = row.querySelector('.philhealth-ps');
        const uefField = row.querySelector('.uef-retirement');
        const emergencyLoanField = row.querySelector('.emergency-loan');
        const gfalField = row.querySelector('.gfal');
        const lbpLoanField = row.querySelector('.lbp-loan');
        const mplField = row.querySelector('.mpl');
        const mplLiteField = row.querySelector('.mpl-lite');
        const sssField = row.querySelector('.sss-contribution');
        const pagibigContField = row.querySelector('.pagibig-cont');
        const stateInsField = row.querySelector('.state-ins-gs');
        const amountAccruedField = row.querySelector('.amount-accrued');
        const monthlySalaryField = row.querySelector('.monthly-salary');
        const daysPresentField = row.querySelector('.hidden-days-present');
        const totalDeductionField = row.querySelector('.total-deduction');
        const amountDueField = row.querySelector('.amount-due');
        const netAmountField = row.querySelector('.net-amount');

        const withholdingTax = withholdingTaxField ? parseFloat(withholdingTaxField.value) || 0 : 0;
        const pagibigLoanMpl = pagibigLoanField ? parseFloat(pagibigLoanField.value) || 0 : 0;
        const corsoLoan = corsoLoanField ? parseFloat(corsoLoanField.value) || 0 : 0;
        const policyLoan = policyLoanField ? parseFloat(policyLoanField.value) || 0 : 0;
        const philhealthPs = philhealthField ? parseFloat(philhealthField.value) || 0 : 0;
        const uefRetirement = uefField ? parseFloat(uefField.value) || 0 : 0;
        const emergencyLoan = emergencyLoanField ? parseFloat(emergencyLoanField.value) || 0 : 0;
        const gfal = gfalField ? parseFloat(gfalField.value) || 0 : 0;
        const lbpLoan = lbpLoanField ? parseFloat(lbpLoanField.value) || 0 : 0;
        const mpl = mplField ? parseFloat(mplField.value) || 0 : 0;
        const mplLite = mplLiteField ? parseFloat(mplLiteField.value) || 0 : 0;
        const sssContribution = sssField ? parseFloat(sssField.value) || 0 : 0;
        const pagibigCont = pagibigContField ? parseFloat(pagibigContField.value) || 0 : 0;
        const stateInsGs = stateInsField ? parseFloat(stateInsField.value) || 0 : 0;

        const amountAccrued = amountAccruedField ? parseFloat(amountAccruedField.value) || 0 : 0;
        const monthlySalary = monthlySalaryField ? parseFloat(monthlySalaryField.value) || 0 : 0;
        const daysPresent = daysPresentField ? parseFloat(daysPresentField.value) || 0 : 0;
        const totalDeductions = totalDeductionField ? parseFloat(totalDeductionField.value) || 0 : 0;
        const amountDue = amountDueField ? parseFloat(amountDueField.value) || 0 : 0;

        const formData = new FormData();
        formData.append('ajax_action', 'save_deductions');
        formData.append('employee_id', userId);
        formData.append('period', period);
        formData.append('cutoff', cutoff);
        formData.append('withholding_tax', withholdingTax);
        formData.append('pagibig_loan_mpl', pagibigLoanMpl);
        formData.append('corso_loan', corsoLoan);
        formData.append('policy_loan', policyLoan);
        formData.append('philhealth_ps', philhealthPs);
        formData.append('uef_retirement', uefRetirement);
        formData.append('emergency_loan', emergencyLoan);
        formData.append('gfal', gfal);
        formData.append('lbp_loan', lbpLoan);
        formData.append('mpl', mpl);
        formData.append('mpl_lite', mplLite);
        formData.append('sss_contribution', sssContribution);
        formData.append('pagibig_cont', pagibigCont);
        formData.append('state_ins_gs', stateInsGs);
        formData.append('other_comp', 0);
        formData.append('amount_accrued', amountAccrued);
        formData.append('gross_amount', amountAccrued);
        formData.append('total_deductions', totalDeductions);
        formData.append('amount_due', amountDue);
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
        console.log('Raw response:', responseText);

        let result;
        try {
          result = JSON.parse(responseText);
        } catch (e) {
          console.error('Failed to parse JSON response:', responseText);
          throw new Error('Invalid JSON response from server');
        }

        if (result.success) {
          showAutoSaveIndicator('saved');

          if (result.total_deductions !== undefined && totalDeductionField) {
            totalDeductionField.value = result.total_deductions.toFixed(2);
          }
          if (result.amount_due !== undefined && amountDueField) {
            amountDueField.value = result.amount_due.toFixed(2);
          }
          if (result.amount_due !== undefined && netAmountField) {
            netAmountField.value = result.amount_due.toFixed(2);
          }

          row.setAttribute('data-payroll-exists', '1');
          if (result.id) {
            row.setAttribute('data-payroll-id', result.id);
          }

          await calculateAll();
        } else {
          console.error('Save failed:', result.error);
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

    // ============================================
    // CALCULATION FUNCTIONS
    // ============================================

    async function calculateRow(row) {
      if (!row) return null;

      const withholdingTaxField = row.querySelector('.withholding-tax');
      const pagibigLoanField = row.querySelector('.pagibig-loan-mpl');
      const corsoLoanField = row.querySelector('.corso-loan');
      const policyLoanField = row.querySelector('.policy-loan');
      const philhealthField = row.querySelector('.philhealth-ps');
      const uefField = row.querySelector('.uef-retirement');
      const emergencyLoanField = row.querySelector('.emergency-loan');
      const gfalField = row.querySelector('.gfal');
      const lbpLoanField = row.querySelector('.lbp-loan');
      const mplField = row.querySelector('.mpl');
      const mplLiteField = row.querySelector('.mpl-lite');
      const sssField = row.querySelector('.sss-contribution');
      const pagibigContField = row.querySelector('.pagibig-cont');
      const stateInsField = row.querySelector('.state-ins-gs');
      const amountAccruedField = row.querySelector('.amount-accrued');
      const totalDeductionField = row.querySelector('.total-deduction');
      const amountDueField = row.querySelector('.amount-due');
      const netAmountField = row.querySelector('.net-amount');

      const withholdingTax = withholdingTaxField ? parseFloat(withholdingTaxField.value) || 0 : 0;
      const pagibigLoanMpl = pagibigLoanField ? parseFloat(pagibigLoanField.value) || 0 : 0;
      const corsoLoan = corsoLoanField ? parseFloat(corsoLoanField.value) || 0 : 0;
      const policyLoan = policyLoanField ? parseFloat(policyLoanField.value) || 0 : 0;
      const philhealthPs = philhealthField ? parseFloat(philhealthField.value) || 0 : 0;
      const uefRetirement = uefField ? parseFloat(uefField.value) || 0 : 0;
      const emergencyLoan = emergencyLoanField ? parseFloat(emergencyLoanField.value) || 0 : 0;
      const gfal = gfalField ? parseFloat(gfalField.value) || 0 : 0;
      const lbpLoan = lbpLoanField ? parseFloat(lbpLoanField.value) || 0 : 0;
      const mpl = mplField ? parseFloat(mplField.value) || 0 : 0;
      const mplLite = mplLiteField ? parseFloat(mplLiteField.value) || 0 : 0;
      const sssContribution = sssField ? parseFloat(sssField.value) || 0 : 0;
      const pagibigCont = pagibigContField ? parseFloat(pagibigContField.value) || 0 : 0;
      const stateInsGs = stateInsField ? parseFloat(stateInsField.value) || 0 : 0;
      const amountAccrued = amountAccruedField ? parseFloat(amountAccruedField.value) || 0 : 0;

      const totalDeduction = withholdingTax + pagibigLoanMpl + corsoLoan + policyLoan +
        philhealthPs + uefRetirement + emergencyLoan + gfal +
        lbpLoan + mpl + mplLite + sssContribution +
        pagibigCont + stateInsGs;

      let amountDue = amountAccrued - totalDeduction;
      if (amountDue < 0) amountDue = 0;

      if (totalDeductionField) totalDeductionField.value = totalDeduction.toFixed(2);
      if (amountDueField) amountDueField.value = amountDue.toFixed(2);
      if (netAmountField) netAmountField.value = amountDue.toFixed(2);

      return {
        amountAccrued,
        totalDeduction,
        amountDue
      };
    }

    async function calculateAll() {
      let totalAmountAccrued = 0;
      let totalWithholdingTax = 0;
      let totalPagibigLoanMpl = 0;
      let totalCorsoLoan = 0;
      let totalPolicyLoan = 0;
      let totalPhilhealthPs = 0;
      let totalUefRetirement = 0;
      let totalEmergencyLoan = 0;
      let totalGfal = 0;
      let totalLbpLoan = 0;
      let totalMpl = 0;
      let totalMplLite = 0;
      let totalSssContribution = 0;
      let totalPagibigCont = 0;
      let totalStateInsGs = 0;
      let totalDeductions = 0;
      let totalAmountDue = 0;

      const rows = document.querySelectorAll('.payroll-row');

      for (const row of rows) {
        const amountAccruedField = row.querySelector('.amount-accrued');
        const amountAccrued = amountAccruedField ? parseFloat(amountAccruedField.value) || 0 : 0;
        totalAmountAccrued += amountAccrued;

        const withholdingTaxField = row.querySelector('.withholding-tax');
        const pagibigLoanField = row.querySelector('.pagibig-loan-mpl');
        const corsoLoanField = row.querySelector('.corso-loan');
        const policyLoanField = row.querySelector('.policy-loan');
        const philhealthField = row.querySelector('.philhealth-ps');
        const uefField = row.querySelector('.uef-retirement');
        const emergencyLoanField = row.querySelector('.emergency-loan');
        const gfalField = row.querySelector('.gfal');
        const lbpLoanField = row.querySelector('.lbp-loan');
        const mplField = row.querySelector('.mpl');
        const mplLiteField = row.querySelector('.mpl-lite');
        const sssField = row.querySelector('.sss-contribution');
        const pagibigContField = row.querySelector('.pagibig-cont');
        const stateInsField = row.querySelector('.state-ins-gs');

        totalWithholdingTax += withholdingTaxField ? parseFloat(withholdingTaxField.value) || 0 : 0;
        totalPagibigLoanMpl += pagibigLoanField ? parseFloat(pagibigLoanField.value) || 0 : 0;
        totalCorsoLoan += corsoLoanField ? parseFloat(corsoLoanField.value) || 0 : 0;
        totalPolicyLoan += policyLoanField ? parseFloat(policyLoanField.value) || 0 : 0;
        totalPhilhealthPs += philhealthField ? parseFloat(philhealthField.value) || 0 : 0;
        totalUefRetirement += uefField ? parseFloat(uefField.value) || 0 : 0;
        totalEmergencyLoan += emergencyLoanField ? parseFloat(emergencyLoanField.value) || 0 : 0;
        totalGfal += gfalField ? parseFloat(gfalField.value) || 0 : 0;
        totalLbpLoan += lbpLoanField ? parseFloat(lbpLoanField.value) || 0 : 0;
        totalMpl += mplField ? parseFloat(mplField.value) || 0 : 0;
        totalMplLite += mplLiteField ? parseFloat(mplLiteField.value) || 0 : 0;
        totalSssContribution += sssField ? parseFloat(sssField.value) || 0 : 0;
        totalPagibigCont += pagibigContField ? parseFloat(pagibigContField.value) || 0 : 0;
        totalStateInsGs += stateInsField ? parseFloat(stateInsField.value) || 0 : 0;

        const result = await calculateRow(row);
        if (result) {
          totalDeductions += result.totalDeduction;
          totalAmountDue += result.amountDue;
        }
      }

      document.getElementById('total-amount-accrued').textContent = '₱' + totalAmountAccrued.toFixed(2);
      document.getElementById('total-withholding-tax').textContent = '₱' + totalWithholdingTax.toFixed(2);
      document.getElementById('total-pagibig-loan-mpl').textContent = '₱' + totalPagibigLoanMpl.toFixed(2);
      document.getElementById('total-corso-loan').textContent = '₱' + totalCorsoLoan.toFixed(2);
      document.getElementById('total-policy-loan').textContent = '₱' + totalPolicyLoan.toFixed(2);
      document.getElementById('total-philhealth-ps').textContent = '₱' + totalPhilhealthPs.toFixed(2);
      document.getElementById('total-uef-retirement').textContent = '₱' + totalUefRetirement.toFixed(2);
      document.getElementById('total-emergency-loan').textContent = '₱' + totalEmergencyLoan.toFixed(2);
      document.getElementById('total-gfal').textContent = '₱' + totalGfal.toFixed(2);
      document.getElementById('total-lbp-loan').textContent = '₱' + totalLbpLoan.toFixed(2);
      document.getElementById('total-mpl').textContent = '₱' + totalMpl.toFixed(2);
      document.getElementById('total-mpl-lite').textContent = '₱' + totalMplLite.toFixed(2);
      document.getElementById('total-sss-contribution').textContent = '₱' + totalSssContribution.toFixed(2);
      document.getElementById('total-pagibig-cont').textContent = '₱' + totalPagibigCont.toFixed(2);
      document.getElementById('total-state-ins-gs').textContent = '₱' + totalStateInsGs.toFixed(2);
      document.getElementById('total-deduction').textContent = '₱' + totalDeductions.toFixed(2);
      document.getElementById('total-amount-due').textContent = '₱' + totalAmountDue.toFixed(2);
      document.getElementById('total-net-amount').textContent = '₱' + totalAmountDue.toFixed(2);

      document.getElementById('total-deductions-display').textContent = '₱' + totalDeductions.toFixed(2);
      document.getElementById('amount-due-display').textContent = '₱' + totalAmountDue.toFixed(2);

      highlightRowsByAttendance();
    }

    function calculateSingleRowHandler(e) {
      e.preventDefault();
      const button = e.currentTarget;
      const row = button.closest('.payroll-row');
      if (row) {
        calculateRow(row).then(() => calculateAll());
      }
    }

    window.calculateSingleRow = calculateSingleRowHandler;

    const calculateAllBtn = document.getElementById('calculate-all-btn');
    if (calculateAllBtn) {
      calculateAllBtn.addEventListener('click', function(e) {
        e.preventDefault();
        calculateAll();
      });
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

    window.addEventListener('click', function(e) {
      if (e.target.classList && e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
      }
    });

    window.viewEmployeeData = async function(userId, period, cutoff) {
      showLoading();
      try {
        const formData = new FormData();
        formData.append('ajax_action', 'get_employee_details');
        formData.append('employee_id', userId);
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
          showNotification('Error loading employee details', 'error');
        }
      } catch (error) {
        console.error('Error:', error);
        showNotification('Error loading employee details', 'error');
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

      const modalBody = document.getElementById('viewModalBody');
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
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
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
                        <p class="text-sm">Monthly Salary: ${safeCurrency(calculations.monthly_salary)}</p>
                        <p class="text-sm">Daily Rate: ${safeCurrency(calculations.daily_rate)}</p>
                        <p class="text-sm">Amount Accrued: ${safeCurrency(calculations.amount_accrued)}</p>
                        <p class="text-sm">Other Compensation: ${safeCurrency(calculations.other_comp)}</p>
                        <p class="text-sm font-bold mt-2">Deductions:</p>
                        <p class="text-sm ml-2">Withholding Tax: ${safeCurrency(calculations.withholding_tax)}</p>
                        <p class="text-sm ml-2">PAG-IBIG LOAN - MPL: ${safeCurrency(calculations.pagibig_loan_mpl)}</p>
                        <p class="text-sm ml-2">Corso Loan: ${safeCurrency(calculations.corso_loan)}</p>
                        <p class="text-sm ml-2">Policy Loan: ${safeCurrency(calculations.policy_loan)}</p>
                        <p class="text-sm ml-2">PhilHealth P.S.: ${safeCurrency(calculations.philhealth_ps)}</p>
                        <p class="text-sm ml-2">UEF / Retirement: ${safeCurrency(calculations.uef_retirement)}</p>
                        <p class="text-sm ml-2">Emergency Loan: ${safeCurrency(calculations.emergency_loan)}</p>
                        <p class="text-sm ml-2">GFAL: ${safeCurrency(calculations.gfal)}</p>
                        <p class="text-sm ml-2">LBP Loan: ${safeCurrency(calculations.lbp_loan)}</p>
                        <p class="text-sm ml-2">MPL: ${safeCurrency(calculations.mpl)}</p>
                        <p class="text-sm ml-2">MPL Lite: ${safeCurrency(calculations.mpl_lite)}</p>
                        <p class="text-sm ml-2">SSS Contribution: ${safeCurrency(calculations.sss_contribution)}</p>
                        <p class="text-sm ml-2">PAG-IBIG CONT.: ${safeCurrency(calculations.pagibig_cont)}</p>
                        <p class="text-sm ml-2">STATE INS. G.S.: ${safeCurrency(calculations.state_ins_gs)}</p>
                        <p class="text-sm font-bold mt-2">Total Deductions: ${safeCurrency(calculations.total_deductions)}</p>
                        <p class="text-sm font-bold text-blue-600 mt-2">Amount Due: ${safeCurrency(calculations.amount_due)}</p>
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
                                        <th class="p-2">Amount Accrued</th>
                                        <th class="p-2">Deductions</th>
                                        <th class="p-2">Amount Due</th>
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
                            <td class="p-2">${safeCurrency(payroll.amount_accrued)}</td>
                            <td class="p-2">${safeCurrency(payroll.total_deductions)}</td>
                            <td class="p-2">${safeCurrency(payroll.amount_due)}</td>
                            <td class="p-2"><span class="status-badge status-${payroll.status || 'pending'}">${safeText(payroll.status)}</span></td>
                        </tr>
                    `;
        });

        html += `</tbody></table></div></div>`;
      }

      html += `</div>`;

      modalBody.innerHTML = html;
      openModal('viewModal');
    }

    window.viewDeductions = async function(payrollId) {
      if (!payrollId) return;

      showLoading();
      try {
        const formData = new FormData();
        formData.append('ajax_action', 'view_deductions');
        formData.append('payroll_id', payrollId);

        const response = await fetch(window.location.href, {
          method: 'POST',
          body: formData
        });
        const result = await response.json();

        if (result.success) {
          let html = '<div class="space-y-3">';
          result.deductions.forEach(ded => {
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
      } catch (error) {
        console.error('Error:', error);
        showNotification('Error loading deductions', 'error');
      } finally {
        hideLoading();
      }
    };

    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.className = `fixed top-20 right-5 z-50 px-4 py-3 rounded-lg shadow-lg transition-all duration-300 transform translate-x-0 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 'bg-blue-500'
            } text-white`;
      notification.innerHTML = `
                <div class="flex items-center gap-2">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;

      document.body.appendChild(notification);

      setTimeout(() => {
        notification.classList.add('opacity-0', 'translate-x-full');
        setTimeout(() => notification.remove(), 300);
      }, 3000);
    }

    function showLoading() {
      const overlay = document.getElementById('loadingOverlay');
      if (overlay) overlay.classList.add('active');
    }

    function hideLoading() {
      const overlay = document.getElementById('loadingOverlay');
      if (overlay) overlay.classList.remove('active');
    }

    function showApproveModal() {
      openModal('approveModal');
    }

    window.generateObligationRequest = function() {
      const periodSelect = document.getElementById('payroll-period');
      const period = periodSelect ? periodSelect.value : '';

      const hiddenCutoff = document.getElementById('hidden-payroll-cutoff');
      const cutoff = hiddenCutoff ? hiddenCutoff.value : '';

      window.location.href = `permanentobligationrequest.php?period=${period}&cutoff=${cutoff}`;
    };

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================

    function highlightRowsByAttendance() {
      document.querySelectorAll('.payroll-row').forEach(row => {
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

    function updateDateTime() {
      const now = new Date();
      const dateString = now.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
      const timeString = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
      });

      const dateElement = document.getElementById('current-date');
      const timeElement = document.getElementById('current-time');

      if (dateElement) dateElement.textContent = dateString;
      if (timeElement) timeElement.textContent = timeString;
    }

    function printEmployeeDetails() {
      const modalBody = document.getElementById('viewModalBody');
      if (!modalBody) return;

      const modalContent = modalBody.innerHTML;
      const printWindow = window.open('', '_blank');
      printWindow.document.write(`
                <html>
                <head>
                    <title>Employee Details</title>
                    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
                    <style>
                        body { padding: 20px; font-family: Arial, sans-serif; }
                        .detail-section { margin-bottom: 20px; }
                        .attendance-summary { display: flex; gap: 10px; margin-bottom: 10px; }
                        .attendance-card { flex: 1; padding: 10px; background: #f5f5f5; border-radius: 5px; text-align: center; }
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

    // ============================================
    // SIDEBAR FUNCTIONS
    // ============================================

    function initSidebar() {
      const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
      const sidebarOverlay = document.getElementById('sidebar-overlay');
      const payrollToggle = document.getElementById('payroll-toggle');

      if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', toggleSidebar);
      }

      if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
      }

      if (payrollToggle) {
        payrollToggle.addEventListener('click', togglePayrollDropdown);
      }

      document.querySelectorAll('.sidebar-item, .submenu-item').forEach(item => {
        item.addEventListener('click', function(e) {
          if (window.innerWidth < 1024) closeSidebar();
        });
      });

      window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) closeSidebar();
      });
    }

    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebar-overlay');

      sidebarOpen = !sidebarOpen;

      if (sidebar) sidebar.classList.toggle('active');
      if (overlay) overlay.classList.toggle('active');

      if (window.innerWidth < 1024) {
        document.body.style.overflow = sidebarOpen ? 'hidden' : '';
      }
    }

    function closeSidebar() {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebar-overlay');

      if (sidebar) sidebar.classList.remove('active');
      if (overlay) overlay.classList.remove('active');

      sidebarOpen = false;
      document.body.style.overflow = '';
    }

    function togglePayrollDropdown(e) {
      e.preventDefault();
      e.stopPropagation();

      const dropdown = document.getElementById('payroll-submenu');
      const chevron = this.querySelector('.chevron');

      payrollMenuOpen = !payrollMenuOpen;

      if (dropdown) dropdown.classList.toggle('open');
      if (chevron) chevron.classList.toggle('rotated');
    }

    // ============================================
    // TAB FUNCTIONS
    // ============================================

    function initTabs() {
      document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
          const tabId = this.dataset.tab;

          document.querySelectorAll('.tab').forEach(t => {
            t.classList.remove('active');
          });

          this.classList.add('active');

          document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
          });

          const tabContent = document.getElementById('tab-' + tabId);
          if (tabContent) {
            tabContent.classList.add('active');
          }

          if (tabId === 'summary') {
            setTimeout(() => {
              initDeductionsChart();
            }, 100);
          }
        });
      });
    }

    function initDeductionsChart() {
      const canvas = document.getElementById('deductionsChart');
      if (!canvas) return;

      const withholdingTax = parseFloat('<?php echo $payroll_summary["total_withholding_tax"] ?? 0; ?>');
      const pagibigLoanMpl = parseFloat('<?php echo $payroll_summary["total_pagibig_loan_mpl"] ?? 0; ?>');
      const corsoLoan = parseFloat('<?php echo $payroll_summary["total_corso_loan"] ?? 0; ?>');
      const policyLoan = parseFloat('<?php echo $payroll_summary["total_policy_loan"] ?? 0; ?>');
      const philhealthPs = parseFloat('<?php echo $payroll_summary["total_philhealth_ps"] ?? 0; ?>');
      const uefRetirement = parseFloat('<?php echo $payroll_summary["total_uef_retirement"] ?? 0; ?>');
      const emergencyLoan = parseFloat('<?php echo $payroll_summary["total_emergency_loan"] ?? 0; ?>');
      const gfal = parseFloat('<?php echo $payroll_summary["total_gfal"] ?? 0; ?>');
      const lbpLoan = parseFloat('<?php echo $payroll_summary["total_lbp_loan"] ?? 0; ?>');
      const mpl = parseFloat('<?php echo $payroll_summary["total_mpl"] ?? 0; ?>');
      const mplLite = parseFloat('<?php echo $payroll_summary["total_mpl_lite"] ?? 0; ?>');
      const sssContribution = parseFloat('<?php echo $payroll_summary["total_sss_contribution"] ?? 0; ?>');
      const pagibigCont = parseFloat('<?php echo $payroll_summary["total_pagibig_cont"] ?? 0; ?>');
      const stateInsGs = parseFloat('<?php echo $payroll_summary["total_state_ins_gs"] ?? 0; ?>');

      if (withholdingTax === 0 && pagibigLoanMpl === 0 && corsoLoan === 0 && policyLoan === 0 &&
        philhealthPs === 0 && uefRetirement === 0 && emergencyLoan === 0 && gfal === 0 &&
        lbpLoan === 0 && mpl === 0 && mplLite === 0 && sssContribution === 0 &&
        pagibigCont === 0 && stateInsGs === 0) return;

      if (window.deductionsChartInstance) {
        window.deductionsChartInstance.destroy();
      }

      window.deductionsChartInstance = new Chart(canvas, {
        type: 'pie',
        data: {
          labels: ['Withholding Tax', 'PAG-IBIG Loan', 'Corso Loan', 'Policy Loan', 'PhilHealth',
            'UEF/Retirement', 'Emergency Loan', 'GFAL', 'LBP Loan', 'MPL', 'MPL Lite',
            'SSS', 'PAG-IBIG Cont.', 'State Ins. GS'
          ],
          datasets: [{
            data: [withholdingTax, pagibigLoanMpl, corsoLoan, policyLoan, philhealthPs,
              uefRetirement, emergencyLoan, gfal, lbpLoan, mpl, mplLite,
              sssContribution, pagibigCont, stateInsGs
            ],
            backgroundColor: ['#ef4444', '#3b82f6', '#8b5cf6', '#ec4899', '#10b981',
              '#f59e0b', '#6366f1', '#14b8a6', '#f97316', '#6b7280',
              '#9ca3af', '#60a5fa', '#34d399', '#a78bfa'
            ],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom'
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  let label = context.label || '';
                  let value = context.raw || 0;
                  let total = context.dataset.data.reduce((a, b) => a + b, 0);
                  let percentage = ((value / total) * 100).toFixed(1);
                  return `${label}: ₱${value.toFixed(2)} (${percentage}%)`;
                }
              }
            }
          }
        }
      });
    }

    window.addEventListener('popstate', function() {
      window.location.reload();
    });
  </script>
</body>

</html>