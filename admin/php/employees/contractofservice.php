<?php
// Debug mode
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Debug: Check if form is being submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("Form submitted with action: " . ($_POST['action'] ?? 'unknown'));
    error_log("POST data count: " . count($_POST));
    error_log("FILES data count: " . count($_FILES));
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Logout functionality
if (isset($_GET['logout'])) {
    // Destroy all session data
    session_destroy();

    // Clear remember me cookie
    setcookie('remember_user', '', time() - 3600, "/");

    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Set user variables from session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Administrator';
$user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'admin@hrmo.gov.ph';

/**
 * PHP Script: contractofservice.php
 * Handles CRUD operations for contractual employees
 */

// ===============================================
// 1. CONFIGURATION AND PDO CONNECTION SETUP
// ===============================================

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hrms_paluan');

// Define upload directory
$upload_dir = 'uploads/contractual_documents/';

// Initialize variables
$success = null;
$error = null;
$employees = [];
$error_message = '';
$employee_data = null;
$is_edit = false;
$edit_id = null;

// Check if we're viewing inactive employees
$view_inactive = isset($_GET['view']) && $_GET['view'] === 'inactive';

// Pagination variables
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 10;
$total_records = 0;
$total_pages = 0;

// Initialize PDO connection
try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8mb4");
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("ERROR: Could not connect to the database. Check server logs for details.");
}

// ===============================================
// 2. HELPER FUNCTIONS
// ===============================================

/**
 * Upload file with validation
 */
function uploadFile($file_input_name, $destination_dir, $file_prefix, $existing_file = null)
{
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] != UPLOAD_ERR_OK) {
        // If there's an upload error, return existing file or null
        if ($_FILES[$file_input_name]['error'] == UPLOAD_ERR_NO_FILE) {
            // No file was uploaded
            return $existing_file;
        }
        // Log actual error
        error_log("File upload error code: " . $_FILES[$file_input_name]['error'] . " for " . $file_input_name);
        return $existing_file;
    }

    $file = $_FILES[$file_input_name];

    // Check file size (max 10MB)
    $max_file_size = 10 * 1024 * 1024; // 10MB in bytes
    if ($file['size'] > $max_file_size) {
        error_log("File upload failed: File too large for " . $file_input_name);
        return $existing_file;
    }

    // Validate file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_types = ['jpeg', 'jpg', 'pdf', 'png'];

    if (!in_array($file_extension, $allowed_types)) {
        error_log("File upload failed: Invalid file type for " . $file_input_name);
        return $existing_file;
    }

    // Generate a unique file name
    $new_file_name = $file_prefix . '_' . time() . '_' . uniqid() . '.' . $file_extension;
    $destination = $destination_dir . $new_file_name;

    // Ensure directory exists
    if (!is_dir($destination_dir)) {
        if (!mkdir($destination_dir, 0777, true)) {
            error_log("Failed to create directory: " . $destination_dir);
            return $existing_file;
        }
    }

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Delete old file if exists
        if ($existing_file && file_exists($existing_file)) {
            @unlink($existing_file);
        }
        return $destination;
    }

    error_log("File upload failed: Could not move uploaded file for " . $file_input_name);
    return $existing_file;
}

/**
 * Delete file if exists
 */
function deleteFile($file_path)
{
    if ($file_path && file_exists($file_path)) {
        unlink($file_path);
        return true;
    }
    return false;
}

/**
 * Generate unique employee ID
 */
function generateEmployeeID($pdo, $office_code = 'COS')
{
    $year = date('Y');

    // Get the last employee ID for this year
    $stmt = $pdo->prepare("SELECT employee_id FROM contractofservice WHERE employee_id LIKE ? ORDER BY employee_id DESC LIMIT 1");
    $stmt->execute(["$office_code-$year-%"]);
    $last_id = $stmt->fetchColumn();

    if ($last_id) {
        // Extract sequence number and increment
        $parts = explode('-', $last_id);
        $sequence = intval($parts[2]) + 1;
    } else {
        $sequence = 1;
    }

    // Format sequence to 4 digits
    $sequence_formatted = str_pad($sequence, 4, '0', STR_PAD_LEFT);

    return "$office_code-$year-$sequence_formatted";
}

/**
 * Get office code from office name
 */
function getOfficeCode($office_name)
{
    $office_codes = [
        'Office of the Municipal Mayor' => 'OMM',
        'Human Resource Management Division' => 'HRMD',
        'Business Permit and Licensing Division' => 'BPLD',
        'Sangguniang Bayan Office' => 'SBO',
        'Office of the Municipal Accountant' => 'OMA',
        'Office of the Assessor' => 'OA',
        'Municipal Budget Office' => 'MBO',
        'Municipal Planning and Development Office' => 'MPDO',
        'Municipal Engineering Office' => 'MEO',
        'Municipal Disaster Risk Reduction and Management Office' => 'MDRRMO',
        'Municipal Social Welfare and Development Office' => 'MSWDO',
        'Municipal Environment and Natural Resources Office' => 'MENRO',
        'Office of the Municipal Agriculturist' => 'OMA',
        'Municipal General Services Office' => 'MGSO',
        'Municipal Public Employment Service Office' => 'MPESO',
        'Municipal Health Office' => 'MHO',
        'Municipal Treasurer\'s Office' => 'MTO'
    ];

    return $office_codes[$office_name] ?? 'COS';
}

// Ensure upload directory exists
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        die("ERROR: Failed to create upload directory. Check permissions.");
    }
}

