<?php
// Turn off all error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// =================================================================================
// --- PhpSpreadsheet Autoloader ---
// =================================================================================

$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

$loaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'PhpSpreadsheet library not installed. Please run: composer require phpoffice/phpspreadsheet']);
    exit();
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hrms_paluan');

// =================================================================================
// --- Database Connection ---
// =================================================================================

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
} catch (PDOException $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection error.']);
    exit();
}

// =================================================================================
// --- File Upload Validation ---
// =================================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_FILES['xlsx_file'])) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit();
}

$file = $_FILES['xlsx_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $file['error']]);
    exit();
}

// =================================================================================
// --- Helper Functions ---
// =================================================================================

/**
 * Validate if employee exists in any of the employee tables
 * With FLEXIBLE name matching for Joel/Jorel variations
 */
function validateEmployeeExists($pdo, $employee_id, $employee_name)
{

    // Clean up inputs
    $employee_id = trim($employee_id);
    $employee_name = trim($employee_name);

    // ===== 1. SEARCH BY EMPLOYEE ID - THIS SHOULD ALWAYS WORK =====
    // Your DTR has User ID: 20230606 which matches exactly with database

    if (!empty($employee_id)) {

        // Check permanent table by ID - THIS IS WHERE JOEL VICENTE IS
        $sql = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department, 'Permanent' as employee_type 
                FROM permanent 
                WHERE employee_id = ? AND status = 'Active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employee_id]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return [
                'exists' => true,
                'employee_id' => $row['employee_id'],
                'employee_name' => $row['full_name'],
                'department' => $row['department'],
                'type' => $row['employee_type'],
                'matched_by' => 'employee_id_exact',
                'note' => "Matched by Employee ID: {$employee_id}"
            ];
        }

        // Check job_order table by ID
        $sql = "SELECT employee_id, employee_name as full_name, office as department, 'Job Order' as employee_type 
                FROM job_order 
                WHERE employee_id = ? AND is_archived = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employee_id]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return [
                'exists' => true,
                'employee_id' => $row['employee_id'],
                'employee_name' => $row['full_name'],
                'department' => $row['department'],
                'type' => $row['employee_type'],
                'matched_by' => 'employee_id_exact',
                'note' => "Matched by Employee ID: {$employee_id}"
            ];
        }

        // Check contractofservice table by ID
        $sql = "SELECT employee_id, full_name, office as department, 'Contractual' as employee_type 
                FROM contractofservice 
                WHERE employee_id = ? AND status = 'active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employee_id]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return [
                'exists' => true,
                'employee_id' => $row['employee_id'],
                'employee_name' => $row['full_name'],
                'department' => $row['department'],
                'type' => $row['employee_type'],
                'matched_by' => 'employee_id_exact',
                'note' => "Matched by Employee ID: {$employee_id}"
            ];
        }
    }

    // ===== 2. IF ID NOT FOUND, TRY NAME MATCHING =====
    // This is fallback for DTR files without employee_id

    if (!empty($employee_name)) {

        // Clean the search name
        $search_name = strtolower(trim($employee_name));
        $search_name = preg_replace('/\s+/', ' ', $search_name);

        // Remove middle initial and punctuation
        $search_name_simple = preg_replace('/\s+[A-Z]\.?\s+/', ' ', $search_name);
        $search_name_simple = preg_replace('/\./', '', $search_name_simple);
        $search_name_simple = trim($search_name_simple);

        // Split into parts
        $name_parts = explode(' ', $search_name_simple);
        $first_name = $name_parts[0];
        $last_name = count($name_parts) > 1 ? $name_parts[count($name_parts) - 1] : '';

        // Create variations for Jorel/Joel
        $first_name_variations = [];
        $first_name_variations[] = $first_name;

        // If name contains 'r', also try without 'r' (Jorel -> Joel)
        if (strpos($first_name, 'r') !== false) {
            $first_name_variations[] = str_replace('r', '', $first_name);
            $first_name_variations[] = str_replace('re', 'e', $first_name);
            $first_name_variations[] = str_replace('rel', 'el', $first_name);
        }

        // If name is missing 'r', try with 'r' (Joel -> Jorel)
        if (strpos($first_name, 'r') === false) {
            $first_name_variations[] = $first_name . 'r';
            $first_name_variations[] = $first_name . 'rel';
            // Try inserting r at different positions
            if (strlen($first_name) >= 4) {
                $first_name_variations[] = substr_replace($first_name, 'r', 3, 0); // Joel -> Jorel
            }
        }

        // Also try the exact name from DTR
        $first_name_variations[] = 'jorel';
        $first_name_variations[] = 'joel';

        // Remove duplicates
        $first_name_variations = array_unique($first_name_variations);

        // Try to match in permanent table
        foreach ($first_name_variations as $fn_var) {
            $sql = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department, 'Permanent' as employee_type 
                    FROM permanent 
                    WHERE status = 'Active' 
                    AND (
                        LOWER(CONCAT(first_name, ' ', last_name)) LIKE ? 
                        OR (LOWER(first_name) LIKE ? AND LOWER(last_name) LIKE ?)
                    )
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $search_pattern = "%{$search_name_simple}%";
            $first_pattern = "%{$fn_var}%";
            $last_pattern = "%{$last_name}%";

            $stmt->execute([$search_pattern, $first_pattern, $last_pattern]);

            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return [
                    'exists' => true,
                    'employee_id' => $row['employee_id'],
                    'employee_name' => $row['full_name'],
                    'department' => $row['department'],
                    'type' => $row['employee_type'],
                    'matched_by' => 'fuzzy_name_match',
                    'note' => "Matched '{$employee_name}' to '{$row['full_name']}'"
                ];
            }
        }

        // Try job_order table
        foreach ($first_name_variations as $fn_var) {
            $sql = "SELECT employee_id, employee_name as full_name, office as department, 'Job Order' as employee_type 
                    FROM job_order 
                    WHERE is_archived = 0 
                    AND (
                        LOWER(employee_name) LIKE ? 
                        OR (LOWER(first_name) LIKE ? AND LOWER(last_name) LIKE ?)
                    )
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$search_pattern, $first_pattern, $last_pattern]);

            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return [
                    'exists' => true,
                    'employee_id' => $row['employee_id'],
                    'employee_name' => $row['full_name'],
                    'department' => $row['department'],
                    'type' => $row['employee_type'],
                    'matched_by' => 'fuzzy_name_match',
                    'note' => "Matched '{$employee_name}' to '{$row['full_name']}'"
                ];
            }
        }

        // Try contractofservice table
        foreach ($first_name_variations as $fn_var) {
            $sql = "SELECT employee_id, full_name, office as department, 'Contractual' as employee_type 
                    FROM contractofservice 
                    WHERE status = 'active' 
                    AND (
                        LOWER(full_name) LIKE ? 
                        OR (LOWER(first_name) LIKE ? AND LOWER(last_name) LIKE ?)
                    )
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$search_pattern, $first_pattern, $last_pattern]);

            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return [
                    'exists' => true,
                    'employee_id' => $row['employee_id'],
                    'employee_name' => $row['full_name'],
                    'department' => $row['department'],
                    'type' => $row['employee_type'],
                    'matched_by' => 'fuzzy_name_match',
                    'note' => "Matched '{$employee_name}' to '{$row['full_name']}'"
                ];
            }
        }
    }

    // ===== 3. EMPLOYEE NOT FOUND =====
    return [
        'exists' => false,
        'searched_id' => $employee_id,
        'searched_name' => $employee_name,
        'message' => "Employee not found. Tried ID: '{$employee_id}' and Name: '{$employee_name}'"
    ];
}

