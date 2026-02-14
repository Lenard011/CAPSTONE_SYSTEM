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

// Get current user ID from session (you need to set this during login)
$current_user_id = $_SESSION['user_id'] ?? 1; // Default to 1 if not set
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

// Handle AJAX requests for real-time calculations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    try {
        if ($_POST['ajax_action'] === 'calculate_row') {
            $employee_id = $_POST['employee_id'];
            $monthly_salary = floatval($_POST['monthly_salary']);
            $days_present = floatval($_POST['days_present']);
            $gratuity = floatval($_POST['gratuity']);
            $income_tax = floatval($_POST['income_tax']);
            $community_tax = floatval($_POST['community_tax']);
            $gsis = floatval($_POST['gsis']);

            // Calculate earned salary
            $earned_salary = ($monthly_salary / 22) * $days_present;

            // Calculate total deductions
            $total_deductions = $gratuity + $income_tax + $community_tax + $gsis;

            // Calculate net amount
            $net_amount = $earned_salary - $total_deductions;

            echo json_encode([
                'success' => true,
                'earned_salary' => number_format($earned_salary, 2),
                'total_deductions' => number_format($total_deductions, 2),
                'net_amount' => number_format($net_amount, 2)
            ]);
            exit();
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Handle form submission for saving payroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payroll'])) {
    try {
        $pdo->beginTransaction();

        $payroll_period = $_POST['payroll_period'] ?? $current_payroll_period;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Get old values for audit log
        $old_values = [];
        foreach ($_POST['employee_id'] as $index => $employee_id) {
            $stmt = $pdo->prepare("
                SELECT * FROM payroll_history 
                WHERE employee_id = ? AND employee_type = 'contractual' AND payroll_period = ?
            ");
            $stmt->execute([$employee_id, $payroll_period]);
            $old_data = $stmt->fetch();
            if ($old_data) {
                $old_values[$employee_id] = $old_data;
            }
        }

        foreach ($_POST['employee_id'] as $index => $employee_id) {
            $gratuity = floatval($_POST['gratuity'][$index] ?? 0);
            $income_tax = floatval($_POST['income_tax'][$index] ?? 0);
            $community_tax = floatval($_POST['community_tax'][$index] ?? 0);
            $gsis = floatval($_POST['gsis'][$index] ?? 0);
            $total_deductions = floatval($_POST['total_deduction'][$index] ?? 0);
            $net_amount = floatval(str_replace(['₱', ','], '', $_POST['net_amount'][$index] ?? 0));
            $monthly_salary = floatval($_POST['monthly_salary'][$index] ?? 0);
            $days_present = floatval($_POST['days_present'][$index] ?? 0);
            $earned_salary = floatval(str_replace(['₱', ','], '', $_POST['earned_salary'][$index] ?? 0));

            // Check if payroll record exists
            $check_stmt = $pdo->prepare("
                SELECT id FROM payroll_history 
                WHERE employee_id = ? AND employee_type = 'contractual' AND payroll_period = ?
            ");
            $check_stmt->execute([$employee_id, $payroll_period]);
            $existing_id = $check_stmt->fetchColumn();

            if ($existing_id) {
                // Update existing record
                $update_stmt = $pdo->prepare("
                    UPDATE payroll_history 
                    SET monthly_salary = ?, days_present = ?, earned_salary = ?,
                        gratuity = ?, income_tax = ?, community_tax = ?, 
                        gsis = ?, total_deductions = ?, net_amount = ?,
                        status = 'pending', updated_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([
                    $monthly_salary,
                    $days_present,
                    $earned_salary,
                    $gratuity,
                    $income_tax,
                    $community_tax,
                    $gsis,
                    $total_deductions,
                    $net_amount,
                    $existing_id
                ]);

                // Insert into payroll_deductions for detailed tracking
                $deduction_types = [
                    ['Gratuity', $gratuity],
                    ['Income Tax', $income_tax],
                    ['Community Tax', $community_tax],
                    ['GSIS', $gsis]
                ];

                foreach ($deduction_types as $deduction) {
                    if ($deduction[1] > 0) {
                        $deduction_stmt = $pdo->prepare("
                            INSERT INTO payroll_deductions 
                            (payroll_id, deduction_type, deduction_amount, deduction_description)
                            VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                            deduction_amount = VALUES(deduction_amount)
                        ");
                        $deduction_stmt->execute([
                            $existing_id,
                            $deduction[0],
                            $deduction[1],
                            $deduction[0] . ' deduction for ' . $payroll_period
                        ]);
                    }
                }
            } else {
                // Insert new record
                $insert_stmt = $pdo->prepare("
                    INSERT INTO payroll_history 
                    (employee_id, employee_type, payroll_period, monthly_salary, days_present, 
                     earned_salary, gratuity, income_tax, community_tax, gsis, 
                     total_deductions, net_amount, processed_date, status)
                    VALUES (?, 'contractual', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')
                ");
                $insert_stmt->execute([
                    $employee_id,
                    $payroll_period,
                    $monthly_salary,
                    $days_present,
                    $earned_salary,
                    $gratuity,
                    $income_tax,
                    $community_tax,
                    $gsis,
                    $total_deductions,
                    $net_amount
                ]);

                $new_id = $pdo->lastInsertId();

                // Insert into payroll_deductions for detailed tracking
                $deduction_types = [
                    ['Gratuity', $gratuity],
                    ['Income Tax', $income_tax],
                    ['Community Tax', $community_tax],
                    ['GSIS', $gsis]
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
                            $deduction[0] . ' deduction for ' . $payroll_period
                        ]);
                    }
                }
            }
        }

        // Update payroll summary
        $summary_stmt = $pdo->prepare("
            INSERT INTO payroll_summary
            (payroll_period, employee_type, total_employees, total_monthly_salary, 
             total_days_present, total_earned_salary, total_income_tax, 
             total_community_tax, total_gsis, total_deductions, total_net_amount, status)
            SELECT 
                ?,
                'contractual',
                COUNT(*) as total_employees,
                SUM(monthly_salary) as total_monthly_salary,
                SUM(days_present) as total_days_present,
                SUM(earned_salary) as total_earned_salary,
                SUM(income_tax) as total_income_tax,
                SUM(community_tax) as total_community_tax,
                SUM(gsis) as total_gsis,
                SUM(total_deductions) as total_deductions,
                SUM(net_amount) as total_net_amount,
                'draft'
            FROM payroll_history
            WHERE payroll_period = ? AND employee_type = 'contractual'
            ON DUPLICATE KEY UPDATE
                total_employees = VALUES(total_employees),
                total_monthly_salary = VALUES(total_monthly_salary),
                total_days_present = VALUES(total_days_present),
                total_earned_salary = VALUES(total_earned_salary),
                total_income_tax = VALUES(total_income_tax),
                total_community_tax = VALUES(total_community_tax),
                total_gsis = VALUES(total_gsis),
                total_deductions = VALUES(total_deductions),
                total_net_amount = VALUES(total_net_amount),
                updated_at = NOW()
        ");
        $summary_stmt->execute([$payroll_period, $payroll_period]);

        $pdo->commit();

        // Call stored procedure for audit log
        try {
            $audit_stmt = $pdo->prepare("CALL sp_audit_payroll_change(?, ?, ?, ?, ?, ?, ?, ?)");
            $audit_stmt->execute([
                $current_user_id,
                'BULK_UPDATE',
                'payroll_history',
                0,
                json_encode($old_values),
                json_encode($_POST),
                $ip_address,
                $user_agent
            ]);
        } catch (Exception $e) {
            // Audit log failed but main transaction succeeded
            error_log("Audit log failed: " . $e->getMessage());
        }

        $_SESSION['success_message'] = "Payroll saved successfully!";

        // Redirect to refresh page
        header("Location: " . $_SERVER['PHP_SELF'] . "?period=" . $payroll_period);
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error saving payroll: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF'] . "?period=" . ($_POST['payroll_period'] ?? $current_payroll_period));
        exit();
    }
}

// Handle payroll approval
if (isset($_GET['approve_payroll']) && isset($_GET['period'])) {
    try {
        $pdo->beginTransaction();

        $period = $_GET['period'];
        $approval_notes = $_POST['approval_notes'] ?? 'Approved via system';

        // Call stored procedure for approval
        $stmt = $pdo->prepare("CALL sp_approve_payroll(?, ?, ?, ?)");
        $stmt->execute([$period, 'contractual', $current_user_name, $approval_notes]);

        $pdo->commit();
        $_SESSION['success_message'] = "Payroll approved successfully!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?period=" . $period);
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error approving payroll: " . $e->getMessage();
    }
}

// Handle payroll payment
if (isset($_GET['mark_paid']) && isset($_GET['period']) && isset($_GET['payroll_id'])) {
    try {
        $pdo->beginTransaction();

        $period = $_GET['period'];
        $payroll_id = $_GET['payroll_id'];
        $payment_method = $_POST['payment_method'] ?? 'bank_transfer';
        $reference_number = $_POST['reference_number'] ?? 'PAY-' . time();

        // Get payroll details
        $payroll_stmt = $pdo->prepare("
            SELECT * FROM payroll_history WHERE id = ?
        ");
        $payroll_stmt->execute([$payroll_id]);
        $payroll = $payroll_stmt->fetch();

        if ($payroll) {
            // Insert payment record
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
                'Payment processed for ' . $period
            ]);

            // Update payroll status
            $update_stmt = $pdo->prepare("
                UPDATE payroll_history 
                SET status = 'paid', updated_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([$payroll_id]);
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Payroll marked as paid successfully!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?period=" . $period);
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error marking payroll as paid: " . $e->getMessage();
    }
}

// Get selected payroll period (default to current month)
$selected_period = isset($_GET['period']) ? $_GET['period'] : $current_payroll_period;

// Fetch tax configuration
$tax_config = [];
$tax_stmt = $pdo->query("
    SELECT * FROM tax_configuration 
    WHERE tax_year = YEAR(CURDATE()) AND is_active = 1
    ORDER BY tax_bracket_min
");
$tax_config = $tax_stmt->fetchAll();

// Fetch GSIS configuration
$gsis_config = [];
$gsis_stmt = $pdo->query("
    SELECT * FROM gsis_configuration 
    WHERE is_active = 1 
    ORDER BY effectivity_date DESC 
    LIMIT 1
");
$gsis_config = $gsis_stmt->fetch();

// Fetch contractual employees from contractofservice table
$contractual_employees = [];

try {
    // Get all contractual employees
    $stmt = $pdo->prepare("
        SELECT 
            id as user_id,
            employee_id,
            CONCAT(first_name, ' ', last_name) as full_name,
            first_name,
            last_name,
            designation as position,
            office as department,
            wages as monthly_salary,
            period_from,
            period_to,
            status,
            email_address,
            mobile_number,
            contribution
        FROM contractofservice 
        WHERE status = 'active'
        ORDER BY last_name, first_name
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll();

    // Get current month's attendance for each employee
    $year_month = explode('-', $selected_period);
    $year = $year_month[0];
    $month = $year_month[1];

    foreach ($employees as &$employee) {
        // Get attendance records for current month
        $attendance_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as days_present,
                SUM(CASE 
                    WHEN total_hours >= 8 THEN 1
                    WHEN total_hours >= 4 THEN 0.5
                    ELSE 0
                END) as attendance_days,
                SUM(total_hours) as total_hours_worked
            FROM attendance 
            WHERE employee_id = ? 
                AND YEAR(date) = ? 
                AND MONTH(date) = ?
                AND total_hours > 0
        ");
        $attendance_stmt->execute([$employee['employee_id'], $year, $month]);
        $attendance = $attendance_stmt->fetch();

        // Calculate days present (consider half day as 0.5)
        $employee['days_present'] = floatval($attendance['attendance_days'] ?? 0);
        $employee['total_hours'] = floatval($attendance['total_hours_worked'] ?? 0);

        // Calculate earned salary (based on 22 working days per month)
        $monthly_salary = floatval($employee['monthly_salary']);
        $daily_rate = $monthly_salary / 22;
        $employee['earned_salary'] = $daily_rate * $employee['days_present'];

        // Check if payroll already exists for this period
        $payroll_stmt = $pdo->prepare("
            SELECT * FROM payroll_history 
            WHERE employee_id = ? AND employee_type = 'contractual' AND payroll_period = ?
        ");
        $payroll_stmt->execute([$employee['user_id'], $selected_period]);
        $payroll_data = $payroll_stmt->fetch();

        if ($payroll_data) {
            $employee['payroll_data'] = $payroll_data;

            // Get detailed deductions
            $deductions_stmt = $pdo->prepare("
                SELECT deduction_type, deduction_amount 
                FROM payroll_deductions 
                WHERE payroll_id = ?
            ");
            $deductions_stmt->execute([$payroll_data['id']]);
            $deductions = $deductions_stmt->fetchAll();

            $employee['deductions_detail'] = [];
            foreach ($deductions as $ded) {
                $employee['deductions_detail'][$ded['deduction_type']] = $ded['deduction_amount'];
            }
        } else {
            $employee['payroll_data'] = null;
            $employee['deductions_detail'] = [];
        }
    }

    $contractual_employees = $employees;
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error fetching employees: " . $e->getMessage();
    $contractual_employees = [];
}

// Calculate totals
$total_monthly_salary = 0;
$total_days_present = 0;
$total_earned_salary = 0;
$total_gratuity = 0;
$total_income_tax = 0;
$total_community_tax = 0;
$total_gsis = 0;
$total_deductions = 0;
$total_net_amount = 0;

foreach ($contractual_employees as $employee) {
    $total_monthly_salary += floatval($employee['monthly_salary']);
    $total_days_present += floatval($employee['days_present']);
    $total_earned_salary += floatval($employee['earned_salary']);

    if (isset($employee['payroll_data']) && $employee['payroll_data']) {
        $total_gratuity += floatval($employee['payroll_data']['gratuity'] ?? 0);
        $total_income_tax += floatval($employee['payroll_data']['income_tax'] ?? 0);
        $total_community_tax += floatval($employee['payroll_data']['community_tax'] ?? 0);
        $total_gsis += floatval($employee['payroll_data']['gsis'] ?? 0);
        $total_deductions += floatval($employee['payroll_data']['total_deductions'] ?? 0);
        $total_net_amount += floatval($employee['payroll_data']['net_amount'] ?? 0);
    } else {
        // Calculate default deductions if no payroll data exists
        $earned = floatval($employee['earned_salary']);

        // Income tax calculation based on tax config
        $income_tax = 0;
        foreach ($tax_config as $bracket) {
            if (
                $earned > $bracket['tax_bracket_min'] &&
                ($bracket['tax_bracket_max'] === null || $earned <= $bracket['tax_bracket_max'])
            ) {
                $income_tax = ($earned - $bracket['tax_bracket_min']) * ($bracket['tax_rate'] / 100);
                break;
            }
        }

        // Default community tax
        $community_tax = 500;

        // GSIS (if applicable)
        $gsis_rate = $gsis_config['premium_rate'] ?? 9.00;
        $gsis = $earned * ($gsis_rate / 100);

        $total_income_tax += $income_tax;
        $total_community_tax += $community_tax;
        $total_gsis += $gsis;
        $total_deductions += ($income_tax + $community_tax + $gsis);
        $total_net_amount += ($earned - ($income_tax + $community_tax + $gsis));
    }
}

// Get payroll summary for the period
$payroll_summary = [];
$summary_stmt = $pdo->prepare("
    SELECT * FROM payroll_summary 
    WHERE payroll_period = ? AND employee_type = 'contractual'
");
$summary_stmt->execute([$selected_period]);
$payroll_summary = $summary_stmt->fetch();

// Get payroll status for the period
$payroll_status = 'pending';
$status_stmt = $pdo->prepare("
    SELECT DISTINCT status FROM payroll_history 
    WHERE employee_type = 'contractual' AND payroll_period = ? 
    LIMIT 1
");
$status_stmt->execute([$selected_period]);
$status_result = $status_stmt->fetch();
if ($status_result) {
    $payroll_status = $status_result['status'];
}

// Get approval history
$approval_history = [];
$approval_stmt = $pdo->prepare("
    SELECT * FROM payroll_approvals 
    WHERE payroll_period = ? AND employee_type = 'contractual'
    ORDER BY approved_date DESC
");
$approval_stmt->execute([$selected_period]);
$approval_history = $approval_stmt->fetchAll();

// Get audit log for this period
$audit_log = [];
$audit_stmt = $pdo->prepare("
    SELECT * FROM payroll_audit_log 
    WHERE table_name = 'payroll_history' 
    AND JSON_EXTRACT(new_values, '$.payroll_period') = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$audit_stmt->execute([$selected_period]);
$audit_log = $audit_stmt->fetchAll();

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Function to calculate deductions for a row
function calculateRowDeductions($employee, $tax_config, $gsis_config)
{
    $earned = floatval($employee['earned_salary']);

    // Calculate income tax based on tax brackets
    $income_tax = 0;
    foreach ($tax_config as $bracket) {
        if (
            $earned > $bracket['tax_bracket_min'] &&
            ($bracket['tax_bracket_max'] === null || $earned <= $bracket['tax_bracket_max'])
        ) {
            $income_tax = ($earned - $bracket['tax_bracket_min']) * ($bracket['tax_rate'] / 100);
            break;
        }
    }

    // GSIS calculation
    $gsis_rate = $gsis_config['premium_rate'] ?? 9.00;
    $gsis = $earned * ($gsis_rate / 100);

    $deductions = [
        'gratuity' => 0,
        'income_tax' => $income_tax,
        'community_tax' => 500,
        'gsis' => $gsis,
        'total' => 0
    ];

    // If payroll data exists, use it
    if (isset($employee['payroll_data']) && $employee['payroll_data']) {
        $deductions['gratuity'] = floatval($employee['payroll_data']['gratuity'] ?? 0);
        $deductions['income_tax'] = floatval($employee['payroll_data']['income_tax'] ?? $income_tax);
        $deductions['community_tax'] = floatval($employee['payroll_data']['community_tax'] ?? 500);
        $deductions['gsis'] = floatval($employee['payroll_data']['gsis'] ?? $gsis);
    }

    // Override with detailed deductions if available
    if (isset($employee['deductions_detail'])) {
        $deductions['gratuity'] = $employee['deductions_detail']['Gratuity'] ?? $deductions['gratuity'];
        $deductions['income_tax'] = $employee['deductions_detail']['Income Tax'] ?? $deductions['income_tax'];
        $deductions['community_tax'] = $employee['deductions_detail']['Community Tax'] ?? $deductions['community_tax'];
        $deductions['gsis'] = $employee['deductions_detail']['GSIS'] ?? $deductions['gsis'];
    }

    $deductions['total'] = array_sum($deductions);

    return $deductions;
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
            min-width: 1600px;
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
            background-color: #f9fafb;
            border-color: #e5e7eb;
            color: #6b7280;
            cursor: not-allowed;
        }

        .payroll-input.error {
            border-color: #ef4444;
            background-color: #fef2f2;
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
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
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
        }

        .modal-body {
            padding: 1rem;
        }

        .modal-footer {
            padding: 1rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
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
            <div class="tab" data-tab="audit">Audit Log</div>
        </div>

        <!-- Payroll Details Tab -->
        <div class="tab-content active" id="tab-payroll">
            <!-- Page Header -->
            <div class="mb-6 mt-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900">Contractual Payroll</h1>
                        <p class="text-gray-600 mt-1 text-sm md:text-base">Manage and process contractual employee payroll</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <!-- Payroll Period Selector -->
                        <div class="period-selector">
                            <select id="payroll-period" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
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

                        <div class="flex gap-2">
                            <?php if ($payroll_status == 'pending' || $payroll_status == 'draft'): ?>
                                <button id="calculate-all-btn" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-calculator mr-2"></i> Calculate All
                                </button>
                                <button id="approve-payroll-btn"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center justify-center"
                                    onclick="showApproveModal()">
                                    <i class="fas fa-check-circle mr-2"></i> Approve Payroll
                                </button>
                            <?php endif; ?>

                            <!-- Export Dropdown -->
                            <div class="export-dropdown">
                                <button class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-download mr-2"></i> Export
                                </button>
                                <div class="export-dropdown-content">
                                    <a href="#" onclick="exportData('pdf')"><i class="fas fa-file-pdf mr-2"></i> Export as PDF</a>
                                    <a href="#" onclick="exportData('excel')"><i class="fas fa-file-excel mr-2"></i> Export as Excel</a>
                                    <a href="#" onclick="exportData('csv')"><i class="fas fa-file-csv mr-2"></i> Export as CSV</a>
                                </div>
                            </div>

                            <button id="refresh-btn" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg flex items-center justify-center">
                                <i class="fas fa-sync-alt mr-2"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Payroll Status -->
                <div class="mt-3 flex items-center gap-4">
                    <div>
                        <span class="text-sm font-medium text-gray-700 mr-2">Payroll Status:</span>
                        <span class="status-badge status-<?php echo $payroll_status; ?>">
                            <?php echo ucfirst($payroll_status); ?>
                        </span>
                    </div>

                    <?php if ($payroll_summary): ?>
                        <div>
                            <span class="text-sm font-medium text-gray-700 mr-2">Total Employees:</span>
                            <span class="font-semibold"><?php echo $payroll_summary['total_employees']; ?></span>
                        </div>
                    <?php endif; ?>
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
                            <p class="text-xl md:text-2xl font-bold text-gray-900" id="total-payroll-display">₱<?php echo number_format($total_earned_salary, 2); ?></p>
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

                <div class="card">
                    <div class="p-4 border-b border-gray-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <h2 class="text-lg font-semibold text-gray-900">Employee Payroll Details for <?php echo date('F Y', strtotime($selected_period . '-01')); ?></h2>
                        <div class="flex items-center space-x-2 w-full md:w-auto">
                            <div class="relative flex-1 md:flex-none">
                                <input type="text" id="search-employees" placeholder="Search employees..." class="pl-9 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 w-full text-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                            </div>
                            <button type="button" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg border border-gray-300" id="filter-btn" onclick="showFilterModal()">
                                <i class="fas fa-filter"></i>
                            </button>
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
                                    <th colspan="3" class="text-center">Compensation</th>
                                    <th colspan="4" class="text-center">Deductions</th>
                                    <th colspan="2" class="text-center">Net Amount</th>
                                    <th rowspan="2" class="min-w-[150px]">Actions</th>
                                </tr>
                                <tr>
                                    <th class="min-w-[100px]">Monthly Salary</th>
                                    <th class="min-w-[80px]">Days Present</th>
                                    <th class="min-w-[100px]">Earned Salary</th>
                                    <th class="min-w-[100px]">Gratuity</th>
                                    <th class="min-w-[100px]">Income Tax</th>
                                    <th class="min-w-[100px]">Community Tax</th>
                                    <th class="min-w-[100px]">GSIS</th>
                                    <th class="min-w-[100px]">Total Deduction</th>
                                    <th class="min-w-[100px]">Net Amount</th>
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
                                        $deductions = calculateRowDeductions($employee, $tax_config, $gsis_config);
                                        $earned_salary = floatval($employee['earned_salary']);
                                        $net_amount_row = $earned_salary - $deductions['total'];
                                        $payroll_data = $employee['payroll_data'] ?? null;
                                        $payroll_id = $payroll_data['id'] ?? null;
                                        ?>
                                        <tr class="bg-white border-b hover:bg-gray-50 payroll-row" data-employee-id="<?php echo $employee['user_id']; ?>" data-payroll-id="<?php echo $payroll_id; ?>">
                                            <td class="text-center"><?php echo $counter++; ?></td>
                                            <td class="font-medium"><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                            <td class="font-medium">
                                                <?php echo htmlspecialchars($employee['full_name']); ?>
                                                <input type="hidden" name="employee_id[]" value="<?php echo $employee['user_id']; ?>">
                                                <input type="hidden" name="monthly_salary[]" class="monthly-salary-hidden" value="<?php echo $employee['monthly_salary']; ?>">
                                                <input type="hidden" name="days_present[]" class="days-present-hidden" value="<?php echo $employee['days_present']; ?>">
                                                <input type="hidden" name="earned_salary[]" class="earned-salary-hidden" value="<?php echo $earned_salary; ?>">
                                            </td>
                                            <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['department']); ?></td>

                                            <!-- Compensation -->
                                            <td>
                                                <input type="text"
                                                    class="payroll-input readonly monthly-salary-display"
                                                    value="₱<?php echo number_format($employee['monthly_salary'], 2); ?>"
                                                    data-original="<?php echo $employee['monthly_salary']; ?>"
                                                    readonly>
                                            </td>
                                            <td class="text-center days-present">
                                                <?php echo number_format($employee['days_present'], 1); ?> / 22
                                            </td>
                                            <td>
                                                <input type="text"
                                                    class="payroll-input readonly earned-salary-display"
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
                                                    data-employee-id="<?php echo $employee['user_id']; ?>"
                                                    <?php echo ($payroll_status != 'pending' && $payroll_status != 'draft') ? 'readonly' : ''; ?>>
                                            </td>
                                            <td>
                                                <input type="number"
                                                    name="income_tax[]"
                                                    class="payroll-input income-tax"
                                                    value="<?php echo $deductions['income_tax']; ?>"
                                                    min="0"
                                                    step="0.01"
                                                    data-employee-id="<?php echo $employee['user_id']; ?>"
                                                    <?php echo ($payroll_status != 'pending' && $payroll_status != 'draft') ? 'readonly' : ''; ?>>
                                            </td>
                                            <td>
                                                <input type="number"
                                                    name="community_tax[]"
                                                    class="payroll-input community-tax"
                                                    value="<?php echo $deductions['community_tax']; ?>"
                                                    min="0"
                                                    step="0.01"
                                                    data-employee-id="<?php echo $employee['user_id']; ?>"
                                                    <?php echo ($payroll_status != 'pending' && $payroll_status != 'draft') ? 'readonly' : ''; ?>>
                                            </td>
                                            <td>
                                                <input type="number"
                                                    name="gsis[]"
                                                    class="payroll-input gsis"
                                                    value="<?php echo $deductions['gsis']; ?>"
                                                    min="0"
                                                    step="0.01"
                                                    data-employee-id="<?php echo $employee['user_id']; ?>"
                                                    <?php echo ($payroll_status != 'pending' && $payroll_status != 'draft') ? 'readonly' : ''; ?>>
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

                                            <td>
                                                <div class="action-buttons">
                                                    <button type="button" class="action-btn bg-blue-500 text-white hover:bg-blue-600 view-row" onclick="viewEmployeeDetails(<?php echo $employee['user_id']; ?>)">
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

                                                    <button type="button" class="action-btn bg-purple-500 text-white hover:bg-purple-600" onclick="viewDeductions(<?php echo $payroll_id; ?>)">
                                                        <i class="fas fa-chart-pie"></i> <span class="hidden md:inline">Deductions</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <!-- Totals Row -->
                                <tr class="bg-gray-100 font-bold">
                                    <td colspan="5" class="text-right">TOTAL AMOUNT</td>
                                    <td class="text-right" id="total-monthly-salary">₱<?php echo number_format($total_monthly_salary, 2); ?></td>
                                    <td class="text-right" id="total-days-present"><?php echo number_format($total_days_present, 1); ?></td>
                                    <td class="text-right" id="total-earned-salary">₱<?php echo number_format($total_earned_salary, 2); ?></td>
                                    <td class="text-right" id="total-gratuity">₱<?php echo number_format($total_gratuity, 2); ?></td>
                                    <td class="text-right" id="total-income-tax">₱<?php echo number_format($total_income_tax, 2); ?></td>
                                    <td class="text-right" id="total-community-tax">₱<?php echo number_format($total_community_tax, 2); ?></td>
                                    <td class="text-right" id="total-gsis">₱<?php echo number_format($total_gsis, 2); ?></td>
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
                        </div>
                        <div class="flex items-center space-x-2">
                            <button type="button" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50" id="prev-btn" disabled>
                                Previous
                            </button>
                            <button type="button" class="px-3 py-1.5 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700" id="page-1">
                                1
                            </button>
                            <button type="button" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50" id="next-btn" disabled>
                                Next
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <?php if ($payroll_status == 'pending' || $payroll_status == 'draft'): ?>
                    <div class="mt-6 flex flex-col sm:flex-row justify-end gap-3">
                        <button type="submit" class="px-4 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 flex items-center justify-center">
                            <i class="fas fa-save mr-2"></i> Save Payroll
                        </button>
                        <button type="button" onclick="generateObligationRequest()" class="px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 flex items-center justify-center">
                            Generate Obligation Request <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Payroll Summary Tab -->
        <div class="tab-content" id="tab-summary">
            <div class="card p-6">
                <h2 class="text-xl font-bold mb-4">Payroll Summary for <?php echo date('F Y', strtotime($selected_period . '-01')); ?></h2>

                <?php if ($payroll_summary): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="summary-card">
                                <h3 class="text-lg opacity-90">Total Payroll Amount</h3>
                                <div class="amount">₱<?php echo number_format($payroll_summary['total_net_amount'], 2); ?></div>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <span class="font-medium">Total Employees:</span>
                                    <span class="font-bold"><?php echo $payroll_summary['total_employees']; ?></span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <span class="font-medium">Total Days Present:</span>
                                    <span class="font-bold"><?php echo number_format($payroll_summary['total_days_present'], 1); ?></span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <span class="font-medium">Total Monthly Salary:</span>
                                    <span class="font-bold">₱<?php echo number_format($payroll_summary['total_monthly_salary'], 2); ?></span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <span class="font-medium">Total Earned Salary:</span>
                                    <span class="font-bold">₱<?php echo number_format($payroll_summary['total_earned_salary'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold mb-3">Deductions Breakdown</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                                    <span class="font-medium">Income Tax:</span>
                                    <span class="font-bold text-red-600">₱<?php echo number_format($payroll_summary['total_income_tax'], 2); ?></span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-yellow-50 rounded">
                                    <span class="font-medium">Community Tax:</span>
                                    <span class="font-bold text-yellow-600">₱<?php echo number_format($payroll_summary['total_community_tax'], 2); ?></span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                                    <span class="font-medium">GSIS:</span>
                                    <span class="font-bold text-blue-600">₱<?php echo number_format($payroll_summary['total_gsis'], 2); ?></span>
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
                    <p class="text-gray-500 text-center py-8">No payroll summary available for this period.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Approval History Tab -->
        <div class="tab-content" id="tab-approvals">
            <div class="card p-6">
                <h2 class="text-xl font-bold mb-4">Approval History</h2>

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
                    <p class="text-gray-500 text-center py-8">No approval history found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Audit Log Tab -->
        <div class="tab-content" id="tab-audit">
            <div class="card p-6">
                <h2 class="text-xl font-bold mb-4">Audit Log</h2>

                <?php if ($audit_log): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Changes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($audit_log as $log): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php echo $log['action'] == 'UPDATE' ? 'bg-yellow-100 text-yellow-800' : ($log['action'] == 'INSERT' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'); ?>">
                                                <?php echo $log['action']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $log['user_id'] ?? 'System'; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $log['ip_address'] ?? 'N/A'; ?></td>
                                        <td class="px-6 py-4">
                                            <button onclick="viewAuditDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)"
                                                class="text-blue-600 hover:text-blue-900">
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-8">No audit log entries found.</p>
                <?php endif; ?>
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
                <form action="?approve_payroll=1&period=<?php echo $selected_period; ?>" method="POST">
                    <div class="modal-body">
                        <p class="mb-4">Are you sure you want to approve the payroll for <?php echo date('F Y', strtotime($selected_period . '-01')); ?>?</p>
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

        // Payroll Calculation Functions with AJAX
        async function calculateRow(row) {
            const monthlySalary = parseFloat(row.querySelector('.monthly-salary-hidden').value) || 0;
            const daysPresent = parseFloat(row.querySelector('.days-present').textContent.split('/')[0]) || 0;

            // Get deduction values
            const gratuity = parseFloat(row.querySelector('.gratuity').value) || 0;
            const incomeTax = parseFloat(row.querySelector('.income-tax').value) || 0;
            const communityTax = parseFloat(row.querySelector('.community-tax').value) || 0;
            const gsis = parseFloat(row.querySelector('.gsis').value) || 0;
            const employeeId = row.dataset.employeeId;

            try {
                // Make AJAX call to calculate
                const formData = new FormData();
                formData.append('ajax_action', 'calculate_row');
                formData.append('employee_id', employeeId);
                formData.append('monthly_salary', monthlySalary);
                formData.append('days_present', daysPresent);
                formData.append('gratuity', gratuity);
                formData.append('income_tax', incomeTax);
                formData.append('community_tax', communityTax);
                formData.append('gsis', gsis);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Update row values
                    row.querySelector('.earned-salary-hidden').value = result.earned_salary.replace(/[₱,]/g, '');
                    row.querySelector('.earned-salary-display').value = '₱' + result.earned_salary;
                    row.querySelector('.total-deduction').value = result.total_deductions;
                    row.querySelector('.net-amount').value = '₱' + result.net_amount;

                    return {
                        earnedSalary: parseFloat(result.earned_salary.replace(/[₱,]/g, '')),
                        totalDeduction: parseFloat(result.total_deductions),
                        netAmount: parseFloat(result.net_amount.replace(/[₱,]/g, '')),
                        gratuity,
                        incomeTax,
                        communityTax,
                        gsis
                    };
                } else {
                    showNotification('Error calculating row: ' + result.error, 'error');
                    return null;
                }
            } catch (error) {
                showNotification('Error connecting to server', 'error');
                return null;
            }
        }

        async function calculateAll() {
            let totalEarned = 0;
            let totalGratuity = 0;
            let totalIncomeTax = 0;
            let totalCommunityTax = 0;
            let totalGsis = 0;
            let totalDeduction = 0;
            let totalNetAmount = 0;
            let totalDaysPresent = 0;
            let totalMonthlySalary = 0;

            const rows = document.querySelectorAll('.payroll-row');

            // Show loading indicator
            showNotification('Calculating all rows...', 'info');

            for (const row of rows) {
                const result = await calculateRow(row);

                if (result) {
                    // Get monthly salary and days present from hidden fields
                    const monthlySalary = parseFloat(row.querySelector('.monthly-salary-hidden').value) || 0;
                    const daysPresent = parseFloat(row.querySelector('.days-present').textContent.split('/')[0]) || 0;

                    totalMonthlySalary += monthlySalary;
                    totalDaysPresent += daysPresent;
                    totalEarned += result.earnedSalary;
                    totalGratuity += result.gratuity;
                    totalIncomeTax += result.incomeTax;
                    totalCommunityTax += result.communityTax;
                    totalGsis += result.gsis;
                    totalDeduction += result.totalDeduction;
                    totalNetAmount += result.netAmount;
                }
            }

            // Update total displays
            document.getElementById('total-monthly-salary').textContent = '₱' + totalMonthlySalary.toFixed(2);
            document.getElementById('total-days-present').textContent = totalDaysPresent.toFixed(1);
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

            showNotification('All calculations completed!', 'success');

            return {
                totalEarned,
                totalDeduction,
                totalNetAmount
            };
        }

        // Event Listeners for Calculations
        document.querySelectorAll('.gratuity, .income-tax, .community-tax, .gsis').forEach(input => {
            input.addEventListener('input', debounce(function() {
                const row = this.closest('.payroll-row');
                calculateRow(row).then(() => calculateAll());
            }, 500));
        });

        // Debounce function to limit API calls
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func.apply(this, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
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

        // View employee details
        window.viewEmployeeDetails = function(employeeId) {
            const row = document.querySelector(`.payroll-row[data-employee-id="${employeeId}"]`);
            if (!row) return;

            const name = row.querySelector('td:nth-child(3)').textContent.trim();
            const empId = row.querySelector('td:nth-child(2)').textContent.trim();
            const position = row.querySelector('td:nth-child(4)').textContent;
            const department = row.querySelector('td:nth-child(5)').textContent;
            const monthlySalary = row.querySelector('.monthly-salary-display').value;
            const daysPresent = row.querySelector('.days-present').textContent;
            const earnedSalary = row.querySelector('.earned-salary-display').value;
            const netAmount = row.querySelector('.net-amount').value;

            const details = `
                Employee: ${name}
                Employee ID: ${empId}
                Position: ${position}
                Department: ${department}
                Monthly Salary: ${monthlySalary}
                Days Present: ${daysPresent}
                Earned Salary: ${earnedSalary}
                Net Amount: ${netAmount}
            `;

            alert(details);
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

        // Refresh button
        document.getElementById('refresh-btn').addEventListener('click', function() {
            location.reload();
        });

        // Payroll period change
        document.getElementById('payroll-period').addEventListener('change', function() {
            const period = this.value;
            window.location.href = '?period=' + period;
        });

        // Form submission
        const payrollForm = document.getElementById('payroll-form');
        if (payrollForm) {
            payrollForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Validate all inputs
                let isValid = true;
                document.querySelectorAll('.payroll-input:not(.readonly)').forEach(input => {
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
                calculateAll().then(() => {
                    // Show confirmation
                    if (confirm('Are you sure you want to save the payroll data?')) {
                        // Show loading
                        showNotification('Saving payroll data...', 'info');
                        this.submit();
                    }
                });
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

        window.showFilterModal = function() {
            // Implement filter modal
            alert('Filter functionality coming soon!');
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
            window.location.href = `export_payroll.php?period=${period}&format=${format}&type=contractual`;
        };

        // Generate obligation request
        window.generateObligationRequest = function() {
            const period = document.getElementById('payroll-period').value;
            window.location.href = `contractualobligationrequest.php?period=${period}`;
        };

        // View audit details
        window.viewAuditDetails = function(log) {
            const details = `
                Action: ${log.action}
                Timestamp: ${log.created_at}
                User ID: ${log.user_id || 'System'}
                IP Address: ${log.ip_address || 'N/A'}
                
                Old Values:
                ${JSON.stringify(log.old_values, null, 2)}
                
                New Values:
                ${JSON.stringify(log.new_values, null, 2)}
            `;
            alert(details);
        };

        // Initialize charts
        function initializeCharts() {
            <?php if ($payroll_summary): ?>
                const ctx = document.getElementById('deductionsChart').getContext('2d');
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: ['Income Tax', 'Community Tax', 'GSIS', 'Net Pay'],
                        datasets: [{
                            data: [
                                <?php echo $payroll_summary['total_income_tax']; ?>,
                                <?php echo $payroll_summary['total_community_tax']; ?>,
                                <?php echo $payroll_summary['total_gsis']; ?>,
                                <?php echo $payroll_summary['total_net_amount']; ?>
                            ],
                            backgroundColor: [
                                '#ef4444',
                                '#f59e0b',
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