// ===============================================
// 3. HANDLE VIEW REQUEST (AJAX) - MUST BE BEFORE ANY HTML OUTPUT
// ===============================================
if (isset($_GET['view_id']) && !isset($_GET['edit_id'])) {
    // This block handles AJAX requests for viewing employee data
    $view_id = intval($_GET['view_id']);

    try {
        $stmt = $pdo->prepare("SELECT * FROM contractofservice WHERE id = ?");
        $stmt->execute([$view_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($employee) {
            // Helper function to format dates
            function formatDateForView($dateStr)
            {
                if (!$dateStr || $dateStr == '0000-00-00' || $dateStr == '0000-00-00 00:00:00') {
                    return 'N/A';
                }
                try {
                    $date = new DateTime($dateStr);
                    return $date->format('F d, Y');
                } catch (Exception $e) {
                    return 'N/A';
                }
            }

            // Helper function to format currency
            function formatCurrency($amount)
            {
                if (!$amount || $amount == '0' || $amount == '0.00') {
                    return '₱0.00';
                }
                $num = floatval($amount);
                return '₱' . number_format($num, 2);
            }

            // Build the HTML response for the modal
            $html = '';

            // Employee ID Section
            $html .= '<div class="section-header">';
            $html .= '<h2>Employee Information</h2>';
            $html .= '</div>';
            $html .= '<div class="info-grid">';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">Employee ID:</span>';
            $html .= '<span class="info-value">' . htmlspecialchars($employee['employee_id'] ?: 'N/A') . '</span>';
            $html .= '</div>';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">Email:</span>';
            $html .= '<span class="info-value">' . htmlspecialchars($employee['email_address'] ?: 'N/A') . '</span>';
            $html .= '</div>';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">Mobile:</span>';
            $html .= '<span class="info-value">' . htmlspecialchars($employee['mobile_number'] ?: 'N/A') . '</span>';
            $html .= '</div>';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">Address:</span>';
            $html .= '<span class="info-value">' . htmlspecialchars(
                ($employee['street_address'] ? $employee['street_address'] . ', ' : '') .
                ($employee['city'] ? $employee['city'] . ', ' : '') .
                ($employee['state_region'] ? $employee['state_region'] . ' ' : '') .
                ($employee['zip_code'] ?: '')
            ) . '</span>';
            $html .= '</div>';
            $html .= '</div>';

            // Section Divider
            $html .= '<div class="section-divider"></div>';

            // Personal Details Section
            $html .= '<div class="section-header">';
            $html .= '<h2>Personal Details</h2>';
            $html .= '</div>';
            $html .= '<div class="info-grid">';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">First Name:</span>';
            $html .= '<span class="info-value">' . htmlspecialchars($employee['first_name'] ?: 'N/A') . '</span>';
            $html .= '</div>';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">Last Name:</span>';
            $html .= '<span class="info-value">' . htmlspecialchars($employee['last_name'] ?: 'N/A') . '</span>';
            $html .= '</div>';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">Date of Birth:</span>';
            $html .= '<span class="info-value">' . formatDateForView($employee['date_of_birth']) . '</span>';
            $html .= '</div>';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">Gender:</span>';
            $html .= '<span class="info-value">' . htmlspecialchars($employee['gender'] ?: 'N/A') . '</span>';
            $html .= '</div>';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">Marital Status:</span>';
            $html .= '<span class="info-value">' . htmlspecialchars($employee['marital_status'] ?: 'N/A') . '</span>';
            $html .= '</div>';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">Nationality:</span>';
            $html .= '<span class="info-value">' . htmlspecialchars($employee['nationality'] ?: 'N/A') . '</span>';
            $html .= '</div>';
            $html .= '</div>';

            // Section Divider
            $html .= '<div class="section-divider"></div>';

            // Documents Section
            $html .= '<div class="section-header">';
            $html .= '<h2>Documents</h2>';
            $html .= '<div class="document-badge">' .
                ($employee['doc_id_path'] || $employee['doc_resume_path'] || $employee['doc_service_path'] ||
                    $employee['doc_appointment_path'] || $employee['doc_transcript_path'] || $employee['doc_eligibility_path'] ?
                    'Some uploaded' : 'None uploaded') .
                '</div>';
            $html .= '</div>';
            $html .= '<div class="document-grid">';

            $documents = [
                ['icon' => 'fa-id-card', 'name' => 'Government ID', 'field' => 'doc_id_path'],
                ['icon' => 'fa-file-alt', 'name' => 'Resume / CV', 'field' => 'doc_resume_path'],
                ['icon' => 'fa-history', 'name' => 'Service Record', 'field' => 'doc_service_path'],
                ['icon' => 'fa-file-contract', 'name' => 'Appointment Paper', 'field' => 'doc_appointment_path'],
                ['icon' => 'fa-graduation-cap', 'name' => 'Transcript', 'field' => 'doc_transcript_path'],
                ['icon' => 'fa-award', 'name' => 'Eligibility', 'field' => 'doc_eligibility_path']
            ];

            foreach ($documents as $doc) {
                $hasDocument = !empty($employee[$doc['field']]);
                $html .= '<div class="document-item">';
                $html .= '<i class="document-icon fas ' . $doc['icon'] . '"></i>';
                $html .= '<div class="document-name">' . $doc['name'] . '</div>';
                $html .= '<div class="document-status ' . ($hasDocument ? 'uploaded' : 'missing') . '">';
                $html .= $hasDocument ? 'Uploaded' : 'Not uploaded';
                $html .= '</div>';
                $html .= '</div>';
            }

            $html .= '</div>';

            // Section Divider
            $html .= '<div class="section-divider"></div>';

            // Employment Details Section
            $html .= '<div class="section-header">';
            $html .= '<h2>Employment Details</h2>';
            $html .= '</div>';
            $html .= '<div class="info-grid">';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">Designation:</span>';
            $html .= '<span class="info-value">' . htmlspecialchars($employee['designation'] ?: 'N/A') . '</span>';
            $html .= '</div>';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">Office Assignment:</span>';
            $html .= '<span class="info-value">' . htmlspecialchars($employee['office_assignment'] ?: 'N/A') . '</span>';
            $html .= '</div>';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">Joining Date:</span>';
            $html .= '<span class="info-value">' . formatDateForView($employee['joining_date']) . '</span>';
            $html .= '</div>';
            $html .= '<div class="info-item">';
            $html .= '<span class="info-label">Contract Period:</span>';
            $html .= '<span class="info-value">' . formatDateForView($employee['period_from']) . ' - ' . formatDateForView($employee['period_to']) . '</span>';
            $html .= '</div>';
            $html .= '</div>';

            // Section Divider
            $html .= '<div class="section-divider"></div>';

            // Salary Information Section
            $html .= '<div class="section-header">';
            $html .= '<h2>Salary Information</h2>';
            $html .= '</div>';
            $html .= '<div class="salary-display">';
            $html .= '<div class="salary-label">Monthly Salary</div>';
            $html .= '<div class="salary-figure">' . formatCurrency($employee['wages']) . '</div>';
            $html .= '<div class="salary-details">';
            $html .= '<div class="detail-item">';
            $html .= '<div class="detail-label">Contribution</div>';
            $html .= '<div class="detail-value">' . htmlspecialchars($employee['contribution'] ?: 'N/A') . '</div>';
            $html .= '</div>';
            $html .= '<div class="detail-item">';
            $html .= '<div class="detail-label">Eligibility Status</div>';
            $html .= '<div class="detail-value">';
            $html .= '<span class="' . ($employee['eligibility'] === 'Eligible' ? 'eligibility-badge eligible' : 'eligibility-badge not-eligible') . '">';
            $html .= htmlspecialchars($employee['eligibility'] ?: 'Not Eligible');
            $html .= '</span>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';

            // Return JSON response with the HTML
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'html' => $html,
                'employee' => [
                    'id' => $employee['id'],
                    'employee_id' => $employee['employee_id'],
                    'full_name' => $employee['full_name'],
                    'designation' => $employee['designation'],
                    'office_assignment' => $employee['office_assignment'],
                    'joining_date' => formatDateForView($employee['joining_date']),
                    'profile_image_path' => $employee['profile_image_path']
                ]
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Employee not found']);
        }
        exit();
    } catch (PDOException $e) {
        error_log("View error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
}

// ===============================================
// 4. HANDLE INACTIVATE REQUEST (CHANGED FROM DELETE)
// ===============================================
if (isset($_GET['inactivate_id'])) {
    $inactivate_id = intval($_GET['inactivate_id']);

    try {
        // Update employee status to inactive
        $stmt = $pdo->prepare("UPDATE contractofservice SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$inactivate_id]);

        $success = "Employee marked as inactive successfully!";

        // Redirect to remove inactivate_id from URL
        header("Location: contractofservice.php");
        exit();
    } catch (PDOException $e) {
        error_log("Inactivate error: " . $e->getMessage());
        $error = "Error marking employee as inactive. Please try again.";
    }
}

// ===============================================
// 5. HANDLE ACTIVATE REQUEST
// ===============================================
if (isset($_GET['activate_id'])) {
    $activate_id = intval($_GET['activate_id']);

    try {
        // Update employee status to active
        $stmt = $pdo->prepare("UPDATE contractofservice SET status = 'active' WHERE id = ?");
        $stmt->execute([$activate_id]);

        $success = "Employee activated successfully!";

        // Redirect to remove activate_id from URL
        header("Location: contractofservice.php");
        exit();
    } catch (PDOException $e) {
        error_log("Activate error: " . $e->getMessage());
        $error = "Error activating employee. Please try again.";
    }
}

// ===============================================
// 6. HANDLE EDIT DATA FETCH
// ===============================================
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $is_edit = true;

    try {
        $stmt = $pdo->prepare("SELECT * FROM contractofservice WHERE id = ?");
        $stmt->execute([$edit_id]);
        $employee_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee_data) {
            $error = "Employee not found!";
            $is_edit = false;
        }
    } catch (PDOException $e) {
        error_log("Edit fetch error: " . $e->getMessage());
        $error = "Error loading employee data.";
        $is_edit = false;
    }
}

// ===============================================
// 7. HANDLE FORM SUBMISSION (ADD/EDIT) - CORRECTED
// ===============================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("=== FORM SUBMISSION STARTED ===");

    // Get action type
    $action = $_POST['action'] ?? 'add';
    $is_edit = ($action === 'edit');
    $edit_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : null;

    error_log("Action: $action, Edit ID: $edit_id");

    // Data sanitization and validation
    $employee_id_input = filter_input(INPUT_POST, 'employee_id_input', FILTER_SANITIZE_SPECIAL_CHARS);
    $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $designation = filter_input(INPUT_POST, 'designation', FILTER_SANITIZE_SPECIAL_CHARS);
    $office = filter_input(INPUT_POST, 'office', FILTER_SANITIZE_SPECIAL_CHARS);
    $period_from = filter_input(INPUT_POST, 'period_from');
    $period_to = filter_input(INPUT_POST, 'period_to');
    $wages = filter_input(INPUT_POST, 'wages', FILTER_VALIDATE_FLOAT);
    $contribution = filter_input(INPUT_POST, 'contribution', FILTER_SANITIZE_SPECIAL_CHARS);

    // Personal Details
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $mobile_number = filter_input(INPUT_POST, 'mobile_number', FILTER_SANITIZE_SPECIAL_CHARS);
    $email_address = filter_input(INPUT_POST, 'email_address', FILTER_VALIDATE_EMAIL);
    $date_of_birth = filter_input(INPUT_POST, 'date_of_birth');
    $marital_status = filter_input(INPUT_POST, 'marital_status', FILTER_SANITIZE_SPECIAL_CHARS);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_SPECIAL_CHARS);
    $nationality = filter_input(INPUT_POST, 'nationality', FILTER_SANITIZE_SPECIAL_CHARS);
    $street_address = filter_input(INPUT_POST, 'street_address', FILTER_SANITIZE_SPECIAL_CHARS);
    $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_SPECIAL_CHARS);
    $state_region = filter_input(INPUT_POST, 'state_region', FILTER_SANITIZE_SPECIAL_CHARS);
    $zip_code = filter_input(INPUT_POST, 'zip_code', FILTER_SANITIZE_SPECIAL_CHARS);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $joining_date = filter_input(INPUT_POST, 'joining_date');
    $eligibility = filter_input(INPUT_POST, 'eligibility', FILTER_SANITIZE_SPECIAL_CHARS);

    error_log("Data received - Full Name: $full_name, Email: $email_address, Wages: $wages");

    // Validation
    $validation_errors = [];

    if (!$full_name)
        $validation_errors[] = "Full Name is required";
    if (!$email_address)
        $validation_errors[] = "Valid Email Address is required";
    if ($wages === false || $wages < 0)
        $validation_errors[] = "Valid Wages amount is required";
    if (!$is_edit && empty($password))
        $validation_errors[] = "Password is required";
    if (!$is_edit && $password !== $confirm_password)
        $validation_errors[] = "Password and Confirm Password do not match";

    // Validate mobile number (must be 11 digits)
    if ($mobile_number && !preg_match('/^[0-9]{11}$/', $mobile_number)) {
        $validation_errors[] = "Mobile number must be exactly 11 digits";
    }

    // Validate ZIP code (Philippine format: 4 digits)
    if ($zip_code && !preg_match('/^[0-9]{4}$/', $zip_code)) {
        $validation_errors[] = "ZIP code must be exactly 4 digits";
    }

    // Validate date of birth (must be past date)
    if ($date_of_birth && strtotime($date_of_birth) >= strtotime('today')) {
        $validation_errors[] = "Date of birth must be in the past";
    }

    // Validate contract period
    if ($period_from && $period_to && strtotime($period_from) > strtotime($period_to)) {
        $validation_errors[] = "Period From cannot be after Period To";
    }

    if (!empty($validation_errors)) {
        $error = "Error: " . implode(", ", $validation_errors);
        error_log("Validation errors: " . implode(", ", $validation_errors));
    } else {
        try {
            // Check if email already exists (for new records or when email is changed)
            if (!$is_edit) {
                $stmt = $pdo->prepare("SELECT id FROM contractofservice WHERE email_address = ?");
                $stmt->execute([$email_address]);
                if ($stmt->fetch()) {
                    throw new Exception("The email address '{$email_address}' is already registered.");
                }
            } else {
                $stmt = $pdo->prepare("SELECT id FROM contractofservice WHERE email_address = ? AND id != ?");
                $stmt->execute([$email_address, $edit_id]);
                if ($stmt->fetch()) {
                    throw new Exception("The email address '{$email_address}' is already registered by another employee.");
                }
            }

            // Prepare file paths (get existing files for edit mode)
            $existing_files = $is_edit && isset($employee_data) ? $employee_data : [];

            // File uploads - only if new file is uploaded
            error_log("Starting file uploads...");
            $profile_image_path = uploadFile(
                'profile_image',
                $upload_dir,
                'profile',
                $is_edit ? ($existing_files['profile_image_path'] ?? null) : null
            );

            $doc_id_path = uploadFile(
                'doc_id',
                $upload_dir,
                'govt_id',
                $is_edit ? ($existing_files['doc_id_path'] ?? null) : null
            );

            $doc_resume_path = uploadFile(
                'doc_resume',
                $upload_dir,
                'resume',
                $is_edit ? ($existing_files['doc_resume_path'] ?? null) : null
            );

            $doc_service_path = uploadFile(
                'doc_service',
                $upload_dir,
                'service_rec',
                $is_edit ? ($existing_files['doc_service_path'] ?? null) : null
            );

            $doc_appointment_path = uploadFile(
                'doc_appointment',
                $upload_dir,
                'appt_paper',
                $is_edit ? ($existing_files['doc_appointment_path'] ?? null) : null
            );

            $doc_transcript_path = uploadFile(
                'doc_transcript',
                $upload_dir,
                'transcript',
                $is_edit ? ($existing_files['doc_transcript_path'] ?? null) : null
            );

            $doc_eligibility_path = uploadFile(
                'doc_eligibility',
                $upload_dir,
                'elig_cert',
                $is_edit ? ($existing_files['doc_eligibility_path'] ?? null) : null
            );

            error_log("File uploads completed");

            // Handle password
            $password_hash = null;
            if ($is_edit && !empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
            } elseif (!$is_edit) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
            }

            // Generate employee ID for new records
            $employee_id = $employee_id_input;
            if (!$is_edit) {
                $office_code = getOfficeCode($office);
                $employee_id = generateEmployeeID($pdo, $office_code);
            }

            error_log("Generated Employee ID: $employee_id");

            if ($is_edit && $edit_id) {
                // UPDATE existing record
                $sql = "UPDATE contractofservice SET
                    employee_id = :employee_id,
                    full_name = :full_name,
                    designation = :designation,
                    office_assignment = :office_assignment,
                    period_from = :period_from,
                    period_to = :period_to,
                    wages = :wages,
                    contribution = :contribution,
                    profile_image_path = :profile_image_path,
                    first_name = :first_name,
                    last_name = :last_name,
                    mobile_number = :mobile_number,
                    email_address = :email_address,
                    date_of_birth = :date_of_birth,
                    marital_status = :marital_status,
                    gender = :gender,
                    nationality = :nationality,
                    street_address = :street_address,
                    city = :city,
                    state_region = :state_region,
                    zip_code = :zip_code,
                    joining_date = :joining_date,
                    eligibility = :eligibility,
                    doc_id_path = :doc_id_path,
                    doc_resume_path = :doc_resume_path,
                    doc_service_path = :doc_service_path,
                    doc_appointment_path = :doc_appointment_path,
                    doc_transcript_path = :doc_transcript_path,
                    doc_eligibility_path = :doc_eligibility_path";

                // Add password update if provided
                if ($password_hash) {
                    $sql .= ", password_hash = :password_hash";
                }

                $sql .= ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";

                error_log("UPDATE SQL: $sql");
                $stmt = $pdo->prepare($sql);

                $params = [
                    ':employee_id' => $employee_id,
                    ':full_name' => $full_name,
                    ':designation' => $designation,
                    ':office_assignment' => $office,
                    ':period_from' => $period_from,
                    ':period_to' => $period_to,
                    ':wages' => $wages,
                    ':contribution' => $contribution,
                    ':profile_image_path' => $profile_image_path,
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':mobile_number' => $mobile_number,
                    ':email_address' => $email_address,
                    ':date_of_birth' => $date_of_birth,
                    ':marital_status' => $marital_status,
                    ':gender' => $gender,
                    ':nationality' => $nationality,
                    ':street_address' => $street_address,
                    ':city' => $city,
                    ':state_region' => $state_region,
                    ':zip_code' => $zip_code,
                    ':joining_date' => $joining_date,
                    ':eligibility' => $eligibility,
                    ':doc_id_path' => $doc_id_path,
                    ':doc_resume_path' => $doc_resume_path,
                    ':doc_service_path' => $doc_service_path,
                    ':doc_appointment_path' => $doc_appointment_path,
                    ':doc_transcript_path' => $doc_transcript_path,
                    ':doc_eligibility_path' => $doc_eligibility_path,
                    ':id' => $edit_id
                ];

                // Add password parameter if needed
                if ($password_hash) {
                    $params[':password_hash'] = $password_hash;
                }

                error_log("Executing UPDATE with params: " . print_r($params, true));
                $stmt->execute($params);
                $affected_rows = $stmt->rowCount();
                error_log("UPDATE affected rows: $affected_rows");

            } else {
                // INSERT new record
                $sql = "INSERT INTO contractofservice (
                    employee_id, full_name, designation, office_assignment, period_from, period_to, wages, contribution, 
                    profile_image_path, first_name, last_name, mobile_number, email_address, date_of_birth, 
                    marital_status, gender, nationality, street_address, 
                    city, state_region, zip_code, password_hash, joining_date, eligibility, 
                    doc_id_path, doc_resume_path, doc_service_path, doc_appointment_path, doc_transcript_path, doc_eligibility_path,
                    status, created_at, updated_at
                ) VALUES (
                    :employee_id, :full_name, :designation, :office_assignment, :period_from, :period_to, :wages, :contribution, 
                    :profile_image_path, :first_name, :last_name, :mobile_number, :email_address, :date_of_birth, 
                    :marital_status, :gender, :nationality, :street_address, 
                    :city, :state_region, :zip_code, :password_hash, :joining_date, :eligibility, 
                    :doc_id_path, :doc_resume_path, :doc_service_path, :doc_appointment_path, :doc_transcript_path, :doc_eligibility_path,
                    'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )";

                error_log("INSERT SQL: $sql");
                $stmt = $pdo->prepare($sql);

                $params = [
                    ':employee_id' => $employee_id,
                    ':full_name' => $full_name,
                    ':designation' => $designation,
                    ':office_assignment' => $office,
                    ':period_from' => $period_from,
                    ':period_to' => $period_to,
                    ':wages' => $wages,
                    ':contribution' => $contribution,
                    ':profile_image_path' => $profile_image_path,
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':mobile_number' => $mobile_number,
                    ':email_address' => $email_address,
                    ':date_of_birth' => $date_of_birth,
                    ':marital_status' => $marital_status,
                    ':gender' => $gender,
                    ':nationality' => $nationality,
                    ':street_address' => $street_address,
                    ':city' => $city,
                    ':state_region' => $state_region,
                    ':zip_code' => $zip_code,
                    ':password_hash' => $password_hash,
                    ':joining_date' => $joining_date,
                    ':eligibility' => $eligibility,
                    ':doc_id_path' => $doc_id_path,
                    ':doc_resume_path' => $doc_resume_path,
                    ':doc_service_path' => $doc_service_path,
                    ':doc_appointment_path' => $doc_appointment_path,
                    ':doc_transcript_path' => $doc_transcript_path,
                    ':doc_eligibility_path' => $doc_eligibility_path
                ];

                error_log("Executing INSERT with params: " . print_r($params, true));
                $stmt->execute($params);
                $last_id = $pdo->lastInsertId();
                error_log("INSERT successful, last ID: $last_id");
            }

            if ($is_edit) {
                $success = "Employee record for <strong>" . htmlspecialchars($full_name) . "</strong> updated successfully!";

                // Redirect to prevent form resubmission
                header("Location: contractofservice.php");
                exit();
            } else {
                $success = "New contractual employee record for <strong>" . htmlspecialchars($full_name) . "</strong> created successfully! (ID: $employee_id)";

                // Clear the form by redirecting
                header("Location: contractofservice.php");
                exit();
            }

        } catch (Exception $e) {
            error_log("Exception during form submission: " . $e->getMessage());
            $error = "Error: " . $e->getMessage();
        }
    }
}