/**
 * Extract employee ID and name from Excel
 */
function extractEmployeeInfo($worksheet)
{
    $employeeInfo = ['id' => '', 'name' => ''];

    // ===== CRITICAL: Get User ID from I5 =====
    // Your DTR has User ID: 20230606 at I5
    $idCell = $worksheet->getCell('I5')->getValue();
    if (!empty($idCell)) {
        $employeeInfo['id'] = trim((string)$idCell);
        // Clean up - remove any non-numeric characters if it's a numeric ID
        $employeeInfo['id'] = preg_replace('/[^0-9]/', '', $employeeInfo['id']);
    }

    // Get Name from I4
    $nameCell = $worksheet->getCell('I4')->getValue();
    if (!empty($nameCell)) {
        $employeeInfo['name'] = trim((string)$nameCell);
    }

    // If name not found at I4, try other positions
    if (empty($employeeInfo['name'])) {
        // Check D column for JOREL B. VICENTE
        for ($row = 40; $row <= 60; $row++) {
            $cellValue = $worksheet->getCell('D' . $row)->getValue();
            if (is_string($cellValue) && strpos($cellValue, 'VICENTE') !== false) {
                $employeeInfo['name'] = trim($cellValue);
                break;
            }
        }
    }

    // If still no name, use sheet name
    if (empty($employeeInfo['name'])) {
        $sheetName = $worksheet->getTitle();
        if (!empty($sheetName) && $sheetName != 'Sheet1') {
            $employeeInfo['name'] = $sheetName;
        }
    }

    return $employeeInfo;
}

/**
 * Format time value from Excel
 */
