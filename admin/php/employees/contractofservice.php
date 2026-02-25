<?php
// Debug mode - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

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

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database Configuration
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
$records_per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
$valid_per_page = [5, 10, 25, 50, 100];
if (!in_array($records_per_page, $valid_per_page)) {
    $records_per_page = 10;
}
$total_records = 0;
$total_pages = 0;

// Initialize PDO connection with error handling
try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("ERROR: Could not connect to the database. Check server logs for details.");
}

// ===============================================
// HELPER FUNCTIONS
// ===============================================

/**
 * Upload file with validation
 */
function uploadFile($file_input_name, $destination_dir, $file_prefix, $existing_file = null)
{
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] != UPLOAD_ERR_OK) {
        return $existing_file;
    }

    $file = $_FILES[$file_input_name];

    // Security checks
    $max_file_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_file_size) {
        error_log("File upload failed: File too large for " . $file_input_name);
        return $existing_file;
    }

    // Validate file extension
    $allowed_extensions = ['jpg', 'jpeg', 'pdf', 'png'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_extension, $allowed_extensions)) {
        error_log("File upload failed: Invalid file type for " . $file_input_name);
        return $existing_file;
    }

    // Generate secure filename
    $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $new_file_name = $file_prefix . '_' . time() . '_' . uniqid() . '.' . $file_extension;
    $destination = $destination_dir . $new_file_name;

    // Ensure directory exists
    if (!is_dir($destination_dir)) {
        if (!mkdir($destination_dir, 0755, true)) {
            error_log("Failed to create directory: " . $destination_dir);
            return $existing_file;
        }
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Delete old file if exists
        if ($existing_file && file_exists($existing_file) && $existing_file != $destination) {
            @unlink($existing_file);
        }
        return $destination;
    }

    return $existing_file;
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
        $parts = explode('-', $last_id);
        $sequence = intval($parts[2]) + 1;
    } else {
        $sequence = 1;
    }

    return sprintf("%s-%s-%04d", $office_code, $year, $sequence);
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

/**
 * Sanitize input data
 */
function sanitizeInput($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate date
 */
function validateDate($date, $format = 'Y-m-d')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Ensure upload directory exists
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        die("ERROR: Failed to create upload directory. Check permissions.");
    }
}

