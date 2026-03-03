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
        window.location.href = document.referrer || 'joborderpayrolltable1.php?period=$period&cutoff=$cutoff';
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

$current_cutoff = $cutoff_ranges[$cutoff];

// Fetch selected employees
$employees_data = [];
$employee_ids_array = explode(',', $employee_ids);

if (!empty($employee_ids_array)) {
    $placeholders = implode(',', array_fill(0, count($employee_ids_array), '?'));

    try {
        $sql = "
            SELECT 
                id as user_id, 
                employee_id, 
                employee_name as full_name,
                occupation as position, 
                office as department,
                rate_per_day,
                sss_contribution,
                ctc_number,
                ctc_date,
                place_of_issue,
                mobile_number,
                email_address,
                joining_date,
                eligibility
            FROM job_order 
            WHERE employee_id IN ($placeholders) AND (is_archived = 0 OR is_archived IS NULL)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($employee_ids_array);
        $employees = $stmt->fetchAll();

        // Get attendance and payroll data for each employee
        foreach ($employees as &$employee) {
            // Get attendance for the cutoff period
            $attendance_days = 0;
            try {
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
            } catch (Exception $e) {
                error_log("Attendance fetch error: " . $e->getMessage());
            }

            $employee['days_present'] = $attendance_days;

            // Get payroll data from payroll_history_joborder table
            if ($cutoff == 'full') {
                // For full month, get data from both halves and sum them
                $payroll_stmt = $pdo->prepare("
                    SELECT 
                        SUM(other_comp) as other_comp,
                        SUM(withholding_tax) as withholding_tax,
                        SUM(sss) as sss,
                        SUM(philhealth) as philhealth,
                        SUM(pagibig) as pagibig,
                        SUM(total_deductions) as total_deductions,
                        SUM(gross_amount) as gross_amount,
                        SUM(net_amount) as net_amount,
                        SUM(days_present) as days_present,
                        COUNT(*) as record_count,
                        GROUP_CONCAT(DISTINCT status) as statuses
                    FROM payroll_history_joborder 
                    WHERE employee_id = ? 
                        AND payroll_period = ? AND payroll_cutoff IN ('first_half', 'second_half')
                ");
                $payroll_stmt->execute([$employee['employee_id'], $period]);
                $payroll_data = $payroll_stmt->fetch();

                if ($payroll_data && $payroll_data['record_count'] > 0) {
                    $employee['other_comp'] = floatval($payroll_data['other_comp'] ?? 0);
                    $employee['withholding_tax'] = floatval($payroll_data['withholding_tax'] ?? 0);
                    $employee['sss'] = floatval($payroll_data['sss'] ?? 0);
                    $employee['philhealth'] = floatval($payroll_data['philhealth'] ?? 0);
                    $employee['pagibig'] = floatval($payroll_data['pagibig'] ?? 0);
                    $employee['total_deductions'] = floatval($payroll_data['total_deductions'] ?? 0);
                    $employee['gross_amount'] = floatval($payroll_data['gross_amount'] ?? 0);
                    $employee['net_amount'] = floatval($payroll_data['net_amount'] ?? 0);

                    // Determine overall status
                    $statuses = explode(',', $payroll_data['statuses']);
                    if (in_array('approved', $statuses)) {
                        $employee['payroll_status'] = 'approved';
                    } elseif (in_array('paid', $statuses)) {
                        $employee['payroll_status'] = 'paid';
                    } elseif (in_array('pending', $statuses)) {
                        $employee['payroll_status'] = 'pending';
                    } else {
                        $employee['payroll_status'] = 'draft';
                    }
                } else {
                    // Calculate based on attendance
                    $rate_per_day = floatval($employee['rate_per_day'] ?? 0);
                    $prorated_salary = $rate_per_day * $attendance_days;

                    $employee['other_comp'] = 0;
                    $employee['withholding_tax'] = 0;
                    $employee['sss'] = 0;
                    $employee['philhealth'] = 0;
                    $employee['pagibig'] = 0;
                    $employee['total_deductions'] = 0;
                    $employee['gross_amount'] = $prorated_salary;
                    $employee['net_amount'] = $prorated_salary;
                    $employee['payroll_status'] = 'draft';
                }
            } else {
                // For specific half, get just that half's data
                $payroll_stmt = $pdo->prepare("
                    SELECT other_comp, withholding_tax, sss, philhealth, pagibig, 
                        total_deductions, gross_amount, net_amount, days_present, status
                    FROM payroll_history_joborder 
                    WHERE employee_id = ? 
                        AND payroll_period = ? AND payroll_cutoff = ?
                ");
                $payroll_stmt->execute([$employee['employee_id'], $period, $cutoff]);
                $payroll_data = $payroll_stmt->fetch();

                if ($payroll_data) {
                    $employee['other_comp'] = floatval($payroll_data['other_comp'] ?? 0);
                    $employee['withholding_tax'] = floatval($payroll_data['withholding_tax'] ?? 0);
                    $employee['sss'] = floatval($payroll_data['sss'] ?? 0);
                    $employee['philhealth'] = floatval($payroll_data['philhealth'] ?? 0);
                    $employee['pagibig'] = floatval($payroll_data['pagibig'] ?? 0);
                    $employee['total_deductions'] = floatval($payroll_data['total_deductions'] ?? 0);
                    $employee['gross_amount'] = floatval($payroll_data['gross_amount'] ?? 0);
                    $employee['net_amount'] = floatval($payroll_data['net_amount'] ?? 0);
                    $employee['payroll_status'] = $payroll_data['status'] ?? 'pending';
                } else {
                    // Calculate based on attendance
                    $rate_per_day = floatval($employee['rate_per_day'] ?? 0);
                    $prorated_salary = $rate_per_day * $attendance_days;

                    $employee['other_comp'] = 0;
                    $employee['withholding_tax'] = 0;
                    $employee['sss'] = 0;
                    $employee['philhealth'] = 0;
                    $employee['pagibig'] = 0;
                    $employee['total_deductions'] = 0;
                    $employee['gross_amount'] = $prorated_salary;
                    $employee['net_amount'] = $prorated_salary;
                    $employee['payroll_status'] = 'draft';
                }
            }

            // Calculate monthly salary from rate_per_day
            $employee['monthly_salary'] = floatval($employee['rate_per_day'] ?? 0) * 22;

            // Calculate total deductions if needed
            if (
                $employee['total_deductions'] == 0 &&
                ($employee['withholding_tax'] > 0 || $employee['sss'] > 0 ||
                    $employee['philhealth'] > 0 || $employee['pagibig'] > 0)
            ) {
                $employee['total_deductions'] = $employee['withholding_tax'] +
                    $employee['sss'] +
                    $employee['philhealth'] +
                    $employee['pagibig'];
            }

            $employees_data[] = $employee;
        }
    } catch (Exception $e) {
        error_log("Error fetching employees: " . $e->getMessage());
        $_SESSION['error_message'] = "Error fetching employee data: " . $e->getMessage();
        echo "<script>
            alert('Error fetching employee data.');
            window.close();
            window.location.href = document.referrer || 'joborderpayrolltable1.php?period=$period&cutoff=$cutoff';
        </script>";
        exit();
    }
}

// Debug: Check if we have data
error_log("Number of employees fetched: " . count($employees_data));
foreach ($employees_data as $emp) {
    error_log("Employee: " . $emp['employee_id'] . " - Gross: " . ($emp['gross_amount'] ?? 0) . " - Net: " . ($emp['net_amount'] ?? 0));
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multiple Job Order Payslips - <?php echo $company_name; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
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

        .attendance-summary {
            padding: 10px 15px;
            background: #f0fdf4;
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
            color: #166534;
        }

        .attendance-box .label {
            font-size: 9px;
            color: #6b7280;
            margin-top: 2px;
        }

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
            background: #f0fdf4;
            font-weight: 700;
            font-size: 12px;
        }

        .deductions-title {
            font-size: 12px;
            font-weight: 600;
            color: #166534;
            margin-bottom: 4px;
        }

        .net-pay {
            margin: 12px 0;
            padding: 8px 15px;
            background: #dcfce7;
            border-radius: 3px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16px;
            font-weight: 700;
        }

        .net-pay-label {
            color: #166534;
        }

        .net-pay-amount {
            color: #059669;
        }

        .amount-words {
            padding: 0 15px 8px;
            font-size: 9px;
            color: #6b7280;
            font-style: italic;
        }

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

        .payslip-footer {
            padding: 6px 15px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            color: #6b7280;
        }

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
            background: #166534;
            color: white;
        }

        .print-btn.back {
            background: #6b7280;
            color: white;
        }

        .text-center {
            text-align: center;
        }

        .joborder-badge {
            display: inline-block;
            background: #166534;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 5px;
        }

        @media print {
            @page {
                size: portrait;
                margin: 0;
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

            .payslip-header,
            .attendance-summary,
            .grand-total,
            .net-pay,
            .total-row {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

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
                            <h3>JOB ORDER PAYSLIP <span class="joborder-badge">JOB ORDER</span></h3>
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

                    <!-- Salary Details -->
                    <table class="salary-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="amount">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Daily Rate</td>
                                <td class="amount"><?php echo formatCurrency($employee['rate_per_day'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Monthly Salary (22 days)</td>
                                <td class="amount"><?php echo formatCurrency($employee['monthly_salary'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Days Present</td>
                                <td class="amount"><?php echo number_format($employee['days_present'] ?? 0, 1); ?> days</td>
                            </tr>
                            <tr>
                                <td>Prorated Salary</td>
                                <td class="amount"><?php echo formatCurrency(($employee['rate_per_day'] ?? 0) * ($employee['days_present'] ?? 0)); ?></td>
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

                            <!-- Deductions -->
                            <tr>
                                <td colspan="2" style="padding: 8px 0 4px;">
                                    <div class="deductions-title">DEDUCTIONS</div>
                                </td>
                            </tr>
                            <?php
                            $deduction_rows = [
                                'withholding_tax' => 'Withholding Tax',
                                'sss' => 'SSS Contribution',
                                'philhealth' => 'PhilHealth Contribution',
                                'pagibig' => 'Pag-IBIG Contribution'
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

                    <!-- CTC Information (if available) -->
                    <?php if (!empty($employee['ctc_number'])): ?>
                        <div style="padding: 0 15px 8px;">
                            <p style="font-size: 8px; color: #6b7280;">
                                <strong>CTC #:</strong> <?php echo htmlspecialchars($employee['ctc_number']); ?> |
                                <strong>Issued at:</strong> <?php echo htmlspecialchars($employee['place_of_issue']); ?> |
                                <strong>Date:</strong> <?php echo !empty($employee['ctc_date']) ? date('F d, Y', strtotime($employee['ctc_date'])) : 'N/A'; ?>
                            </p>
                        </div>
                    <?php endif; ?>

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
                    window.location.href = 'joborderpayrolltable1.php?period=<?php echo $period; ?>&cutoff=<?php echo $cutoff; ?>';
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