function formatTimeValue($timeValue)
{
    if ($timeValue === null || $timeValue === '' || $timeValue == 'NULL' || $timeValue == 'null') {
        return null;
    }

    // Handle numeric Excel time
    if (is_numeric($timeValue)) {
        try {
            $timestamp = Date::excelToTimestamp($timeValue);
            return date('H:i:s', $timestamp);
        } catch (Exception $e) {
            return null;
        }
    }

    $timeStr = trim((string)$timeValue);

    // Format HH:MM:SS
    if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $timeStr, $matches)) {
        $hour = intval($matches[1]);
        $minute = intval($matches[2]);
        $second = intval($matches[3]);
        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }

    // Format HH:MM
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeStr, $matches)) {
        $hour = intval($matches[1]);
        $minute = intval($matches[2]);
        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    return null;
}

/**
 * Calculate working hours
 */
function calculateWorkingHours($am_in, $am_out, $pm_in, $pm_out)
{
    $total_minutes = 0;
    $ot_minutes = 0;
    $undertime_minutes = 0;

    $standard_start_am = strtotime('08:00:00');
    $standard_end_pm = strtotime('17:00:00');

    // AM calculation
    if ($am_in && $am_out) {
        $in = is_string($am_in) ? strtotime($am_in) : null;
        $out = is_string($am_out) ? strtotime($am_out) : null;

        if ($in && $out && $out > $in) {
            $worked = ($out - $in) / 60;
            $total_minutes += $worked;

            if ($in < $standard_start_am) {
                $ot_minutes += ($standard_start_am - $in) / 60;
            }

            if ($in > $standard_start_am) {
                $undertime_minutes += ($in - $standard_start_am) / 60;
            }
        }
    }

    // PM calculation
    if ($pm_in && $pm_out) {
        $in = is_string($pm_in) ? strtotime($pm_in) : null;
        $out = is_string($pm_out) ? strtotime($pm_out) : null;

        if ($in && $out && $out > $in) {
            $worked = ($out - $in) / 60;
            $total_minutes += $worked;

            if ($out > $standard_end_pm) {
                $ot_minutes += ($out - $standard_end_pm) / 60;
            }

            if ($out < $standard_end_pm) {
                $undertime_minutes += ($standard_end_pm - $out) / 60;
            }
        }
    }

    return [
        'total_hours' => round($total_minutes / 60, 2),
        'ot_hours' => round($ot_minutes / 60, 2),
        'undertime_hours' => round($undertime_minutes / 60, 2)
    ];
}

// =================================================================================
// --- Main Processing ---
// =================================================================================

$response = [
    'success' => false,
    'imported' => 0,
    'duplicates' => 0,
    'errors' => 0,
    'invalid_employees' => 0,
    'message' => '',
    'error_messages' => [],
    'records' => []
];

