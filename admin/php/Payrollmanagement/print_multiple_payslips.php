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

    // Get parameters from URL
    $employee_ids = isset($_GET['employees']) ? $_GET['employees'] : '';
    $period = isset($_GET['period']) ? $_GET['period'] : date('Y-m');
    $cutoff = isset($_GET['cutoff']) ? $_GET['cutoff'] : 'full';

    // If no employees selected, redirect back
    if (empty($employee_ids)) {
        $_SESSION['error_message'] = "No employees selected for printing.";
        header("Location: contractualpayrolltable1.php?period=$period&cutoff=$cutoff");
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

    // Function to get employee salary column name
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

    // Fetch selected employees
    $employees_data = [];
    $employee_ids_array = explode(',', $employee_ids);

    if (!empty($employee_ids_array)) {
        $placeholders = implode(',', array_fill(0, count($employee_ids_array), '?'));
        
        try {
            // Get salary column
            $salary_col = getSalaryColumnName($pdo);
            
            // Build SELECT query
            $select_fields = "id as user_id, employee_id, CONCAT(first_name, ' ', last_name) as full_name, first_name, last_name, designation as position, office as department";
            
            if ($salary_col) {
                if ($salary_col == 'rate_per_day' || $salary_col == 'daily_rate') {
                    $select_fields .= ", ($salary_col * 22) as monthly_salary";
                } else {
                    $select_fields .= ", $salary_col as monthly_salary";
                }
            } else {
                $select_fields .= ", 0 as monthly_salary";
            }
            
            $sql = "SELECT $select_fields FROM contractofservice WHERE id IN ($placeholders) AND status = 'active'";
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
                
                // Get payroll data
                $payroll_stmt = $pdo->prepare("
                    SELECT other_comp, withholding_tax, sss, total_deductions, gross_amount, net_amount, days_present, status
                    FROM payroll_history 
                    WHERE employee_id = ? AND employee_type = 'contractual' 
                        AND payroll_period = ? AND payroll_cutoff = ?
                ");
                $payroll_stmt->execute([$employee['user_id'], $period, $cutoff]);
                $payroll_data = $payroll_stmt->fetch();
                
                if ($payroll_data) {
                    $employee['other_comp'] = floatval($payroll_data['other_comp'] ?? 0);
                    $employee['withholding_tax'] = floatval($payroll_data['withholding_tax'] ?? 0);
                    $employee['sss'] = floatval($payroll_data['sss'] ?? 0);
                    $employee['total_deductions'] = floatval($payroll_data['total_deductions'] ?? 0);
                    $employee['gross_amount'] = floatval($payroll_data['gross_amount'] ?? 0);
                    $employee['net_amount'] = floatval($payroll_data['net_amount'] ?? 0);
                    $employee['payroll_status'] = $payroll_data['status'] ?? 'pending';
                } else {
                    // Calculate based on attendance
                    $monthly_salary = floatval($employee['monthly_salary'] ?? 0);
                    $daily_rate = $monthly_salary / 22;
                    $prorated_salary = $daily_rate * $attendance_days;
                    
                    $employee['other_comp'] = 0;
                    $employee['withholding_tax'] = 0;
                    $employee['sss'] = 0;
                    $employee['total_deductions'] = 0;
                    $employee['gross_amount'] = $prorated_salary;
                    $employee['net_amount'] = $prorated_salary;
                    $employee['payroll_status'] = 'draft';
                }
                
                $employees_data[] = $employee;
            }
        } catch (Exception $e) {
            error_log("Error fetching employees: " . $e->getMessage());
            $_SESSION['error_message'] = "Error fetching employee data: " . $e->getMessage();
            header("Location: contractualpayrolltable1.php?period=$period&cutoff=$cutoff");
            exit();
        }
    }

    // Get company info
    $company_name = "LGU Paluan";
    $company_address = "Paluan, Occidental Mindoro";
    $company_logo = "https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png";

    // Format currency
    function formatCurrency($amount) {
        return '₱' . number_format($amount, 2);
    }

    // Format date
    function formatDate($date) {
        return date('F d, Y', strtotime($date));
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Multiple Payslips - <?php echo $company_name; ?></title>
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
                max-width: 1200px;
                margin: 0 auto;
            }
            
            .payslip {
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                margin-bottom: 30px;
                page-break-after: always;
                position: relative;
            }
            
            .payslip:last-child {
                page-break-after: auto;
            }
            
            .payslip-header {
                background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
                color: white;
                padding: 20px;
                border-radius: 8px 8px 0 0;
                display: flex;
                align-items: center;
                gap: 20px;
            }
            
            .company-logo {
                width: 80px;
                height: 80px;
                object-fit: contain;
                background: white;
                border-radius: 50%;
                padding: 5px;
            }
            
            .company-info h2 {
                font-size: 24px;
                margin-bottom: 5px;
            }
            
            .company-info p {
                font-size: 14px;
                opacity: 0.9;
            }
            
            .payslip-title {
                margin-left: auto;
                text-align: right;
            }
            
            .payslip-title h3 {
                font-size: 20px;
                margin-bottom: 5px;
            }
            
            .payslip-title .period {
                font-size: 14px;
                opacity: 0.9;
            }
            
            .employee-info {
                padding: 20px;
                border-bottom: 2px solid #e5e7eb;
                background: #f8fafc;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
            }
            
            .info-item {
                display: flex;
                flex-direction: column;
            }
            
            .info-label {
                font-size: 12px;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .info-value {
                font-size: 16px;
                font-weight: 600;
                color: #1f2937;
                margin-top: 4px;
            }
            
            .attendance-summary {
                padding: 20px;
                background: #f0f9ff;
                border-bottom: 2px solid #e5e7eb;
            }
            
            .attendance-grid {
                display: flex;
                gap: 30px;
                justify-content: center;
            }
            
            .attendance-box {
                text-align: center;
            }
            
            .attendance-box .value {
                font-size: 24px;
                font-weight: 700;
                color: #1e40af;
            }
            
            .attendance-box .label {
                font-size: 12px;
                color: #6b7280;
                margin-top: 4px;
            }
            
            .salary-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            
            .salary-table th {
                background: #f3f4f6;
                padding: 12px;
                text-align: left;
                font-size: 14px;
                font-weight: 600;
                color: #374151;
                border-bottom: 2px solid #d1d5db;
            }
            
            .salary-table td {
                padding: 12px;
                border-bottom: 1px solid #e5e7eb;
                font-size: 14px;
            }
            
            .salary-table .amount {
                text-align: right;
                font-weight: 500;
            }
            
            .salary-table .total-row {
                background: #f8fafc;
                font-weight: 600;
            }
            
            .salary-table .grand-total {
                background: #e6f3ff;
                font-weight: 700;
                font-size: 16px;
            }
            
            .deductions-section {
                padding: 20px;
                border-top: 2px dashed #e5e7eb;
            }
            
            .deductions-title {
                font-size: 18px;
                font-weight: 600;
                color: #1e40af;
                margin-bottom: 15px;
            }
            
            .deductions-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            
            .deduction-item {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px dotted #e5e7eb;
            }
            
            .deduction-label {
                color: #4b5563;
            }
            
            .deduction-amount {
                font-weight: 500;
                color: #dc2626;
            }
            
            .net-pay {
                margin-top: 20px;
                padding: 15px;
                background: #dcfce7;
                border-radius: 8px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 20px;
                font-weight: 700;
            }
            
            .net-pay-label {
                color: #166534;
            }
            
            .net-pay-amount {
                color: #059669;
            }
            
            .payslip-footer {
                padding: 20px;
                border-top: 2px solid #e5e7eb;
                display: flex;
                justify-content: space-between;
                font-size: 12px;
                color: #6b7280;
            }
            
            .signature-area {
                display: flex;
                justify-content: space-between;
                margin-top: 30px;
                padding: 0 20px 20px;
            }
            
            .signature-box {
                text-align: center;
                width: 200px;
            }
            
            .signature-line {
                border-top: 1px solid #000;
                margin: 10px 0 5px;
                width: 100%;
            }
            
            .signature-label {
                font-size: 12px;
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
                padding: 12px 24px;
                border: none;
                border-radius: 50px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .print-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            }
            
            .print-btn.print {
                background: #1e40af;
                color: white;
            }
            
            .print-btn.back {
                background: #6b7280;
                color: white;
            }
            
            @media print {
                body {
                    background: white;
                    padding: 0;
                }
                
                .print-controls {
                    display: none;
                }
                
                .payslip {
                    box-shadow: none;
                    margin: 0;
                    page-break-after: always;
                }
                
                .payslip-header {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
            }
        </style>
    </head>
    <body>
        <div class="print-container">
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
                            <h3>PAYSLIP</h3>
                            <p class="period"><?php echo $current_cutoff['label']; ?> - <?php echo date('F Y', strtotime($period . '-01')); ?></p>
                        </div>
                    </div>
                    
                    <!-- Employee Information -->
                    <div class="employee-info">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Employee ID</span>
                                <span class="info-value"><?php echo htmlspecialchars($employee['employee_id']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Full Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($employee['full_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Position</span>
                                <span class="info-value"><?php echo htmlspecialchars($employee['position']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Department</span>
                                <span class="info-value"><?php echo htmlspecialchars($employee['department']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Payroll Period</span>
                                <span class="info-value"><?php echo formatDate($current_cutoff['start']); ?> - <?php echo formatDate($current_cutoff['end']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="info-value"><?php echo ucfirst($employee['payroll_status']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Summary -->
                    <div class="attendance-summary">
                        <div class="attendance-grid">
                            <div class="attendance-box">
                                <div class="value"><?php echo number_format($employee['days_present'], 1); ?></div>
                                <div class="label">Days Present</div>
                            </div>
                            <div class="attendance-box">
                                <div class="value"><?php echo $current_cutoff['working_days']; ?></div>
                                <div class="label">Working Days</div>
                            </div>
                            <div class="attendance-box">
                                <div class="value"><?php echo number_format(($employee['days_present'] / $current_cutoff['working_days']) * 100, 1); ?>%</div>
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
                                <td>Monthly Salary (Base)</td>
                                <td class="amount"><?php echo formatCurrency($employee['monthly_salary']); ?></td>
                            </tr>
                            <tr>
                                <td>Daily Rate (<?php echo $current_cutoff['working_days']; ?> days/month)</td>
                                <td class="amount"><?php echo formatCurrency($employee['monthly_salary'] / 22); ?></td>
                            </tr>
                            <tr>
                                <td>Days Present</td>
                                <td class="amount"><?php echo number_format($employee['days_present'], 1); ?> days</td>
                            </tr>
                            <tr>
                                <td>Prorated Salary (Base)</td>
                                <td class="amount"><?php echo formatCurrency(($employee['monthly_salary'] / 22) * $employee['days_present']); ?></td>
                            </tr>
                            <tr>
                                <td>Other Compensation</td>
                                <td class="amount"><?php echo formatCurrency($employee['other_comp']); ?></td>
                            </tr>
                            <tr class="total-row">
                                <td><strong>GROSS AMOUNT</strong></td>
                                <td class="amount"><strong><?php echo formatCurrency($employee['gross_amount']); ?></strong></td>
                            </tr>
                            
                            <!-- Deductions -->
                            <tr>
                                <td colspan="2" style="padding: 20px 0 10px;">
                                    <div class="deductions-title">DEDUCTIONS</div>
                                </td>
                            </tr>
                            <?php if ($employee['withholding_tax'] > 0): ?>
                            <tr>
                                <td>Withholding Tax</td>
                                <td class="amount" style="color: #dc2626;"><?php echo formatCurrency($employee['withholding_tax']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($employee['sss'] > 0): ?>
                            <tr>
                                <td>SSS Contribution</td>
                                <td class="amount" style="color: #dc2626;"><?php echo formatCurrency($employee['sss']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($employee['withholding_tax'] == 0 && $employee['sss'] == 0): ?>
                            <tr>
                                <td colspan="2" class="text-center" style="color: #6b7280; padding: 10px;">No deductions for this period</td>
                            </tr>
                            <?php endif; ?>
                            
                            <tr class="total-row">
                                <td><strong>TOTAL DEDUCTIONS</strong></td>
                                <td class="amount"><strong style="color: #dc2626;"><?php echo formatCurrency($employee['total_deductions']); ?></strong></td>
                            </tr>
                            
                            <!-- Net Pay -->
                            <tr class="grand-total">
                                <td><strong>NET PAY (Take Home Pay)</strong></td>
                                <td class="amount"><strong><?php echo formatCurrency($employee['net_amount']); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <!-- Net Pay Summary -->
                    <div class="net-pay">
                        <span class="net-pay-label">NET AMOUNT DUE:</span>
                        <span class="net-pay-amount"><?php echo formatCurrency($employee['net_amount']); ?></span>
                    </div>
                    
                    <!-- Amount in Words -->
                    <div style="padding: 0 20px 20px;">
                        <p style="font-size: 12px; color: #6b7280;">
                            <strong>Amount in Words:</strong> 
                            <?php 
                            // Simple number to words conversion (you can enhance this)
                            $amount_parts = explode('.', number_format($employee['net_amount'], 2));
                            echo strtoupper(convertNumberToWords($amount_parts[0])) . ' PESOS AND ' . $amount_parts[1] . '/100 ONLY';
                            ?>
                        </p>
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
                        <span>Generated on: <?php echo date('F d, Y h:i A'); ?></span>
                        <span>This is a computer-generated document. No signature required.</span>
                    </div>
                    
                    <?php if ($index < count($employees_data) - 1): ?>
                        <div style="page-break-before: always;"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Print Controls -->
        <div class="print-controls">
            <button class="print-btn back" onclick="window.location.href='contractualpayrolltable1.php?period=<?php echo $period; ?>&cutoff=<?php echo $cutoff; ?>'">
                <i class="fas fa-arrow-left"></i> Back
            </button>
            <button class="print-btn print" onclick="window.print()">
                <i class="fas fa-print"></i> Print All Payslips
            </button>
        </div>
        
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
        
        <script>
            // Auto-trigger print dialog when page loads (optional)
            // Uncomment the line below if you want the print dialog to appear automatically
            // window.onload = function() { window.print(); };
        </script>
    </body>
    </html>

    <?php
    // Helper function to convert numbers to words
    function convertNumberToWords($number) {
        $words = array(
            0 => 'ZERO', 1 => 'ONE', 2 => 'TWO', 3 => 'THREE', 4 => 'FOUR', 5 => 'FIVE',
            6 => 'SIX', 7 => 'SEVEN', 8 => 'EIGHT', 9 => 'NINE', 10 => 'TEN',
            11 => 'ELEVEN', 12 => 'TWELVE', 13 => 'THIRTEEN', 14 => 'FOURTEEN', 15 => 'FIFTEEN',
            16 => 'SIXTEEN', 17 => 'SEVENTEEN', 18 => 'EIGHTEEN', 19 => 'NINETEEN',
            20 => 'TWENTY', 30 => 'THIRTY', 40 => 'FORTY', 50 => 'FIFTY', 60 => 'SIXTY',
            70 => 'SEVENTY', 80 => 'EIGHTY', 90 => 'NINETY'
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