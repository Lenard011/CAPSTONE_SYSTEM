<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
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

// Get parameters from URL
$employee_ids = isset($_GET['employees']) ? $_GET['employees'] : '';
$period = isset($_GET['period']) ? $_GET['period'] : date('Y-m');
$cutoff = isset($_GET['cutoff']) ? $_GET['cutoff'] : 'full';

// If no employees selected, redirect back
if (empty($employee_ids)) {
    $_SESSION['error_message'] = "No employees selected for printing.";
    echo "<script>
        alert('No employees selected for printing.');
        window.close();
        window.location.href = document.referrer || 'permanentpayrolltable1.php?period=$period&cutoff=$cutoff';
    </script>";
    exit();
}

// Parse period
$year_month = explode('-', $period);
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

$current_cutoff = $cutoff_ranges[$cutoff];

/**
 * Function to get employee's payroll data from permanent table
 */
function getEmployeePayrollData($pdo, $employee_id, $period, $cutoff, $default_amount_accrued = 0)
{
    $data = [
        'monthly_salary' => 0,
        'amount_accrued' => $default_amount_accrued,
        'other_comp' => 0,
        'gross_amount' => $default_amount_accrued,
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
        'amount_due' => $default_amount_accrued,
        'net_amount' => $default_amount_accrued,
        'days_present' => 0,
        'status' => 'draft'
    ];

    try {
        if ($cutoff == 'full') {
            // For full month, get data from both halves and sum them
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(monthly_salary), 0) as monthly_salary,
                    COALESCE(SUM(amount_accrued), 0) as amount_accrued,
                    COALESCE(SUM(other_comp), 0) as other_comp,
                    COALESCE(SUM(gross_amount), 0) as gross_amount,
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
                    COALESCE(SUM(amount_due), 0) as amount_due,
                    COALESCE(SUM(net_amount), 0) as net_amount,
                    COALESCE(SUM(days_present), 0) as days_present,
                    COUNT(DISTINCT id) as record_count,
                    GROUP_CONCAT(DISTINCT status) as statuses
                FROM payroll_history_permanent 
                WHERE employee_id = ? 
                    AND payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half')
            ");
            $stmt->execute([$employee_id, $period]);
            $result = $stmt->fetch();

            if ($result && $result['record_count'] > 0) {
                $data['monthly_salary'] = floatval($result['monthly_salary'] ?? 0);
                $data['amount_accrued'] = floatval($result['amount_accrued'] ?? 0);
                $data['other_comp'] = floatval($result['other_comp'] ?? 0);
                $data['gross_amount'] = floatval($result['gross_amount'] ?? 0);
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
                $data['amount_due'] = floatval($result['amount_due'] ?? 0);
                $data['net_amount'] = floatval($result['net_amount'] ?? 0);
                $data['days_present'] = floatval($result['days_present'] ?? 0);

                // Determine overall status
                $statuses = explode(',', $result['statuses']);
                if (in_array('approved', $statuses)) {
                    $data['status'] = 'approved';
                } elseif (in_array('paid', $statuses)) {
                    $data['status'] = 'paid';
                } elseif (in_array('pending', $statuses)) {
                    $data['status'] = 'pending';
                } else {
                    $data['status'] = 'draft';
                }
            }
        } else {
            // For specific half, get just that half's data
            $stmt = $pdo->prepare("
                SELECT monthly_salary, amount_accrued, other_comp, gross_amount,
                       withholding_tax, pagibig_loan_mpl, corso_loan, policy_loan,
                       philhealth_ps, uef_retirement, emergency_loan, gfal,
                       lbp_loan, mpl, mpl_lite, sss_contribution,
                       pagibig_cont, state_ins_gs,
                       total_deductions, amount_due, net_amount, days_present, status
                FROM payroll_history_permanent 
                WHERE employee_id = ? 
                    AND payroll_period = ? AND payroll_cutoff = ?
                LIMIT 1
            ");
            $stmt->execute([$employee_id, $period, $cutoff]);
            $result = $stmt->fetch();

            if ($result) {
                $data['monthly_salary'] = floatval($result['monthly_salary'] ?? 0);
                $data['amount_accrued'] = floatval($result['amount_accrued'] ?? 0);
                $data['other_comp'] = floatval($result['other_comp'] ?? 0);
                $data['gross_amount'] = floatval($result['gross_amount'] ?? 0);
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
                $data['amount_due'] = floatval($result['amount_due'] ?? 0);
                $data['net_amount'] = floatval($result['net_amount'] ?? 0);
                $data['days_present'] = floatval($result['days_present'] ?? 0);
                $data['status'] = $result['status'] ?? 'pending';
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching payroll data: " . $e->getMessage());
    }

    return $data;
}

// Fetch selected employees
$employees_data = [];
$employee_ids_array = explode(',', $employee_ids);

if (!empty($employee_ids_array)) {
    // First, try to get employees by employee_id (string)
    try {
        $placeholders = implode(',', array_fill(0, count($employee_ids_array), '?'));

        $sql = "
            SELECT 
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
            WHERE employee_id IN ($placeholders) AND (status = 'Active' OR status IS NULL)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($employee_ids_array);
        $employees = $stmt->fetchAll();

        // If no employees found, try by id (numeric)
        if (empty($employees)) {
            // Convert to integers for ID lookup
            $numeric_ids = array_filter($employee_ids_array, 'is_numeric');
            if (!empty($numeric_ids)) {
                $id_placeholders = implode(',', array_fill(0, count($numeric_ids), '?'));
                $sql = "
                    SELECT 
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
                    WHERE id IN ($id_placeholders) AND (status = 'Active' OR status IS NULL)
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($numeric_ids);
                $employees = $stmt->fetchAll();
            }
        }

        // Get attendance and payroll data for each employee
        foreach ($employees as &$employee) {
            // Get attendance for the cutoff period
            $attendance_days = 0;
            try {
                $table_check = $pdo->query("SHOW TABLES LIKE 'attendance'");
                if ($table_check->rowCount() > 0) {
                    $attendance_stmt = $pdo->prepare("
                        SELECT 
                            SUM(CASE 
                                WHEN total_hours >= 8 THEN 1
                                WHEN total_hours >= 4 THEN 0.5
                                ELSE 0
                            END) as attendance_days
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
                }
            } catch (Exception $e) {
                error_log("Attendance fetch error: " . $e->getMessage());
            }

            $employee['days_present'] = $attendance_days;

            // Calculate prorated salary based on attendance
            $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
            $prorated_salary = ($monthly_salary / 22) * $attendance_days;

            // Get payroll data from payroll_history_permanent table
            $payroll_data = getEmployeePayrollData($pdo, $employee['employee_id'], $period, $cutoff, $prorated_salary);

            // Set all payroll fields (use payroll_data if available, otherwise use calculated values)
            $employee['monthly_salary_db'] = floatval($employee['monthly_salary'] ?? 0);
            $employee['amount_accrued'] = $payroll_data['amount_accrued'] > 0 ? $payroll_data['amount_accrued'] : $prorated_salary;
            $employee['other_comp'] = $payroll_data['other_comp'];
            $employee['gross_amount'] = $payroll_data['gross_amount'] > 0 ? $payroll_data['gross_amount'] : ($prorated_salary + $payroll_data['other_comp']);
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
            $employee['amount_due'] = $payroll_data['amount_due'] > 0 ? $payroll_data['amount_due'] : ($employee['gross_amount'] - $employee['total_deductions']);
            $employee['net_amount'] = $payroll_data['net_amount'] > 0 ? $payroll_data['net_amount'] : $employee['amount_due'];
            $employee['payroll_status'] = $payroll_data['status'];

            // Calculate daily rate
            $employee['daily_rate'] = $monthly_salary / 22;

            $employees_data[] = $employee;
        }
    } catch (Exception $e) {
        error_log("Error fetching employees: " . $e->getMessage());
        $_SESSION['error_message'] = "Error fetching employee data: " . $e->getMessage();
        echo "<script>
            alert('Error fetching employee data: " . addslashes($e->getMessage()) . "');
            window.close();
            window.location.href = document.referrer || 'permanentpayrolltable1.php?period=$period&cutoff=$cutoff';
        </script>";
        exit();
    }
}

// Get company info
$company_name = "LGU Paluan";
$company_address = "Paluan, Occidental Mindoro";
$company_logo = "https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png";

// Format currency
function formatCurrency($amount)
{
    return '₱' . number_format($amount, 2);
}

// Format date
function formatDate($date)
{
    return date('F d, Y', strtotime($date));
}

// Helper function to convert numbers to words
function convertNumberToWords($number)
{
    $words = array(
        0 => 'ZERO',
        1 => 'ONE',
        2 => 'TWO',
        3 => 'THREE',
        4 => 'FOUR',
        5 => 'FIVE',
        6 => 'SIX',
        7 => 'SEVEN',
        8 => 'EIGHT',
        9 => 'NINE',
        10 => 'TEN',
        11 => 'ELEVEN',
        12 => 'TWELVE',
        13 => 'THIRTEEN',
        14 => 'FOURTEEN',
        15 => 'FIFTEEN',
        16 => 'SIXTEEN',
        17 => 'SEVENTEEN',
        18 => 'EIGHTEEN',
        19 => 'NINETEEN',
        20 => 'TWENTY',
        30 => 'THIRTY',
        40 => 'FORTY',
        50 => 'FIFTY',
        60 => 'SIXTY',
        70 => 'SEVENTY',
        80 => 'EIGHTY',
        90 => 'NINETY'
    );

    if ($number < 20) {
        return $words[$number];
    } elseif ($number < 100) {
        $tens = floor($number / 10) * 10;
        $units = $number % 10;
        if ($units == 0) {
            return $words[$tens];
        } else {
            return $words[$tens] . ' ' . $words[$units];
        }
    } elseif ($number < 1000) {
        $hundreds = floor($number / 100);
        $remainder = $number % 100;
        if ($remainder == 0) {
            return $words[$hundreds] . ' HUNDRED';
        } else {
            return $words[$hundreds] . ' HUNDRED ' . convertNumberToWords($remainder);
        }
    } elseif ($number < 1000000) {
        $thousands = floor($number / 1000);
        $remainder = $number % 1000;
        if ($remainder == 0) {
            return convertNumberToWords($thousands) . ' THOUSAND';
        } else {
            return convertNumberToWords($thousands) . ' THOUSAND ' . convertNumberToWords($remainder);
        }
    } else {
        return 'NUMBER TOO LARGE';
    }
}

// Determine if we have multiple employees
$has_multiple_employees = count($employees_data) > 1;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multiple Permanent Payslips - <?php echo $company_name; ?></title>
    <style>
        /* ===== SCREEN STYLES ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }

        .print-container {
            max-width: 8.5in;
            /* Exact width of short bond paper */
            margin: 0 auto;
            background: white;
        }

        .payslip {
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            border: 1px solid #e5e7eb;
            padding: 0.25in;
            /* 0.25 inch padding inside the payslip */
        }

        /* Only add page breaks when printing multiple employees */
        .payslip:not(:last-child) {
            page-break-after: always;
        }

        /* HEADER */
        .payslip-header {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            padding: 12px 15px;
            border-radius: 3px;
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .company-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
            background: white;
            border-radius: 50%;
            padding: 3px;
        }

        .company-info h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .company-info p {
            font-size: 11px;
            opacity: 0.9;
        }

        .payslip-title {
            margin-left: auto;
            text-align: right;
        }

        .payslip-title h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .payslip-title .period {
            font-size: 11px;
            opacity: 0.9;
        }

        .permanent-badge {
            display: inline-block;
            background: #fbbf24;
            color: #1e3a8a;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 5px;
        }

        /* EMPLOYEE INFO */
        .employee-info {
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
            margin-bottom: 12px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .info-value {
            font-size: 12px;
            font-weight: 600;
            color: #1f2937;
            margin-top: 2px;
        }

        /* ATTENDANCE SUMMARY */
        .attendance-summary {
            padding: 10px 15px;
            background: #eff6ff;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 12px;
        }

        .attendance-grid {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .attendance-box {
            text-align: center;
        }

        .attendance-box .value {
            font-size: 18px;
            font-weight: 700;
            color: #1e40af;
        }

        .attendance-box .label {
            font-size: 9px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* SALARY TABLE */
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 11px;
        }

        .salary-table th {
            background: #f3f4f6;
            padding: 6px 8px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .salary-table td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            font-size: 11px;
        }

        .salary-table .amount {
            text-align: right;
            font-weight: 500;
            width: 120px;
        }

        .salary-table .total-row {
            background: #f8fafc;
            font-weight: 600;
        }

        .salary-table .grand-total {
            background: #eff6ff;
            font-weight: 700;
            font-size: 12px;
        }

        /* DEDUCTIONS SECTION */
        .deductions-title {
            font-size: 12px;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 4px;
        }

        /* NET PAY */
        .net-pay {
            margin: 12px 0;
            padding: 8px 15px;
            background: #dbeafe;
            border-radius: 3px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16px;
            font-weight: 700;
        }

        .net-pay-label {
            color: #1e40af;
        }

        .net-pay-amount {
            color: #059669;
        }

        /* AMOUNT IN WORDS */
        .amount-words {
            padding: 0 15px 8px;
            font-size: 9px;
            color: #6b7280;
            font-style: italic;
        }

        /* SIGNATURE AREA */
        .signature-area {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding: 0 15px 8px;
        }

        .signature-box {
            text-align: center;
            width: 180px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin: 6px 0 4px;
            width: 100%;
        }

        .signature-label {
            font-size: 9px;
            color: #6b7280;
        }

        /* FOOTER */
        .payslip-footer {
            padding: 6px 15px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            color: #6b7280;
        }

        /* PRINT CONTROLS - Screen only */
        .print-controls {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }

        .print-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .print-btn.print {
            background: #1e40af;
            color: white;
        }

        .print-btn.back {
            background: #6b7280;
            color: white;
        }

        /* ===== PRINT STYLES ===== */
        @media print {
            @page {
                size: portrait;
                margin: 0;
                /* No margins - we control padding in .payslip */
            }

            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .print-controls {
                display: none;
            }

            .print-container {
                max-width: 100%;
                margin: 0;
                background: white;
            }

            .payslip {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 0.25in;
                /* Same padding as screen */
                border-radius: 0;
                background: white;
            }

            /* Only add page breaks between payslips when there are multiple */
            .payslip:not(:last-child) {
                page-break-after: always;
            }

            .payslip:last-child {
                page-break-after: auto;
            }

            /* Preserve all colors */
            .payslip-header,
            .attendance-summary,
            .grand-total,
            .net-pay,
            .total-row {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Ensure tables don't break across pages */
            .salary-table {
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="print-container">
        <?php if (empty($employees_data)): ?>
            <div class="payslip" style="padding: 40px; text-align: center;">
                <h3 style="font-size: 16px; margin-bottom: 15px;">No employee data found</h3>
                <p style="font-size: 12px; margin-bottom: 20px;">Please select employees with valid payroll data.</p>
                <button class="print-btn back" onclick="goBackAndClose()" style="margin-top: 15px; display: inline-block; position: static;">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($employees_data as $index => $employee): ?>
                <div class="payslip">
                    <!-- Header -->
                    <div class="payslip-header">
                        <img src="<?php echo $company_logo; ?>" alt="Company Logo" class="company-logo">
                        <div class="company-info">
                            <h2><?php echo $company_name; ?></h2>
                            <p><?php echo $company_address; ?></p>
                        </div>
                        <div class="payslip-title">
                            <h3>PERMANENT PAYSLIP <span class="permanent-badge">PERMANENT</span></h3>
                            <p class="period"><?php echo $current_cutoff['label']; ?> - <?php echo date('F Y', strtotime($period . '-01')); ?></p>
                        </div>
                    </div>

                    <!-- Employee Information -->
                    <div class="employee-info">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Employee ID</span>
                                <span class="info-value"><?php echo htmlspecialchars($employee['employee_id'] ?? ''); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($employee['full_name'] ?? ''); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Position</span>
                                <span class="info-value"><?php echo htmlspecialchars($employee['position'] ?? ''); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Department</span>
                                <span class="info-value"><?php echo htmlspecialchars($employee['department'] ?? ''); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Period</span>
                                <span class="info-value"><?php echo formatDate($current_cutoff['start']); ?> - <?php echo formatDate($current_cutoff['end']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="info-value"><?php echo ucfirst($employee['payroll_status'] ?? 'pending'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Summary -->
                    <div class="attendance-summary">
                        <div class="attendance-grid">
                            <div class="attendance-box">
                                <div class="value"><?php echo number_format($employee['days_present'] ?? 0, 1); ?></div>
                                <div class="label">Days Present</div>
                            </div>
                            <div class="attendance-box">
                                <div class="value"><?php echo $current_cutoff['working_days']; ?></div>
                                <div class="label">Working Days</div>
                            </div>
                            <div class="attendance-box">
                                <div class="value"><?php echo ($employee['days_present'] ?? 0) > 0 ? number_format((($employee['days_present'] ?? 0) / $current_cutoff['working_days']) * 100, 1) : '0'; ?>%</div>
                                <div class="label">Attendance Rate</div>
                            </div>
                        </div>
                    </div>

                    <!-- Salary Table -->
                    <table class="salary-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="amount">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Monthly Salary</td>
                                <td class="amount"><?php echo formatCurrency($employee['monthly_salary_db'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Daily Rate (22 days)</td>
                                <td class="amount"><?php echo formatCurrency(($employee['monthly_salary_db'] ?? 0) / 22); ?></td>
                            </tr>
                            <tr>
                                <td>Days Present</td>
                                <td class="amount"><?php echo number_format($employee['days_present'] ?? 0, 1); ?> days</td>
                            </tr>
                            <tr>
                                <td>Amount Accrued</td>
                                <td class="amount"><?php echo formatCurrency($employee['amount_accrued'] ?? 0); ?></td>
                            </tr>
                            <?php if (isset($employee['other_comp']) && $employee['other_comp'] > 0): ?>
                                <tr>
                                    <td>Other Compensation</td>
                                    <td class="amount"><?php echo formatCurrency($employee['other_comp']); ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr class="total-row">
                                <td><strong>GROSS AMOUNT</strong></td>
                                <td class="amount"><strong><?php echo formatCurrency($employee['gross_amount'] ?? 0); ?></strong></td>
                            </tr>

                            <!-- Deductions Section Header -->
                            <tr>
                                <td colspan="2" style="padding: 8px 0 4px;">
                                    <div class="deductions-title">DEDUCTIONS</div>
                                </td>
                            </tr>

                            <!-- Deduction Rows -->
                            <?php
                            $deduction_rows = [
                                'withholding_tax' => 'Withholding Tax',
                                'pagibig_loan_mpl' => 'PAG-IBIG LOAN - MPL',
                                'corso_loan' => 'Corso Loan',
                                'policy_loan' => 'Policy Loan',
                                'philhealth_ps' => 'PhilHealth P.S.',
                                'uef_retirement' => 'UEF / Retirement',
                                'emergency_loan' => 'Emergency Loan',
                                'gfal' => 'GFAL',
                                'lbp_loan' => 'LBP Loan',
                                'mpl' => 'MPL',
                                'mpl_lite' => 'MPL Lite',
                                'sss_contribution' => 'SSS Contribution',
                                'pagibig_cont' => 'PAG-IBIG CONT.',
                                'state_ins_gs' => 'STATE INS. G.S.'
                            ];

                            $has_deductions = false;
                            foreach ($deduction_rows as $field => $label):
                                if (isset($employee[$field]) && $employee[$field] > 0):
                                    $has_deductions = true;
                            ?>
                                    <tr>
                                        <td><?php echo $label; ?></td>
                                        <td class="amount" style="color: #dc2626;"><?php echo formatCurrency($employee[$field]); ?></td>
                                    </tr>
                            <?php
                                endif;
                            endforeach;
                            ?>

                            <?php if (!$has_deductions): ?>
                                <tr>
                                    <td colspan="2" class="text-center" style="color: #6b7280; padding: 8px; text-align: center;">No deductions for this period</td>
                                </tr>
                            <?php endif; ?>

                            <tr class="total-row">
                                <td><strong>TOTAL DEDUCTIONS</strong></td>
                                <td class="amount"><strong style="color: #dc2626;"><?php echo formatCurrency($employee['total_deductions'] ?? 0); ?></strong></td>
                            </tr>

                            <!-- Net Pay -->
                            <tr class="grand-total">
                                <td><strong>NET PAY (Take Home Pay)</strong></td>
                                <td class="amount"><strong><?php echo formatCurrency($employee['net_amount'] ?? 0); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Net Pay Summary -->
                    <div class="net-pay">
                        <span class="net-pay-label">NET AMOUNT DUE:</span>
                        <span class="net-pay-amount"><?php echo formatCurrency($employee['net_amount'] ?? 0); ?></span>
                    </div>

                    <!-- Amount in Words -->
                    <div class="amount-words">
                        <strong>Amount in Words:</strong>
                        <?php
                        $net_amount = $employee['net_amount'] ?? 0;
                        $amount_parts = explode('.', number_format($net_amount, 2, '.', ''));
                        echo strtoupper(convertNumberToWords((int)$amount_parts[0])) . ' PESOS AND ' . $amount_parts[1] . '/100 ONLY';
                        ?>
                    </div>

                    <!-- Signature Area -->
                    <div class="signature-area">
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div class="signature-label">Employee Signature</div>
                        </div>
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div class="signature-label">HR Officer Signature</div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="payslip-footer">
                        <span>Generated: <?php echo date('F d, Y h:i A'); ?></span>
                        <span>Computer-generated document</span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Print Controls -->
    <div class="print-controls">
        <button class="print-btn back" onclick="goBackAndClose()">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <?php if (!empty($employees_data)): ?>
            <button class="print-btn print" onclick="window.print()">
                <i class="fas fa-print"></i> Print All Payslips
            </button>
        <?php endif; ?>
    </div>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <script>
        // Function to go back to previous page and close current tab
        function goBackAndClose() {
            window.close();
            setTimeout(function() {
                if (document.referrer) {
                    window.location.href = document.referrer;
                } else {
                    window.location.href = 'permanentpayrolltable1.php?period=<?php echo $period; ?>&cutoff=<?php echo $cutoff; ?>';
                }
            }, 100);
        }

        window.onafterprint = function() {
            if (confirm('Printing complete. Do you want to close this window?')) {
                goBackAndClose();
            }
        };
    </script>
</body>

</html>