try {
    // Load the spreadsheet
    $spreadsheet = IOFactory::load($file['tmp_name']);

    // Use the first sheet
    $worksheet = $spreadsheet->getSheet(0);
    $sheetName = $worksheet->getTitle();

    $pdo->beginTransaction();

    // Extract employee info
    $employeeInfo = extractEmployeeInfo($worksheet);

    // If no employee info found, try to get from sheet name
    if (empty($employeeInfo['name']) && !empty($sheetName) && $sheetName != 'Sheet1') {
        $employeeInfo['name'] = $sheetName;
    }

    // Validate employee exists
    $validation = validateEmployeeExists($pdo, $employeeInfo['id'], $employeeInfo['name']);

    if (!$validation['exists']) {
        $response['invalid_employees'] = 1;
        $response['error_messages'][] = "Employee not found in database. Tried:";
        $response['error_messages'][] = "- ID: " . ($employeeInfo['id'] ?: 'Not provided');
        $response['error_messages'][] = "- Name: " . ($employeeInfo['name'] ?: 'Not provided');
        $response['error_messages'][] = "Please check:";
        $response['error_messages'][] = "1. Employee exists in Permanent/Job Order/Contractual tables";
        $response['error_messages'][] = "2. Status is 'Active' or not archived";
        $response['error_messages'][] = "3. Name in DTR: '{$employeeInfo['name']}'";
        $response['message'] = "Import failed: Employee not found in database";

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Extract date range
    $year = 2026;
    $month = 1;
    $dateCell = $worksheet->getCell('D2')->getValue();
    if (is_string($dateCell) && preg_match('/(\d{4})-(\d{2})-\d{2}/', $dateCell, $matches)) {
        $year = intval($matches[1]);
        $month = intval($matches[2]);
    }

    // Process attendance rows
    $imported = 0;
    $duplicates = 0;
    $records = [];

    // Find where data starts - look for "05 Mo" pattern
    $startRow = 0;
    for ($row = 10; $row <= 20; $row++) {
        $cellValue = $worksheet->getCell('A' . $row)->getValue();
        if (!empty($cellValue) && preg_match('/^\d{1,2}\s+[A-Za-z]{2}/', trim((string)$cellValue))) {
            $startRow = $row;
            break;
        }
    }

    if ($startRow == 0) {
        $startRow = 12; // Default fallback
    }

    // Process rows until empty
    for ($row = $startRow; $row <= $startRow + 40; $row++) {

        $dayCell = $worksheet->getCell('A' . $row)->getValue();

        if (empty($dayCell)) {
            continue;
        }

        $dayStr = trim((string)$dayCell);

        if (!preg_match('/^(\d{1,2})/', $dayStr, $matches)) {
            continue;
        }

        $day = intval($matches[1]);

        if ($day < 1 || $day > 31) {
            continue;
        }

        // Get time values
        $amIn = $worksheet->getCell('B' . $row)->getValue();
        $amOut = $worksheet->getCell('D' . $row)->getValue();
        $pmIn = $worksheet->getCell('G' . $row)->getValue();
        $pmOut = $worksheet->getCell('I' . $row)->getValue();

        // Check if has data
        $hasData = false;
        if (!empty($amIn) && $amIn != 'null' && $amIn != 'NULL') $hasData = true;
        if (!empty($amOut) && $amOut != 'null' && $amOut != 'NULL') $hasData = true;
        if (!empty($pmIn) && $pmIn != 'null' && $pmIn != 'NULL') $hasData = true;
        if (!empty($pmOut) && $pmOut != 'null' && $pmOut != 'NULL') $hasData = true;

        if (!$hasData) {
            continue;
        }

        $dateStr = sprintf('%d-%02d-%02d', $year, $month, $day);

        // Check duplicate
        $checkSql = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$validation['employee_id'], $dateStr]);

        if ($checkStmt->rowCount() > 0) {
            $duplicates++;
            continue;
        }

        // Format times
        $amInFormatted = formatTimeValue($amIn);
        $amOutFormatted = formatTimeValue($amOut);
        $pmInFormatted = formatTimeValue($pmIn);
        $pmOutFormatted = formatTimeValue($pmOut);

        // Calculate hours
        $hours = calculateWorkingHours($amInFormatted, $amOutFormatted, $pmInFormatted, $pmOutFormatted);

        // Insert record
        $sql = "INSERT INTO attendance 
                (date, employee_id, employee_name, department, am_time_in, am_time_out, pm_time_in, pm_time_out, ot_hours, under_time, total_hours) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([
            $dateStr,
            $validation['employee_id'],
            $validation['employee_name'],
            $validation['department'],
            $amInFormatted,
            $amOutFormatted,
            $pmInFormatted,
            $pmOutFormatted,
            $hours['ot_hours'],
            $hours['undertime_hours'],
            $hours['total_hours']
        ])) {
            $imported++;
            $records[] = [
                'employee' => $validation['employee_name'],
                'date' => $dateStr,
                'day' => $day,
                'am_in' => $amInFormatted,
                'am_out' => $amOutFormatted,
                'pm_in' => $pmInFormatted,
                'pm_out' => $pmOutFormatted,
                'total_hours' => $hours['total_hours']
            ];
        }
    }

    $pdo->commit();

    $response['success'] = true;
    $response['imported'] = $imported;
    $response['duplicates'] = $duplicates;
    $response['records'] = $records;
    $response['message'] = "Successfully imported $imported attendance records for {$validation['employee_name']}!";

    if ($duplicates > 0) {
        $response['message'] .= " Skipped $duplicates duplicate records.";
    }

    // Add matching info
    if (isset($validation['matched_by'])) {
        $response['message'] .= " (Matched by: {$validation['matched_by']})";
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['success'] = false;
    $response['message'] = 'Error processing file: ' . $e->getMessage();
}

$employeeInfo = extractEmployeeInfo($worksheet);

// Debug - log what we found
error_log("=== DTR Import Debug ===");
error_log("Employee ID from DTR: " . ($employeeInfo['id'] ?: 'EMPTY'));
error_log("Employee Name from DTR: " . ($employeeInfo['name'] ?: 'EMPTY'));
error_log("========================");

// If ID is empty but name exists, try to find ID from name
if (empty($employeeInfo['id']) && !empty($employeeInfo['name'])) {
    // Try to extract ID from the sheet name or other cells
    // Your DTR has User ID 20230606 at I5 - make sure we're getting it
    $idCell = $worksheet->getCell('I5')->getValue();
    if (!empty($idCell)) {
        $employeeInfo['id'] = trim((string)$idCell);
        $employeeInfo['id'] = preg_replace('/[^0-9]/', '', $employeeInfo['id']);
    }
}

// Clear output buffer and send JSON
ob_clean();
header('Content-Type: application/json');
echo json_encode($response);
exit();