// ===============================================
// HANDLE VIEW REQUEST (AJAX)
// ===============================================
if (isset($_GET['view_id']) && !isset($_GET['edit_id'])) {
    $view_id = intval($_GET['view_id']);

    try {
        $stmt = $pdo->prepare("SELECT * FROM contractofservice WHERE id = ?");
        $stmt->execute([$view_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($employee) {
            // Helper functions for formatting
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

            function formatCurrency($amount)
            {
                if (!$amount || $amount == '0' || $amount == '0.00') {
                    return '₱0.00';
                }
                $num = floatval($amount);
                return '₱' . number_format($num, 2);
            }

            // Build HTML response
            $html = '<div class="employee-view-content">';

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
            $html .= '</div>';

            // Return JSON response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'html' => $html,
                'employee' => [
                    'id' => $employee['id'],
                    'employee_id' => $employee['employee_id'],
                    'full_name' => $employee['full_name'],
                    'designation' => $employee['designation'],
                    'office' => $employee['office'],
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
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit();
    }
}

// ===============================================
// HANDLE INACTIVATE/ACTIVATE REQUESTS
// ===============================================
if (isset($_GET['inactivate_id'])) {
    $inactivate_id = intval($_GET['inactivate_id']);

    try {
        $stmt = $pdo->prepare("UPDATE contractofservice SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$inactivate_id]);
        $success = "Employee marked as inactive successfully!";
        header("Location: contractofservice.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error marking employee as inactive.";
    }
}

if (isset($_GET['activate_id'])) {
    $activate_id = intval($_GET['activate_id']);

    try {
        $stmt = $pdo->prepare("UPDATE contractofservice SET status = 'active' WHERE id = ?");
        $stmt->execute([$activate_id]);
        $success = "Employee activated successfully!";
        header("Location: contractofservice.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error activating employee.";
    }
}


// ===============================================
// HANDLE EDIT FETCH REQUEST (AJAX)
// ===============================================
if (isset($_GET['edit_fetch_id'])) {
    header('Content-Type: application/json');

    try {
        $edit_id = intval($_GET['edit_fetch_id']);

        $stmt = $pdo->prepare("SELECT * FROM contractofservice WHERE id = ?");
        $stmt->execute([$edit_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($employee) {
            // Make sure middle initial is included
            if (!isset($employee['middle'])) {
                $employee['middle'] = '';
            }

            // Construct full name from components
            $first_name = isset($employee['first_name']) ? trim($employee['first_name']) : '';
            $last_name = isset($employee['last_name']) ? trim($employee['last_name']) : '';
            $middle = isset($employee['middle']) ? trim($employee['middle']) : '';

            $full_name_parts = [];
            if (!empty($first_name))
                $full_name_parts[] = $first_name;
            if (!empty($middle))
                $full_name_parts[] = substr($middle, 0, 1) . '.';
            if (!empty($last_name))
                $full_name_parts[] = $last_name;
            $employee['full_name'] = !empty($full_name_parts) ? implode(' ', $full_name_parts) : 'No Name Provided';

            echo json_encode([
                'success' => true,
                'employee' => $employee
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Employee not found']);
        }
        exit();

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// ===============================================
// HANDLE EDIT DATA FETCH
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
        $error = "Error loading employee data.";
        $is_edit = false;
    }
}

// ===============================================
// HANDLE FORM SUBMISSION (ADD/EDIT) - FIXED
// ===============================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        // Get action type
        $action = $_POST['action'] ?? 'add';
        $is_edit = ($action === 'edit');

        // FIXED: Check both possible field names for employee ID
        $edit_id = null;
        if ($is_edit) {
            // Try to get ID from employeeId field first (from modal), then from employee_id field
            if (isset($_POST['employeeId']) && !empty($_POST['employeeId'])) {
                $edit_id = intval($_POST['employeeId']);
            } elseif (isset($_POST['id']) && !empty($_POST['id'])) {
                $edit_id = intval($_POST['id']);
            }
        }

        error_log("========== FORM SUBMISSION ==========");
        error_log("Action: " . $action);
        error_log("Edit ID: " . ($edit_id ?? 'null'));
        error_log("POST data: " . print_r($_POST, true));

        // Validate required fields
        $required_fields = [
            'designation',
            'office',
            'period_from',
            'period_to',
            'wages',
            'ctc_number',
            'ctc_date',
            'first_name',
            'last_name',
            'mobile_number',
            'email_address',
            'date_of_birth',
            'marital_status',
            'gender',
            'nationality',
            'street_address',
            'city',
            'state_region',
            'zip_code',
            'joining_date'
        ];

        $validation_errors = [];

        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $validation_errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
            }
        }

        // Specific validations
        if (!filter_var($_POST['email_address'], FILTER_VALIDATE_EMAIL)) {
            $validation_errors[] = "Valid email address is required";
        }

        if (!preg_match('/^[0-9]{11}$/', $_POST['mobile_number'])) {
            $validation_errors[] = "Mobile number must be exactly 11 digits";
        }

        if (!preg_match('/^[0-9]{4}$/', $_POST['zip_code'])) {
            $validation_errors[] = "ZIP code must be exactly 4 digits";
        }

        $wages = filter_var($_POST['wages'], FILTER_VALIDATE_FLOAT);
        if ($wages === false || $wages < 0) {
            $validation_errors[] = "Valid wages amount is required";
        }

        // Date validations
        if (!validateDate($_POST['date_of_birth'])) {
            $validation_errors[] = "Invalid date of birth";
        } elseif (strtotime($_POST['date_of_birth']) >= strtotime('today')) {
            $validation_errors[] = "Date of birth must be in the past";
        }

        if (!validateDate($_POST['period_from']) || !validateDate($_POST['period_to'])) {
            $validation_errors[] = "Invalid contract period dates";
        } elseif (strtotime($_POST['period_from']) > strtotime($_POST['period_to'])) {
            $validation_errors[] = "Period From cannot be after Period To";
        }

        if (!validateDate($_POST['joining_date'])) {
            $validation_errors[] = "Invalid joining date";
        }

        // FIXED: Additional validation for edit mode - require employee ID
        if ($is_edit && empty($edit_id)) {
            $validation_errors[] = "Employee ID is missing for update operation";
        }

        if (empty($validation_errors)) {
            try {
                $pdo->beginTransaction();

                // Check if email already exists (for new records or different employee)
                if (!$is_edit) {
                    $stmt = $pdo->prepare("SELECT id FROM contractofservice WHERE email_address = ?");
                    $stmt->execute([$_POST['email_address']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Email address already exists.");
                    }
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM contractofservice WHERE email_address = ? AND id != ?");
                    $stmt->execute([$_POST['email_address'], $edit_id]);
                    if ($stmt->fetch()) {
                        throw new Exception("Email address already exists for another employee.");
                    }
                }

                // Get existing files for edit mode
                $existing_files = [];
                if ($is_edit && $edit_id) {
                    $stmt = $pdo->prepare("SELECT * FROM contractofservice WHERE id = ?");
                    $stmt->execute([$edit_id]);
                    $existing_files = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                }

                // Handle file uploads
                $profile_image_path = uploadFile(
                    'profile_image',
                    $upload_dir,
                    'profile',
                    $is_edit ? ($existing_files['profile_image_path'] ?? null) : null
                );

                $doc_paths = [
                    'doc_id' => uploadFile(
                        'doc_id',
                        $upload_dir,
                        'govt_id',
                        $is_edit ? ($existing_files['doc_id_path'] ?? null) : null
                    ),
                    'doc_resume' => uploadFile(
                        'doc_resume',
                        $upload_dir,
                        'resume',
                        $is_edit ? ($existing_files['doc_resume_path'] ?? null) : null
                    ),
                    'doc_service' => uploadFile(
                        'doc_service',
                        $upload_dir,
                        'service_rec',
                        $is_edit ? ($existing_files['doc_service_path'] ?? null) : null
                    ),
                    'doc_appointment' => uploadFile(
                        'doc_appointment',
                        $upload_dir,
                        'appt_paper',
                        $is_edit ? ($existing_files['doc_appointment_path'] ?? null) : null
                    ),
                    'doc_transcript' => uploadFile(
                        'doc_transcript',
                        $upload_dir,
                        'transcript',
                        $is_edit ? ($existing_files['doc_transcript_path'] ?? null) : null
                    ),
                    'doc_eligibility' => uploadFile(
                        'doc_eligibility',
                        $upload_dir,
                        'elig_cert',
                        $is_edit ? ($existing_files['doc_eligibility_path'] ?? null) : null
                    )
                ];

                // Generate or use employee ID
                $employee_id = $_POST['employee_id'] ?? '';
                if (empty($employee_id) && !$is_edit) {
                    $office_code = getOfficeCode($_POST['office']);
                    $employee_id = generateEmployeeID($pdo, $office_code);
                }

                if ($is_edit && $edit_id) {
                    // FIXED: Ensure we have the ID for update
                    if (!$edit_id) {
                        throw new Exception("Cannot update: Employee ID is missing");
                    }

                    $full_name = trim($_POST['first_name'] . ' ' .
                        (!empty($_POST['middle']) ? substr($_POST['middle'], 0, 1) . '. ' : '') .
                        $_POST['last_name']);

                    $sql = "UPDATE contractofservice SET
                    employee_id = :employee_id,
                    designation = :designation,
                    office = :office,
                    period_from = :period_from,
                    period_to = :period_to,
                    wages = :wages,
                    contribution = :contribution,
                    ctc_number = :ctc_number,
                    ctc_date = :ctc_date, 
                    profile_image_path = :profile_image_path,
                    first_name = :first_name,
                    last_name = :last_name,
                    middle = :middle,
                    full_name = :full_name,
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
                    doc_eligibility_path = :doc_eligibility_path,
                    updated_at = CURRENT_TIMESTAMP 
                    WHERE id = :id";

                    $stmt = $pdo->prepare($sql);

                    $params = [
                        ':employee_id' => $employee_id,
                        ':designation' => $_POST['designation'],
                        ':office' => $_POST['office'],
                        ':period_from' => $_POST['period_from'],
                        ':period_to' => $_POST['period_to'],
                        ':wages' => $wages,
                        ':contribution' => $_POST['contribution'] ?? null,
                        ':ctc_number' => $_POST['ctc_number'] ?? null,
                        ':ctc_date' => $_POST['ctc_date'] ?? null,
                        ':profile_image_path' => $profile_image_path,
                        ':first_name' => $_POST['first_name'],
                        ':last_name' => $_POST['last_name'],
                        ':middle' => $_POST['middle'] ?? '',
                        ':full_name' => $full_name,
                        ':mobile_number' => $_POST['mobile_number'],
                        ':email_address' => $_POST['email_address'],
                        ':date_of_birth' => $_POST['date_of_birth'],
                        ':marital_status' => $_POST['marital_status'],
                        ':gender' => $_POST['gender'],
                        ':nationality' => $_POST['nationality'],
                        ':street_address' => $_POST['street_address'],
                        ':city' => $_POST['city'],
                        ':state_region' => $_POST['state_region'],
                        ':zip_code' => $_POST['zip_code'],
                        ':joining_date' => $_POST['joining_date'],
                        ':eligibility' => $_POST['eligibility'] ?? 'Not Eligible',
                        ':doc_id_path' => $doc_paths['doc_id'],
                        ':doc_resume_path' => $doc_paths['doc_resume'],
                        ':doc_service_path' => $doc_paths['doc_service'],
                        ':doc_appointment_path' => $doc_paths['doc_appointment'],
                        ':doc_transcript_path' => $doc_paths['doc_transcript'],
                        ':doc_eligibility_path' => $doc_paths['doc_eligibility'],
                        ':id' => $edit_id
                    ];

                    $stmt->execute($params);
                    $pdo->commit();
                    $success = "Employee record updated successfully!";
                    error_log("Employee updated successfully. ID: " . $edit_id);

                } else {
                    $full_name = trim($_POST['first_name'] . ' ' .
                        (!empty($_POST['middle']) ? substr($_POST['middle'], 0, 1) . '. ' : '') .
                        $_POST['last_name']);

                    $sql = "INSERT INTO contractofservice (
                    employee_id, designation, office, period_from, period_to, wages, contribution, 
                    ctc_number, ctc_date, full_name,
                    profile_image_path, first_name, last_name, middle, mobile_number, email_address, date_of_birth, 
                    marital_status, gender, nationality, street_address, 
                    city, state_region, zip_code, joining_date, eligibility, 
                    doc_id_path, doc_resume_path, doc_service_path, doc_appointment_path, doc_transcript_path, doc_eligibility_path,
                    status, created_at, updated_at
                ) VALUES (
                    :employee_id, :designation, :office, :period_from, :period_to, :wages, :contribution,
                    :ctc_number, :ctc_date, :full_name,
                    :profile_image_path, :first_name, :last_name, :middle, :mobile_number, :email_address, :date_of_birth, 
                    :marital_status, :gender, :nationality, :street_address, 
                    :city, :state_region, :zip_code, :joining_date, :eligibility, 
                    :doc_id_path, :doc_resume_path, :doc_service_path, :doc_appointment_path, :doc_transcript_path, :doc_eligibility_path,
                    'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )";

                    $stmt = $pdo->prepare($sql);

                    $params = [
                        ':employee_id' => $employee_id,
                        ':designation' => $_POST['designation'],
                        ':office' => $_POST['office'],
                        ':period_from' => $_POST['period_from'],
                        ':period_to' => $_POST['period_to'],
                        ':wages' => $wages,
                        ':contribution' => $_POST['contribution'] ?? null,
                        ':ctc_number' => $_POST['ctc_number'] ?? null,
                        ':ctc_date' => $_POST['ctc_date'] ?? null,
                        ':full_name' => $full_name,
                        ':profile_image_path' => $profile_image_path,
                        ':first_name' => $_POST['first_name'],
                        ':last_name' => $_POST['last_name'],
                        ':middle' => $_POST['middle'] ?? '',
                        ':mobile_number' => $_POST['mobile_number'],
                        ':email_address' => $_POST['email_address'],
                        ':date_of_birth' => $_POST['date_of_birth'],
                        ':marital_status' => $_POST['marital_status'],
                        ':gender' => $_POST['gender'],
                        ':nationality' => $_POST['nationality'],
                        ':street_address' => $_POST['street_address'],
                        ':city' => $_POST['city'],
                        ':state_region' => $_POST['state_region'],
                        ':zip_code' => $_POST['zip_code'],
                        ':joining_date' => $_POST['joining_date'],
                        ':eligibility' => $_POST['eligibility'] ?? 'Not Eligible',
                        ':doc_id_path' => $doc_paths['doc_id'],
                        ':doc_resume_path' => $doc_paths['doc_resume'],
                        ':doc_service_path' => $doc_paths['doc_service'],
                        ':doc_appointment_path' => $doc_paths['doc_appointment'],
                        ':doc_transcript_path' => $doc_paths['doc_transcript'],
                        ':doc_eligibility_path' => $doc_paths['doc_eligibility']
                    ];

                    $stmt->execute($params);
                    $pdo->commit();
                    $success = "New contractual employee created successfully! (ID: $employee_id)";
                    error_log("New employee created. ID: " . $employee_id);
                }

                // Redirect to prevent form resubmission
                header("Location: contractofservice.php");
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error: " . $e->getMessage();
                error_log("Form submission error: " . $e->getMessage());
            }
        } else {
            $error = "Please fix the following errors:<br>" . implode("<br>", $validation_errors);
            error_log("Validation errors: " . print_r($validation_errors, true));
        }
    }
}

// ===============================================
// DATA FETCHING LOGIC WITH PAGINATION
// ===============================================
try {
    // Get total number of records
    $status_condition = $view_inactive ? "status = 'inactive'" : "status = 'active'";
    $count_sql = "SELECT COUNT(*) FROM contractofservice WHERE $status_condition";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute();
    $total_records = $stmt->fetchColumn();

    // Calculate total pages
    $total_pages = ceil($total_records / $records_per_page);

    // Validate current page
    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
        header("Location: contractofservice.php?page=$current_page" . ($view_inactive ? "&view=inactive" : "") . "&per_page=$records_per_page");
        exit();
    }

    // Calculate offset
    $offset = ($current_page - 1) * $records_per_page;

    $sql = "SELECT 
        id, employee_id, first_name, last_name, middle, full_name, designation, office, 
        period_from, period_to, wages, contribution,
        email_address, mobile_number, status
    FROM contractofservice 
    WHERE $status_condition
    ORDER BY first_name ASC
    LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $employees = $stmt->fetchAll();

    // CONSTRUCT FULL NAME FOR EACH EMPLOYEE
    foreach ($employees as $key => $employee) {
        $first_name = isset($employee['first_name']) ? trim($employee['first_name']) : '';
        $last_name = isset($employee['last_name']) ? trim($employee['last_name']) : '';
        $middle = isset($employee['middle']) ? trim($employee['middle']) : '';

        // Build the full name
        $full_name_parts = [];

        if (!empty($first_name)) {
            $full_name_parts[] = $first_name;
        }

        if (!empty($middle)) {
            // Show middle initial with dot
            $full_name_parts[] = substr($middle, 0, 1) . '.';
        }

        if (!empty($last_name)) {
            $full_name_parts[] = $last_name;
        }

        // Format: First M. Last (e.g., Dexter C. Balanza)
        $full_name = !empty($full_name_parts) ? implode(' ', $full_name_parts) : 'No Name Provided';

        // ADD THE CONSTRUCTED FULL NAME TO THE EMPLOYEE ARRAY
        $employees[$key]['full_name'] = $full_name;

        // Also create an alternative format (Last, First M.) if needed
        $alt_name_parts = [];
        if (!empty($last_name)) {
            $alt_name_parts[] = $last_name . ',';
        }
        if (!empty($first_name)) {
            $alt_name_parts[] = $first_name;
        }
        if (!empty($middle)) {
            $alt_name_parts[] = substr($middle, 0, 1) . '.';
        }
        $employees[$key]['full_name_alt'] = !empty($alt_name_parts) ? implode(' ', $alt_name_parts) : 'No Name Provided';
    }

} catch (PDOException $e) {
    error_log("Query execution error: " . $e->getMessage());
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

    <style>
        /* ===============================================
       ROOT VARIABLES & BASE STYLES
    =============================================== */
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

        /* ===============================================
       NAVIGATION
    =============================================== */
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

        /* ===============================================
       SIDEBAR
    =============================================== */
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

        /* ===============================================
       MAIN CONTENT
    =============================================== */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ===============================================
       MODAL STYLES - FIXED VERSION
    =============================================== */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1099;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-backdrop.active {
            opacity: 1;
            visibility: visible;
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
            z-index: 1100;
            padding: 1rem;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-container.active {
            opacity: 1;
            visibility: visible;
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
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .modal-container.active .modal-content {
            transform: translateY(0);
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

        /* Employee Modal Specific */
        #employeeModal .bg-white {
            max-width: 1200px;
            max-height: 90vh;
            overflow: hidden;
        }

        /* View Employee Modal Specific */
        #viewEmployeeModal .bg-white {
            max-width: 900px;
            max-height: 90vh;
            overflow: hidden;
        }

        /* Confirmation Modals */
        #inactivateModal .bg-white,
        #activateModal .bg-white {
            max-width: 500px;
        }

        /* ===============================================
       FORM STYLES
    =============================================== */
        /* Form Steps */
        .form-step {
            display: none;
        }

        .form-step.active {
            display: block;
        }

        /* Form Grid */
        .form-grid-responsive {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .form-grid-responsive {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .form-grid-responsive {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* File Drop Zones */
        .file-drop-zone {
            border: 2px dashed #d1d5db;
            border-radius: 0.5rem;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f9fafb;
        }

        .file-drop-zone:hover {
            border-color: #3b82f6;
            background-color: #f0f9ff;
        }

        .file-drop-zone.border-green-500 {
            border-color: #10b981;
            background-color: #f0fdf4;
        }

        /* Validation Errors */
        .validation-error {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }

        .validation-error:not(.hidden) {
            display: block;
        }

        .input-error {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        /* ===============================================
       ACTION BUTTONS
    =============================================== */
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

        /* ===============================================
       STATUS BADGES
    =============================================== */
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

        /* ===============================================
       PAGINATION
    =============================================== */
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

        /* ===============================================
       VIEW EMPLOYEE MODAL STYLES
    =============================================== */
        .view-employee-modal {
            background: white;
            border-radius: 12px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            position: relative;
        }

        .modal-close-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: transparent;
            border: none;
            color: #6b7280;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 10;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .modal-close-btn:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .employee-header {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            padding: 2rem;
        }

        .employee-header-content {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .employee-photo-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
        }

        .employee-photo-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .employee-header-info {
            flex: 1;
        }

        .employee-main-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .employee-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .employee-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .meta-item i {
            font-size: 1rem;
        }

        .employee-body {
            padding: 2rem;
            max-height: 60vh;
            overflow-y: auto;
        }

        .edit-employee-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .edit-employee-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }

        /* Employee View Content */
        .employee-view-content .section-header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .employee-view-content .section-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }

        .employee-view-content .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .employee-view-content .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .employee-view-content .info-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .employee-view-content .info-value {
            font-size: 1rem;
            color: #1f2937;
            font-weight: 500;
        }

        .employee-view-content .document-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .employee-view-content .document-item {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s;
        }

        .employee-view-content .document-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .employee-view-content .document-icon {
            font-size: 2rem;
            color: #3b82f6;
            margin-bottom: 0.5rem;
        }

        .employee-view-content .document-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .employee-view-content .document-status {
            font-size: 0.875rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            display: inline-block;
        }

        .employee-view-content .document-status.uploaded {
            background: #d1fae5;
            color: #065f46;
        }

        .employee-view-content .document-status.missing {
            background: #fef3c7;
            color: #92400e;
        }

        .employee-view-content .salary-display {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
        }

        .employee-view-content .salary-label {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .employee-view-content .salary-figure {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
        }

        .employee-view-content .salary-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .employee-view-content .detail-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 1rem;
        }

        .employee-view-content .detail-label {
            font-size: 0.875rem;
            opacity: 0.8;
            margin-bottom: 0.25rem;
        }

        .employee-view-content .detail-value {
            font-size: 1.125rem;
            font-weight: 600;
        }

        .employee-view-content .eligibility-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
        }

        .employee-view-content .eligibility-badge.eligible {
            background: #d1fae5;
            color: #065f46;
        }

        .employee-view-content .eligibility-badge.not-eligible {
            background: #fef3c7;
            color: #92400e;
        }

        .employee-view-content .section-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 2rem 0;
        }

        /* ===============================================
       CONFIRMATION MODAL STYLES
    =============================================== */
        .inactivate-modal-content {
            text-align: center;
            padding: 2rem;
        }

        .inactivate-icon {
            width: 80px;
            height: 80px;
            background: #fef3c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .inactivate-icon i {
            font-size: 2.5rem;
            color: #f59e0b;
        }

        .inactivate-warning-list {
            text-align: left;
            background: #f9fafb;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .inactivate-warning-list ul {
            padding-left: 1.5rem;
        }

        .inactivate-warning-list li {
            margin-bottom: 0.5rem;
            color: #4b5563;
        }

        .inactivate-warning-list li:last-child {
            margin-bottom: 0;
        }

        /* ===============================================
       MOBILE RESPONSIVE STYLES
    =============================================== */
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

            .employee-header-content {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .employee-photo-large {
                width: 100px;
                height: 100px;
            }

            .employee-meta {
                justify-content: center;
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

            .employee-view-content .info-grid,
            .employee-view-content .document-grid,
            .employee-view-content .salary-details {
                grid-template-columns: 1fr;
            }

            .form-grid-responsive {
                grid-template-columns: 1fr;
            }

            .employee-view-content .section-header h2 {
                font-size: 1.25rem;
            }

            .employee-view-content .salary-figure {
                font-size: 2rem;
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

            .employee-header {
                padding: 1.5rem;
            }

            .employee-body {
                padding: 1.5rem;
            }

            .employee-main-title {
                font-size: 1.5rem;
            }

            .employee-subtitle {
                font-size: 1rem;
            }
        }

        /* ===============================================
       OVERLAY FOR MOBILE MENU
    =============================================== */
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

        /* ===============================================
       SCROLLBAR STYLING
    =============================================== */
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

        /* For modals */
        .modal-content::-webkit-scrollbar,
        .employee-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track,
        .employee-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb,
        .employee-body::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover,
        .employee-body::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* ===============================================
       ANIMATIONS & TRANSITIONS
    =============================================== */
        .fade-in {
            animation: fadeIn 0.3s ease;
        }

        .slide-in {
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* ===============================================
       UTILITY CLASSES
    =============================================== */
        .hidden {
            display: none !important;
        }

        .modal-open {
            overflow: hidden;
        }

        .cursor-pointer {
            cursor: pointer;
        }

        .transition-all {
            transition: all 0.3s ease;
        }

        .z-1100 {
            z-index: 1100;
        }

        .z-1099 {
            z-index: 1099;
        }

        /* ===============================================
       TABLE STYLES
    =============================================== */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            font-weight: 600;
            text-align: left;
            padding: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
        }

        table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }


        /* ===============================================
       FORM INPUT STYLES
    =============================================== */
        input,
        select,
        textarea {
            font-family: inherit;
            font-size: 1rem;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background-color: white;
            transition: all 0.2s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        input:disabled {
            background-color: #f3f4f6;
            cursor: not-allowed;
        }

        /* ===============================================
       BUTTON STYLES
    =============================================== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.375rem;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2563eb;
        }

        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #4b5563;
        }

        .btn-success {
            background-color: #10b981;
            color: white;
        }

        .btn-success:hover {
            background-color: #059669;
        }

        .btn-danger {
            background-color: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-warning {
            background-color: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background-color: #d97706;
        }

        /* Loading State */
        .btn-loading {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* ===============================================
       CARD STYLES
    =============================================== */
        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            padding: 1.5rem;
        }

        .card-header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
        }

        /* ===============================================
       ALERT STYLES
    =============================================== */
        .alert {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
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

        .alert-warning {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .alert-info {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        /* ===============================================
       LOADING SPINNER
    =============================================== */
        .spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* ===============================================
       TOGGLE SWITCH
    =============================================== */
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

        /* ===============================================
       BADGE STYLES
    =============================================== */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 9999px;
        }

        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-primary {
            background-color: #dbeafe;
            color: #1e40af;
        }

        /* Dropdown Menu in Sidebar */
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
            padding: 0.5rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .sidebar-dropdown-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-dropdown-item i {
            font-size: 0.75rem;
            margin-right: 0.5rem;
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 1rem;
        }

        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            min-width: 2.5rem;
            height: 2.5rem;
            border: 1px solid #d1d5db;
            background-color: white;
            color: #374151;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .pagination-btn:hover:not(:disabled) {
            background-color: #f3f4f6;
            border-color: #9ca3af;
            transform: translateY(-1px);
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

        .pagination-btn.pagination-ellipsis {
            border: none;
            background: none;
            cursor: default;
            min-width: auto;
        }

        .pagination-btn.pagination-ellipsis:hover {
            background: none;
            transform: none;
        }

        .pagination-info {
            font-size: 0.875rem;
            color: #6b7280;
            margin-right: 1rem;
        }

        .pagination-nav {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Mobile responsive pagination */
        @media (max-width: 768px) {
            .pagination-container {
                flex-direction: column;
                gap: 1rem;
            }

            .pagination-nav {
                flex-wrap: wrap;
                justify-content: center;
            }

            .pagination-info {
                margin-right: 0;
                text-align: center;
            }
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
                <a href="Employee.php" class="sidebar-item active">
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
                <div class="sidebar-dropdown-menu" id="payroll-dropdown">
                    <a href="../Payrollmanagement/contractualpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Contractual
                    </a>
                    <a href="../Payrollmanagement/joboerderpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Job Order
                    </a>
                    <a href="../Payrollmanagement/permanentpayrolltable1.php" class="sidebar-dropdown-item">
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
                <div class="text-center text-white/60 text-sm">
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
                    <!-- Success/Error Messages - Matching permanent.php style -->
                    <?php if (isset($success) && !empty($success)): ?>
                        <div class="mb-4 p-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                            <span class="font-medium">Success!</span> <?php echo htmlspecialchars($success); ?>
                        </div>
                        <script>
                            setTimeout(() => {
                                const element = document.querySelector('.bg-green-50');
                                if (element) element.remove();
                            }, 5000);
                        </script>
                    <?php endif; ?>

                    <?php if (isset($error) && !empty($error)): ?>
                        <div class="mb-4 p-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                            <span class="font-medium">Error:</span> <?php echo $error; ?>
                        </div>
                        <script>
                            setTimeout(() => {
                                const element = document.querySelector('.bg-red-50');
                                if (element) element.remove();
                            }, 5000);
                        </script>
                    <?php endif; ?>
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
                                    class="inline-flex items-center justify-center px-4 md:px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-all duration-200 hover:shadow-lg">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    <span>Back to Active</span>
                                </a>
                            <?php else: ?>
                                <!-- Records per page selector -->
                                <div
                                    class="flex items-center space-x-2 bg-white border border-gray-300 rounded-lg px-3 py-2.5 hover:border-blue-500 transition-colors">
                                    <span class="text-sm text-gray-600 whitespace-nowrap">Show:</span>
                                    <select id="recordsPerPage" onchange="changeRecordsPerPage(this.value)"
                                        class="bg-transparent border-none text-gray-900 text-sm focus:outline-none focus:ring-0 cursor-pointer appearance-none">
                                        <option value="5" <?php echo ($records_per_page == 5) ? 'selected' : ''; ?>>5</option>
                                        <option value="10" <?php echo ($records_per_page == 10) ? 'selected' : ''; ?>>10
                                        </option>
                                        <option value="25" <?php echo ($records_per_page == 25) ? 'selected' : ''; ?>>25
                                        </option>
                                        <option value="50" <?php echo ($records_per_page == 50) ? 'selected' : ''; ?>>50
                                        </option>
                                        <option value="100" <?php echo ($records_per_page == 100) ? 'selected' : ''; ?>>100
                                        </option>
                                    </select>
                                    <span class="text-sm text-gray-600 whitespace-nowrap">per page</span>
                                </div>

                                <!-- FIXED: Added onclick to open modal -->
                                <button type="button" onclick="openAddModal()"
                                    class="inline-flex items-center justify-center px-4 md:px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-all duration-200 hover:shadow-lg">
                                    <i class="fas fa-plus mr-2"></i>
                                    <span>Add New Contractual</span>
                                </button>

                                <a href="contractofservice.php?view=inactive"
                                    class="inline-flex items-center justify-center px-4 md:px-5 py-2.5 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition-all duration-200 hover:shadow-lg">
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
                                        class="px-6 md:px-8 py-6 md:py-6 text-left text-xs font-semibold text-white uppercase tracking-wider">
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
                                                    <?php echo htmlspecialchars($error_message); ?>
                                                </p>
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
                                                            <?php echo htmlspecialchars($employee['full_name'] ?? 'N/A'); ?>
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
                                                <?php echo htmlspecialchars($employee['office']); ?>
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
                        <div class="pagination-container">
                            <div class="pagination-info">
                                Showing
                                <span class="font-semibold text-gray-900">
                                    <?php echo ($total_records > 0) ? (($current_page - 1) * $records_per_page + 1) : 0; ?>-
                                    <?php echo min($total_records, $current_page * $records_per_page); ?>
                                </span>
                                of
                                <span
                                    class="font-semibold text-gray-900"><?php echo number_format($total_records); ?></span>
                                <?php if ($view_inactive): ?>
                                    <span class="text-yellow-600 ml-2">[Inactive Employees]</span>
                                <?php endif; ?>
                            </div>

                            <div class="pagination-nav">
                                <!-- First Page Button -->
                                <?php if ($current_page > 1): ?>
                                    <a href="contractofservice.php?page=1<?php echo $view_inactive ? '&view=inactive' : ''; ?>&per_page=<?php echo $records_per_page; ?>"
                                        class="pagination-btn" title="First Page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="pagination-btn" disabled>
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- Previous Button -->
                                <?php if ($current_page > 1): ?>
                                    <a href="contractofservice.php?page=<?php echo $current_page - 1; ?><?php echo $view_inactive ? '&view=inactive' : ''; ?>&per_page=<?php echo $records_per_page; ?>"
                                        class="pagination-btn" title="Previous">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="pagination-btn" disabled>
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php
                                // Smart page number display
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);

                                // Show first page with ellipsis if needed
                                if ($start_page > 1) {
                                    $view_param = $view_inactive ? "&view=inactive" : "";
                                    echo '<a href="contractofservice.php?page=1' . $view_param . '&per_page=' . $records_per_page . '" class="pagination-btn">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span class="pagination-btn pagination-ellipsis">...</span>';
                                    }
                                }

                                // Show page numbers
                                for ($i = $start_page; $i <= $end_page; $i++):
                                    $view_param = $view_inactive ? "&view=inactive" : "";
                                    ?>
                                    <a href="contractofservice.php?page=<?php echo $i; ?><?php echo $view_param; ?>&per_page=<?php echo $records_per_page; ?>"
                                        class="pagination-btn <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php
                                // Show last page with ellipsis if needed
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="pagination-btn pagination-ellipsis">...</span>';
                                    }
                                    $view_param = $view_inactive ? "&view=inactive" : "";
                                    echo '<a href="contractofservice.php?page=' . $total_pages . $view_param . '&per_page=' . $records_per_page . '" class="pagination-btn">' . $total_pages . '</a>';
                                }
                                ?>

                                <!-- Next Button -->
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="contractofservice.php?page=<?php echo $current_page + 1; ?><?php echo $view_inactive ? '&view=inactive' : ''; ?>&per_page=<?php echo $records_per_page; ?>"
                                        class="pagination-btn" title="Next">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="pagination-btn" disabled>
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- Last Page Button -->
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="contractofservice.php?page=<?php echo $total_pages; ?><?php echo $view_inactive ? '&view=inactive' : ''; ?>&per_page=<?php echo $records_per_page; ?>"
                                        class="pagination-btn" title="Last Page">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="pagination-btn" disabled>
                                        <i class="fas fa-angle-double-right"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Backdrop -->
    <div id="modalBackdrop" class="modal-backdrop"></div>

    <!-- Add/Edit Employee Modal -->
    <div id="employeeModal" class="modal-container">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl max-h-[90vh] overflow-hidden mx-2">
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
                        class="step-nav flex-1 py-2 px-2 md:px-4 text-center font-medium border-b-2 border-transparent text-gray-500 text-sm md:text-base"
                        data-step="2">Personal</button>
                    <button type="button"
                        class="step-nav flex-1 py-2 px-2 md:px-4 text-center font-medium border-b-2 border-transparent text-gray-500 text-sm md:text-base"
                        data-step="3">Documents</button>
                </div>

                <!-- Multi-Step Form -->
                <form id="employeeForm" action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="employee_id" id="employeeId" value="">

                    <!-- Employee ID Field -->
                    <!-- Employee ID Field -->
                    <div>
                        <label for="employee_id" class="block mb-2 text-sm font-medium text-gray-900">
                            Employee ID * <span class="text-xs text-gray-500 ml-2">(Format: COS-YEAR-XXXX)</span>
                        </label>
                        <div class="relative">
                            <input type="text" name="employee_id" id="employee_id"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                placeholder="Enter Employee ID (e.g., COS-2024-0001)" required
                                pattern="^[A-Za-z0-9\-_]+$"
                                title="Employee ID can contain letters, numbers, hyphens, and underscores">
                            <div class="mt-1 text-xs text-gray-500">
                                Format: COS-YEAR-XXXX (example: COS-2024-0001) or use your own format
                            </div>
                        </div>
                        <div class="error-message" id="employee_id_error"></div>
                    </div>

                    <!-- Step 1: Professional Information -->
                    <div id="step1" class="form-step active">
                        <h2 class="text-base md:text-lg font-semibold text-gray-800 mb-3 md:mb-4">Professional Details
                        </h2>

                        <div class="grid gap-3 md:gap-4 mb-4">
                            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="first_name" class="block mb-2 text-sm font-medium text-gray-900">First
                                        Name *</label>
                                    <input type="text" name="first_name" id="first_name"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                </div>
                                <div>
                                    <label for="last_name" class="block mb-2 text-sm font-medium text-gray-900">Last
                                        Name *</label>
                                    <input type="text" name="last_name" id="last_name"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                </div>
                                <div>
                                    <label for="middle" class="block mb-2 text-sm font-medium text-gray-900">Middle
                                        Initial *</label>
                                    <input type="text" name="middle" id="middle"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="period_from" class="block mb-2 text-sm font-medium text-gray-900">Period
                                        From *</label>
                                    <input type="date" name="period_from" id="period_from"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                </div>
                                <div>
                                    <label for="period_to" class="block mb-2 text-sm font-medium text-gray-900">Period
                                        To *</label>
                                    <input type="date" name="period_to" id="period_to"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="wages" class="block mb-2 text-sm font-medium text-gray-900">Wages (per
                                        period) *</label>
                                    <input type="number" name="wages" id="wages" step="0.01" min="0"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="0.00" required>
                                    <div class="validation-error hidden">Enter a valid amount</div>
                                </div>
                                <div>
                                    <label for="contribution"
                                        class="block mb-2 text-sm font-medium text-gray-900">Contribution</label>
                                    <input type="text" name="contribution" id="contribution"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="SSS, PhilHealth, etc.">
                                </div>
                            </div>
                            <!-- Add this right after the wages and contribution fields in Step 1 -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="ctc_number" class="block mb-2 text-sm font-medium text-gray-900">CTC
                                        Number *</label>
                                    <input type="text" name="ctc_number" id="ctc_number"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="Enter CTC Number" required>
                                    <div class="validation-error hidden">CTC Number is required</div>
                                </div>
                                <div>
                                    <label for="ctc_date" class="block mb-2 text-sm font-medium text-gray-900">CTC Date
                                        *</label>
                                    <input type="date" name="ctc_date" id="ctc_date"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                    <div class="validation-error hidden">CTC Date is required</div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="button"
                                class="next-step text-white bg-blue-700 hover:bg-blue-800 font-medium rounded-lg text-sm px-4 py-2.5 md:px-5"
                                data-next="2">
                                Next <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Personal Information -->
                    <div id="step2" class="form-step">
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
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="mobile_number"
                                        class="block mb-2 text-sm font-medium text-gray-900">Mobile Number *</label>
                                    <input type="text" name="mobile_number" id="mobile_number"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="09123456789" pattern="[0-9]{11}" required>
                                    <div class="validation-error hidden">Mobile number must be exactly 11 digits</div>
                                </div>
                                <div>
                                    <label for="email_address"
                                        class="block mb-2 text-sm font-medium text-gray-900">Email Address *</label>
                                    <input type="email" name="email_address" id="email_address"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="juan.delacruz@email.com" required>
                                    <div class="validation-error hidden">Please enter a valid email address</div>
                                </div>
                                <div>
                                    <label for="date_of_birth" class="block mb-2 text-sm font-medium text-gray-900">Date
                                        of Birth *</label>
                                    <input type="date" name="date_of_birth" id="date_of_birth"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                    <div class="validation-error hidden">Date of birth must be in the past</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                                        value="Filipino" required>
                                    <div class="validation-error hidden">Please enter a valid nationality</div>
                                </div>
                            </div>

                            <div>
                                <label for="street_address" class="block mb-2 text-sm font-medium text-gray-900">Street
                                    Address *</label>
                                <input type="text" name="street_address" id="street_address"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    placeholder="123 Main Street, Barangay Poblacion" required>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="city" class="block mb-2 text-sm font-medium text-gray-900">City
                                        *</label>
                                    <input type="text" name="city" id="city"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="Paluan" required>
                                    <div class="validation-error hidden">Please enter a valid city name</div>
                                </div>
                                <div>
                                    <label for="state_region"
                                        class="block mb-2 text-sm font-medium text-gray-900">State/Region *</label>
                                    <input type="text" name="state_region" id="state_region"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        value="Occidental Mindoro" required>
                                    <div class="validation-error hidden">Please enter a valid state/region</div>
                                </div>
                                <div>
                                    <label for="zip_code" class="block mb-2 text-sm font-medium text-gray-900">ZIP Code
                                        *</label>
                                    <input type="text" name="zip_code" id="zip_code"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="5104" pattern="[0-9]{4}" required>
                                    <div class="validation-error hidden">ZIP code must be exactly 4 digits</div>
                                </div>
                            </div>

                            <!-- REMOVED PASSWORD FIELDS -->

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="joining_date"
                                        class="block mb-2 text-sm font-medium text-gray-900">Joining Date *</label>
                                    <input type="date" name="joining_date" id="joining_date"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
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
                                            <input type="radio" name="eligibility" value="Not Eligible" checked
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500">
                                            <span class="ml-2 text-sm font-medium text-gray-900">Not Eligible</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between">
                            <button type="button"
                                class="prev-step text-gray-900 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-4 py-2.5 md:px-5">
                                <i class="fas fa-arrow-left mr-2"></i>Previous
                            </button>
                            <button type="button"
                                class="next-step text-white bg-blue-700 hover:bg-blue-800 font-medium rounded-lg text-sm px-4 py-2.5 md:px-5"
                                data-next="3">
                                Next <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Documents -->
                    <div id="step3" class="form-step">
                        <h2 class="text-base md:text-lg font-semibold text-gray-800 mb-3 md:mb-4">Documents</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 mb-4 md:mb-6">
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

                        <div class="flex justify-between">
                            <button type="button"
                                class="prev-step text-gray-900 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-4 py-2.5 md:px-5">
                                <i class="fas fa-arrow-left mr-2"></i>Previous
                            </button>
                            <button type="submit"
                                class="text-white bg-green-600 hover:bg-green-700 font-medium rounded-lg text-sm px-4 py-2.5 md:px-5"
                                id="submitBtn">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span id="submitButtonText">Submit Employee</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Employee Modal -->
    <div id="viewEmployeeModal" class="modal-container">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden mx-2">
            <div class="flex justify-end p-3">
                <button type="button" onclick="closeViewModal()"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-3 md:p-5 overflow-y-auto max-h-[calc(90vh-80px)]">
                <div id="viewEmployeeContent">
                    <!-- Content will be loaded via AJAX -->
                </div>

                <div class="mt-6 text-center">
                    <button onclick="editCurrentEmployee()"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                        <i class="fas fa-edit mr-2"></i>Edit Employee Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Inactivate Confirmation Modal -->
    <div id="inactivateModal" class="modal-container">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-md mx-2">
            <div class="text-center">
                <div class="mx-auto w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-user-slash text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2" id="inactivateEmployeeName"></h3>
                <p class="text-gray-600 mb-4">Are you sure you want to mark this employee as inactive?</p>

                <div class="text-left bg-yellow-50 p-4 rounded-lg mb-6">
                    <ul class="list-disc pl-5 text-sm text-gray-700">
                        <li>Employee will be moved to inactive status</li>
                        <li>Can be reactivated later if needed</li>
                        <li>No data will be deleted</li>
                    </ul>
                </div>

                <div class="flex justify-center space-x-4">
                    <button type="button" onclick="closeInactivateModal()"
                        class="px-6 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100">
                        Cancel
                    </button>
                    <button type="button" id="confirmInactivateBtn"
                        class="px-6 py-2 text-sm font-medium text-white bg-yellow-600 rounded-lg hover:bg-yellow-700">
                        Mark as Inactive
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Activate Confirmation Modal -->
    <div id="activateModal" class="modal-container">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-md mx-2">
            <div class="text-center">
                <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-user-check text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2" id="activateEmployeeName"></h3>
                <p class="text-gray-600 mb-4">Are you sure you want to activate this employee?</p>

                <div class="text-left bg-green-50 p-4 rounded-lg mb-6">
                    <ul class="list-disc pl-5 text-sm text-gray-700">
                        <li>Employee will be moved to active status</li>
                        <li>Will appear in active employee list</li>
                        <li>Can access the system again</li>
                    </ul>
                </div>

                <div class="flex justify-center space-x-4">
                    <button type="button" onclick="closeActivateModal()"
                        class="px-6 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100">
                        Cancel
                    </button>
                    <button type="button" id="confirmActivateBtn"
                        class="px-6 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                        Activate Employee
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <script>

    </script>
    <script>
        // ===============================================
        // SIDEBAR FUNCTIONALITY
        // ===============================================
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarContainer = document.getElementById('sidebar-container');
        const overlay = document.getElementById('overlay');

        if (sidebarToggle && sidebarContainer && overlay) {
            sidebarToggle.addEventListener('click', function () {
                sidebarContainer.classList.toggle('active');
                overlay.classList.toggle('active');
            });

            overlay.addEventListener('click', function () {
                sidebarContainer.classList.remove('active');
                overlay.classList.remove('active');
            });
        }

        // Payroll dropdown functionality
        const payrollToggle = document.getElementById('payroll-toggle');
        const payrollDropdown = document.getElementById('payroll-dropdown');

        if (payrollToggle && payrollDropdown) {
            payrollToggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                payrollDropdown.classList.toggle('open');
                const chevron = this.querySelector('.chevron');
                if (chevron) {
                    chevron.classList.toggle('rotated');
                }
            });
        }

        // ===============================================
        // MODAL FUNCTIONS
        // ===============================================
        let currentStep = 1;
        let isEditMode = false;
        let inactivateEmployeeId = null;
        let activateEmployeeId = null;
        let currentViewEmployeeId = null;

        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            const backdrop = document.getElementById('modalBackdrop');

            if (modal && backdrop) {
                document.querySelectorAll('.modal-container').forEach(m => {
                    m.classList.remove('active');
                });

                backdrop.classList.add('active');

                setTimeout(() => {
                    modal.classList.add('active');
                    document.body.classList.add('modal-open');
                    document.body.style.overflow = 'hidden';
                }, 10);
            }
        }

        function hideModal(modalId) {
            const modal = document.getElementById(modalId);
            const backdrop = document.getElementById('modalBackdrop');

            if (modal && backdrop) {
                modal.classList.remove('active');

                setTimeout(() => {
                    const anyModalOpen = Array.from(document.querySelectorAll('.modal-container'))
                        .some(m => m.classList.contains('active'));

                    if (!anyModalOpen) {
                        backdrop.classList.remove('active');
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow = 'auto';
                    }
                }, 300);
            }
        }

        // ===============================================
        // ADD EMPLOYEE MODAL
        // ===============================================
        function openAddModal() {
            isEditMode = false;
            document.getElementById('modalTitle').textContent = 'Add New Contractual Employee';
            document.getElementById('formAction').value = 'add';
            document.getElementById('employeeId').value = '';
            document.getElementById('submitButtonText').textContent = 'Submit Employee';

            const form = document.getElementById('employeeForm');
            if (form) {
                form.action = 'contractofservice.php';
                form.reset();
            }

            resetFormToStep1();

            // Reset file drop zones
            document.querySelectorAll('.file-drop-zone').forEach(zone => {
                const fileStatus = zone.querySelector('.file-status');
                if (fileStatus) fileStatus.textContent = '';
                zone.classList.remove('border-green-500', 'bg-green-50');
                zone.classList.add('border-gray-300', 'bg-gray-50');
            });

            // Reset profile image
            const profileImageContainer = document.getElementById('profileImageContainer');
            if (profileImageContainer) {
                profileImageContainer.innerHTML = '<i class="fas fa-user text-gray-400 text-3xl md:text-4xl"></i>';
            }

            // Set default dates
            const today = new Date().toISOString().split('T')[0];
            const nextMonth = new Date();
            nextMonth.setMonth(nextMonth.getMonth() + 1);
            const nextMonthStr = nextMonth.toISOString().split('T')[0];

            if (document.getElementById('period_from')) document.getElementById('period_from').value = today;
            if (document.getElementById('period_to')) document.getElementById('period_to').value = nextMonthStr;
            if (document.getElementById('joining_date')) document.getElementById('joining_date').value = today;
            if (document.getElementById('date_of_birth')) document.getElementById('date_of_birth').value = '1990-01-01';
            if (document.getElementById('employee_id')) document.getElementById('employee_id').value = '';
            if (document.getElementById('nationality')) document.getElementById('nationality').value = 'Filipino';
            if (document.getElementById('state_region')) document.getElementById('state_region').value = 'Occidental Mindoro';
            if (document.getElementById('city')) document.getElementById('city').value = 'Paluan';

            showModal('employeeModal');
        }

        // ===============================================
        // EDIT EMPLOYEE FUNCTIONS
        // ===============================================
        function editEmployee(employeeId) {
            if (!employeeId) {
                alert('Invalid employee ID');
                return;
            }

            showModal('employeeModal');

            document.getElementById('modalTitle').innerHTML = 'Edit Contractual Employee <span class="ml-2 text-sm font-normal text-gray-500"><i class="fas fa-spinner fa-spin"></i> Loading...</span>';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('employeeId').value = employeeId;
            document.getElementById('submitButtonText').textContent = 'Update Employee';

            const form = document.getElementById('employeeForm');
            if (form) {
                form.action = 'contractofservice.php';
                form.method = 'POST';
                form.classList.add('opacity-50', 'pointer-events-none');
            }

            resetFormToStep1();

            fetch(`contractofservice.php?edit_fetch_id=${employeeId}&t=${Date.now()}`)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (form) form.classList.remove('opacity-50', 'pointer-events-none');
                    document.getElementById('modalTitle').innerHTML = 'Edit Contractual Employee';

                    if (data.success) {
                        openEditModal(data.employee);
                    } else {
                        alert('Error loading employee data: ' + (data.error || 'Unknown error'));
                        closeModal();
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    if (form) form.classList.remove('opacity-50', 'pointer-events-none');
                    document.getElementById('modalTitle').innerHTML = 'Edit Contractual Employee';
                    alert('Error loading employee data. Please try again.');
                    closeModal();
                });
        }

        function openEditModal(employeeData) {
            isEditMode = true;

            // Set hidden ID field
            const employeeIdHidden = document.getElementById('employeeId');
            if (employeeIdHidden) {
                employeeIdHidden.value = employeeData.id;
                console.log('Set employeeId to:', employeeData.id);
            }

            // Set backup hidden field
            let idField = document.getElementById('hidden_id_field');
            if (!idField) {
                idField = document.createElement('input');
                idField.type = 'hidden';
                idField.name = 'id';
                idField.id = 'hidden_id_field';
                document.getElementById('employeeForm').appendChild(idField);
            }
            idField.value = employeeData.id;

            // Set all form fields
            setFieldValue('employee_id', employeeData.employee_id);
            setFieldValue('designation', employeeData.designation);
            setSelectValue('office', employeeData.office);
            setFieldValue('period_from', employeeData.period_from);
            setFieldValue('period_to', employeeData.period_to);
            setFieldValue('wages', employeeData.wages);
            setFieldValue('ctc_number', employeeData.ctc_number);
            setFieldValue('ctc_date', employeeData.ctc_date);
            setFieldValue('contribution', employeeData.contribution);
            setFieldValue('first_name', employeeData.first_name);
            setFieldValue('last_name', employeeData.last_name);
            setFieldValue('middle', employeeData.middle);
            setFieldValue('mobile_number', employeeData.mobile_number);
            setFieldValue('email_address', employeeData.email_address);
            setFieldValue('date_of_birth', employeeData.date_of_birth);
            setSelectValue('marital_status', employeeData.marital_status);
            setSelectValue('gender', employeeData.gender);
            setFieldValue('nationality', employeeData.nationality || 'Filipino');
            setFieldValue('street_address', employeeData.street_address);
            setFieldValue('city', employeeData.city || 'Paluan');
            setFieldValue('state_region', employeeData.state_region || 'Occidental Mindoro');
            setFieldValue('zip_code', employeeData.zip_code);
            setFieldValue('joining_date', employeeData.joining_date);

            // Eligibility
            const eligibility = employeeData.eligibility || 'Not Eligible';
            document.querySelectorAll('input[name="eligibility"]').forEach(radio => {
                radio.checked = (radio.value === eligibility);
            });

            // Profile image
            const profileImageContainer = document.getElementById('profileImageContainer');
            if (profileImageContainer) {
                if (employeeData.profile_image_path && employeeData.profile_image_path !== 'NULL' && employeeData.profile_image_path !== '') {
                    profileImageContainer.innerHTML = `<img src="${employeeData.profile_image_path}" class="w-full h-full object-cover rounded-full" alt="Profile Image">`;
                    setFieldValue('current_profile_image', employeeData.profile_image_path);
                } else {
                    profileImageContainer.innerHTML = '<i class="fas fa-user text-gray-400 text-3xl md:text-4xl"></i>';
                    setFieldValue('current_profile_image', '');
                }
            }

            // Documents
            const fileFields = [
                { id: 'doc_id', field: 'doc_id_path' },
                { id: 'doc_resume', field: 'doc_resume_path' },
                { id: 'doc_service', field: 'doc_service_path' },
                { id: 'doc_appointment', field: 'doc_appointment_path' },
                { id: 'doc_transcript', field: 'doc_transcript_path' },
                { id: 'doc_eligibility', field: 'doc_eligibility_path' }
            ];

            fileFields.forEach(fileField => {
                const zone = document.querySelector(`[data-file-input="${fileField.id}"]`);
                if (zone) {
                    const fileStatus = zone.querySelector('.file-status');
                    if (employeeData[fileField.field] && employeeData[fileField.field] !== 'NULL' && employeeData[fileField.field] !== '') {
                        const fileName = employeeData[fileField.field].split('/').pop();
                        if (fileStatus) fileStatus.textContent = `Current: ${fileName.substring(0, 20)}${fileName.length > 20 ? '...' : ''}`;
                        zone.classList.add('border-green-500', 'bg-green-50');
                        zone.classList.remove('border-gray-300', 'bg-gray-50');
                    } else {
                        if (fileStatus) fileStatus.textContent = '';
                        zone.classList.remove('border-green-500', 'bg-green-50');
                        zone.classList.add('border-gray-300', 'bg-gray-50');
                    }
                }
            });

            resetFormToStep1();
        }

        // Helper functions for setting form values
        function setFieldValue(id, value) {
            const element = document.getElementById(id);
            if (element) element.value = value || '';
        }

        function setSelectValue(id, value) {
            const element = document.getElementById(id);
            if (element && value) {
                for (let i = 0; i < element.options.length; i++) {
                    if (element.options[i].value === value) {
                        element.selectedIndex = i;
                        break;
                    }
                }
            }
        }

        // ===============================================
        // VIEW EMPLOYEE FUNCTIONS
        // ===============================================
        function viewEmployee(employeeId) {
            currentViewEmployeeId = employeeId;

            const content = document.getElementById('viewEmployeeContent');
            if (content) {
                content.innerHTML = `
            <div class="flex justify-center items-center h-64">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                <p class="ml-3 text-gray-600">Loading employee data...</p>
            </div>
        `;
            }

            showModal('viewEmployeeModal');

            fetch(`contractofservice.php?view_id=${employeeId}`)
                .then(response => response.json())
                .then(data => {
                    if (content) {
                        if (data.success) {
                            content.innerHTML = data.html;
                        } else {
                            content.innerHTML = `
                        <div class="text-center p-8">
                            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                            <p class="text-red-600 font-medium">Error loading employee data</p>
                            <p class="text-gray-500 text-sm mt-2">${data.error || 'Unknown error'}</p>
                        </div>
                    `;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (content) {
                        content.innerHTML = `
                    <div class="text-center p-8">
                        <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                        <p class="text-red-600 font-medium">Error loading employee data</p>
                        <p class="text-gray-500 text-sm mt-2">Network error. Please try again.</p>
                    </div>
                `;
                    }
                });
        }

        function editCurrentEmployee() {
            if (currentViewEmployeeId) {
                closeViewModal();
                setTimeout(() => editEmployee(currentViewEmployeeId), 300);
            }
        }

        // ===============================================
        // CONFIRMATION MODALS
        // ===============================================
        function confirmInactivate(employeeId, employeeName) {
            inactivateEmployeeId = employeeId;
            const nameElement = document.getElementById('inactivateEmployeeName');
            if (nameElement) nameElement.textContent = `"${employeeName}"`;
            showModal('inactivateModal');
        }

        function confirmActivate(employeeId, employeeName) {
            activateEmployeeId = employeeId;
            const nameElement = document.getElementById('activateEmployeeName');
            if (nameElement) nameElement.textContent = `"${employeeName}"`;
            showModal('activateModal');
        }

        // ===============================================
        // CLOSE MODAL FUNCTIONS
        // ===============================================
        function closeModal() { hideModal('employeeModal'); }
        function closeViewModal() {
            hideModal('viewEmployeeModal');
            currentViewEmployeeId = null;
        }
        function closeInactivateModal() {
            hideModal('inactivateModal');
            inactivateEmployeeId = null;
        }
        function closeActivateModal() {
            hideModal('activateModal');
            activateEmployeeId = null;
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
                step.classList.remove('active');
            });
            const stepElement = document.getElementById(`step${stepIndex}`);
            if (stepElement) stepElement.classList.add('active');
        }

        function updateStepNavigation() {
            document.querySelectorAll('.step-nav').forEach(nav => {
                const step = parseInt(nav.getAttribute('data-step'));
                nav.classList.remove('border-blue-600', 'text-blue-600', 'border-transparent', 'text-gray-500');
                nav.classList.add(step === currentStep ? 'border-blue-600' : 'border-transparent',
                    step === currentStep ? 'text-blue-600' : 'text-gray-500');
            });
        }

        function validateFormStep(step) {
            let isValid = true;
            const stepElement = document.getElementById(`step${step}`);
            if (!stepElement) return true;

            const inputs = stepElement.querySelectorAll('input[required], select[required]');
            inputs.forEach(input => {
                input.classList.remove('border-red-500');
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('border-red-500');
                }
            });

            // Additional validation for CTC date (step 1)
            if (step === 1) {
                const ctcDate = document.getElementById('ctc_date');
                const ctcNumber = document.getElementById('ctc_number');

                // CTC Number validation (alphanumeric with hyphens allowed)
                if (ctcNumber && ctcNumber.value.trim()) {
                    const ctcRegex = /^[A-Za-z0-9\-]+$/;
                    if (!ctcRegex.test(ctcNumber.value.trim())) {
                        isValid = false;
                        ctcNumber.classList.add('border-red-500');
                    }
                }

                // CTC Date validation (not in future)
                if (ctcDate && ctcDate.value) {
                    const today = new Date().toISOString().split('T')[0];
                    if (ctcDate.value > today) {
                        isValid = false;
                        ctcDate.classList.add('border-red-500');
                    }
                }
            }

            return isValid;
        }

        // ===============================================
        // FORM SUBMISSION - FIXED
        // ===============================================
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('employeeForm');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    // Validate all steps
                    let allValid = true;
                    for (let i = 1; i <= 3; i++) {
                        if (!validateFormStep(i)) {
                            allValid = false;
                            currentStep = i;
                            showStep(currentStep);
                            updateStepNavigation();
                            break;
                        }
                    }

                    if (allValid) {
                        const submitBtn = document.getElementById('submitBtn');
                        if (submitBtn) {
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
                            submitBtn.disabled = true;
                        }

                        // Log form data for debugging
                        console.log('Submitting form...');
                        console.log('Action:', document.getElementById('formAction').value);
                        console.log('Employee ID:', document.getElementById('employeeId').value);

                        form.submit();
                    }
                });
            }

            // Step navigation
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

            // Next/Previous buttons
            document.querySelectorAll('.next-step').forEach(btn => {
                btn.addEventListener('click', function () {
                    const nextStep = parseInt(this.getAttribute('data-next'));
                    if (validateFormStep(currentStep)) {
                        currentStep = nextStep;
                        showStep(currentStep);
                        updateStepNavigation();
                    }
                });
            });

            document.querySelectorAll('.prev-step').forEach(btn => {
                btn.addEventListener('click', function () {
                    currentStep--;
                    showStep(currentStep);
                    updateStepNavigation();
                });
            });

            // File upload zones
            document.querySelectorAll('.file-drop-zone').forEach(zone => {
                const fileInput = zone.querySelector('input[type="file"]');
                const fileStatus = zone.querySelector('.file-status');

                if (fileInput && fileStatus) {
                    zone.addEventListener('click', () => fileInput.click());

                    fileInput.addEventListener('change', function () {
                        if (this.files.length > 0) {
                            const fileName = this.files[0].name;
                            const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2);

                            if (fileSize > 10) {
                                alert('File size must be less than 10MB');
                                this.value = '';
                                fileStatus.textContent = '';
                                return;
                            }

                            fileStatus.textContent = `Selected: ${fileName.substring(0, 20)}${fileName.length > 20 ? '...' : ''}`;
                            zone.classList.add('border-green-500', 'bg-green-50');
                            zone.classList.remove('border-gray-300', 'bg-gray-50');
                        }
                    });
                }
            });

            // Profile image upload
            const profileImageInput = document.getElementById('profile_image');
            const profileImageContainer = document.getElementById('profileImageContainer');

            if (profileImageInput && profileImageContainer) {
                profileImageInput.addEventListener('change', function () {
                    if (this.files && this.files[0]) {
                        const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2);
                        if (fileSize > 5) {
                            alert('Profile image size must be less than 5MB');
                            this.value = '';
                            return;
                        }

                        const reader = new FileReader();
                        reader.onload = (e) => {
                            profileImageContainer.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover rounded-full" alt="Profile Image">`;
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }

            // Confirmation buttons
            const confirmInactivateBtn = document.getElementById('confirmInactivateBtn');
            if (confirmInactivateBtn) {
                confirmInactivateBtn.addEventListener('click', function () {
                    if (inactivateEmployeeId) {
                        window.location.href = `contractofservice.php?inactivate_id=${inactivateEmployeeId}`;
                    }
                });
            }

            const confirmActivateBtn = document.getElementById('confirmActivateBtn');
            if (confirmActivateBtn) {
                confirmActivateBtn.addEventListener('click', function () {
                    if (activateEmployeeId) {
                        window.location.href = `contractofservice.php?activate_id=${activateEmployeeId}`;
                    }
                });
            }

            // Close modals on backdrop click
            const backdrop = document.getElementById('modalBackdrop');
            if (backdrop) {
                backdrop.addEventListener('click', function () {
                    closeModal();
                    closeViewModal();
                    closeInactivateModal();
                    closeActivateModal();
                });
            }

            // Search functionality
            const searchInput = document.getElementById('search-employee');
            if (searchInput) {
                searchInput.addEventListener('input', searchEmployees);
            }
        });

        // ===============================================
        // PAGINATION FUNCTIONS
        // ===============================================
        function goToPage(page) {
            const viewParam = <?php echo isset($view_inactive) && $view_inactive ? "'&view=inactive'" : "''"; ?>;
            window.location.href = `contractofservice.php?page=${page}${viewParam}&per_page=<?php echo $records_per_page ?? 10; ?>`;
        }

        function changeRecordsPerPage(perPage) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('per_page', perPage);
            currentUrl.searchParams.set('page', 1);
            window.location.href = currentUrl.toString();
        }

        // ===============================================
        // SEARCH FUNCTIONALITY
        // ===============================================
        function searchEmployees() {
            const searchTerm = document.getElementById('search-employee').value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                if (row.querySelector('td[colspan]')) return;

                let shouldShow = false;
                row.querySelectorAll('td, th').forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(searchTerm)) {
                        shouldShow = true;
                    }
                });
                row.style.display = shouldShow ? '' : 'none';
            });
        }

        // ===============================================
        // DATE & TIME FUNCTIONALITY
        // ===============================================
        function updateDateTime() {
            try {
                const now = new Date();
                const dateString = now.toLocaleDateString('en-US', {
                    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                });

                let hours = now.getHours();
                let minutes = now.getMinutes();
                let seconds = now.getSeconds();
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12 || 12;

                const timeString = `${hours}:${minutes < 10 ? '0' + minutes : minutes}:${seconds < 10 ? '0' + seconds : seconds} ${ampm}`;

                const dateElement = document.getElementById('current-date');
                const timeElement = document.getElementById('current-time');
                if (dateElement) dateElement.textContent = dateString;
                if (timeElement) timeElement.textContent = timeString;
            } catch (error) {
                console.error('Error updating date/time:', error);
            }
        }

        // Initialize date/time
        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>
</body>

</html>