// ===============================================
// 8. DATA FETCHING LOGIC WITH PAGINATION
// ===============================================
try {
    // Get total number of active employees
    $status_condition = $view_inactive ? "status = 'inactive'" : "status = 'active'";
    $count_sql = "SELECT COUNT(*) FROM contractofservice WHERE $status_condition";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute();
    $total_records = $stmt->fetchColumn();

    // Calculate total pages
    $total_pages = ceil($total_records / $records_per_page);

    // Ensure current page is within valid range
    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
        header("Location: contractofservice.php?page=$current_page" . ($view_inactive ? "&view=inactive" : ""));
        exit();
    }

    // Calculate offset
    $offset = ($current_page - 1) * $records_per_page;

    // Fetch employees with pagination
    $sql = "SELECT 
                id, employee_id, full_name, designation, office_assignment, 
                period_from, period_to, wages, contribution,
                email_address, mobile_number, status
            FROM 
                contractofservice 
            WHERE $status_condition
            ORDER BY full_name ASC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Query execution error in fetch block: " . $e->getMessage());
    $error_message = "Could not retrieve employee data.";
}

// Office options array
$office_options = [
    'Select Department',
    'Office of the Municipal Mayor',
    'Human Resource Management Division',
    'Business Permit and Licensing Division',
    'Sangguniang Bayan Office',
    'Office of the Municipal Accountant',
    'Office of the Assessor',
    'Municipal Budget Office',
    'Municipal Planning and Development Office',
    'Municipal Engineering Office',
    'Municipal Disaster Risk Reduction and Management Office',
    'Municipal Social Welfare and Development Office',
    'Municipal Environment and Natural Resources Office',
    'Office of the Municipal Agriculturist',
    'Municipal General Services Office',
    'Municipal Public Employment Service Office',
    'Municipal Health Office',
    'Municipal Treasurer\'s Office'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contractual Employees - HR Management System</title>
    <!-- Flowbite CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.2.1/dist/flowbite.min.css" rel="stylesheet" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/output.css">
    <link rel="stylesheet" href="../css/dasboard.css">

    <style>
        /* Your existing CSS styles remain the same */
        :root {
            --primary: #1e40af;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --secondary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gradient-primary: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            --gradient-secondary: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            --gradient-danger: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --shadow-blue: 0 10px 25px -3px rgba(30, 64, 175, 0.2);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            color: var(--dark);
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f8fafc 100%);
            overflow-x: hidden;
        }

        /* Navigation */
        .navbar {
            background: var(--gradient-primary);
            box-shadow: var(--shadow-md);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 70px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .navbar-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 1rem;
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
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            transition: transform 0.3s ease;
        }

        .mobile-toggle:hover {
            transform: scale(1.1);
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

        /* Sidebar */
        .sidebar-container {
            position: fixed;
            left: 0;
            top: 70px;
            width: 260px;
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
            z-index: 999;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }

        .sidebar {
            height: 100%;
            padding: 1.5rem 0;
        }

        .sidebar-content {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
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

        .sidebar-footer {
            margin-top: auto;
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Modal Styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1100;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
        }

        .modal-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1101;
            display: none;
            padding: 1rem;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            width: 100%;
        }

        @media (min-width: 768px) {
            .modal-content {
                max-width: 800px;
            }
        }

        @media (min-width: 1024px) {
            .modal-content {
                max-width: 1000px;
            }
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.25rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .action-btn i {
            font-size: 14px;
        }

        .action-btn.view {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .action-btn.view:hover {
            background-color: #2563eb;
            border-color: #2563eb;
        }

        .action-btn.edit {
            background-color: #10b981;
            color: white;
            border-color: #10b981;
        }

        .action-btn.edit:hover {
            background-color: #059669;
            border-color: #059669;
        }

        .action-btn.inactive {
            background-color: #f59e0b;
            color: white;
            border-color: #f59e0b;
        }

        .action-btn.inactive:hover {
            background-color: #d97706;
            border-color: #d97706;
        }

        .action-btn.active {
            background-color: #10b981;
            color: white;
            border-color: #10b981;
        }

        .action-btn.active:hover {
            background-color: #059669;
            border-color: #059669;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background-color: #fef3c7;
            color: #92400e;
        }

        /* Form Steps */
        .form-step,
        .edit-form-step {
            display: none;
        }

        .form-step.active,
        .edit-form-step.active {
            display: block;
        }

        /* Error Messages */
        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Input Error State */
        .input-error {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        /* Mobile Responsive Styles */
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

            .navbar-right .datetime-container {
                display: none;
            }

            .brand-text {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .datetime-container {
                display: none;
            }

            .main-content {
                padding: 1rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }

            .action-btn {
                width: 28px;
                height: 28px;
            }

            .modal-content {
                margin: 0.5rem;
                max-height: 85vh;
            }
        }

        @media (max-width: 640px) {
            .navbar-container {
                padding: 0 0.75rem;
            }

            .main-content {
                padding: 0.75rem;
            }

            .modal-content {
                margin: 0.25rem;
                max-height: 80vh;
            }
        }

        /* Overlay for mobile menu */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
            backdrop-filter: blur(4px);
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

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
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

        /* Pagination Styles */
        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            background-color: white;
            color: #374151;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 0.375rem;
        }

        .pagination-btn:hover:not(:disabled) {
            background-color: #f3f4f6;
            border-color: #9ca3af;
        }

        .pagination-btn.active {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.toggle-slider {
            background-color: #3b82f6;
        }

        input:checked+.toggle-slider:before {
            transform: translateX(30px);
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Success/Error Messages -->
    <?php if (isset($success)): ?>
        <div id="successMessage"
            class="fixed top-4 right-4 z-50 p-4 text-sm text-green-800 rounded-lg bg-green-50 shadow-lg" role="alert">
            <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
        </div>
        <script>
            setTimeout(() => {
                const element = document.getElementById('successMessage');
                if (element) element.remove();
            }, 5000);
        </script>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div id="errorMessage" class="fixed top-4 right-4 z-50 p-4 text-sm text-red-800 rounded-lg bg-red-50 shadow-lg"
            role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
        </div>
        <script>
            setTimeout(() => {
                const element = document.getElementById('errorMessage');
                if (element) element.remove();
            }, 5000);
        </script>
    <?php endif; ?>

    <!-- Navigation Header -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Left Section -->
            <div class="navbar-left">
                <!-- Mobile Menu Toggle -->
                <button class="mobile-toggle" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- Logo and Brand -->
                <a href="dashboard.php" class="navbar-brand">
                    <img class="brand-logo"
                        src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                        alt="Logo" />
                    <div class="brand-text">
                        <span class="brand-title">HR Management System</span>
                        <span class="brand-subtitle">Paluan Occidental Mindoro</span>
                    </div>
                </a>
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

    <!-- Sidebar -->
    <div class="sidebar-container" id="sidebar-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <!-- Dashboard -->
                <a href="../dashboard.php" class="sidebar-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard Analytics</span>
                </a>

                <!-- Employees -->
                <a href="./employees/Employee.php" class="sidebar-item active">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>

                <!-- Attendance -->
                <a href="../attendance.php" class="sidebar-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>

                <!-- Payroll -->
                <a href="#" class="sidebar-item" id="payroll-toggle">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll</span>
                    <i class="fas fa-chevron-down chevron ml-auto"></i>
                </a>
                <div class="dropdown-menu" id="payroll-dropdown"
                    style="display: none; padding-left: 1rem; margin-left: 2.5rem; border-left: 2px solid rgba(255, 255, 255, 0.1);">
                    <a href="../Payrollmanagement/contractualpayrolltable1.php" class="dropdown-item"
                        style="padding: 0.7rem 1rem; font-size: 0.9rem; color: rgba(255, 255, 255, 0.8);">
                        <i class="fas fa-circle text-xs"></i>
                        Contractual
                    </a>
                    <a href="../Payrollmanagement/joboerderpayrolltable1.php" class="dropdown-item"
                        style="padding: 0.7rem 1rem; font-size: 0.9rem; color: rgba(255, 255, 255, 0.8);">
                        <i class="fas fa-circle text-xs"></i>
                        Job Order
                    </a>
                    <a href="../Payrollmanagement/permanentpayrolltable1.php" class="dropdown-item"
                        style="padding: 0.7rem 1rem; font-size: 0.9rem; color: rgba(255, 255, 255, 0.8);">
                        <i class="fas fa-circle text-xs"></i>
                        Permanent
                    </a>
                </div>

                <!-- Reports -->
                <a href="../paysliphistory.php" class="sidebar-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>

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
                <div class="text-center text-white/60 text-sm ">
                    <p>HRMS v2.0</p>
                    <p class="text-xs mt-1">© 2024 Paluan LGU</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="main-container">
            <div class="bg-white rounded-xl shadow-lg p-4 md:p-6">
                <!-- Breadcrumb -->
                <nav class="flex mb-4 overflow-x-auto">
                    <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse whitespace-nowrap">
                        <li class="inline-flex items-center">
                            <a href="Employee.php"
                                class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                <i class="fas fa-home mr-2"></i>All Employee
                            </a>
                        </li>
                        <li>
                            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                            <a href="permanent.php" class="ms-1 text-sm font-medium  md:ms-2">Permanent</a>
                        </li>
                        <li>
                            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                            <a href="contractofservice.php"
                                class="ms-1 text-sm font-medium hover:text-blue-600 text-blue-600 md:ms-2">Contractual</a>
                        </li>
                        <li>
                            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                            <a href="Job_order.php"
                                class="ms-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ms-2">Job Order</a>
                        </li>
                    </ol>
                </nav>

                <!-- Page Header -->
                <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 md:mb-8 gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                            <?php echo $view_inactive ? 'Inactive Contractual Employees' : 'Contract of Service Employees'; ?>
                        </h1>
                        <p class="text-gray-600 text-sm md:text-base">
                            <?php echo $view_inactive ? 'Manage all inactive contractual employees' : 'Manage all contractual employees in the system'; ?>
                        </p>
                    </div>

                    <!-- Quick Stats -->
                    <div class="flex items-center gap-3">
                        <div class="bg-blue-50 px-4 py-2 rounded-lg border border-blue-100">
                            <span class="text-blue-700 font-semibold text-lg md:text-xl">
                                <?php echo number_format($total_records); ?>
                            </span>
                            <span class="text-blue-600 text-sm ml-2">
                                <?php echo $view_inactive ? 'Inactive Employees' : 'Active Employees'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="bg-gray-50 rounded-lg p-4 md:p-5 mb-6 border border-gray-200">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="w-full lg:w-auto lg:flex-1">
                            <div class="relative max-w-md">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400 text-sm"></i>
                                </div>
                                <input type="text" id="search-employee"
                                    class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm md:text-base"
                                    placeholder="Search by name, ID, or designation...">
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3">
                            <?php if ($view_inactive): ?>
                                <a href="contractofservice.php"
                                    class="inline-flex items-center justify-center px-4 md:px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-all duration-200 hover:shadow-lg focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    <span>Back to Active</span>
                                </a>
                            <?php else: ?>
                                <button type="button" onclick="openAddModal()"
                                    class="inline-flex items-center justify-center px-4 md:px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-all duration-200 hover:shadow-lg focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    <i class="fas fa-plus mr-2"></i>
                                    <span>Add New Contractual</span>
                                </button>

                                <a href="contractofservice.php?view=inactive"
                                    class="inline-flex items-center justify-center px-4 md:px-5 py-2.5 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition-all duration-200 hover:shadow-lg focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                    <i class="fas fa-archive mr-2"></i>
                                    <span>View Inactive</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-blue-600">
                                <tr>
                                    <th scope="col"
                                        class="px-4 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                        No.
                                    </th>
                                    <th scope="col"
                                        class="px-4 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                        Employee ID
                                    </th>
                                    <th scope="col"
                                        class="px-4 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                        Name
                                    </th>
                                    <th scope="col"
                                        class="px-4 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                        Designation
                                    </th>
                                    <th scope="col"
                                        class="px-4 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                        Office
                                    </th>
                                    <th scope="col"
                                        class="px-4 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                        Contract Period
                                    </th>
                                    <th scope="col"
                                        class="px-4 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col"
                                        class="px-4 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                        Wages
                                    </th>
                                    <th scope="col"
                                        class="px-4 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (isset($error_message) && !empty($error_message)): ?>
                                    <tr>
                                        <td colspan="9" class="px-6 py-8 text-center">
                                            <div class="flex flex-col items-center justify-center py-4">
                                                <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-3"></i>
                                                <p class="text-red-600 font-medium">
                                                    <?php echo htmlspecialchars($error_message); ?></p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php elseif (empty($employees)): ?>
                                    <tr>
                                        <td colspan="9" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="fas fa-users text-gray-300 text-4xl mb-4"></i>
                                                <p class="text-gray-500 font-medium text-lg mb-2">
                                                    <?php echo $view_inactive ? 'No inactive contractual employees found' : 'No contractual employees found'; ?>
                                                </p>
                                                <p class="text-gray-400 text-sm mb-4">
                                                    <?php echo $view_inactive ? 'All employees are currently active' : 'Add your first contractual employee to get started'; ?>
                                                </p>
                                                <?php if (!$view_inactive): ?>
                                                    <button onclick="openAddModal()"
                                                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                                        <i class="fas fa-plus mr-2"></i>
                                                        Add New Employee
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    $start_number = ($current_page - 1) * $records_per_page + 1;
                                    foreach ($employees as $index => $employee):
                                        $today = date('Y-m-d');
                                        // Check contract status
                                        $contract_status = ($employee['period_to'] >= $today) ? 'active' : 'expired';
                                        // Get overall status
                                        $status = $employee['status']; // This will be 'active' or 'inactive'
                                        $period_from = date('M d, Y', strtotime($employee['period_from']));
                                        $period_to = date('M d, Y', strtotime($employee['period_to']));
                                        ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                                <?php echo $start_number + $index; ?>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <?php echo htmlspecialchars($employee['employee_id'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div
                                                            class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                            <i class="fas fa-user text-gray-500"></i>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($employee['full_name']); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            <?php echo htmlspecialchars($employee['email_address'] ?? 'No email'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($employee['designation']); ?>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                <?php echo htmlspecialchars($employee['office_assignment']); ?>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                <div class="flex flex-col">
                                                    <span class="font-medium"><?php echo $period_from; ?></span>
                                                    <span class="text-xs text-gray-400">to</span>
                                                    <span class="font-medium"><?php echo $period_to; ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php
                                                    if ($status === 'active') {
                                                        echo $contract_status === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                                    } else {
                                                        echo 'bg-red-100 text-red-800';
                                                    }
                                                    ?>">
                                                    <?php
                                                    if ($status === 'active') {
                                                        echo ucfirst($contract_status);
                                                    } else {
                                                        echo 'Inactive';
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm font-bold text-green-600">
                                                ₱<?php echo number_format($employee['wages'], 2); ?>
                                            </td>
                                            <td class="px-4 md:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex items-center space-x-2">
                                                    <button onclick="viewEmployee(<?php echo $employee['id']; ?>)"
                                                        class="inline-flex items-center p-2 text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
                                                        title="View Details">
                                                        <i class="fas fa-eye text-sm"></i>
                                                    </button>
                                                    <button onclick="editEmployee(<?php echo $employee['id']; ?>)"
                                                        class="inline-flex items-center p-2 text-green-600 hover:text-green-800 bg-green-50 hover:bg-green-100 rounded-lg transition-colors"
                                                        title="Edit">
                                                        <i class="fas fa-edit text-sm"></i>
                                                    </button>
                                                    <?php if ($view_inactive): ?>
                                                        <button
                                                            onclick="confirmActivate(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars(addslashes($employee['full_name'])); ?>')"
                                                            class="inline-flex items-center p-2 text-green-600 hover:text-green-800 bg-green-50 hover:bg-green-100 rounded-lg transition-colors"
                                                            title="Activate Employee">
                                                            <i class="fas fa-user-check text-sm"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button
                                                            onclick="confirmInactivate(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars(addslashes($employee['full_name'])); ?>')"
                                                            class="inline-flex items-center p-2 text-yellow-600 hover:text-yellow-800 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors"
                                                            title="Mark as Inactive">
                                                            <i class="fas fa-user-slash text-sm"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 0): ?>
                        <div class="bg-gray-50 px-4 md:px-6 py-4 border-t border-gray-200">
                            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                                <!-- Showing information -->
                                <div class="text-sm text-gray-700">
                                    Showing
                                    <span
                                        class="font-semibold"><?php echo min($total_records, ($current_page - 1) * $records_per_page + 1); ?></span>
                                    to
                                    <span
                                        class="font-semibold"><?php echo min($total_records, $current_page * $records_per_page); ?></span>
                                    of
                                    <span class="font-semibold"><?php echo number_format($total_records); ?></span>
                                    results
                                </div>

                                <!-- Pagination controls -->
                                <div class="flex items-center space-x-1">
                                    <!-- First Page -->
                                    <button onclick="goToPage(1)"
                                        class="px-3 py-2 rounded-md text-sm font-medium <?php echo $current_page == 1 ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'; ?>"
                                        <?php echo $current_page == 1 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>

                                    <!-- Previous Page -->
                                    <button onclick="goToPage(<?php echo max(1, $current_page - 1); ?>)"
                                        class="px-3 py-2 rounded-md text-sm font-medium <?php echo $current_page == 1 ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'; ?>"
                                        <?php echo $current_page == 1 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-chevron-left"></i>
                                    </button>

                                    <!-- Page Numbers -->
                                    <div class="flex items-center space-x-1">
                                        <?php
                                        // Calculate page range to show
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);

                                        // Show first page if not in range
                                        if ($start_page > 1) {
                                            echo '<button onclick="goToPage(1)" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100">1</button>';
                                            if ($start_page > 2) {
                                                echo '<span class="px-2 text-gray-400">...</span>';
                                            }
                                        }

                                        // Show page numbers
                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                            if ($i == $current_page) {
                                                echo '<button class="px-3 py-2 rounded-md text-sm font-medium bg-blue-600 text-white">' . $i . '</button>';
                                            } else {
                                                $view_param = $view_inactive ? "&view=inactive" : "";
                                                echo '<button onclick="goToPage(' . $i . ')" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100">' . $i . '</button>';
                                            }
                                        }

                                        // Show last page if not in range
                                        if ($end_page < $total_pages) {
                                            if ($end_page < $total_pages - 1) {
                                                echo '<span class="px-2 text-gray-400">...</span>';
                                            }
                                            echo '<button onclick="goToPage(' . $total_pages . ')" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100">' . $total_pages . '</button>';
                                        }
                                        ?>
                                    </div>

                                    <!-- Next Page -->
                                    <button onclick="goToPage(<?php echo min($total_pages, $current_page + 1); ?>)"
                                        class="px-3 py-2 rounded-md text-sm font-medium <?php echo $current_page == $total_pages ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'; ?>"
                                        <?php echo $current_page == $total_pages ? 'disabled' : ''; ?>>
                                        <i class="fas fa-chevron-right"></i>
                                    </button>

                                    <!-- Last Page -->
                                    <button onclick="goToPage(<?php echo $total_pages; ?>)"
                                        class="px-3 py-2 rounded-md text-sm font-medium <?php echo $current_page == $total_pages ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'; ?>"
                                        <?php echo $current_page == $total_pages ? 'disabled' : ''; ?>>
                                        <i class="fas fa-angle-double-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Backdrop -->
    <div id="modalBackdrop" class="modal-backdrop hidden"></div>

    <!-- Add/Edit Employee Modal -->
    <div id="employeeModal" class="modal-container hidden">
        <div
            class="bg-white rounded-lg shadow-xl w-full max-w-2xl md:max-w-4xl lg:max-w-6xl max-h-[90vh] overflow-hidden mx-2">
            <!-- Modal header -->
            <div class="flex items-center justify-between p-3 md:p-5 border-b sticky top-0 bg-white z-10">
                <h3 class="text-lg md:text-xl font-semibold text-gray-900" id="modalTitle">Add New Contractual Employee
                </h3>
                <button type="button"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center"
                    onclick="closeModal()">
                    <i class="fas fa-times"></i>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>

            <!-- Modal body -->
            <div class="p-3 md:p-5 overflow-y-auto max-h-[calc(90vh-120px)]">
                <!-- Step Navigation -->
                <div class="flex mb-4 border-b sticky top-0 bg-white z-10 pt-2">
                    <button type="button"
                        class="step-nav flex-1 py-2 px-2 md:px-4 text-center font-medium border-b-2 border-blue-600 text-blue-600 text-sm md:text-base"
                        data-step="1">Professional</button>
                    <button type="button"
                        class="step-nav flex-1 py-2 px-2 md:px-4 text-center font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 text-sm md:text-base"
                        data-step="2">Personal</button>
                    <button type="button"
                        class="step-nav flex-1 py-2 px-2 md:px-4 text-center font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 text-sm md:text-base"
                        data-step="3">Documents</button>
                </div>

                <!-- Multi-Step Form -->
                <form id="employeeForm" action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="employee_id" id="employeeId" value="">

                    <!-- Employee ID Field (read-only for edit, auto-generated for new) -->
                    <div class="mb-4 md:mb-6">
                        <label for="employee_id_input" class="block mb-2 text-sm font-medium text-gray-900">Employee
                            ID</label>
                        <input type="text" name="employee_id_input" id="employee_id_input"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 font-mono"
                            placeholder="Auto-generated" readonly>
                        <p class="mt-1 text-xs text-gray-500">Employee ID will be auto-generated based on office and
                            year</p>
                    </div>

                    <!-- Step 1: Professional Information -->
                    <div id="step1" class="form-step active">
                        <h2 class="text-base md:text-lg font-semibold text-gray-800 mb-3 md:mb-4">Professional Details
                        </h2>

                        <div class="grid gap-3 md:gap-4 mb-4">
                            <div>
                                <label for="full_name" class="block mb-2 text-sm font-medium text-gray-900">Full Name
                                    *</label>
                                <input type="text" name="full_name" id="full_name"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    placeholder="Juan Dela Cruz Jr." pattern="^[A-Za-z\s.'-]+(?: [A-Za-z\s.'-]+)*$"
                                    title="Please enter a valid full name (letters, spaces, apostrophes, dots, and hyphens only)"
                                    required>
                                <div class="validation-error">Please enter a valid full name (e.g., Juan Dela Cruz Jr.)
                                </div>
                            </div>

                            <div class="form-grid-responsive">
                                <div>
                                    <label for="designation"
                                        class="block mb-2 text-sm font-medium text-gray-900">Designation *</label>
                                    <input type="text" name="designation" id="designation"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="Job title" required>
                                </div>
                                <div>
                                    <label for="office" class="block mb-2 text-sm font-medium text-gray-900">Office
                                        Assignment *</label>
                                    <select name="office" id="office"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                        <?php foreach ($office_options as $option): ?>
                                            <option value="<?php echo htmlspecialchars($option); ?>">
                                                <?php echo htmlspecialchars($option); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-grid-responsive">
                                <div>
                                    <label for="period_from" class="block mb-2 text-sm font-medium text-gray-900">Period
                                        From *</label>
                                    <input type="date" name="period_from" id="period_from"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        max="2099-12-31" required>
                                </div>
                                <div>
                                    <label for="period_to" class="block mb-2 text-sm font-medium text-gray-900">Period
                                        To *</label>
                                    <input type="date" name="period_to" id="period_to"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        max="2099-12-31" required>
                                </div>
                            </div>

                            <div class="form-grid-responsive">
                                <div>
                                    <label for="wages" class="block mb-2 text-sm font-medium text-gray-900">Wages (per
                                        period) *</label>
                                    <input type="number" name="wages" id="wages" step="0.01" min="0"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="0.00" pattern="^\d+(\.\d{1,2})?$"
                                        title="Enter a valid amount (e.g., 25000.50)" required>
                                    <div class="validation-error">Enter a valid amount (e.g., 25000.50)</div>
                                </div>
                                <div>
                                    <label for="contribution"
                                        class="block mb-2 text-sm font-medium text-gray-900">Contribution</label>
                                    <input type="text" name="contribution" id="contribution"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="SSS, PhilHealth, etc.">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="button"
                                class="next-step text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2.5 md:px-5"
                                data-next="2">
                                Next <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Personal Information -->
                    <div id="step2" class="form-step hidden">
                        <h2 class="text-base md:text-lg font-semibold text-gray-800 mb-3 md:mb-4">Personal Details</h2>

                        <div class="grid gap-3 md:gap-4 mb-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-1">
                                    <div class="flex flex-col items-center">
                                        <div id="profileImageContainer"
                                            class="w-24 h-24 md:w-32 md:h-32 bg-gray-200 rounded-full flex items-center justify-center mb-3 md:mb-4 overflow-hidden">
                                            <i class="fas fa-user text-gray-400 text-3xl md:text-4xl"></i>
                                        </div>
                                        <input type="file" name="profile_image" id="profile_image" accept="image/*"
                                            class="hidden">
                                        <label for="profile_image"
                                            class="cursor-pointer text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            <i class="fas fa-upload mr-1"></i>Upload Photo
                                        </label>
                                        <input type="hidden" id="current_profile_image" name="current_profile_image"
                                            value="">
                                    </div>
                                </div>
                                <div class="md:col-span-2 form-grid-responsive">
                                    <div>
                                        <label for="first_name"
                                            class="block mb-2 text-sm font-medium text-gray-900">First Name *</label>
                                        <input type="text" name="first_name" id="first_name"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                            pattern="^[A-Za-z\s.'-]+$" title="Please enter a valid first name" required>
                                        <div class="validation-error">Please enter a valid first name</div>
                                    </div>
                                    <div>
                                        <label for="last_name" class="block mb-2 text-sm font-medium text-gray-900">Last
                                            Name *</label>
                                        <input type="text" name="last_name" id="last_name"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                            pattern="^[A-Za-z\s.'-]+$" title="Please enter a valid last name" required>
                                        <div class="validation-error">Please enter a valid last name</div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-grid-responsive">
                                <div>
                                    <label for="mobile_number"
                                        class="block mb-2 text-sm font-medium text-gray-900">Mobile Number *</label>
                                    <input type="text" name="mobile_number" id="mobile_number"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="09123456789" pattern="^[0-9]{11}$"
                                        title="Mobile number must be exactly 11 digits" required>
                                    <div class="validation-error">Mobile number must be exactly 11 digits</div>
                                </div>
                                <div>
                                    <label for="email_address"
                                        class="block mb-2 text-sm font-medium text-gray-900">Email Address *</label>
                                    <input type="email" name="email_address" id="email_address"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="juan.delacruz@email.com"
                                        pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                                        title="Please enter a valid email address" required>
                                    <div class="validation-error">Please enter a valid email address</div>
                                </div>
                                <div class="md:col-span-2 lg:col-span-1">
                                    <label for="date_of_birth" class="block mb-2 text-sm font-medium text-gray-900">Date
                                        of Birth *</label>
                                    <input type="date" name="date_of_birth" id="date_of_birth"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        max="<?php echo date('Y-m-d'); ?>" title="Date of birth must be in the past"
                                        required>
                                    <div class="validation-error">Date of birth must be in the past</div>
                                </div>
                            </div>

                            <div class="form-grid-responsive">
                                <div>
                                    <label for="marital_status"
                                        class="block mb-2 text-sm font-medium text-gray-900">Marital Status *</label>
                                    <select name="marital_status" id="marital_status"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                        <option value="">Select Status</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="gender" class="block mb-2 text-sm font-medium text-gray-900">Gender
                                        *</label>
                                    <select name="gender" id="gender"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="nationality"
                                        class="block mb-2 text-sm font-medium text-gray-900">Nationality *</label>
                                    <input type="text" name="nationality" id="nationality"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        value="Filipino" pattern="^[A-Za-z\s]+$"
                                        title="Please enter a valid nationality" required>
                                    <div class="validation-error">Please enter a valid nationality</div>
                                </div>
                            </div>

                            <div>
                                <label for="street_address" class="block mb-2 text-sm font-medium text-gray-900">Street
                                    Address *</label>
                                <input type="text" name="street_address" id="street_address"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    placeholder="123 Main Street, Barangay Poblacion" required>
                            </div>

                            <div class="form-grid-responsive">
                                <div>
                                    <label for="city" class="block mb-2 text-sm font-medium text-gray-900">City
                                        *</label>
                                    <input type="text" name="city" id="city"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="Paluan" pattern="^[A-Za-z\s.]+$"
                                        title="Please enter a valid city name" required>
                                    <div class="validation-error">Please enter a valid city name</div>
                                </div>
                                <div>
                                    <label for="state_region"
                                        class="block mb-2 text-sm font-medium text-gray-900">State/Region *</label>
                                    <input type="text" name="state_region" id="state_region"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        value="Occidental Mindoro" pattern="^[A-Za-z\s]+$"
                                        title="Please enter a valid state/region" required>
                                    <div class="validation-error">Please enter a valid state/region</div>
                                </div>
                                <div>
                                    <label for="zip_code" class="block mb-2 text-sm font-medium text-gray-900">ZIP Code
                                        *</label>
                                    <input type="text" name="zip_code" id="zip_code"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="5104" pattern="^[0-9]{4}$"
                                        title="ZIP code must be exactly 4 digits" required>
                                    <div class="validation-error">ZIP code must be exactly 4 digits</div>
                                </div>
                            </div>

                            <div class="form-grid-responsive">
                                <div>
                                    <label for="password" class="block mb-2 text-sm font-medium text-gray-900">Password
                                        <span class="text-xs text-gray-500">(Leave blank to keep current)</span></label>
                                    <input type="password" name="password" id="password"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$"
                                        title="Password must be at least 8 characters with uppercase, lowercase, number and special character">
                                    <div class="validation-error">Password: 8+ chars with uppercase, lowercase, number,
                                        special character</div>
                                </div>
                                <div>
                                    <label for="confirm_password"
                                        class="block mb-2 text-sm font-medium text-gray-900">Confirm Password</label>
                                    <input type="password" name="confirm_password" id="confirm_password"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                </div>
                            </div>

                            <div class="form-grid-responsive">
                                <div>
                                    <label for="joining_date"
                                        class="block mb-2 text-sm font-medium text-gray-900">Joining Date *</label>
                                    <input type="date" name="joining_date" id="joining_date"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        max="2099-12-31" required>
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-900">Civil Service
                                        Eligibility *</label>
                                    <div class="flex space-x-4">
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="eligibility" value="Eligible"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500">
                                            <span class="ml-2 text-sm font-medium text-gray-900">Eligible</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="eligibility" value="Not Eligible"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500"
                                                checked>
                                            <span class="ml-2 text-sm font-medium text-gray-900">Not Eligible</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col md:flex-row justify-between gap-3">
                            <button type="button"
                                class="prev-step text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-4 py-2.5 md:px-5 order-2 md:order-1">
                                <i class="fas fa-arrow-left mr-2"></i>Previous
                            </button>
                            <button type="button"
                                class="next-step text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2.5 md:px-5 order-1 md:order-2"
                                data-next="3">
                                Next <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Documents -->
                    <div id="step3" class="form-step hidden">
                        <h2 class="text-base md:text-lg font-semibold text-gray-800 mb-3 md:mb-4">Documents</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 mb-4 md:mb-6">
                            <!-- Government ID -->
                            <div class="file-drop-zone p-4 md:p-6 text-center" data-file-input="doc_id">
                                <i class="fas fa-id-card text-3xl md:text-4xl text-blue-400 mb-2 md:mb-3"></i>
                                <h4 class="text-base md:text-lg font-medium text-gray-900 mb-1 md:mb-2">Government ID
                                </h4>
                                <p class="text-xs md:text-sm text-gray-600 mb-1 md:mb-2">Government-issued
                                    identification</p>
                                <p class="text-xs text-gray-500 mb-2 md:mb-3">JPG, JPEG, PDF, PNG</p>
                                <input type="file" name="doc_id" id="doc_id" accept=".jpg,.jpeg,.pdf,.png"
                                    class="hidden">
                                <div class="file-status text-xs md:text-sm text-green-600"></div>
                            </div>

                            <!-- Resume -->
                            <div class="file-drop-zone p-4 md:p-6 text-center" data-file-input="doc_resume">
                                <i class="fas fa-file-alt text-3xl md:text-4xl text-blue-400 mb-2 md:mb-3"></i>
                                <h4 class="text-base md:text-lg font-medium text-gray-900 mb-1 md:mb-2">Resume / CV</h4>
                                <p class="text-xs md:text-sm text-gray-600 mb-1 md:mb-2">Resume or curriculum vitae</p>
                                <p class="text-xs text-gray-500 mb-2 md:mb-3">JPG, JPEG, PDF, PNG</p>
                                <input type="file" name="doc_resume" id="doc_resume" accept=".jpg,.jpeg,.pdf,.png"
                                    class="hidden">
                                <div class="file-status text-xs md:text-sm text-green-600"></div>
                            </div>

                            <!-- Service Record -->
                            <div class="file-drop-zone p-4 md:p-6 text-center" data-file-input="doc_service">
                                <i class="fas fa-history text-3xl md:text-4xl text-blue-400 mb-2 md:mb-3"></i>
                                <h4 class="text-base md:text-lg font-medium text-gray-900 mb-1 md:mb-2">Service Record
                                </h4>
                                <p class="text-xs md:text-sm text-gray-600 mb-1 md:mb-2">Service record document</p>
                                <p class="text-xs text-gray-500 mb-2 md:mb-3">JPG, JPEG, PDF, PNG</p>
                                <input type="file" name="doc_service" id="doc_service" accept=".jpg,.jpeg,.pdf,.png"
                                    class="hidden">
                                <div class="file-status text-xs md:text-sm text-green-600"></div>
                            </div>

                            <!-- Appointment Paper -->
                            <div class="file-drop-zone p-4 md:p-6 text-center" data-file-input="doc_appointment">
                                <i class="fas fa-file-contract text-3xl md:text-4xl text-blue-400 mb-2 md:mb-3"></i>
                                <h4 class="text-base md:text-lg font-medium text-gray-900 mb-1 md:mb-2">Appointment
                                    Paper</h4>
                                <p class="text-xs md:text-sm text-gray-600 mb-1 md:mb-2">Appointment paper</p>
                                <p class="text-xs text-gray-500 mb-2 md:mb-3">JPG, JPEG, PDF, PNG</p>
                                <input type="file" name="doc_appointment" id="doc_appointment"
                                    accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                                <div class="file-status text-xs md:text-sm text-green-600"></div>
                            </div>

                            <!-- Transcript -->
                            <div class="file-drop-zone p-4 md:p-6 text-center" data-file-input="doc_transcript">
                                <i class="fas fa-graduation-cap text-3xl md:text-4xl text-blue-400 mb-2 md:mb-3"></i>
                                <h4 class="text-base md:text-lg font-medium text-gray-900 mb-1 md:mb-2">Transcript</h4>
                                <p class="text-xs md:text-sm text-gray-600 mb-1 md:mb-2">Transcript and diploma</p>
                                <p class="text-xs text-gray-500 mb-2 md:mb-3">JPG, JPEG, PDF, PNG</p>
                                <input type="file" name="doc_transcript" id="doc_transcript"
                                    accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                                <div class="file-status text-xs md:text-sm text-green-600"></div>
                            </div>

                            <!-- Eligibility Certificate -->
                            <div class="file-drop-zone p-4 md:p-6 text-center" data-file-input="doc_eligibility">
                                <i class="fas fa-award text-3xl md:text-4xl text-blue-400 mb-2 md:mb-3"></i>
                                <h4 class="text-base md:text-lg font-medium text-gray-900 mb-1 md:mb-2">Eligibility</h4>
                                <p class="text-xs md:text-sm text-gray-600 mb-1 md:mb-2">Civil service eligibility</p>
                                <p class="text-xs text-gray-500 mb-2 md:mb-3">JPG, JPEG, PDF, PNG</p>
                                <input type="file" name="doc_eligibility" id="doc_eligibility"
                                    accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                                <div class="file-status text-xs md:text-sm text-green-600"></div>
                            </div>
                        </div>

                        <div class="flex flex-col md:flex-row justify-between gap-3">
                            <button type="button"
                                class="prev-step text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-4 py-2.5 md:px-5 order-2 md:order-1">
                                <i class="fas fa-arrow-left mr-2"></i>Previous
                            </button>
                            <button type="submit"
                                class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-4 py-2.5 md:px-5 order-1 md:order-2"
                                id="submitBtn">
                                <i class="fas fa-check-circle mr-2"></i><span id="submitButtonText">Submit
                                    Employee</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Employee Modal - REDESIGNED TO MATCH IMAGE EXACTLY -->
    <div id="viewEmployeeModal" class="modal-container hidden">
        <div class="view-employee-modal">
            <button class="modal-close-btn" onclick="closeViewModal()">
                <i class="fas fa-times"></i>
            </button>

            <!-- Employee Header -->
            <div class="employee-header">
                <div class="employee-header-content">
                    <div id="viewEmployeePhoto">
                        <div class="employee-photo-large bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-user text-gray-400 text-3xl md:text-4xl"></i>
                        </div>
                    </div>
                    <div class="employee-header-info">
                        <h3 class="employee-main-title" id="viewEmployeeName">Employee Name</h3>
                        <p class="employee-subtitle" id="viewEmployeePosition">Position • Office</p>
                        <div class="employee-meta">
                            <div class="meta-item">
                                <i class="fas fa-id-card"></i>
                                <span id="viewEmployeeId">ID: EMP-0001</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span id="viewEmployeeJoiningDate">Joined: Jan 01, 2024</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee Body -->
            <div class="employee-body">
                <div id="viewEmployeeContent">
                    <!-- Content will be loaded via AJAX -->
                </div>

                <div class="text-center pt-4 md:pt-6">
                    <button onclick="editCurrentEmployee()" class="edit-employee-btn">
                        <i class="fas fa-edit"></i>Edit Employee Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Inactivate Confirmation Modal -->
    <div id="inactivateModal" class="modal-container hidden">
        <div class="inactivate-modal-content">
            <div class="inactivate-icon">
                <i class="fas fa-user-slash"></i>
            </div>
            <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-3 md:mb-4" id="inactivateEmployeeName"></h3>
            <p class="text-gray-600 mb-4 md:mb-6">Are you sure you want to mark this employee as inactive?</p>

            <div class="inactivate-warning-list">
                <ul class="list-disc">
                    <li>Employee will be moved to inactive status</li>
                    <li>Can be reactivated later if needed</li>
                    <li>No data will be deleted</li>
                </ul>
            </div>

            <div class="flex flex-col md:flex-row justify-center gap-3 md:space-x-4 mt-6 md:mt-8">
                <button type="button"
                    class="px-4 md:px-6 py-2.5 text-sm font-medium text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-100"
                    onclick="closeInactivateModal()">
                    Cancel
                </button>
                <button type="button" id="confirmInactivateBtn"
                    class="px-4 md:px-6 py-2.5 text-sm font-medium text-white bg-yellow-600 rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-4 focus:ring-yellow-300">
                    Mark as Inactive
                </button>
            </div>
        </div>
    </div>

    <!-- Activate Confirmation Modal -->
    <div id="activateModal" class="modal-container hidden">
        <div class="inactivate-modal-content">
            <div class="inactivate-icon">
                <i class="fas fa-user-check" style="color: #10b981;"></i>
            </div>
            <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-3 md:mb-4" id="activateEmployeeName"></h3>
            <p class="text-gray-600 mb-4 md:mb-6">Are you sure you want to activate this employee?</p>

            <div class="inactivate-warning-list">
                <ul class="list-disc">
                    <li>Employee will be moved to active status</li>
                    <li>Will appear in active employee list</li>
                    <li>Can access the system again</li>
                </ul>
            </div>

            <div class="flex flex-col md:flex-row justify-center gap-3 md:space-x-4 mt-6 md:mt-8">
                <button type="button"
                    class="px-4 md:px-6 py-2.5 text-sm font-medium text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-100"
                    onclick="closeActivateModal()">
                    Cancel
                </button>
                <button type="button" id="confirmActivateBtn"
                    class="px-4 md:px-6 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-300">
                    Activate Employee
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom JavaScript -->
    <script>
        // ===============================================
        // NAVBAR DATE & TIME FUNCTIONALITY
        // ===============================================
        function updateDateTime() {
            const now = new Date();

            // Format date: Weekday, Month Day, Year
            const optionsDate = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const dateString = now.toLocaleDateString('en-US', optionsDate);

            // Format time: HH:MM:SS AM/PM
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;

            const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;

            // Update the DOM
            document.getElementById('current-date').textContent = dateString;
            document.getElementById('current-time').textContent = timeString;
        }

        // Initial call
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // ===============================================
        // MODAL FUNCTIONS
        // ===============================================
        let currentStep = 1;
        let isEditMode = false;
        let inactivateEmployeeId = null;
        let inactivateEmployeeName = null;
        let activateEmployeeId = null;
        let activateEmployeeName = null;
        let currentViewEmployeeId = null;

        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            const backdrop = document.getElementById('modalBackdrop');

            if (modal && backdrop) {
                modal.classList.remove('hidden');
                backdrop.classList.remove('hidden');
                document.body.classList.add('modal-open');
                document.body.style.overflow = 'hidden';
            }
        }

        function hideModal(modalId) {
            const modal = document.getElementById(modalId);
            const backdrop = document.getElementById('modalBackdrop');

            if (modal && backdrop) {
                modal.classList.add('hidden');
                backdrop.classList.add('hidden');
                document.body.classList.remove('modal-open');
                document.body.style.overflow = 'auto';
            }
        }

        function openAddModal() {
            isEditMode = false;
            document.getElementById('modalTitle').textContent = 'Add New Contractual Employee';
            document.getElementById('formAction').value = 'add';
            document.getElementById('employeeId').value = '';
            document.getElementById('submitButtonText').textContent = 'Submit Employee';

            // Reset form
            document.getElementById('employeeForm').reset();
            resetFormToStep1();

            // Reset file drop zones
            document.querySelectorAll('.file-drop-zone').forEach(zone => {
                const fileStatus = zone.querySelector('.file-status');
                fileStatus.textContent = '';
                zone.classList.remove('border-green-500', 'bg-green-50');
                zone.classList.add('border-gray-300', 'bg-gray-50');
            });

            // Reset profile image
            const profileImageContainer = document.getElementById('profileImageContainer');
            profileImageContainer.innerHTML = '<i class="fas fa-user text-gray-400 text-3xl md:text-4xl"></i>';

            // Set default dates
            const today = new Date().toISOString().split('T')[0];
            const nextMonth = new Date();
            nextMonth.setMonth(nextMonth.getMonth() + 1);
            const nextMonthStr = nextMonth.toISOString().split('T')[0];

            document.getElementById('period_from').value = today;
            document.getElementById('period_to').value = nextMonthStr;
            document.getElementById('joining_date').value = today;

            // Clear employee ID (will be auto-generated)
            document.getElementById('employee_id_input').value = '';

            // Set default values
            document.getElementById('nationality').value = 'Filipino';
            document.getElementById('state_region').value = 'Occidental Mindoro';
            document.getElementById('city').value = 'Paluan';

            // Show modal
            showModal('employeeModal');
        }

        function editEmployee(employeeId) {
            // Redirect to edit mode
            window.location.href = `contractofservice.php?edit_id=${employeeId}`;
        }

        function editCurrentEmployee() {
            if (currentViewEmployeeId) {
                editEmployee(currentViewEmployeeId);
            }
        }

        // FIXED VIEW EMPLOYEE FUNCTION
        function viewEmployee(employeeId) {
            currentViewEmployeeId = employeeId;

            // Reset modal content to initial state
            document.getElementById('viewEmployeeName').textContent = 'Loading...';
            document.getElementById('viewEmployeePosition').textContent = 'Loading...';
            document.getElementById('viewEmployeeId').textContent = 'ID: Loading...';
            document.getElementById('viewEmployeeJoiningDate').textContent = 'Joined: Loading...';
            document.getElementById('viewEmployeePhoto').innerHTML = `
                <div class="employee-photo-large bg-gray-100 flex items-center justify-center">
                    <i class="fas fa-user text-gray-400 text-3xl md:text-4xl"></i>
                </div>
            `;
            document.getElementById('viewEmployeeContent').innerHTML = `
                <div class="flex justify-center items-center h-48 md:h-64">
                    <div class="animate-spin rounded-full h-10 w-10 md:h-12 md:w-12 border-b-2 border-blue-600"></div>
                    <p class="ml-3 text-gray-600">Loading employee data...</p>
                </div>
            `;

            // Show modal immediately while loading data
            showModal('viewEmployeeModal');

            // Fetch employee data via AJAX
            fetch(`contractofservice.php?view_id=${employeeId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to load employee data');
                    }

                    const employee = data.employee;
                    const html = data.html;

                    // Update header information
                    document.getElementById('viewEmployeeName').textContent = employee.full_name || 'N/A';
                    document.getElementById('viewEmployeePosition').textContent =
                        `${employee.designation || 'N/A'} • ${employee.office_assignment || 'N/A'}`;
                    document.getElementById('viewEmployeeId').textContent = `ID: ${employee.employee_id || 'N/A'}`;
                    document.getElementById('viewEmployeeJoiningDate').textContent = `Joined: ${employee.joining_date || 'N/A'}`;

                    // Update photo
                    const photoContainer = document.getElementById('viewEmployeePhoto');
                    if (employee.profile_image_path) {
                        photoContainer.innerHTML = `
                            <img src="${employee.profile_image_path}" class="employee-photo-large" alt="Profile" onerror="this.onerror=null; this.src=''; this.parentElement.innerHTML='<div class=\"employee-photo-large bg-gray-100 flex items-center justify-center\"><i class=\"fas fa-user text-gray-400 text-3xl md:text-4xl\"></i></div>';">
                        `;
                    } else {
                        photoContainer.innerHTML = `
                            <div class="employee-photo-large bg-gray-100 flex items-center justify-center">
                                <i class="fas fa-user text-gray-400 text-3xl md:text-4xl"></i>
                            </div>
                        `;
                    }

                    // Update content
                    document.getElementById('viewEmployeeContent').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('viewEmployeeContent').innerHTML = `
                        <div class="text-center p-4 md:p-8">
                            <i class="fas fa-exclamation-triangle text-red-500 text-3xl md:text-4xl mb-3 md:mb-4"></i>
                            <p class="text-red-600 font-medium">Error loading employee data</p>
                            <p class="text-gray-500 text-xs md:text-sm mt-1 md:mt-2">${error.message}</p>
                            <button onclick="viewEmployee(${employeeId})" class="mt-3 md:mt-4 px-3 md:px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                <i class="fas fa-redo mr-1 md:mr-2"></i>Try Again
                            </button>
                        </div>
                    `;
                });
        }

        function confirmInactivate(employeeId, employeeName) {
            inactivateEmployeeId = employeeId;
            inactivateEmployeeName = employeeName;

            document.getElementById('inactivateEmployeeName').textContent = `"${employeeName}"`;
            showModal('inactivateModal');
        }

        function confirmActivate(employeeId, employeeName) {
            activateEmployeeId = employeeId;
            activateEmployeeName = employeeName;

            document.getElementById('activateEmployeeName').textContent = `"${employeeName}"`;
            showModal('activateModal');
        }

        function closeInactivateModal() {
            hideModal('inactivateModal');
            inactivateEmployeeId = null;
            inactivateEmployeeName = null;
        }

        function closeActivateModal() {
            hideModal('activateModal');
            activateEmployeeId = null;
            activateEmployeeName = null;
        }

        function closeModal() {
            hideModal('employeeModal');
        }

        function closeViewModal() {
            hideModal('viewEmployeeModal');
            currentViewEmployeeId = null;
        }

        // Handle inactivate confirmation
        document.getElementById('confirmInactivateBtn').addEventListener('click', function () {
            if (inactivateEmployeeId) {
                window.location.href = `contractofservice.php?inactivate_id=${inactivateEmployeeId}`;
            }
        });

        // Handle activate confirmation
        document.getElementById('confirmActivateBtn').addEventListener('click', function () {
            if (activateEmployeeId) {
                window.location.href = `contractofservice.php?activate_id=${activateEmployeeId}`;
            }
        });

        // Close modal when clicking on backdrop
        document.getElementById('modalBackdrop').addEventListener('click', function () {
            closeModal();
            closeViewModal();
            closeInactivateModal();
            closeActivateModal();
        });

        // ===============================================
        // PAGINATION FUNCTIONS
        // ===============================================
        function goToPage(page) {
            const viewParam = <?php echo $view_inactive ? "'&view=inactive'" : "''"; ?>;
            if (page >= 1 && page <= <?php echo $total_pages; ?>) {
                window.location.href = `contractofservice.php?page=${page}${viewParam}`;
            }
        }

        // ===============================================
        // FORM STEP FUNCTIONALITY
        // ===============================================
        function resetFormToStep1() {
            currentStep = 1;
            showStep(currentStep);
            updateStepNavigation();
        }

        function showStep(stepIndex) {
            document.querySelectorAll('.form-step').forEach(step => {
                step.classList.add('hidden');
                step.classList.remove('active');
            });

            const currentStepElement = document.getElementById(`step${stepIndex}`);
            if (currentStepElement) {
                currentStepElement.classList.remove('hidden');
                currentStepElement.classList.add('active');
            }
        }

        function updateStepNavigation() {
            document.querySelectorAll('.step-nav').forEach(nav => {
                const step = parseInt(nav.getAttribute('data-step'));
                nav.classList.remove('border-blue-600', 'text-blue-600', 'border-transparent', 'text-gray-500');

                if (step === currentStep) {
                    nav.classList.add('border-blue-600', 'text-blue-600');
                } else {
                    nav.classList.add('border-transparent', 'text-gray-500');
                }
            });
        }

        // ===============================================
        // FORM VALIDATION FUNCTIONS
        // ===============================================
        function validateFormStep(step) {
            let isValid = true;
            const stepElement = document.getElementById(`step${step}`);
            const inputs = stepElement.querySelectorAll('input[required], select[required], textarea[required]');

            inputs.forEach(input => {
                // Reset error state
                input.classList.remove('border-red-500', 'border-green-500');

                // Check validity
                if (!input.checkValidity()) {
                    isValid = false;
                    input.classList.add('border-red-500');

                    // Show validation message
                    const errorSpan = input.nextElementSibling;
                    if (errorSpan && errorSpan.classList.contains('validation-error')) {
                        errorSpan.style.display = 'block';
                        // Set specific error message
                        if (input.validity.valueMissing) {
                            errorSpan.textContent = 'This field is required';
                        } else if (input.validity.typeMismatch) {
                            errorSpan.textContent = 'Please enter a valid ' + input.type;
                        } else if (input.validity.patternMismatch) {
                            errorSpan.textContent = 'Please match the requested format';
                        }
                    }
                } else {
                    input.classList.add('border-green-500');
                    // Hide validation message
                    const errorSpan = input.nextElementSibling;
                    if (errorSpan && errorSpan.classList.contains('validation-error')) {
                        errorSpan.style.display = 'none';
                    }
                }
            });

            // Special validation for step 2 password fields
            if (step === 2) {
                const password = document.getElementById('password');
                const confirmPassword = document.getElementById('confirm_password');

                // For new employees (not edit mode), password is required
                if (!isEditMode) {
                    if (!password.value.trim()) {
                        password.classList.add('border-red-500');
                        isValid = false;

                        let errorMsg = password.nextElementSibling;
                        if (errorMsg && errorMsg.classList.contains('validation-error')) {
                            errorMsg.textContent = 'Password is required for new employees';
                            errorMsg.style.display = 'block';
                        }
                    } else if (password.value !== confirmPassword.value) {
                        confirmPassword.classList.add('border-red-500');
                        isValid = false;

                        let errorMsg = confirmPassword.nextElementSibling;
                        if (errorMsg && errorMsg.classList.contains('validation-error')) {
                            errorMsg.textContent = 'Passwords do not match';
                            errorMsg.style.display = 'block';
                        }
                    } else {
                        password.classList.remove('border-red-500');
                        confirmPassword.classList.remove('border-red-500');
                    }
                }

                // For edit mode with password change
                if (isEditMode && password.value.trim() && password.value !== confirmPassword.value) {
                    confirmPassword.classList.add('border-red-500');
                    isValid = false;

                    let errorMsg = confirmPassword.nextElementSibling;
                    if (errorMsg && errorMsg.classList.contains('validation-error')) {
                        errorMsg.textContent = 'Passwords do not match';
                        errorMsg.style.display = 'block';
                    }
                }
            }

            return isValid;
        }

        // ===============================================
        // EVENT LISTENERS
        // ===============================================
        document.addEventListener('DOMContentLoaded', function () {
            // Sidebar functionality
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarContainer = document.getElementById('sidebar-container');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            if (sidebarToggle && sidebarContainer && sidebarOverlay) {
                sidebarToggle.addEventListener('click', function () {
                    sidebarContainer.classList.toggle('active');
                    sidebarOverlay.classList.toggle('active');
                    document.body.style.overflow = sidebarContainer.classList.contains('active') ? 'hidden' : 'auto';
                });

                sidebarOverlay.addEventListener('click', function () {
                    sidebarContainer.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                });
            }

            // Payroll dropdown functionality
            const payrollToggle = document.getElementById('payroll-toggle');
            const payrollDropdown = document.getElementById('payroll-dropdown');

            if (payrollToggle && payrollDropdown) {
                payrollToggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    payrollDropdown.classList.toggle('open');
                    const chevron = payrollToggle.querySelector('.chevron');
                    chevron.classList.toggle('rotated');
                });
            }

            // Step navigation click
            document.querySelectorAll('.step-nav').forEach(nav => {
                nav.addEventListener('click', function () {
                    const step = parseInt(this.getAttribute('data-step'));
                    if (step < currentStep || validateFormStep(currentStep)) {
                        currentStep = step;
                        showStep(currentStep);
                        updateStepNavigation();
                    }
                });
            });

            // Next button click
            document.querySelectorAll('.next-step').forEach(button => {
                button.addEventListener('click', function () {
                    const nextStep = parseInt(this.getAttribute('data-next'));
                    if (validateFormStep(currentStep)) {
                        currentStep = nextStep;
                        showStep(currentStep);
                        updateStepNavigation();
                    }
                });
            });

            // Previous button click
            document.querySelectorAll('.prev-step').forEach(button => {
                button.addEventListener('click', function () {
                    currentStep--;
                    showStep(currentStep);
                    updateStepNavigation();
                });
            });

            // Form submission
            document.getElementById('employeeForm').addEventListener('submit', function (e) {
                e.preventDefault();

                // Validate all steps before submission
                let allValid = true;
                for (let i = 1; i <= 3; i++) {
                    if (!validateFormStep(i)) {
                        allValid = false;
                        // Go to the first step with errors
                        currentStep = i;
                        showStep(currentStep);
                        updateStepNavigation();
                        break;
                    }
                }

                if (!allValid) {
                    // Scroll to first error
                    const firstError = document.querySelector('.border-red-500');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }

                // Special validation for password in add mode
                if (!isEditMode) {
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;

                    if (!password.trim()) {
                        alert('Password is required for new employees');
                        currentStep = 2;
                        showStep(currentStep);
                        updateStepNavigation();
                        document.getElementById('password').focus();
                        return false;
                    }

                    if (password !== confirmPassword) {
                        alert('Passwords do not match');
                        currentStep = 2;
                        showStep(currentStep);
                        updateStepNavigation();
                        document.getElementById('confirm_password').focus();
                        return false;
                    }
                }

                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-loading');

                // Submit the form
                setTimeout(() => {
                    this.submit();
                }, 500);
            });

            // Real-time validation for inputs
            document.querySelectorAll('input[pattern], select[required]').forEach(input => {
                input.addEventListener('input', function () {
                    if (this.checkValidity()) {
                        this.classList.remove('border-red-500');
                        this.classList.add('border-green-500');
                        const errorSpan = this.nextElementSibling;
                        if (errorSpan && errorSpan.classList.contains('validation-error')) {
                            errorSpan.style.display = 'none';
                        }
                    } else {
                        this.classList.remove('border-green-500');
                        this.classList.add('border-red-500');
                    }
                });

                input.addEventListener('blur', function () {
                    if (!this.checkValidity()) {
                        const errorSpan = this.nextElementSibling;
                        if (errorSpan && errorSpan.classList.contains('validation-error')) {
                            errorSpan.style.display = 'block';
                        }
                    }
                });
            });

            // Phone number formatting
            const mobileInput = document.getElementById('mobile_number');
            if (mobileInput) {
                mobileInput.addEventListener('input', function () {
                    // Remove non-numeric characters
                    this.value = this.value.replace(/\D/g, '');

                    // Limit to 11 digits
                    if (this.value.length > 11) {
                        this.value = this.value.substring(0, 11);
                    }
                });
            }

            // ZIP code formatting
            const zipInput = document.getElementById('zip_code');
            if (zipInput) {
                zipInput.addEventListener('input', function () {
                    // Remove non-numeric characters
                    this.value = this.value.replace(/\D/g, '');

                    // Limit to 4 digits
                    if (this.value.length > 4) {
                        this.value = this.value.substring(0, 4);
                    }
                });
            }

            // Wages formatting
            const wagesInput = document.getElementById('wages');
            if (wagesInput) {
                wagesInput.addEventListener('blur', function () {
                    if (this.value) {
                        const value = parseFloat(this.value);
                        if (!isNaN(value)) {
                            this.value = value.toFixed(2);
                        }
                    }
                });
            }

            // File upload functionality
            document.querySelectorAll('.file-drop-zone').forEach(zone => {
                const fileInput = zone.querySelector('input[type="file"]');
                const fileStatus = zone.querySelector('.file-status');

                zone.addEventListener('click', function (e) {
                    if (e.target !== fileInput) {
                        fileInput.click();
                    }
                });

                fileInput.addEventListener('change', function () {
                    if (this.files.length > 0) {
                        const fileName = this.files[0].name;
                        const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2); // MB

                        // Validate file size (max 10MB)
                        if (fileSize > 10) {
                            alert('File size must be less than 10MB');
                            this.value = '';
                            fileStatus.textContent = '';
                            zone.classList.remove('border-green-500', 'bg-green-50');
                            zone.classList.add('border-gray-300', 'bg-gray-50');
                            return;
                        }

                        fileStatus.textContent = `Selected: ${fileName.substring(0, 20)}${fileName.length > 20 ? '...' : ''} (${fileSize} MB)`;
                        zone.classList.add('border-green-500', 'bg-green-50');
                        zone.classList.remove('border-gray-300', 'bg-gray-50');
                    } else {
                        fileStatus.textContent = '';
                        zone.classList.remove('border-green-500', 'bg-green-50');
                        zone.classList.add('border-gray-300', 'bg-gray-50');
                    }
                });
            });

            // Profile image upload
            const profileImageInput = document.getElementById('profile_image');
            const profileImageContainer = document.getElementById('profileImageContainer');

            if (profileImageInput && profileImageContainer) {
                profileImageInput.addEventListener('change', function () {
                    if (this.files && this.files[0]) {
                        const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2); // MB

                        // Validate file size (max 5MB for images)
                        if (fileSize > 5) {
                            alert('Profile image size must be less than 5MB');
                            this.value = '';
                            return;
                        }

                        const reader = new FileReader();
                        reader.onload = function (e) {
                            profileImageContainer.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover rounded-full" alt="Profile Image">`;
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }

            // Search functionality
            const searchInput = document.getElementById('search-employee');
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('tbody tr');

                    rows.forEach(row => {
                        const nameCell = row.querySelector('td:nth-child(3)');
                        const idCell = row.querySelector('td:nth-child(2)');
                        if ((nameCell && nameCell.textContent.toLowerCase().includes(searchTerm)) ||
                            (idCell && idCell.textContent.toLowerCase().includes(searchTerm))) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }

            // Auto-open modal if in edit mode
            <?php if ($is_edit && $employee_data): ?>
                setTimeout(() => {
                    openEditModal(<?php echo json_encode($employee_data); ?>);
                }, 100);
            <?php endif; ?>

            // Office change event for employee ID generation
            const officeSelect = document.getElementById('office');
            if (officeSelect) {
                officeSelect.addEventListener('change', function () {
                    // Only update employee ID for new records
                    if (!isEditMode) {
                        const employeeIdInput = document.getElementById('employee_id_input');
                        if (employeeIdInput) {
                            employeeIdInput.value = 'Auto-generated on save';
                        }
                    }
                });
            }

            // Prevent accidental form submission
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                    const activeElement = document.activeElement;
                    if (activeElement && activeElement.form && activeElement.type !== 'submit') {
                        e.preventDefault();
                    }
                }
            });
        });

        // ===============================================
        // EDIT MODAL FUNCTION
        // ===============================================
        function openEditModal(employeeData) {
            isEditMode = true;
            document.getElementById('modalTitle').textContent = 'Edit Contractual Employee';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('employeeId').value = employeeData.id;
            document.getElementById('submitButtonText').textContent = 'Update Employee';

            // Fill form with existing data
            document.getElementById('employee_id_input').value = employeeData.employee_id || '';
            document.getElementById('full_name').value = employeeData.full_name || '';
            document.getElementById('designation').value = employeeData.designation || '';
            document.getElementById('office').value = employeeData.office_assignment || '';
            document.getElementById('period_from').value = employeeData.period_from || '';
            document.getElementById('period_to').value = employeeData.period_to || '';
            document.getElementById('wages').value = employeeData.wages || '';
            document.getElementById('contribution').value = employeeData.contribution || '';
            document.getElementById('first_name').value = employeeData.first_name || '';
            document.getElementById('last_name').value = employeeData.last_name || '';
            document.getElementById('mobile_number').value = employeeData.mobile_number || '';
            document.getElementById('email_address').value = employeeData.email_address || '';
            document.getElementById('date_of_birth').value = employeeData.date_of_birth || '';
            document.getElementById('marital_status').value = employeeData.marital_status || '';
            document.getElementById('gender').value = employeeData.gender || '';
            document.getElementById('nationality').value = employeeData.nationality || 'Filipino';
            document.getElementById('street_address').value = employeeData.street_address || '';
            document.getElementById('city').value = employeeData.city || 'Paluan';
            document.getElementById('state_region').value = employeeData.state_region || 'Occidental Mindoro';
            document.getElementById('zip_code').value = employeeData.zip_code || '';
            document.getElementById('joining_date').value = employeeData.joining_date || '';

            // Set eligibility radio
            const eligibility = employeeData.eligibility || 'Not Eligible';
            document.querySelectorAll('input[name="eligibility"]').forEach(radio => {
                radio.checked = (radio.value === eligibility);
            });

            // Set profile image
            const profileImageContainer = document.getElementById('profileImageContainer');
            if (employeeData.profile_image_path) {
                profileImageContainer.innerHTML = `<img src="${employeeData.profile_image_path}" class="w-full h-full object-cover rounded-full" alt="Profile Image">`;
                document.getElementById('current_profile_image').value = employeeData.profile_image_path;
            } else {
                profileImageContainer.innerHTML = '<i class="fas fa-user text-gray-400 text-3xl md:text-4xl"></i>';
                document.getElementById('current_profile_image').value = '';
            }

            // Set file status for existing documents
            const fileFields = [
                { id: 'doc_id', field: 'doc_id_path', label: 'Government ID' },
                { id: 'doc_resume', field: 'doc_resume_path', label: 'Resume' },
                { id: 'doc_service', field: 'doc_service_path', label: 'Service Record' },
                { id: 'doc_appointment', field: 'doc_appointment_path', label: 'Appointment Paper' },
                { id: 'doc_transcript', field: 'doc_transcript_path', label: 'Transcript' },
                { id: 'doc_eligibility', field: 'doc_eligibility_path', label: 'Eligibility Certificate' }
            ];

            fileFields.forEach(fileField => {
                const zone = document.querySelector(`[data-file-input="${fileField.id}"]`);
                if (zone) {
                    const fileStatus = zone.querySelector('.file-status');
                    const fileInput = zone.querySelector('input[type="file"]');

                    // Clear any existing file selection
                    if (fileInput) {
                        fileInput.value = '';
                    }

                    if (employeeData[fileField.field]) {
                        const fileName = employeeData[fileField.field].split('/').pop();
                        fileStatus.textContent = `Current: ${fileName.substring(0, 20)}${fileName.length > 20 ? '...' : ''}`;
                        zone.classList.add('border-green-500', 'bg-green-50');
                        zone.classList.remove('border-gray-300', 'bg-gray-50');
                    } else {
                        fileStatus.textContent = '';
                        zone.classList.remove('border-green-500', 'bg-green-50');
                        zone.classList.add('border-gray-300', 'bg-gray-50');
                    }
                }
            });

            // Reset to step 1
            resetFormToStep1();

            // Show modal
            showModal('employeeModal');
        }
    </script>
</body>

</html>