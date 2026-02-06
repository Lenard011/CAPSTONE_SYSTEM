<?php

/**
 * PHP Script: Job_order.php
 * Handles the form submission for adding a new job order employee,
 * including validation, file uploads, and database insertion.
 * It also includes logic to fetch and display employee data.
 * 
 * Database: hrms_paluan
 * Table: job_order
 */

// ===============================================
// 1. CONFIGURATION AND PDO CONNECTION SETUP
// ===============================================

// Start session for notifications
session_start();

// Define database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hrms_paluan');

// Get the current directory and set upload path correctly
$current_dir = dirname(__FILE__);
$upload_dir = $current_dir . '/uploads/job_order_documents/';

// Ensure the upload directory exists
if (!file_exists($upload_dir)) {
  mkdir($upload_dir, 0755, true);
}

// Initialize variables
$success_message = '';
$error_message = '';
$employees = [];
$counter = 1;
$current_file = basename($_SERVER['PHP_SELF']);

// Initialize user variables for navigation
$user_name = "Administrator";
$user_email = "admin@example.com";

// Initialize PDO connection with better error handling
try {
  $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];
  $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
} catch (PDOException $e) {
  $error_message = "Database Connection Error: " . $e->getMessage();
  error_log("Database connection error: " . $e->getMessage());
}

// ===============================================
// 2. HELPER FUNCTIONS
// ===============================================

function uploadFile($file_input_name, $destination_dir, $file_prefix, $existing_file = null)
{
  if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] != 0) {
    return $existing_file; // Return existing file if no new upload
  }

  $file = $_FILES[$file_input_name];

  // Check if file was uploaded without errors
  if ($file['error'] != UPLOAD_ERR_OK) {
    $_SESSION['error'] = "Error uploading file '{$file_input_name}'. Error code: " . $file['error'];
    return $existing_file;
  }

  // Check file size (limit to 5MB)
  if ($file['size'] > 5 * 1024 * 1024) {
    $_SESSION['error'] = "File '{$file_input_name}' is too large. Maximum size is 5MB.";
    return $existing_file;
  }

  $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  $allowed_types = ['jpeg', 'jpg', 'pdf', 'png'];

  if (!in_array($file_extension, $allowed_types)) {
    $_SESSION['error'] = "Invalid file type for '{$file_input_name}'. Allowed: JPG, JPEG, PNG, PDF.";
    return $existing_file;
  }

  // Generate a unique file name
  $new_file_name = $file_prefix . '_' . time() . '_' . uniqid() . '.' . $file_extension;
  $destination = $destination_dir . $new_file_name;

  // Ensure directory exists
  if (!is_dir($destination_dir)) {
    mkdir($destination_dir, 0755, true);
  }

  // Delete existing file if it exists
  if ($existing_file && file_exists($destination_dir . $existing_file)) {
    @unlink($destination_dir . $existing_file);
  }

  if (move_uploaded_file($file['tmp_name'], $destination)) {
    return $new_file_name;
  }

  $_SESSION['error'] = "Failed to upload file '{$file_input_name}'. Please try again.";
  return $existing_file;
}

// ===============================================
// 3. FORM SUBMISSION AND DATABASE OPERATIONS
// ===============================================

// 3.1. ADD NEW EMPLOYEE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_submit'])) {
  // Clear any previous messages
  unset($_SESSION['success']);
  unset($_SESSION['error']);

  // Data sanitization and validation
  $employee_id = isset($_POST['employee_id']) ? filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $employee_name = isset($_POST['employee_name']) ? filter_input(INPUT_POST, 'employee_name', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $occupation = isset($_POST['occupation']) ? filter_input(INPUT_POST, 'occupation', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $office = isset($_POST['office']) ? filter_input(INPUT_POST, 'office', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $rate_per_day = isset($_POST['rate_per_day']) ? filter_input(INPUT_POST, 'rate_per_day', FILTER_VALIDATE_FLOAT) : 0;
  $sss_contribution = isset($_POST['sss_contribution']) ? filter_input(INPUT_POST, 'sss_contribution', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $ctc_number = isset($_POST['ctc_number']) ? filter_input(INPUT_POST, 'ctc_number', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $ctc_date = isset($_POST['ctc_date']) ? $_POST['ctc_date'] : '';
  $place_of_issue = isset($_POST['place_of_issue']) ? filter_input(INPUT_POST, 'place_of_issue', FILTER_SANITIZE_SPECIAL_CHARS) : '';

  // Personal Details
  $first_name = isset($_POST['first_name']) ? filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $last_name = isset($_POST['last_name']) ? filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $mobile_number = isset($_POST['mobile_number']) ? filter_input(INPUT_POST, 'mobile_number', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $email_address = isset($_POST['email_address']) ? filter_input(INPUT_POST, 'email_address', FILTER_VALIDATE_EMAIL) : '';
  $date_of_birth = isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : '';
  $marital_status = isset($_POST['marital_status']) ? filter_input(INPUT_POST, 'marital_status', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $gender = isset($_POST['gender']) ? filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $nationality = isset($_POST['nationality']) ? filter_input(INPUT_POST, 'nationality', FILTER_SANITIZE_SPECIAL_CHARS) : 'Filipino';
  $street_address = isset($_POST['street_address']) ? filter_input(INPUT_POST, 'street_address', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $city = isset($_POST['city']) ? filter_input(INPUT_POST, 'city', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $state_region = isset($_POST['state_region']) ? filter_input(INPUT_POST, 'state_region', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $zip_code = isset($_POST['zip_code']) ? filter_input(INPUT_POST, 'zip_code', FILTER_SANITIZE_SPECIAL_CHARS) : '';
  $joining_date = isset($_POST['joining_date']) ? $_POST['joining_date'] : '';
  $eligibility = isset($_POST['eligibility']) ? filter_input(INPUT_POST, 'eligibility', FILTER_SANITIZE_SPECIAL_CHARS) : 'Not Eligible';

  // Validation
  $errors = [];

  // Required fields validation
  $required_fields = [
    'Employee ID' => $employee_id,
    'Employee Name' => $employee_name,
    'Occupation' => $occupation,
    'Office' => $office,
    'Rate per Day' => $rate_per_day,
    'CTC Number' => $ctc_number,
    'CTC Date' => $ctc_date,
    'Place of Issue' => $place_of_issue,
    'First Name' => $first_name,
    'Last Name' => $last_name,
    'Mobile Number' => $mobile_number,
    'Email Address' => $email_address,
    'Date of Birth' => $date_of_birth,
    'Marital Status' => $marital_status,
    'Gender' => $gender,
    'Nationality' => $nationality,
    'Street Address' => $street_address,
    'City' => $city,
    'State/Region' => $state_region,
    'ZIP Code' => $zip_code,
    'Joining Date' => $joining_date
  ];

  foreach ($required_fields as $field => $value) {
    if (empty(trim($value))) {
      $errors[] = "$field is required";
    }
  }

  // Specific validations
  if (!$email_address) {
    $errors[] = "Valid email address is required";
  }

  if ($rate_per_day === false || $rate_per_day <= 0) {
    $errors[] = "Valid rate per day is required (must be greater than 0)";
  }

  // Check for duplicate employee ID before processing
  if (empty($errors) && isset($pdo)) {
    try {
      // Check if employee ID already exists (including archived)
      $checkEmployeeId = $pdo->prepare("SELECT id FROM job_order WHERE employee_id = :employee_id");
      $checkEmployeeId->execute([':employee_id' => $employee_id]);

      if ($checkEmployeeId->rowCount() > 0) {
        $errors[] = "The Employee ID '{$employee_id}' is already registered.";
      }

      // Check if email already exists (including archived)
      $checkEmail = $pdo->prepare("SELECT id FROM job_order WHERE email_address = :email");
      $checkEmail->execute([':email' => $email_address]);

      if ($checkEmail->rowCount() > 0) {
        $errors[] = "The email address '{$email_address}' is already registered.";
      }
    } catch (PDOException $e) {
      error_log("Duplicate check error: " . $e->getMessage());
      $errors[] = "Database error during duplicate check.";
    }
  }

  if (empty($errors) && isset($pdo)) {
    // File uploads - handle optional files properly
    $profile_image_path = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
      $profile_image_path = uploadFile('profile_image', $upload_dir, 'profile');
    }

    $doc_id_path = null;
    if (isset($_FILES['doc_id']) && $_FILES['doc_id']['error'] == 0) {
      $doc_id_path = uploadFile('doc_id', $upload_dir, 'govt_id');
    }

    $doc_resume_path = null;
    if (isset($_FILES['doc_resume']) && $_FILES['doc_resume']['error'] == 0) {
      $doc_resume_path = uploadFile('doc_resume', $upload_dir, 'resume');
    }

    $doc_service_path = null;
    if (isset($_FILES['doc_service']) && $_FILES['doc_service']['error'] == 0) {
      $doc_service_path = uploadFile('doc_service', $upload_dir, 'service_rec');
    }

    $doc_appointment_path = null;
    if (isset($_FILES['doc_appointment']) && $_FILES['doc_appointment']['error'] == 0) {
      $doc_appointment_path = uploadFile('doc_appointment', $upload_dir, 'appt_paper');
    }

    $doc_transcript_path = null;
    if (isset($_FILES['doc_transcript']) && $_FILES['doc_transcript']['error'] == 0) {
      $doc_transcript_path = uploadFile('doc_transcript', $upload_dir, 'transcript');
    }

    $doc_eligibility_path = null;
    if (isset($_FILES['doc_eligibility']) && $_FILES['doc_eligibility']['error'] == 0) {
      $doc_eligibility_path = uploadFile('doc_eligibility', $upload_dir, 'elig_cert');
    }

    try {
      // Database insertion
      $sql = "INSERT INTO job_order (
                employee_id, employee_name, occupation, office, rate_per_day, sss_contribution, 
                ctc_number, ctc_date, place_of_issue,
                profile_image_path, first_name, last_name, mobile_number, email_address, date_of_birth, 
                marital_status, gender, nationality, street_address, 
                city, state_region, zip_code, joining_date, eligibility, 
                doc_id_path, doc_resume_path, doc_service_path, doc_appointment_path, 
                doc_transcript_path, doc_eligibility_path,
                is_archived, created_at, updated_at
            ) VALUES (
                :employee_id, :employee_name, :occupation, :office, :rate_per_day, :sss_contribution, 
                :ctc_number, :ctc_date, :place_of_issue,
                :profile_image_path, :first_name, :last_name, :mobile_number, :email_address, :date_of_birth, 
                :marital_status, :gender, :nationality, :street_address, 
                :city, :state_region, :zip_code, :joining_date, :eligibility, 
                :doc_id_path, :doc_resume_path, :doc_service_path, :doc_appointment_path, 
                :doc_transcript_path, :doc_eligibility_path,
                :is_archived, NOW(), NOW()
            )";

      $stmt = $pdo->prepare($sql);

      // Bind parameters
      $params = [
        ':employee_id' => $employee_id,
        ':employee_name' => $employee_name,
        ':occupation' => $occupation,
        ':office' => $office,
        ':rate_per_day' => $rate_per_day,
        ':sss_contribution' => $sss_contribution,
        ':ctc_number' => $ctc_number,
        ':ctc_date' => $ctc_date,
        ':place_of_issue' => $place_of_issue,
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
        ':is_archived' => 0
      ];

      if ($stmt->execute($params)) {
        $_SESSION['success'] = "Employee '{$employee_name}' added successfully!";
        header("Location: " . $current_file);
        exit;
      } else {
        $error = "Database Error: Failed to insert employee record.";
        $_SESSION['error'] = $error;
      }
    } catch (PDOException $e) {
      error_log("Database execution error: " . $e->getMessage());
      $_SESSION['error'] = "Failed to save employee data: " . $e->getMessage();
    }
  } else {
    if (!empty($errors)) {
      $error = implode("<br>", $errors);
      $_SESSION['error'] = $error;
    }
    if (!isset($pdo)) {
      $_SESSION['error'] = "Database connection failed. Please check your database configuration.";
    }
  }
}

// 3.2. UPDATE EMPLOYEE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_submit'])) {
  unset($_SESSION['success']);
  unset($_SESSION['error']);

  $id = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);

  if (!$id) {
    $_SESSION['error'] = "Invalid employee ID";
  } elseif (!isset($pdo)) {
    $_SESSION['error'] = "Database connection failed";
  } else {
    // Fetch existing data
    try {
      $stmt = $pdo->prepare("SELECT * FROM job_order WHERE id = :id");
      $stmt->execute([':id' => $id]);
      $existing_employee = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$existing_employee) {
        $_SESSION['error'] = "Employee not found";
      } else {
        // Data sanitization and validation
        $employee_id_field = filter_input(INPUT_POST, 'employee_id_field', FILTER_SANITIZE_SPECIAL_CHARS);
        $employee_name = filter_input(INPUT_POST, 'employee_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $occupation = filter_input(INPUT_POST, 'occupation', FILTER_SANITIZE_SPECIAL_CHARS);
        $office = filter_input(INPUT_POST, 'office', FILTER_SANITIZE_SPECIAL_CHARS);
        $rate_per_day = filter_input(INPUT_POST, 'rate_per_day', FILTER_VALIDATE_FLOAT);
        $sss_contribution = filter_input(INPUT_POST, 'sss_contribution', FILTER_SANITIZE_SPECIAL_CHARS);
        $ctc_number = filter_input(INPUT_POST, 'ctc_number', FILTER_SANITIZE_SPECIAL_CHARS);
        $ctc_date = filter_input(INPUT_POST, 'ctc_date');
        $place_of_issue = filter_input(INPUT_POST, 'place_of_issue', FILTER_SANITIZE_SPECIAL_CHARS);

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
        $joining_date = filter_input(INPUT_POST, 'joining_date');
        $eligibility = filter_input(INPUT_POST, 'eligibility', FILTER_SANITIZE_SPECIAL_CHARS);

        $errors = [];

        // Validation
        $required_fields = [
          'Employee ID' => $employee_id_field,
          'Employee Name' => $employee_name,
          'Occupation' => $occupation,
          'Office' => $office,
          'Rate per Day' => $rate_per_day,
          'CTC Number' => $ctc_number,
          'CTC Date' => $ctc_date,
          'Place of Issue' => $place_of_issue,
          'First Name' => $first_name,
          'Last Name' => $last_name,
          'Mobile Number' => $mobile_number,
          'Email Address' => $email_address,
          'Date of Birth' => $date_of_birth,
          'Marital Status' => $marital_status,
          'Gender' => $gender,
          'Nationality' => $nationality,
          'Street Address' => $street_address,
          'City' => $city,
          'State/Region' => $state_region,
          'ZIP Code' => $zip_code,
          'Joining Date' => $joining_date
        ];

        foreach ($required_fields as $field => $value) {
          if (empty(trim($value))) {
            $errors[] = "$field is required";
          }
        }

        if (!$email_address) {
          $errors[] = "Valid email address is required";
        }

        if ($rate_per_day === false || $rate_per_day <= 0) {
          $errors[] = "Valid rate per day is required (must be greater than 0)";
        }

        // Check for duplicate employee ID (excluding current employee)
        if (empty($errors)) {
          $checkEmployeeId = $pdo->prepare("SELECT id FROM job_order WHERE employee_id = :employee_id AND id != :id");
          $checkEmployeeId->execute([':employee_id' => $employee_id_field, ':id' => $id]);

          if ($checkEmployeeId->rowCount() > 0) {
            $errors[] = "The Employee ID '{$employee_id_field}' is already registered by another employee.";
          }
        }

        // Check for duplicate email (excluding current employee)
        if (empty($errors)) {
          $checkEmail = $pdo->prepare("SELECT id FROM job_order WHERE email_address = :email AND id != :id");
          $checkEmail->execute([':email' => $email_address, ':id' => $id]);

          if ($checkEmail->rowCount() > 0) {
            $errors[] = "The email address '{$email_address}' is already registered by another employee.";
          }
        }

        if (empty($errors)) {
          // File uploads (only if new files are uploaded)
          $profile_image_path = $existing_employee['profile_image_path'];
          if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $profile_image_path = uploadFile('profile_image', $upload_dir, 'profile', $existing_employee['profile_image_path']);
          }

          $doc_id_path = $existing_employee['doc_id_path'];
          if (isset($_FILES['doc_id']) && $_FILES['doc_id']['error'] == 0) {
            $doc_id_path = uploadFile('doc_id', $upload_dir, 'govt_id', $existing_employee['doc_id_path']);
          }

          $doc_resume_path = $existing_employee['doc_resume_path'];
          if (isset($_FILES['doc_resume']) && $_FILES['doc_resume']['error'] == 0) {
            $doc_resume_path = uploadFile('doc_resume', $upload_dir, 'resume', $existing_employee['doc_resume_path']);
          }

          $doc_service_path = $existing_employee['doc_service_path'];
          if (isset($_FILES['doc_service']) && $_FILES['doc_service']['error'] == 0) {
            $doc_service_path = uploadFile('doc_service', $upload_dir, 'service_rec', $existing_employee['doc_service_path']);
          }

          $doc_appointment_path = $existing_employee['doc_appointment_path'];
          if (isset($_FILES['doc_appointment']) && $_FILES['doc_appointment']['error'] == 0) {
            $doc_appointment_path = uploadFile('doc_appointment', $upload_dir, 'appt_paper', $existing_employee['doc_appointment_path']);
          }

          $doc_transcript_path = $existing_employee['doc_transcript_path'];
          if (isset($_FILES['doc_transcript']) && $_FILES['doc_transcript']['error'] == 0) {
            $doc_transcript_path = uploadFile('doc_transcript', $upload_dir, 'transcript', $existing_employee['doc_transcript_path']);
          }

          $doc_eligibility_path = $existing_employee['doc_eligibility_path'];
          if (isset($_FILES['doc_eligibility']) && $_FILES['doc_eligibility']['error'] == 0) {
            $doc_eligibility_path = uploadFile('doc_eligibility', $upload_dir, 'elig_cert', $existing_employee['doc_eligibility_path']);
          }

          // Database update
          $sql = "UPDATE job_order SET
                        employee_id = :employee_id,
                        employee_name = :employee_name,
                        occupation = :occupation,
                        office = :office,
                        rate_per_day = :rate_per_day,
                        sss_contribution = :sss_contribution,
                        ctc_number = :ctc_number,
                        ctc_date = :ctc_date,
                        place_of_issue = :place_of_issue,
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
                        doc_eligibility_path = :doc_eligibility_path,
                        updated_at = NOW()
                    WHERE id = :id";

          $stmt = $pdo->prepare($sql);

          // Bind parameters
          $params = [
            ':id' => $id,
            ':employee_id' => $employee_id_field,
            ':employee_name' => $employee_name,
            ':occupation' => $occupation,
            ':office' => $office,
            ':rate_per_day' => $rate_per_day,
            ':sss_contribution' => $sss_contribution,
            ':ctc_number' => $ctc_number,
            ':ctc_date' => $ctc_date,
            ':place_of_issue' => $place_of_issue,
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
            ':doc_eligibility_path' => $doc_eligibility_path
          ];

          if ($stmt->execute($params)) {
            $_SESSION['success'] = "Employee '{$employee_name}' updated successfully!";
            header("Location: " . $current_file);
            exit;
          }
        } else {
          $_SESSION['error'] = implode("<br>", $errors);
        }
      }
    } catch (PDOException $e) {
      error_log("Database update error: " . $e->getMessage());
      $_SESSION['error'] = "Failed to update employee: " . $e->getMessage();
    }
  }
}

// 3.3. ARCHIVE EMPLOYEE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['archive_submit'])) {
  unset($_SESSION['success']);
  unset($_SESSION['error']);

  $id = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
  $archive_action = filter_input(INPUT_POST, 'archive_action', FILTER_SANITIZE_SPECIAL_CHARS);

  if (!$id) {
    $_SESSION['error'] = "Invalid employee ID";
  } elseif (!isset($pdo)) {
    $_SESSION['error'] = "Database connection failed";
  } else {
    try {
      // Archive or unarchive employee
      $is_archived = ($archive_action === 'archive') ? 1 : 0;

      // Get employee name for message
      $stmt = $pdo->prepare("SELECT employee_name FROM job_order WHERE id = :id");
      $stmt->execute([':id' => $id]);
      $employee = $stmt->fetch(PDO::FETCH_ASSOC);
      $employee_name = $employee ? $employee['employee_name'] : 'Employee';

      $stmt = $pdo->prepare("UPDATE job_order SET is_archived = :is_archived, updated_at = NOW() WHERE id = :id");
      $stmt->execute([':is_archived' => $is_archived, ':id' => $id]);

      if ($stmt->rowCount() > 0) {
        $action = $is_archived ? 'archived' : 'restored';
        $_SESSION['success'] = "Employee '{$employee_name}' {$action} successfully!";
        header("Location: " . $current_file);
        exit;
      } else {
        $_SESSION['error'] = "Employee not found";
      }
    } catch (PDOException $e) {
      error_log("Archive error: " . $e->getMessage());
      $_SESSION['error'] = "Error: " . $e->getMessage();
    }
  }
}

// ===============================================
// 4. PAGINATION CONFIGURATION
// ===============================================

// Records per page configuration
$records_per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
// Ensure it's a valid value
$valid_per_page_values = [5, 10, 25, 50, 100];
if (!in_array($records_per_page, $valid_per_page_values)) {
  $records_per_page = 10;
}

$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($current_page < 1) {
  $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

// Initialize filter variables
$search = $_GET['search'] ?? '';
$office_filter = $_GET['office'] ?? '';
$occupation_filter = $_GET['occupation'] ?? '';
$eligibility_filter = $_GET['eligibility'] ?? '';
$show_archived = isset($_GET['show_archived']) && $_GET['show_archived'] == '1';

// Get unique values for filters
if (isset($pdo)) {
  try {
    // Get distinct offices
    $officeStmt = $pdo->query("SELECT DISTINCT office FROM job_order WHERE office IS NOT NULL AND office != '' ORDER BY office");
    $offices = $officeStmt->fetchAll(PDO::FETCH_COLUMN);

    // Get distinct occupations
    $occupationStmt = $pdo->query("SELECT DISTINCT occupation FROM job_order WHERE occupation IS NOT NULL AND occupation != '' ORDER BY occupation");
    $occupations = $occupationStmt->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
    error_log("Filter query error: " . $e->getMessage());
    $_SESSION['error'] = "Filter error: " . $e->getMessage();
    $offices = [];
    $occupations = [];
  }
} else {
  $offices = [];
  $occupations = [];
}

// Build query with filters
$query = "SELECT 
    id, employee_id, employee_name, occupation, office, 
    rate_per_day, sss_contribution, ctc_number, 
    ctc_date, place_of_issue, eligibility, is_archived
FROM job_order 
WHERE 1=1";

$params = [];

// Apply archive filter
if (!$show_archived) {
  $query .= " AND is_archived = 0";
} else {
  $query .= " AND is_archived = 1";
}

// Apply search filter
if (!empty($search)) {
  $query .= " AND (
        employee_name LIKE :search OR 
        employee_id LIKE :search OR 
        occupation LIKE :search OR
        first_name LIKE :search OR
        last_name LIKE :search OR
        email_address LIKE :search OR
        mobile_number LIKE :search
    )";
  $params[':search'] = "%{$search}%";
}

// Apply office filter
if (!empty($office_filter)) {
  $query .= " AND office = :office";
  $params[':office'] = $office_filter;
}

// Apply occupation filter
if (!empty($occupation_filter)) {
  $query .= " AND occupation = :occupation";
  $params[':occupation'] = $occupation_filter;
}

// Apply eligibility filter
if (!empty($eligibility_filter)) {
  $query .= " AND eligibility = :eligibility";
  $params[':eligibility'] = $eligibility_filter;
}

// Count total records for pagination
$total_records = 0;
if (isset($pdo)) {
  try {
    $countQuery = "SELECT COUNT(*) FROM (" . str_replace(
      "SELECT id, employee_id, employee_name, occupation, office, rate_per_day, sss_contribution, ctc_number, ctc_date, place_of_issue, eligibility, is_archived",
      "SELECT id",
      $query
    ) . ") as count_table";

    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $total_records = $countStmt->fetchColumn();
  } catch (PDOException $e) {
    error_log("Count query error: " . $e->getMessage());
    $_SESSION['error'] = "Count error: " . $e->getMessage();
    $total_records = 0;
  }
}

// Calculate total pages
$total_pages = ceil($total_records / $records_per_page);
if ($total_pages < 1) $total_pages = 1;
if ($current_page > $total_pages) $current_page = $total_pages;

// Adjust offset for current page
$offset = ($current_page - 1) * $records_per_page;

// Add ordering and pagination
$query .= " ORDER BY is_archived ASC, employee_name ASC LIMIT :limit OFFSET :offset";

if (isset($pdo)) {
  try {
    $stmt = $pdo->prepare($query);

    // Bind parameters
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    // Bind pagination parameters
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Reset counter for current page
    $counter = ($current_page - 1) * $records_per_page + 1;
  } catch (PDOException $e) {
    error_log("Query execution error: " . $e->getMessage());
    $_SESSION['error'] = "Query error: " . $e->getMessage();
    $employees = [];
  }
} else {
  $employees = [];
}

// 4.1. GET EMPLOYEE DATA FOR EDITING (AJAX ENDPOINT)
if (isset($_GET['get_employee_data']) && isset($_GET['id']) && isset($pdo)) {
  try {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id) {
      $stmt = $pdo->prepare("SELECT * FROM job_order WHERE id = :id");
      $stmt->execute([':id' => $id]);
      $employee = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($employee) {
        header('Content-Type: application/json');
        echo json_encode($employee);
        exit;
      }
    }
  } catch (PDOException $e) {
    error_log("Get employee data error: " . $e->getMessage());
  }
  echo json_encode(['error' => 'Employee not found']);
  exit;
}

// ===============================================
// 5. VIEW EMPLOYEE DETAILS LOGIC
// ===============================================
$view_employee = null;
if (isset($_GET['view_id']) && isset($pdo)) {
  try {
    $view_id = filter_var($_GET['view_id'], FILTER_VALIDATE_INT);
    if ($view_id) {
      $stmt = $pdo->prepare("SELECT * FROM job_order WHERE id = :id");
      $stmt->execute([':id' => $view_id]);
      $view_employee = $stmt->fetch(PDO::FETCH_ASSOC);
    }
  } catch (PDOException $e) {
    error_log("View employee error: " . $e->getMessage());
    $_SESSION['error'] = "View error: " . $e->getMessage();
  }
}

// Calculate monthly salary (rate_per_day * 22 working days)
if ($view_employee && isset($view_employee['rate_per_day'])) {
  $monthly_salary = $view_employee['rate_per_day'] * 22;
  $amount_accrued = $monthly_salary * 0.1; // Example calculation
}

// Display session messages
if (isset($_SESSION['success'])) {
  $success_message = $_SESSION['success'];
  unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
  $error_message = $_SESSION['error'];
  unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Order Employees</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../css/output.css">
  <link rel="stylesheet" href="../css/dasboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    /* Your existing CSS styles remain the same */
    /* Only including key pagination CSS for brevity */
    .pagination-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      background: white;
      border-top: 1px solid #e5e7eb;
      flex-wrap: wrap;
    }

    .pagination-info {
      font-size: 0.875rem;
      color: #6b7280;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    .pagination-nav {
      display: flex;
      align-items: center;
      gap: 0.25rem;
      flex-wrap: wrap;
    }

    .pagination-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.5rem 0.75rem;
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

    .badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .badge-warning {
      background-color: #fef3c7;
      color: #92400e;
    }

    .badge-info {
      background-color: #dbeafe;
      color: #1e40af;
    }

    /* Notification styles */
    .notification-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      max-width: 400px;
    }

    .notification {
      display: flex;
      align-items: flex-start;
      padding: 16px;
      margin-bottom: 10px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      animation: slideIn 0.3s ease;
      transform: translateX(100%);
      opacity: 0;
    }

    .notification.show {
      transform: translateX(0);
      opacity: 1;
    }

    .notification.hide {
      transform: translateX(100%);
      opacity: 0;
      transition: transform 0.3s ease, opacity 0.3s ease;
    }

    .notification-success {
      background: linear-gradient(135deg, #d1fae5 0%, #10b981 100%);
      border-left: 4px solid #059669;
      color: #065f46;
    }

    .notification-error {
      background: linear-gradient(135deg, #fee2e2 0%, #ef4444 100%);
      border-left: 4px solid #dc2626;
      color: #991b1b;
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
  </style>
  <style>
    /* Notification Popup Styles */
    .notification-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      max-width: 400px;
    }

    .notification {
      display: flex;
      align-items: flex-start;
      padding: 16px;
      margin-bottom: 10px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      animation: slideIn 0.3s ease;
      transform: translateX(100%);
      opacity: 0;
    }

    .notification.show {
      transform: translateX(0);
      opacity: 1;
    }

    .notification.hide {
      transform: translateX(100%);
      opacity: 0;
      transition: transform 0.3s ease, opacity 0.3s ease;
    }

    .notification-success {
      background: linear-gradient(135deg, #d1fae5 0%, #10b981 100%);
      border-left: 4px solid #059669;
      color: #065f46;
    }

    .notification-error {
      background: linear-gradient(135deg, #fee2e2 0%, #ef4444 100%);
      border-left: 4px solid #dc2626;
      color: #991b1b;
    }

    .notification-warning {
      background: linear-gradient(135deg, #fef3c7 0%, #f59e0b 100%);
      border-left: 4px solid #d97706;
      color: #92400e;
    }

    .notification-info {
      background: linear-gradient(135deg, #dbeafe 0%, #3b82f6 100%);
      border-left: 4px solid #1d4ed8;
      color: #1e40af;
    }

    .notification-icon {
      font-size: 20px;
      margin-right: 12px;
      flex-shrink: 0;
      margin-top: 2px;
    }

    .notification-content {
      flex: 1;
      min-width: 0;
    }

    .notification-title {
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 4px;
    }

    .notification-message {
      font-size: 13px;
      line-height: 1.4;
    }

    .notification-close {
      background: none;
      border: none;
      color: inherit;
      opacity: 0.7;
      cursor: pointer;
      padding: 0;
      margin-left: 12px;
      font-size: 14px;
      flex-shrink: 0;
      transition: opacity 0.2s;
    }

    .notification-close:hover {
      opacity: 1;
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

    @keyframes slideOut {
      from {
        transform: translateX(0);
        opacity: 1;
      }

      to {
        transform: translateX(100%);
        opacity: 0;
      }
    }

    /* Debug overlay for troubleshooting */
    .debug-overlay {
      position: fixed;
      bottom: 10px;
      left: 10px;
      background: rgba(0, 0, 0, 0.8);
      color: white;
      padding: 10px;
      border-radius: 5px;
      font-family: monospace;
      font-size: 12px;
      z-index: 10000;
      max-width: 300px;
      max-height: 200px;
      overflow: auto;
      display: none;
    }

    /* All other CSS styles remain the same */
    :root {
      --primary: #1e40af;
      --secondary: #1e3a8a;
      --accent: #3b82f6;
      --success: #10b981;
      --danger: #ef4444;
      --warning: #f59e0b;
      --info: #3b82f6;
      --light: #f8fafc;
      --dark: #1f2937;
      --border: #e5e7eb;
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

    /* Base Styles */
    :root {
      --primary: #1e40af;
      --secondary: #1e3a8a;
      --accent: #3b82f6;
      --success: #10b981;
      --danger: #ef4444;
      --warning: #f59e0b;
      --info: #3b82f6;
      --light: #f8fafc;
      --dark: #1f2937;
      --border: #e5e7eb;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    /* Filter Section Styles */
    .filter-section {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      border: 1px solid var(--border);
    }

    .filter-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      align-items: end;
    }

    .filter-group {
      display: flex;
      flex-direction: column;
    }

    .filter-label {
      font-size: 0.875rem;
      font-weight: 500;
      color: #4b5563;
      margin-bottom: 0.5rem;
    }

    .filter-select,
    .filter-input {
      width: 100%;
      padding: 0.625rem 1rem;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 0.875rem;
      transition: all 0.2s;
    }

    .filter-select:focus,
    .filter-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .filter-actions {
      display: flex;
      gap: 0.75rem;
      align-items: center;
    }

    .filter-btn {
      padding: 0.625rem 1.5rem;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      border: none;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .filter-btn-primary {
      background: var(--primary);
      color: white;
    }

    .filter-btn-primary:hover {
      background: var(--secondary);
      transform: translateY(-1px);
    }

    .filter-btn-secondary {
      background: white;
      color: var(--dark);
      border: 1px solid var(--border);
    }

    .filter-btn-secondary:hover {
      background: var(--light);
    }

    /* Archive Badge */
    .archive-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      background-color: #fee2e2;
      color: #991b1b;
    }

    .archive-badge i {
      margin-right: 0.25rem;
      font-size: 0.625rem;
    }

    /* Archived Row Styling */
    tr.archived {
      background-color: #fef2f2;
    }

    tr.archived:hover {
      background-color: #fee2e2;
    }

    /* Pagination Styles - UPDATED */
    .pagination {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem;
      background: white;
      border-radius: 0 0 12px 12px;
      border-top: 1px solid var(--border);
    }

    .pagination-info {
      font-size: 0.875rem;
      color: #6b7280;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .pagination-buttons {
      display: flex;
      gap: 0.25rem;
      align-items: center;
    }

    .page-btn {
      padding: 0.5rem 0.75rem;
      border: 1px solid var(--border);
      border-radius: 6px;
      background: white;
      color: var(--dark);
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 36px;
      height: 36px;
    }

    .page-btn:hover:not(:disabled) {
      background: #f3f4f6;
      border-color: #d1d5db;
    }

    .page-btn.active {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }

    .page-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      background: #f9fafb;
    }

    .page-btn i {
      font-size: 0.875rem;
    }

    /* Add dots styling */
    .pagination-buttons span {
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 24px;
      height: 36px;
      color: #9ca3af;
    }

    /* Old pagination styles to remove */
    .page-input {
      display: none;
    }

    /* Modal System */
    .modal-fixed-container {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      padding: 1rem;
      background-color: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .modal-fixed-container.active {
      display: flex;
      opacity: 1;
    }

    .modal-content-wrapper {
      position: relative;
      background: white;
      border-radius: 0.75rem;
      max-width: 90%;
      max-height: 90vh;
      overflow: hidden;
      z-index: 10000;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      transform: scale(0.95);
      opacity: 0;
      transition: transform 0.3s ease, opacity 0.3s ease;
    }

    .modal-fixed-container.active .modal-content-wrapper {
      transform: scale(1);
      opacity: 1;
    }

    /* Modal Sizes */
    .modal-sm {
      width: 400px;
    }

    .modal-md {
      width: 600px;
    }

    .modal-lg {
      width: 800px;
    }

    .modal-xl {
      width: 1140px;
    }

    /* Modal Header */
    .modal-header {
      padding: 1.5rem 2rem;
      border-bottom: 1px solid var(--border);
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      color: white;
    }

    .modal-header h3 {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }

    .modal-header p {
      color: rgba(255, 255, 255, 0.9);
      font-size: 0.875rem;
    }

    .modal-close-btn {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: rgba(255, 255, 255, 0.1);
      border: none;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .modal-close-btn:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: rotate(90deg);
    }

    /* Modal Body */
    .modal-body {
      padding: 2rem;
      max-height: 60vh;
      overflow-y: auto;
    }

    /* Modal Footer */
    .modal-footer {
      padding: 1.5rem 2rem;
      border-top: 1px solid var(--border);
      background: var(--light);
      display: flex;
      justify-content: flex-end;
      gap: 1rem;
    }

    /* Tab Navigation */
    .modal-tabs {
      display: flex;
      border-bottom: 1px solid var(--border);
      background: var(--light);
    }

    .modal-tab {
      padding: 1rem 1.5rem;
      font-weight: 500;
      color: #6b7280;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .modal-tab:hover {
      color: var(--primary);
      background: rgba(59, 130, 246, 0.05);
    }

    .modal-tab.active {
      color: var(--primary);
      border-bottom-color: var(--primary);
      background: white;
    }

    /* Step Navigation */
    .step-navigation {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      position: relative;
    }

    .step-navigation::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 0;
      right: 0;
      height: 2px;
      background: var(--border);
      z-index: 1;
    }

    .step-item {
      position: relative;
      z-index: 2;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
    }

    .step-number {
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      background: var(--light);
      border: 2px solid var(--border);
      transition: all 0.3s ease;
    }

    .step-item.active .step-number {
      background: var(--primary);
      border-color: var(--primary);
      color: white;
    }

    .step-item.completed .step-number {
      background: var(--success);
      border-color: var(--success);
      color: white;
    }

    .step-label {
      font-size: 0.875rem;
      font-weight: 500;
      color: #6b7280;
      white-space: nowrap;
    }

    .step-item.active .step-label,
    .step-item.completed .step-label {
      color: var(--primary);
    }

    /* Form Steps */
    .form-step {
      display: none;
      animation: fadeIn 0.3s ease;
    }

    .form-step.active {
      display: block;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Form Elements */
    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
      color: #374151;
      font-size: 0.875rem;
    }

    .form-control {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 1px solid var(--border);
      border-radius: 0.5rem;
      font-size: 0.875rem;
      transition: all 0.3s;
      background: white;
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-control.error {
      border-color: var(--danger);
      background: #fef2f2;
    }

    .error-message {
      color: var(--danger);
      font-size: 0.75rem;
      margin-top: 0.25rem;
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }

    /* Grid System */
    .grid-2 {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
    }

    .grid-3 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
    }

    .col-span-2 {
      grid-column: span 2;
    }

    .col-span-3 {
      grid-column: span 3;
    }

    /* File Upload Zones */
    .file-upload-zone {
      border: 2px dashed var(--border);
      border-radius: 0.75rem;
      padding: 2rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      background: var(--light);
    }

    .file-upload-zone:hover {
      border-color: var(--primary);
      background: rgba(59, 130, 246, 0.05);
    }

    .file-upload-zone.dragover {
      border-color: var(--primary);
      background: rgba(59, 130, 246, 0.1);
      transform: scale(1.02);
    }

    .file-upload-icon {
      font-size: 2.5rem;
      color: var(--primary);
      margin-bottom: 1rem;
    }

    .file-upload-text h4 {
      font-size: 1.125rem;
      font-weight: 600;
      color: var(--dark);
      margin-bottom: 0.5rem;
    }

    .file-upload-text p {
      color: #6b7280;
      font-size: 0.875rem;
      margin-bottom: 0.25rem;
    }

    .file-upload-text small {
      color: #9ca3af;
      font-size: 0.75rem;
    }

    .file-preview {
      margin-top: 1rem;
      padding: 0.75rem;
      background: white;
      border: 1px solid var(--border);
      border-radius: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .file-preview i {
      color: var(--primary);
      font-size: 1.25rem;
    }

    .file-preview span {
      flex: 1;
      font-size: 0.875rem;
      color: var(--dark);
    }

    .file-preview button {
      background: none;
      border: none;
      color: var(--danger);
      cursor: pointer;
      font-size: 1rem;
    }

    /* Profile Image Upload */
    .profile-image-upload {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
      margin-bottom: 2rem;
    }

    .profile-image-container {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      overflow: hidden;
      border: 4px solid white;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      position: relative;
      background: var(--light);
    }

    .profile-image-container img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .profile-image-placeholder {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    .profile-image-placeholder i {
      font-size: 2.5rem;
    }

    .profile-upload-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 0.5rem;
      font-weight: 500;
      font-size: 0.875rem;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .profile-upload-btn:hover {
      background: var(--secondary);
      transform: translateY(-2px);
    }

    /* Button Styles */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.5rem;
      border-radius: 0.5rem;
      font-weight: 500;
      font-size: 0.875rem;
      cursor: pointer;
      transition: all 0.3s ease;
      border: none;
      text-decoration: none;
    }

    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-primary:hover:not(:disabled) {
      background: var(--secondary);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
    }

    .btn-secondary {
      background: white;
      color: var(--dark);
      border: 1px solid var(--border);
    }

    .btn-secondary:hover:not(:disabled) {
      background: var(--light);
      transform: translateY(-2px);
    }

    .btn-success {
      background: var(--success);
      color: white;
    }

    .btn-success:hover:not(:disabled) {
      background: #059669;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .btn-danger {
      background: var(--danger);
      color: white;
    }

    .btn-danger:hover:not(:disabled) {
      background: #dc2626;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    .btn-warning {
      background: var(--warning);
      color: white;
    }

    .btn-warning:hover:not(:disabled) {
      background: #d97706;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }

    /* View Modal Styles */
    .employee-profile-header {
      display: flex;
      align-items: center;
      gap: 1.5rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid var(--border);
      margin-bottom: 1.5rem;
    }

    .profile-avatar-large {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      border: 4px solid white;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .profile-info {
      flex: 1;
    }

    .profile-info h3 {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 0.25rem;
    }

    .profile-info p {
      color: #6b7280;
      margin-bottom: 0.5rem;
    }

    .profile-meta {
      display: flex;
      gap: 1.5rem;
      flex-wrap: wrap;
    }

    .profile-meta-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
      color: #6b7280;
    }

    .profile-meta-item i {
      color: var(--primary);
    }

    /* Info Sections */
    .info-section {
      margin-bottom: 2rem;
    }

    .section-title {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 1.125rem;
      font-weight: 600;
      color: var(--dark);
      margin-bottom: 1rem;
      padding-bottom: 0.5rem;
      border-bottom: 2px solid var(--border);
    }

    .section-title i {
      color: var(--primary);
    }

    /* Info Grids */
    .info-grid {
      display: grid;
      gap: 1rem;
    }

    .info-grid-2 {
      grid-template-columns: repeat(2, 1fr);
    }

    .info-grid-3 {
      grid-template-columns: repeat(3, 1fr);
    }

    .info-item {
      display: flex;
      flex-direction: column;
    }

    .info-label {
      font-size: 0.75rem;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.25rem;
      font-weight: 500;
    }

    .info-value {
      font-size: 0.875rem;
      color: var(--dark);
      font-weight: 500;
    }

    /* Document Grid */
    .document-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1rem;
    }

    .document-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem;
      background: var(--light);
      border: 1px solid var(--border);
      border-radius: 0.5rem;
      transition: all 0.2s;
    }

    .document-item:hover {
      background: #f3f4f6;
      transform: translateY(-2px);
    }

    .document-icon {
      width: 2.5rem;
      height: 2.5rem;
      background: white;
      border-radius: 0.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
    }

    .document-info {
      flex: 1;
    }

    .document-name {
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--dark);
    }

    .document-status {
      font-size: 0.75rem;
      color: #6b7280;
    }

    .document-status.available {
      color: var(--success);
    }

    .document-status.unavailable {
      color: var(--danger);
    }

    /* Salary Cards */
    .salary-cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .salary-card {
      padding: 1.25rem;
      border-radius: 0.75rem;
      text-align: center;
      border: 1px solid var(--border);
      background: white;
    }

    .salary-card.primary {
      background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%);
      border-color: #93c5fd;
    }

    .salary-card.success {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      border-color: #a7f3d0;
    }

    .salary-card.warning {
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      border-color: #fde68a;
    }

    .salary-label {
      font-size: 0.875rem;
      color: #6b7280;
      margin-bottom: 0.5rem;
    }

    .salary-amount {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark);
    }

    /* Badges */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .badge-success {
      background-color: #d1fae5;
      color: #065f46;
    }

    .badge-danger {
      background-color: #fee2e2;
      color: #991b1b;
    }

    .badge-warning {
      background-color: #fef3c7;
      color: #92400e;
    }

    .badge-info {
      background-color: #dbeafe;
      color: #1e40af;
    }

    /* Alert Messages */
    .alert {
      padding: 1rem 1.5rem;
      border-radius: 0.75rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      border: 1px solid transparent;
    }

    .alert-success {
      background-color: #d1fae5;
      border-color: #a7f3d0;
      color: #065f46;
    }

    .alert-error {
      background-color: #fee2e2;
      border-color: #fecaca;
      color: #991b1b;
    }

    .alert-info {
      background-color: #dbeafe;
      border-color: #93c5fd;
      color: #1e40af;
    }

    .alert-warning {
      background-color: #fef3c7;
      border-color: #fde68a;
      color: #92400e;
    }

    .alert-icon {
      font-size: 1.25rem;
      flex-shrink: 0;
    }

    .alert-content {
      flex: 1;
    }

    .alert-title {
      font-weight: 600;
      margin-bottom: 0.25rem;
    }

    .alert-message {
      font-size: 0.875rem;
    }

    /* Loading Spinner */
    .spinner {
      width: 1.5rem;
      height: 1.5rem;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: white;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .modal-content-wrapper {
        max-width: 95%;
        max-height: 85vh;
      }

      .modal-sm,
      .modal-md,
      .modal-lg,
      .modal-xl {
        width: 100%;
      }

      .grid-2,
      .grid-3,
      .info-grid-2,
      .info-grid-3,
      .salary-cards,
      .document-grid {
        grid-template-columns: 1fr;
      }

      .employee-profile-header {
        flex-direction: column;
        text-align: center;
      }

      .profile-meta {
        justify-content: center;
      }

      .modal-body {
        padding: 1.5rem;
        max-height: 50vh;
      }

      .step-navigation {
        flex-direction: column;
        gap: 1rem;
      }

      .step-navigation::before {
        display: none;
      }

      .filter-grid {
        grid-template-columns: 1fr;
      }

      .pagination {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
      }

      .pagination-buttons {
        justify-content: center;
        flex-wrap: wrap;
      }
    }

    /* Print Styles */
    @media print {
      .modal-fixed-container {
        position: static;
        background: white;
        display: block;
        height: auto;
      }

      .modal-content-wrapper {
        box-shadow: none;
        max-width: 100%;
        max-height: none;
      }

      .modal-close-btn,
      .modal-footer {
        display: none;
      }
    }

    /* Scrollbar Styling */
    ::-webkit-scrollbar {
      width: 6px;
    }

    ::-webkit-scrollbar-track {
      background: rgba(0, 0, 0, 0.05);
      border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb {
      background: rgba(0, 0, 0, 0.2);
      border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: rgba(0, 0, 0, 0.3);
    }

    /* Custom Utilities */
    .text-truncate {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .cursor-pointer {
      cursor: pointer;
    }

    .transition-all {
      transition: all 0.3s ease;
    }

    .shadow-lg {
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .shadow-xl {
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    /* Form Validation Animation */
    @keyframes shake {

      0%,
      100% {
        transform: translateX(0);
      }

      10%,
      30%,
      50%,
      70%,
      90% {
        transform: translateX(-5px);
      }

      20%,
      40%,
      60%,
      80% {
        transform: translateX(5px);
      }
    }

    .shake {
      animation: shake 0.6s ease;
    }

    /* ... ALL YOUR EXISTING CSS STYLES ... */
    /* They remain exactly the same as before */
  </style>

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
      margin-top: 3.1%;
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

    /* Chevron rotation */
    .sidebar-item .chevron {
      transition: transform 0.3s ease;
      font-size: 0.8rem;
    }

    .sidebar-item .chevron.rotated {
      transform: rotate(180deg);
    }
  </style>
</head>

<body>

  <!-- Notification Container -->
  <div class="notification-container" id="notificationContainer"></div>

  <!-- Navigation and Sidebar remain the same -->
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

        <!-- Payroll - SIMPLIFIED VERSION -->
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
  <main class="main-content">
    <div class="bg-white rounded-xl shadow-lg p-2 md:p-6">
      <!-- Success/Error messages -->
      <?php if (!empty($success_message)): ?>
        <div id="successNotification" class="notification notification-success show">
          <div class="notification-icon">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="notification-content">
            <div class="notification-title">Success</div>
            <div class="notification-message"><?= htmlspecialchars($success_message) ?></div>
          </div>
          <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
          </button>
        </div>
      <?php endif; ?>

      <?php if (!empty($error_message)): ?>
        <div id="errorNotification" class="notification notification-error show">
          <div class="notification-icon">
            <i class="fas fa-exclamation-circle"></i>
          </div>
          <div class="notification-content">
            <div class="notification-title">Error</div>
            <div class="notification-message"><?= htmlspecialchars($error_message) ?></div>
          </div>
          <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
          </button>
        </div>
      <?php endif; ?>

      <!-- Breadcrumb -->
      <nav class="flex mb-4 overflow-x-auto">
        <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse whitespace-nowrap">
          <li class="inline-flex items-center">
            <a href="Employee.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
              <i class="fas fa-home mr-2"></i>All Employee
            </a>
          </li>
          <li>
            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
            <a href="permanent.php" class="ms-1 text-sm font-medium hover:text-blue-600 md:ms-2">Permanent</a>
          </li>
          <li>
            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
            <a href="contractofservice.php" class="ms-1 text-sm font-medium hover:text-blue-600 md:ms-2">Contractual</a>
          </li>
          <li>
            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
            <a href="Job_order.php" class="ms-1 text-sm font-medium text-blue-700 hover:text-blue-600 md:ms-2">Job Order</a>
          </li>
        </ol>
      </nav>
      <h1 class="text-2xl font-bold text-gray-900 mb-6">Job Order Employees</h1>

      <!-- Archive Filter -->
      <div class="filter-section mb-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Employee Records</h3>
            <p class="text-sm text-gray-600">Manage active and archived employees</p>
          </div>
          <div class="flex items-center gap-3">
            <a href="<?php echo $current_file . ($show_archived ? '' : '?show_archived=1'); ?>" class="btn <?php echo $show_archived ? 'btn-warning' : 'btn-secondary'; ?>">
              <i class="fas fa-<?php echo $show_archived ? 'history' : 'archive'; ?>"></i>
              <?php echo $show_archived ? 'View Active Employees' : 'View Archived Employees'; ?>
            </a>
          </div>
        </div>
      </div>

      <div class="bg-white shadow-lg rounded-xl overflow-hidden">
        <div class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
          <div class="w-full md:w-1/3">
            <div class="relative">
              <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
              <label for="simple-search" class="sr-only">Search</label>
              <input type="text" id="simple-search" value="<?= htmlspecialchars($search) ?>"
                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Search for employees...">
            </div>
          </div>

          <div class="w-full md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0">
            <!-- Records per page selector -->
            <div class="flex items-center space-x-2 bg-white border border-gray-300 rounded-lg px-3 py-2.5 hover:border-blue-500 transition-colors">
              <span class="text-sm text-gray-600 whitespace-nowrap">Show:</span>
              <select id="recordsPerPage" onchange="changeRecordsPerPage(this.value)"
                class="bg-transparent border-none text-gray-900 text-sm focus:outline-none focus:ring-0 cursor-pointer appearance-none">
                <option value="5" <?php echo ($records_per_page == 5) ? 'selected' : ''; ?>>5</option>
                <option value="10" <?php echo ($records_per_page == 10) ? 'selected' : ''; ?>>10</option>
                <option value="25" <?php echo ($records_per_page == 25) ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo ($records_per_page == 50) ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo ($records_per_page == 100) ? 'selected' : ''; ?>>100</option>
              </select>
              <span class="text-sm text-gray-600 whitespace-nowrap">per page</span>
            </div>

            <button id="openAddEmployeeModal" type="button" class="btn btn-primary">
              <i class="fas fa-plus"></i>
              <span>Add New Job Order</span>
            </button>
          </div>
        </div>

        
      </div>

      <div class="overflow-x-auto mb-4 md:mb-10">
        <table class="w-full text-sm text-left text-gray-500">
          <thead class="text-xs text-white uppercase bg-gradient-to-r from-blue-600 to-blue-800">
            <tr>
              <th scope="col" class="p-4">#</th>
              <th scope="col" class="px-6 py-3">EMP ID</th>
              <th scope="col" class="px-6 py-3">NAME</th>
              <th scope="col" class="px-6 py-3">OCCUPATION</th>
              <th scope="col" class="px-6 py-3">OFFICE</th>
              <th scope="col" class="px-6 py-3">RATE/DAY</th>
              <th scope="col" class="px-6 py-3">SSS CONTRIBUTION</th>
              <th scope="col" class="px-6 py-3">CTC NUMBER</th>
              <th scope="col" class="px-6 py-3">CTC DATE</th>
              <th scope="col" class="px-6 py-3">PLACE OF ISSUE</th>
              <th scope="col" class="px-6 py-3">STATUS</th>
              <th scope="col" class="px-6 py-3">ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($employees)): ?>
              <?php foreach ($employees as $index => $employee): ?>
                <tr class="bg-white border-b hover:bg-gray-50 transition-colors <?php echo $employee['is_archived'] ? 'archived' : ''; ?>">
                  <td class="p-4"><?= $counter++ ?></td>
                  <td class="px-6 py-4 font-mono font-semibold"><?= htmlspecialchars($employee['employee_id']) ?></td>
                  <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap"><?= htmlspecialchars($employee['employee_name']) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($employee['occupation']) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($employee['office']) ?></td>
                  <td class="px-6 py-4 font-semibold">₱<?= number_format($employee['rate_per_day'], 2) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($employee['sss_contribution']) ?></td>
                  <td class="px-6 py-4 font-mono"><?= htmlspecialchars($employee['ctc_number']) ?></td>
                  <td class="px-6 py-4"><?= !empty($employee['ctc_date']) ? date('M d, Y', strtotime($employee['ctc_date'])) : 'N/A' ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($employee['place_of_issue']) ?></td>
                  <td class="px-6 py-4">
                    <?php if ($employee['is_archived']): ?>
                      <span class="archive-badge"><i class="fas fa-archive"></i> Archived</span>
                    <?php else: ?>
                      <span class="badge badge-success">Active</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex items-center space-x-2">
                      <button type="button" class="view-trigger-btn text-green-600 hover:text-green-800 transition-colors"
                        data-employee-id="<?= $employee['id'] ?>"><i class="fas fa-eye"></i></button>
                      <span class="text-gray-300">|</span>
                      <button type="button" class="edit-trigger-btn text-blue-600 hover:text-blue-800 transition-colors"
                        data-employee-id="<?= $employee['id'] ?>"><i class="fas fa-edit"></i></button>
                      <span class="text-gray-300">|</span>
                      <?php if ($employee['is_archived']): ?>
                        <button type="button" class="restore-trigger-btn text-purple-600 hover:text-purple-800 transition-colors"
                          data-employee-id="<?= $employee['id'] ?>"
                          data-employee-name="<?= htmlspecialchars($employee['employee_name']) ?>"><i class="fas fa-history"></i></button>
                      <?php else: ?>
                        <button type="button" class="archive-trigger-btn text-orange-600 hover:text-orange-800 transition-colors"
                          data-employee-id="<?= $employee['id'] ?>"
                          data-employee-name="<?= htmlspecialchars($employee['employee_name']) ?>"><i class="fas fa-archive"></i></button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="12" class="px-6 py-8 text-center">
                  <div class="flex flex-col items-center justify-center text-gray-500">
                    <i class="fas fa-users text-4xl mb-4 opacity-50"></i>
                    <?php if (!empty($search) || !empty($office_filter) || !empty($occupation_filter) || !empty($eligibility_filter)): ?>
                      <p class="text-lg font-medium mb-2">No employees found</p>
                      <p class="text-sm">Try adjusting your filters or search terms</p>
                    <?php else: ?>
                      <p class="text-lg font-medium mb-2">No <?php echo $show_archived ? 'archived' : 'job order'; ?> records found</p>
                      <p class="text-sm"><?php echo $show_archived ? 'No archived employees found' : 'Add your first job order employee using the button above'; ?></p>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <!-- PAGINATION -->
        <?php if ($total_records > 0): ?>
          <div class="pagination-container">
            <div class="pagination-info">
              Showing
              <span class="font-semibold text-gray-900">
                <?php echo ($total_records > 0) ? ($offset + 1) : 0; ?>-<?php echo min($offset + count($employees), $total_records); ?>
              </span>
              of
              <span class="font-semibold text-gray-900"><?php echo $total_records; ?></span>
              records
              <?php if ($show_archived): ?>
                <span class="badge badge-warning ml-2">Archived</span>
              <?php endif; ?>
              <?php if (!empty($search)): ?>
                <span class="badge badge-info ml-2">Search: "<?php echo htmlspecialchars($search); ?>"</span>
              <?php endif; ?>
            </div>

            <div class="pagination-nav">
              <!-- First Page -->
              <button onclick="goToPage(1)" class="pagination-btn <?php echo ($current_page == 1) ? 'disabled' : ''; ?>"
                <?php echo ($current_page == 1) ? 'disabled' : ''; ?> title="First Page">
                <i class="fas fa-angle-double-left"></i>
              </button>

              <!-- Previous Page -->
              <button onclick="goToPage(<?php echo $current_page - 1; ?>)" class="pagination-btn <?php echo ($current_page == 1) ? 'disabled' : ''; ?>"
                <?php echo ($current_page == 1) ? 'disabled' : ''; ?> title="Previous Page">
                <i class="fas fa-angle-left"></i>
              </button>

              <!-- Page numbers -->
              <?php
              // Calculate page range
              $start_page = max(1, $current_page - 2);
              $end_page = min($total_pages, $current_page + 2);

              // Adjust if at the beginning
              if ($start_page > 1) {
                echo '<button onclick="goToPage(1)" class="pagination-btn">1</button>';
                if ($start_page > 2) {
                  echo '<span class="pagination-btn pagination-ellipsis">...</span>';
                }
              }

              // Display page numbers
              for ($i = $start_page; $i <= $end_page; $i++) {
                $active = ($i == $current_page) ? 'active' : '';
                echo '<button onclick="goToPage(' . $i . ')" class="pagination-btn ' . $active . '">' . $i . '</button>';
              }

              // Adjust if at the end
              if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                  echo '<span class="pagination-btn pagination-ellipsis">...</span>';
                }
                echo '<button onclick="goToPage(' . $total_pages . ')" class="pagination-btn">' . $total_pages . '</button>';
              }
              ?>

              <!-- Next Page -->
              <button onclick="goToPage(<?php echo $current_page + 1; ?>)" class="pagination-btn <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>"
                <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?> title="Next Page">
                <i class="fas fa-angle-right"></i>
              </button>

              <!-- Last Page -->
              <button onclick="goToPage(<?php echo $total_pages; ?>)" class="pagination-btn <?php echo ($current_page == $total_pages) ? 'disabled' : ''; ?>"
                <?php echo ($current_page == $total_pages) ? 'disabled' : ''; ?> title="Last Page">
                <i class="fas fa-angle-double-right"></i>
              </button>
            </div>
          </div>
        <?php elseif ($total_records == 0 && (!empty($search) || !empty($office_filter) || !empty($occupation_filter) || !empty($eligibility_filter))): ?>
          <div class="pagination-container">
            <div class="pagination-info">
              <span class="text-gray-600">No records found with the current filters</span>
              <?php if (!empty($search)): ?>
                <span class="badge badge-info ml-2">Search: "<?php echo htmlspecialchars($search); ?>"</span>
              <?php endif; ?>
              <a href="<?php echo $current_file; ?>" class="ml-4 text-blue-600 hover:text-blue-800 hover:underline">
                <i class="fas fa-times mr-1"></i> Clear all filters
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- Add Employee Modal -->
  <div class="modal-fixed-container" id="addEmployeeModal">
    <div class="modal-content-wrapper modal-xl">
      <!-- Modal Header -->
      <div class="modal-header">
        <div>
          <h3>Add New Job Order Employee</h3>
          <p>Fill in the employee information below</p>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeModal('addEmployeeModal')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Step Navigation -->
      <div class="step-navigation p-6 pb-0">
        <div class="step-item active" data-step="1">
          <div class="step-number">1</div>
          <div class="step-label">Professional Info</div>
        </div>
        <div class="step-item" data-step="2">
          <div class="step-number">2</div>
          <div class="step-label">Personal Info</div>
        </div>
        <div class="step-item" data-step="3">
          <div class="step-number">3</div>
          <div class="step-label">Documents</div>
        </div>
      </div>

      <!-- Form -->
      <form id="employeeForm" action="<?php echo htmlspecialchars($current_file); ?>" method="POST"
        enctype="multipart/form-data" onsubmit="return validateAddForm()">
        <input type="hidden" name="add_submit" value="1">

        <div class="modal-body">
          <!-- Step 1: Professional Information -->
          <div id="step1" class="form-step active">
            <div class="grid-2 overflow-auto">
              <!-- Employee ID Field -->
              <div class="form-group">
                <label for="employee_id" class="form-label">Employee ID *</label>
                <input type="text" name="employee_id" id="employee_id" class="form-control" placeholder="e.g., JO-001"
                  required>
                <div class="error-message hidden" id="error-employee_id"></div>
              </div>

              <div class="form-group">
                <label for="employee_name" class="form-label">Full Name *</label>
                <input type="text" name="employee_name" id="employee_name" class="form-control"
                  placeholder="Enter employee full name" required>
                <div class="error-message hidden" id="error-employee_name"></div>
              </div>

              <div class="form-group">
                <label for="occupation" class="form-label">Occupation *</label>
                <input type="text" name="occupation" id="occupation" class="form-control"
                  placeholder="Job title/position" required>
                <div class="error-message hidden" id="error-occupation"></div>
              </div>

              <div class="form-group">
                <label for="office" class="form-label">Office Assignment *</label>
                <select name="office" id="office" class="form-control" required>
                  <option value="">Select Department</option>
                  <option value="Office of the Municipal Mayor">Office of the Municipal Mayor</option>
                  <option value="Human Resource Management Division">Human Resource Management Division</option>
                  <option value="Business Permit and Licensing Division">Business Permit and Licensing Division
                  </option>
                  <option value="Sangguniang Bayan Office">Sangguniang Bayan Office</option>
                  <option value="Office of the Municipal Accountant">Office of the Municipal Accountant</option>
                  <option value="Office of the Assessor">Office of the Assessor</option>
                  <option value="Municipal Budget Office">Municipal Budget Office</option>
                  <option value="Municipal Planning and Development Office">Municipal Planning and Development Office
                  </option>
                  <option value="Municipal Engineering Office">Municipal Engineering Office</option>
                  <option value="Municipal Disaster Risk Reduction and Management Office">Municipal Disaster Risk
                    Reduction and Management Office</option>
                  <option value="Municipal Social Welfare and Development Office">Municipal Social Welfare and
                    Development Office</option>
                  <option value="Municipal Environment and Natural Resources Office">Municipal Environment and Natural
                    Resources Office</option>
                  <option value="Office of the Municipal Agriculturist">Office of the Municipal Agriculturist</option>
                  <option value="Municipal General Services Office">Municipal General Services Office</option>
                  <option value="Municipal Public Employment Service Office">Municipal Public Employment Service
                    Office</option>
                  <option value="Municipal Health Office">Municipal Health Office</option>
                  <option value="Municipal Treasurer's Office">Municipal Treasurer's Office</option>
                </select>
                <div class="error-message hidden" id="error-office"></div>
              </div>

              <div class="form-group">
                <label for="rate_per_day" class="form-label">Rate per Day (₱) *</label>
                <input type="number" step="0.01" min="0" name="rate_per_day" id="rate_per_day" class="form-control"
                  placeholder="0.00" required>
                <div class="error-message hidden" id="error-rate_per_day"></div>
              </div>

              <div class="form-group">
                <label for="sss_contribution" class="form-label">SSS Contribution</label>
                <input type="text" name="sss_contribution" id="sss_contribution" class="form-control"
                  placeholder="Enter SSS Contribution amount or details">
              </div>

              <div class="form-group">
                <label for="ctc_number" class="form-label">CTC Number *</label>
                <input type="text" name="ctc_number" id="ctc_number" class="form-control" placeholder="CTC No."
                  required>
                <div class="error-message hidden" id="error-ctc_number"></div>
              </div>

              <div class="form-group">
                <label for="ctc_date" class="form-label">CTC Date *</label>
                <input type="date" name="ctc_date" id="ctc_date" class="form-control" required>
                <div class="error-message hidden" id="error-ctc_date"></div>
              </div>

              <div class="form-group col-span-2">
                <label for="place_of_issue" class="form-label">Place of Issue *</label>
                <input type="text" name="place_of_issue" id="place_of_issue" class="form-control"
                  placeholder="City/Municipality where CTC was issued" required>
                <div class="error-message hidden" id="error-place_of_issue"></div>
              </div>
            </div>
          </div>

          <!-- Step 2: Personal Information -->
          <div id="step2" class="form-step">
            <!-- Profile Image Upload -->
            <div class="profile-image-upload">
              <div class="profile-image-container">
                <div class="profile-image-placeholder">
                  <i class="fas fa-user"></i>
                </div>
              </div>
              <input type="file" name="profile_image" id="profile_image" accept="image/*" class="hidden">
              <button type="button" class="profile-upload-btn"
                onclick="document.getElementById('profile_image').click()">
                <i class="fas fa-upload"></i>
                Upload Profile Photo
              </button>
              <small class="text-gray-500">JPG, JPEG, PNG (Max 5MB)</small>
            </div>

            <div class="grid-2">
              <div class="form-group">
                <label for="first_name" class="form-label">First Name *</label>
                <input type="text" name="first_name" id="first_name" class="form-control" required>
                <div class="error-message hidden" id="error-first_name"></div>
              </div>

              <div class="form-group">
                <label for="last_name" class="form-label">Last Name *</label>
                <input type="text" name="last_name" id="last_name" class="form-control" required>
                <div class="error-message hidden" id="error-last_name"></div>
              </div>

              <div class="form-group">
                <label for="mobile_number" class="form-label">Mobile Number *</label>
                <input type="tel" name="mobile_number" id="mobile_number" class="form-control"
                  placeholder="09XXXXXXXXX" required>
                <div class="error-message hidden" id="error-mobile_number"></div>
              </div>

              <div class="form-group">
                <label for="email_address" class="form-label">Email Address *</label>
                <input type="email" name="email_address" id="email_address" class="form-control" required>
                <div class="error-message hidden" id="error-email_address"></div>
              </div>

              <div class="form-group">
                <label for="date_of_birth" class="form-label">Date of Birth *</label>
                <input type="date" name="date_of_birth" id="date_of_birth" class="form-control" required>
                <div class="error-message hidden" id="error-date_of_birth"></div>
              </div>

              <div class="form-group">
                <label for="marital_status" class="form-label">Marital Status *</label>
                <select name="marital_status" id="marital_status" class="form-control" required>
                  <option value="">Select Status</option>
                  <option value="Single">Single</option>
                  <option value="Married">Married</option>
                  <option value="Divorced">Divorced</option>
                  <option value="Widowed">Widowed</option>
                </select>
                <div class="error-message hidden" id="error-marital_status"></div>
              </div>

              <div class="form-group">
                <label for="gender" class="form-label">Gender *</label>
                <select name="gender" id="gender" class="form-control" required>
                  <option value="">Select Gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
                <div class="error-message hidden" id="error-gender"></div>
              </div>

              <div class="form-group">
                <label for="nationality" class="form-label">Nationality *</label>
                <input type="text" name="nationality" id="nationality" class="form-control" value="Filipino" required>
              </div>

              <div class="form-group col-span-2">
                <label for="street_address" class="form-label">Street Address *</label>
                <input type="text" name="street_address" id="street_address" class="form-control" required>
                <div class="error-message hidden" id="error-street_address"></div>
              </div>

              <div class="form-group">
                <label for="city" class="form-label">City *</label>
                <input type="text" name="city" id="city" class="form-control" required>
                <div class="error-message hidden" id="error-city"></div>
              </div>

              <div class="form-group">
                <label for="state_region" class="form-label">State/Region *</label>
                <input type="text" name="state_region" id="state_region" class="form-control" required>
                <div class="error-message hidden" id="error-state_region"></div>
              </div>

              <div class="form-group">
                <label for="zip_code" class="form-label">ZIP Code *</label>
                <input type="text" name="zip_code" id="zip_code" class="form-control" required>
                <div class="error-message hidden" id="error-zip_code"></div>
              </div>

              <!-- REMOVED: Password fields as job order employees don't need system login -->

              <div class="form-group">
                <label for="joining_date" class="form-label">Joining Date *</label>
                <input type="date" name="joining_date" id="joining_date" class="form-control" required>
                <div class="error-message hidden" id="error-joining_date"></div>
              </div>

              <div class="form-group">
                <label class="form-label">Civil Service Eligibility *</label>
                <div class="flex space-x-4 mt-2">
                  <label class="inline-flex items-center">
                    <input type="radio" name="eligibility" value="Eligible" class="w-4 h-4 text-blue-600" checked>
                    <span class="ml-2">Eligible</span>
                  </label>
                  <label class="inline-flex items-center">
                    <input type="radio" name="eligibility" value="Not Eligible" class="w-4 h-4 text-blue-600">
                    <span class="ml-2">Not Eligible</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 3: Documents -->
          <div id="step3" class="form-step">
            <div class="grid-2">
              <!-- Government ID -->
              <div class="file-upload-zone" data-file-input="doc_id">
                <div class="file-upload-icon">
                  <i class="fas fa-id-card"></i>
                </div>
                <div class="file-upload-text">
                  <h4>Government Issued ID</h4>
                  <p>Upload government-issued identification</p>
                  <small>JPG, JPEG, PDF, PNG (Max 5MB)</small>
                </div>
                <input type="file" name="doc_id" id="doc_id" accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                <div class="file-preview hidden"></div>
              </div>

              <!-- Resume -->
              <div class="file-upload-zone" data-file-input="doc_resume">
                <div class="file-upload-icon">
                  <i class="fas fa-file-alt"></i>
                </div>
                <div class="file-upload-text">
                  <h4>Resume / CV</h4>
                  <p>Upload resume or curriculum vitae</p>
                  <small>JPG, JPEG, PDF, PNG (Max 5MB)</small>
                </div>
                <input type="file" name="doc_resume" id="doc_resume" accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                <div class="file-preview hidden"></div>
              </div>

              <!-- Service Record -->
              <div class="file-upload-zone" data-file-input="doc_service">
                <div class="file-upload-icon">
                  <i class="fas fa-history"></i>
                </div>
                <div class="file-upload-text">
                  <h4>Service Record</h4>
                  <p>Upload service record document</p>
                  <small>JPG, JPEG, PDF, PNG (Max 5MB)</small>
                </div>
                <input type="file" name="doc_service" id="doc_service" accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                <div class="file-preview hidden"></div>
              </div>

              <!-- Appointment Paper -->
              <div class="file-upload-zone" data-file-input="doc_appointment">
                <div class="file-upload-icon">
                  <i class="fas fa-file-contract"></i>
                </div>
                <div class="file-upload-text">
                  <h4>Appointment Paper</h4>
                  <p>Upload appointment paper</p>
                  <small>JPG, JPEG, PDF, PNG (Max 5MB)</small>
                </div>
                <input type="file" name="doc_appointment" id="doc_appointment" accept=".jpg,.jpeg,.pdf,.png"
                  class="hidden">
                <div class="file-preview hidden"></div>
              </div>

              <!-- Transcript -->
              <div class="file-upload-zone" data-file-input="doc_transcript">
                <div class="file-upload-icon">
                  <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="file-upload-text">
                  <h4>Transcript of Records</h4>
                  <p>Upload transcript and diploma</p>
                  <small>JPG, JPEG, PDF, PNG (Max 5MB)</small>
                </div>
                <input type="file" name="doc_transcript" id="doc_transcript" accept=".jpg,.jpeg,.pdf,.png"
                  class="hidden">
                <div class="file-preview hidden"></div>
              </div>

              <!-- Eligibility Certificate -->
              <div class="file-upload-zone" data-file-input="doc_eligibility">
                <div class="file-upload-icon">
                  <i class="fas fa-award"></i>
                </div>
                <div class="file-upload-text">
                  <h4>Eligibility Certificate</h4>
                  <p>Upload civil service eligibility</p>
                  <small>JPG, JPEG, PDF, PNG (Max 5MB)</small>
                </div>
                <input type="file" name="doc_eligibility" id="doc_eligibility" accept=".jpg,.jpeg,.pdf,.png"
                  class="hidden">
                <div class="file-preview hidden"></div>
              </div>
            </div>

            <div class="mt-6 p-4 bg-blue-50 rounded-lg">
              <div class="flex items-start gap-3">
                <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                <div>
                  <p class="text-sm text-blue-800 font-medium">Document Upload Guidelines</p>
                  <ul class="text-xs text-blue-700 mt-1 space-y-1">
                    <li>• Allowed file types: JPG, JPEG, PNG, PDF</li>
                    <li>• Maximum file size: 5MB per document</li>
                    <li>• Ensure documents are clear and readable</li>
                    <li>• Sensitive information should be properly redacted</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>


          <!-- Modal Footer -->
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="prevStepBtn" onclick="prevStep()" disabled>
              <i class="fas fa-arrow-left"></i> Previous
            </button>
            <button type="button" class="btn btn-primary" id="nextStepBtn" onclick="nextStep()">
              Next <i class="fas fa-arrow-right"></i>
            </button>
            <button type="submit" class="btn btn-success hidden" id="submitFormBtn">
              <i class="fas fa-check-circle"></i> Submit Employee
            </button>
          </div>
        </div>

      </form>
    </div>
  </div>

  <!-- View Employee Modal -->
  <?php if ($view_employee): ?>
    <div class="modal-fixed-container active" id="viewEmployeeModal">
      <div class="modal-content-wrapper modal-xl">
        <!-- Modal Header -->
        <div class="modal-header">
          <div>
            <h3>Employee Details</h3>
            <p>Complete employee information</p>
          </div>
          <button type="button" class="modal-close-btn" onclick="closeModal('viewEmployeeModal')">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <div class="modal-body">
          <!-- Profile Header -->
          <div class="employee-profile-header">
            <?php if (!empty($view_employee['profile_image_path'])): ?>
              <img src="<?= htmlspecialchars($upload_dir . $view_employee['profile_image_path']) ?>" alt="Profile"
                class="profile-avatar-large">
            <?php else: ?>
              <div
                class="profile-avatar-large bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                <i class="fas fa-user text-white text-3xl"></i>
              </div>
            <?php endif; ?>

            <div class="profile-info">
              <h3><?= htmlspecialchars($view_employee['employee_name']) ?></h3>
              <p><?= htmlspecialchars($view_employee['occupation']) ?> •
                <?= htmlspecialchars($view_employee['office']) ?>
              </p>

              <div class="profile-meta">
                <div class="profile-meta-item">
                  <i class="fas fa-id-badge"></i>
                  <span>Employee ID: <?= htmlspecialchars($view_employee['employee_id']) ?></span>
                </div>
                <div class="profile-meta-item">
                  <i class="fas fa-calendar-alt"></i>
                  <span>Joined:
                    <?= !empty($view_employee['joining_date']) ? date('F j, Y', strtotime($view_employee['joining_date'])) : 'N/A' ?></span>
                </div>
                <div class="profile-meta-item">
                  <i class="fas fa-briefcase"></i>
                  <span>Job Order Employee</span>
                </div>
                <?php if ($view_employee['is_archived']): ?>
                  <div class="profile-meta-item">
                    <i class="fas fa-archive"></i>
                    <span class="text-orange-600 font-semibold">Archived</span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Main Content Grid -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column -->
            <div class="space-y-6">
              <!-- Contact Information -->
              <div class="info-section">
                <div class="section-title">
                  <i class="fas fa-address-book"></i>
                  <span>Contact Information</span>
                </div>
                <div class="info-grid info-grid-2">
                  <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?= htmlspecialchars($view_employee['email_address']) ?></div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Mobile</div>
                    <div class="info-value"><?= htmlspecialchars($view_employee['mobile_number']) ?></div>
                  </div>
                  <div class="info-item col-span-2">
                    <div class="info-label">Address</div>
                    <div class="info-value">
                      <?= htmlspecialchars($view_employee['street_address']) ?><br>
                      <?= htmlspecialchars($view_employee['city']) ?>,
                      <?= htmlspecialchars($view_employee['state_region']) ?><br>
                      <?= htmlspecialchars($view_employee['zip_code']) ?>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Personal Details -->
              <div class="info-section">
                <div class="section-title">
                  <i class="fas fa-user"></i>
                  <span>Personal Details</span>
                </div>
                <div class="info-grid info-grid-3">
                  <div class="info-item">
                    <div class="info-label">First Name</div>
                    <div class="info-value"><?= htmlspecialchars($view_employee['first_name']) ?></div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Last Name</div>
                    <div class="info-value"><?= htmlspecialchars($view_employee['last_name']) ?></div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Date of Birth</div>
                    <div class="info-value">
                      <?= !empty($view_employee['date_of_birth']) ? date('F j, Y', strtotime($view_employee['date_of_birth'])) : 'N/A' ?>
                    </div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Gender</div>
                    <div class="info-value"><?= htmlspecialchars($view_employee['gender']) ?></div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Marital Status</div>
                    <div class="info-value"><?= htmlspecialchars($view_employee['marital_status']) ?></div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Nationality</div>
                    <div class="info-value"><?= htmlspecialchars($view_employee['nationality']) ?></div>
                  </div>
                </div>
              </div>

              <!-- Documents -->
              <div class="info-section">
                <div class="section-title">
                  <i class="fas fa-folder"></i>
                  <span>Documents</span>
                </div>
                <div class="document-grid">
                  <?php
                  $documents = [
                    'Government ID' => $view_employee['doc_id_path'],
                    'Resume/CV' => $view_employee['doc_resume_path'],
                    'Service Record' => $view_employee['doc_service_path'],
                    'Appointment Paper' => $view_employee['doc_appointment_path'],
                    'Transcript' => $view_employee['doc_transcript_path'],
                    'Eligibility Certificate' => $view_employee['doc_eligibility_path']
                  ];

                  foreach ($documents as $doc_name => $doc_path):
                  ?>
                    <div class="document-item">
                      <div class="document-icon">
                        <?php if (!empty($doc_path)): ?>
                          <?php if (pathinfo($doc_path, PATHINFO_EXTENSION) === 'pdf'): ?>
                            <i class="fas fa-file-pdf text-red-500"></i>
                          <?php else: ?>
                            <i class="fas fa-file-image text-blue-500"></i>
                          <?php endif; ?>
                        <?php else: ?>
                          <i class="fas fa-file text-gray-400"></i>
                        <?php endif; ?>
                      </div>
                      <div class="document-info">
                        <div class="document-name text-truncate"><?= $doc_name ?></div>
                        <div class="document-status <?= !empty($doc_path) ? 'available' : 'unavailable' ?>">
                          <?= !empty($doc_path) ? 'Uploaded' : 'Not uploaded' ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">
              <!-- Employment Details -->
              <div class="info-section">
                <div class="section-title">
                  <i class="fas fa-briefcase"></i>
                  <span>Employment Details</span>
                </div>
                <div class="info-grid info-grid-2">
                  <div class="info-item">
                    <div class="info-label">Employee ID</div>
                    <div class="info-value font-mono font-semibold">
                      <?= htmlspecialchars($view_employee['employee_id']) ?>
                    </div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Position</div>
                    <div class="info-value"><?= htmlspecialchars($view_employee['occupation']) ?></div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Office Assignment</div>
                    <div class="info-value"><?= htmlspecialchars($view_employee['office']) ?></div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Joining Date</div>
                    <div class="info-value">
                      <?= !empty($view_employee['joining_date']) ? date('F j, Y', strtotime($view_employee['joining_date'])) : 'N/A' ?>
                    </div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">CTC Number</div>
                    <div class="info-value"><?= htmlspecialchars($view_employee['ctc_number']) ?></div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">CTC Date</div>
                    <div class="info-value">
                      <?= !empty($view_employee['ctc_date']) ? date('F j, Y', strtotime($view_employee['ctc_date'])) : 'N/A' ?>
                    </div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Place of Issue</div>
                    <div class="info-value"><?= htmlspecialchars($view_employee['place_of_issue']) ?></div>
                  </div>
                </div>
              </div>

              <!-- Salary Information -->
              <div class="info-section">
                <div class="section-title">
                  <i class="fas fa-money-bill-wave"></i>
                  <span>Salary Information</span>
                </div>
                <div class="salary-cards">
                  <div class="salary-card primary">
                    <div class="salary-label">Monthly Salary</div>
                    <div class="salary-amount">
                      ₱<?= isset($monthly_salary) ? number_format($monthly_salary, 2) : '0.00' ?></div>
                    <small class="text-gray-600">(Rate/day × 22 days)</small>
                  </div>
                  <div class="salary-card success">
                    <div class="salary-label">Amount Accrued</div>
                    <div class="salary-amount">
                      ₱<?= isset($amount_accrued) ? number_format($amount_accrued, 2) : '0.00' ?></div>
                    <small class="text-gray-600">(10% of monthly)</small>
                  </div>
                  <div class="salary-card warning">
                    <div class="salary-label">Eligibility Status</div>
                    <div class="salary-amount"><?= htmlspecialchars($view_employee['eligibility']) ?></div>
                    <small class="text-gray-600">Civil Service</small>
                  </div>
                </div>
                <div class="info-grid info-grid-2 mt-4">
                  <div class="info-item">
                    <div class="info-label">Rate per Day</div>
                    <div class="info-value font-bold">₱<?= number_format($view_employee['rate_per_day'], 2) ?></div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">SSS Contribution</div>
                    <div class="info-value"><?= htmlspecialchars($view_employee['sss_contribution']) ?: 'N/A' ?></div>
                  </div>
                </div>
              </div>

              <!-- Account Information -->
              <div class="info-section">
                <div class="section-title">
                  <i class="fas fa-key"></i>
                  <span>Account Information</span>
                </div>
                <div class="info-grid info-grid-2">
                  <div class="info-item">
                    <div class="info-label">Account Created</div>
                    <div class="info-value"><?= date('F j, Y', strtotime($view_employee['joining_date'])) ?></div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Account Status</div>
                    <div class="info-value">
                      <?php if ($view_employee['is_archived']): ?>
                        <span class="badge badge-danger">Archived</span>
                      <?php else: ?>
                        <span class="badge badge-success">Active</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
          <div class="text-sm text-gray-600">
            <i class="fas fa-info-circle mr-1"></i>
            Last updated: <?= date('F j, Y \a\t g:i A') ?>
          </div>
          <div class="flex gap-3">
            <button onclick="closeModal('viewEmployeeModal')" class="btn btn-secondary">
              Close
            </button>
            <?php if (!$view_employee['is_archived']): ?>
              <button type="button" class="btn btn-primary edit-from-view-btn"
                data-employee-id="<?= $view_employee['id'] ?>">
                <i class="fas fa-edit mr-2"></i>
                Edit Employee
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Edit Employee Modal -->
  <div class="modal-fixed-container" id="editEmployeeModal">
    <div class="modal-content-wrapper modal-xl">
      <!-- Modal Header -->
      <div class="modal-header">
        <div>
          <h3>Edit Employee</h3>
          <p>Update employee information</p>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeModal('editEmployeeModal')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Tab Navigation -->
      <div class="modal-tabs">
        <div class="modal-tab active" data-tab="professional">
          <i class="fas fa-briefcase"></i>
          <span>Professional Info</span>
        </div>
        <div class="modal-tab" data-tab="personal">
          <i class="fas fa-user"></i>
          <span>Personal Info</span>
        </div>
        <div class="modal-tab" data-tab="documents">
          <i class="fas fa-file-alt"></i>
          <span>Documents</span>
        </div>
      </div>

      <!-- Form -->
      <form id="editEmployeeForm" action="<?php echo htmlspecialchars($current_file); ?>" method="POST"
        enctype="multipart/form-data" onsubmit="return validateEditForm()">
        <input type="hidden" name="employee_id" id="edit_employee_id">
        <input type="hidden" name="update_submit" value="1">

        <div class="modal-body">
          <!-- Professional Info Tab -->
          <div id="edit-tab-professional" class="edit-tab-content active">
            <div class="grid-2">
              <!-- Employee ID Field -->
              <div class="form-group">
                <label for="edit_employee_id_field" class="form-label">Employee ID *</label>
                <input type="text" name="employee_id_field" id="edit_employee_id_field" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_employee_name" class="form-label">Full Name *</label>
                <input type="text" name="employee_name" id="edit_employee_name" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_occupation" class="form-label">Occupation *</label>
                <input type="text" name="occupation" id="edit_occupation" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_office" class="form-label">Office Assignment *</label>
                <select name="office" id="edit_office" class="form-control" required>
                  <option value="">Select Department</option>
                  <option value="Office of the Municipal Mayor">Office of the Municipal Mayor</option>
                  <option value="Human Resource Management Division">Human Resource Management Division</option>
                  <option value="Business Permit and Licensing Division">Business Permit and Licensing Division
                  </option>
                  <option value="Sangguniang Bayan Office">Sangguniang Bayan Office</option>
                  <option value="Office of the Municipal Accountant">Office of the Municipal Accountant</option>
                  <option value="Office of the Assessor">Office of the Assessor</option>
                  <option value="Municipal Budget Office">Municipal Budget Office</option>
                  <option value="Municipal Planning and Development Office">Municipal Planning and Development Office
                  </option>
                  <option value="Municipal Engineering Office">Municipal Engineering Office</option>
                  <option value="Municipal Disaster Risk Reduction and Management Office">Municipal Disaster Risk
                    Reduction and Management Office</option>
                  <option value="Municipal Social Welfare and Development Office">Municipal Social Welfare and
                    Development Office</option>
                  <option value="Municipal Environment and Natural Resources Office">Municipal Environment and Natural
                    Resources Office</option>
                  <option value="Office of the Municipal Agriculturist">Office of the Municipal Agriculturist</option>
                  <option value="Municipal General Services Office">Municipal General Services Office</option>
                  <option value="Municipal Public Employment Service Office">Municipal Public Employment Service
                    Office</option>
                  <option value="Municipal Health Office">Municipal Health Office</option>
                  <option value="Municipal Treasurer's Office">Municipal Treasurer's Office</option>
                </select>
              </div>

              <div class="form-group">
                <label for="edit_rate_per_day" class="form-label">Rate per Day (₱) *</label>
                <input type="number" step="0.01" min="0" name="rate_per_day" id="edit_rate_per_day"
                  class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_sss_contribution" class="form-label">SSS Contribution</label>
                <input type="text" name="sss_contribution" id="edit_sss_contribution" class="form-control"
                  placeholder="Enter SSS Contribution amount or details">
              </div>

              <div class="form-group">
                <label for="edit_ctc_number" class="form-label">CTC Number *</label>
                <input type="text" name="ctc_number" id="edit_ctc_number" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_ctc_date" class="form-label">CTC Date *</label>
                <input type="date" name="ctc_date" id="edit_ctc_date" class="form-control" required>
              </div>

              <div class="form-group col-span-2">
                <label for="edit_place_of_issue" class="form-label">Place of Issue *</label>
                <input type="text" name="place_of_issue" id="edit_place_of_issue" class="form-control" required>
              </div>
            </div>
          </div>

          <!-- Personal Info Tab -->
          <div id="edit-tab-personal" class="edit-tab-content hidden">
            <!-- Profile Image Upload -->
            <div class="profile-image-upload">
              <div class="profile-image-container" id="edit-profile-image-container">
                <div class="profile-image-placeholder">
                  <i class="fas fa-user"></i>
                </div>
              </div>
              <input type="file" name="profile_image" id="edit_profile_image" accept="image/*" class="hidden">
              <button type="button" class="profile-upload-btn"
                onclick="document.getElementById('edit_profile_image').click()">
                <i class="fas fa-upload"></i>
                Update Profile Photo
              </button>
              <small class="text-gray-500">Leave empty to keep current photo</small>
            </div>

            <div class="grid-2">
              <div class="form-group">
                <label for="edit_first_name" class="form-label">First Name *</label>
                <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_last_name" class="form-label">Last Name *</label>
                <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_mobile_number" class="form-label">Mobile Number *</label>
                <input type="tel" name="mobile_number" id="edit_mobile_number" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_email_address" class="form-label">Email Address *</label>
                <input type="email" name="email_address" id="edit_email_address" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_date_of_birth" class="form-label">Date of Birth *</label>
                <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_marital_status" class="form-label">Marital Status *</label>
                <select name="marital_status" id="edit_marital_status" class="form-control" required>
                  <option value="">Select Status</option>
                  <option value="Single">Single</option>
                  <option value="Married">Married</option>
                  <option value="Divorced">Divorced</option>
                  <option value="Widowed">Widowed</option>
                </select>
              </div>

              <div class="form-group">
                <label for="edit_gender" class="form-label">Gender *</label>
                <select name="gender" id="edit_gender" class="form-control" required>
                  <option value="">Select Gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>

              <div class="form-group">
                <label for="edit_nationality" class="form-label">Nationality *</label>
                <input type="text" name="nationality" id="edit_nationality" class="form-control" value="Filipino"
                  required>
              </div>

              <div class="form-group col-span-2">
                <label for="edit_street_address" class="form-label">Street Address *</label>
                <input type="text" name="street_address" id="edit_street_address" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_city" class="form-label">City *</label>
                <input type="text" name="city" id="edit_city" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_state_region" class="form-label">State/Region *</label>
                <input type="text" name="state_region" id="edit_state_region" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_zip_code" class="form-label">ZIP Code *</label>
                <input type="text" name="zip_code" id="edit_zip_code" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="edit_joining_date" class="form-label">Joining Date *</label>
                <input type="date" name="joining_date" id="edit_joining_date" class="form-control" required>
              </div>

              <div class="form-group">
                <label class="form-label">Civil Service Eligibility *</label>
                <div class="flex space-x-4 mt-2">
                  <label class="inline-flex items-center">
                    <input type="radio" name="eligibility" value="Eligible" class="w-4 h-4 text-blue-600">
                    <span class="ml-2">Eligible</span>
                  </label>
                  <label class="inline-flex items-center">
                    <input type="radio" name="eligibility" value="Not Eligible" class="w-4 h-4 text-blue-600">
                    <span class="ml-2">Not Eligible</span>
                  </label>
                </div>
              </div>

              <!-- REMOVED: Password update section as job order employees don't need system login -->
            </div>
          </div>

          <!-- Documents Tab -->
          <div id="edit-tab-documents" class="edit-tab-content hidden">
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Government ID</label>
                <input type="file" name="doc_id" class="form-control">
                <small class="text-gray-500">Upload to replace existing file</small>
              </div>

              <div class="form-group">
                <label class="form-label">Resume/CV</label>
                <input type="file" name="doc_resume" class="form-control">
                <small class="text-gray-500">Upload to replace existing file</small>
              </div>

              <div class="form-group">
                <label class="form-label">Service Record</label>
                <input type="file" name="doc_service" class="form-control">
                <small class="text-gray-500">Upload to replace existing file</small>
              </div>

              <div class="form-group">
                <label class="form-label">Appointment Paper</label>
                <input type="file" name="doc_appointment" class="form-control">
                <small class="text-gray-500">Upload to replace existing file</small>
              </div>

              <div class="form-group">
                <label class="form-label">Transcript</label>
                <input type="file" name="doc_transcript" class="form-control">
                <small class="text-gray-500">Upload to replace existing file</small>
              </div>

              <div class="form-group">
                <label class="form-label">Eligibility Certificate</label>
                <input type="file" name="doc_eligibility" class="form-control">
                <small class="text-gray-500">Upload to replace existing file</small>
              </div>
            </div>

            <div class="mt-6 p-4 bg-blue-50 rounded-lg">
              <div class="flex items-start gap-3">
                <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                <div>
                  <p class="text-sm text-blue-800 font-medium">Document Update Note</p>
                  <p class="text-xs text-blue-700 mt-1">Existing documents will be kept if no new files are uploaded.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary edit-tab-prev hidden">
            <i class="fas fa-arrow-left"></i> Previous
          </button>
          <button type="button" class="btn btn-primary edit-tab-next">
            Next <i class="fas fa-arrow-right"></i>
          </button>
          <button type="submit" class="btn btn-success edit-tab-submit hidden">
            <i class="fas fa-save"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Archive Employee Modal -->
  <div class="modal-fixed-container" id="archiveEmployeeModal">
    <div class="modal-content-wrapper modal-sm">
      <!-- Modal Header -->
      <div class="modal-header">
        <div>
          <h3>Archive Employee</h3>
          <p>This action can be reversed</p>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeModal('archiveEmployeeModal')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body text-center">
        <div class="mb-4">
          <div class="w-16 h-16 mx-auto mb-4 bg-orange-100 rounded-full flex items-center justify-center">
            <i class="fas fa-archive text-orange-600 text-2xl"></i>
          </div>
          <h4 class="text-lg font-semibold text-gray-900 mb-2">Confirm Archive</h4>
          <p class="text-gray-600">Are you sure you want to archive <span id="archive_employee_name"
              class="font-semibold"></span>?</p>
          <p class="text-sm text-gray-500 mt-2">Archived employees will be hidden from the main list but can be
            restored later.</p>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <form id="archiveEmployeeForm" action="<?php echo htmlspecialchars($current_file); ?>" method="POST"
          class="w-full">
          <input type="hidden" name="employee_id" id="archive_employee_id">
          <input type="hidden" name="archive_action" value="archive">
          <input type="hidden" name="archive_submit" value="1">
          <div class="flex gap-3 w-full">
            <button type="button" class="btn btn-secondary flex-1" onclick="closeModal('archiveEmployeeModal')">
              Cancel
            </button>
            <button type="submit" class="btn btn-warning flex-1">
              <i class="fas fa-archive mr-2"></i> Archive
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Restore Employee Modal -->
  <div class="modal-fixed-container" id="restoreEmployeeModal">
    <div class="modal-content-wrapper modal-sm">
      <!-- Modal Header -->
      <div class="modal-header">
        <div>
          <h3>Restore Employee</h3>
          <p>Restore from archive</p>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeModal('restoreEmployeeModal')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body text-center">
        <div class="mb-4">
          <div class="w-16 h-16 mx-auto mb-4 bg-green-100 rounded-full flex items-center justify-center">
            <i class="fas fa-history text-green-600 text-2xl"></i>
          </div>
          <h4 class="text-lg font-semibold text-gray-900 mb-2">Confirm Restore</h4>
          <p class="text-gray-600">Are you sure you want to restore <span id="restore_employee_name"
              class="font-semibold"></span>?</p>
          <p class="text-sm text-gray-500 mt-2">This will make the employee visible in the main list again.</p>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <form id="restoreEmployeeForm" action="<?php echo htmlspecialchars($current_file); ?>" method="POST"
          class="w-full">
          <input type="hidden" name="employee_id" id="restore_employee_id">
          <input type="hidden" name="archive_action" value="restore">
          <input type="hidden" name="archive_submit" value="1">
          <div class="flex gap-3 w-full">
            <button type="button" class="btn btn-secondary flex-1" onclick="closeModal('restoreEmployeeModal')">
              Cancel
            </button>
            <button type="submit" class="btn btn-success flex-1">
              <i class="fas fa-history mr-2"></i> Restore
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div style="display: none;">
    <p>Upload Directory: <?php echo htmlspecialchars($upload_dir); ?></p>
    <p>Directory Exists: <?php echo file_exists($upload_dir) ? 'Yes' : 'No'; ?></p>
    <p>PDO Connection: <?php echo isset($pdo) ? 'Connected' : 'Not Connected'; ?></p>
  </div>

  <!-- JavaScript Libraries -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>

  <script>
    // ===============================================
    // PAGINATION FUNCTIONS
    // ===============================================

    // Change records per page
    function changeRecordsPerPage(value) {
      const url = new URL(window.location.href);
      url.searchParams.set('per_page', value);
      url.searchParams.set('page', 1); // Reset to first page
      window.location.href = url.toString();
    }

    // Go to specific page
    function goToPage(page) {
      const url = new URL(window.location.href);
      url.searchParams.set('page', page);

      // Preserve all current filters
      const show_archived = <?php echo $show_archived ? 'true' : 'false'; ?>;
      if (show_archived) {
        url.searchParams.set('show_archived', '1');
      }

      // Preserve search
      const search = document.getElementById('simple-search').value;
      if (search) {
        url.searchParams.set('search', search);
      }

      // Preserve other filters
      const office_filter = "<?php echo htmlspecialchars($office_filter); ?>";
      const occupation_filter = "<?php echo htmlspecialchars($occupation_filter); ?>";
      const eligibility_filter = "<?php echo htmlspecialchars($eligibility_filter); ?>";

      if (office_filter) {
        url.searchParams.set('office', office_filter);
      }
      if (occupation_filter) {
        url.searchParams.set('occupation', occupation_filter);
      }
      if (eligibility_filter) {
        url.searchParams.set('eligibility', eligibility_filter);
      }

      // Preserve records per page
      url.searchParams.set('per_page', <?php echo $records_per_page; ?>);

      window.location.href = url.toString();
    }

    // Search functionality (server-side)
    function searchEmployees() {
      const searchInput = document.getElementById('simple-search');
      if (!searchInput) return;

      const searchTerm = searchInput.value.trim();
      const url = new URL(window.location.href);

      if (searchTerm) {
        url.searchParams.set('search', searchTerm);
        url.searchParams.set('page', 1); // Reset to first page when searching
      } else {
        url.searchParams.delete('search');
      }

      // Preserve other filters
      const show_archived = <?php echo $show_archived ? 'true' : 'false'; ?>;
      if (show_archived) {
        url.searchParams.set('show_archived', '1');
      }

      const office_filter = "<?php echo htmlspecialchars($office_filter); ?>";
      const occupation_filter = "<?php echo htmlspecialchars($occupation_filter); ?>";
      const eligibility_filter = "<?php echo htmlspecialchars($eligibility_filter); ?>";

      if (office_filter) {
        url.searchParams.set('office', office_filter);
      }
      if (occupation_filter) {
        url.searchParams.set('occupation', occupation_filter);
      }
      if (eligibility_filter) {
        url.searchParams.set('eligibility', eligibility_filter);
      }

      // Preserve records per page
      url.searchParams.set('per_page', <?php echo $records_per_page; ?>);

      window.location.href = url.toString();
    }

    // ===============================================
    // INITIALIZATION
    // ===============================================

    document.addEventListener('DOMContentLoaded', function() {
      console.log('Pagination initialized');

      // Add Enter key support for search
      const searchInput = document.getElementById('simple-search');
      if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
          if (e.key === 'Enter') {
            searchEmployees();
          }
        });
      }

      // Auto-remove notifications after 5 seconds
      setTimeout(() => {
        const notifications = document.querySelectorAll('.notification');
        notifications.forEach(notification => {
          if (notification.parentElement) {
            notification.classList.add('hide');
            setTimeout(() => {
              if (notification.parentElement) {
                notification.remove();
              }
            }, 300);
          }
        });
      }, 5000);

      // Close notification buttons
      document.querySelectorAll('.notification-close').forEach(btn => {
        btn.addEventListener('click', function() {
          const notification = this.closest('.notification');
          if (notification) {
            notification.classList.add('hide');
            setTimeout(() => {
              if (notification.parentElement) {
                notification.remove();
              }
            }, 300);
          }
        });
      });
    });
  </script>
  <script>
    // Add pagination functionality
    function changeRecordsPerPage(value) {
      const url = new URL(window.location.href);
      url.searchParams.set('per_page', value);
      url.searchParams.set('page', 1); // Reset to first page
      window.location.href = url.toString();
    }

    // Update existing goToPage function to include all filters
    function goToPage(page) {
      const url = new URL(window.location.href);
      url.searchParams.set('page', page);

      // Preserve all current filters
      const show_archived = <?php echo $show_archived ? 'true' : 'false'; ?>;
      if (show_archived) {
        url.searchParams.set('show_archived', '1');
      }

      // Preserve other filters
      const office_filter = "<?php echo htmlspecialchars($office_filter); ?>";
      const occupation_filter = "<?php echo htmlspecialchars($occupation_filter); ?>";
      const eligibility_filter = "<?php echo htmlspecialchars($eligibility_filter); ?>";

      if (office_filter) {
        url.searchParams.set('office', office_filter);
      }
      if (occupation_filter) {
        url.searchParams.set('occupation', occupation_filter);
      }
      if (eligibility_filter) {
        url.searchParams.set('eligibility', eligibility_filter);
      }

      // Preserve records per page
      url.searchParams.set('per_page', <?php echo $records_per_page; ?>);

      window.location.href = url.toString();
    }

    // Update pagination buttons to use the new goToPage function
    document.addEventListener('DOMContentLoaded', function() {
      // Add loading indicators to pagination buttons
      document.querySelectorAll('.pagination-btn[href]').forEach(link => {
        link.addEventListener('click', function(e) {
          // Only show loading for page changes, not first/last/prev/next icons
          if (!this.querySelector('i')) {
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.classList.add('opacity-75');
          }
        });
      });

      // Auto-scroll to top when paginating
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('page')) {
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      }
    });

    // Global variables
    let currentStep = 1;
    const totalSteps = 3;

    // =========================================
    // 1. NOTIFICATION SYSTEM
    // =========================================

    // Show notification popup
    function showNotification(message, type = 'info', title = null) {
      const container = document.getElementById('notificationContainer');
      if (!container) return;

      const notification = document.createElement('div');
      notification.className = `notification notification-${type}`;

      // Set icon based on type
      let icon = 'info-circle';
      switch (type) {
        case 'success':
          icon = 'check-circle';
          break;
        case 'error':
          icon = 'exclamation-circle';
          break;
        case 'warning':
          icon = 'exclamation-triangle';
          break;
        default:
          icon = 'info-circle';
      }

      // Set title based on type
      let notificationTitle = title;
      if (!notificationTitle) {
        switch (type) {
          case 'success':
            notificationTitle = 'Success';
            break;
          case 'error':
            notificationTitle = 'Error';
            break;
          case 'warning':
            notificationTitle = 'Warning';
            break;
          default:
            notificationTitle = 'Information';
        }
      }

      notification.innerHTML = `
                <div class="notification-icon">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${notificationTitle}</div>
                    <div class="notification-message">${message}</div>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;

      container.appendChild(notification);

      // Add show class after a tiny delay
      setTimeout(() => {
        notification.classList.add('show');
      }, 10);

      // Auto remove after 5 seconds
      setTimeout(() => {
        if (notification.parentElement) {
          notification.classList.add('hide');
          setTimeout(() => {
            if (notification.parentElement) {
              notification.remove();
            }
          }, 300);
        }
      }, 5000);
    }

    // Show toast notification (simpler version)
    function showToast(message, type = 'info') {
      showNotification(message, type);
    }

    // =========================================
    // 2. MODAL MANAGEMENT
    // =========================================

    // Open modal
    function openModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
    }

    // Close modal
    function closeModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
      }
    }

    // =========================================
    // 3. FORM STEP MANAGEMENT
    // =========================================

    // Show step
    function showStep(stepNumber) {
      // Hide all steps
      for (let i = 1; i <= totalSteps; i++) {
        const stepElement = document.getElementById(`step${i}`);
        if (stepElement) {
          stepElement.classList.remove('active');
        }
        const stepNav = document.querySelector(`.step-item[data-step="${i}"]`);
        if (stepNav) {
          stepNav.classList.remove('active');
        }
      }

      // Show current step
      const currentStepElement = document.getElementById(`step${stepNumber}`);
      if (currentStepElement) {
        currentStepElement.classList.add('active');
      }
      const currentStepNav = document.querySelector(`.step-item[data-step="${stepNumber}"]`);
      if (currentStepNav) {
        currentStepNav.classList.add('active');
      }

      currentStep = stepNumber;

      // Update navigation buttons
      const prevBtn = document.getElementById('prevStepBtn');
      const nextBtn = document.getElementById('nextStepBtn');
      const submitBtn = document.getElementById('submitFormBtn');

      if (prevBtn) {
        prevBtn.disabled = (stepNumber === 1);
      }

      if (nextBtn && submitBtn) {
        if (stepNumber === totalSteps) {
          nextBtn.classList.add('hidden');
          submitBtn.classList.remove('hidden');
        } else {
          nextBtn.classList.remove('hidden');
          submitBtn.classList.add('hidden');
        }
      }
    }

    // Next step
    function nextStep() {
      if (currentStep < totalSteps) {
        // Validate current step before proceeding
        if (validateStep(currentStep)) {
          showStep(currentStep + 1);
        }
      }
    }

    // Previous step
    function prevStep() {
      if (currentStep > 1) {
        showStep(currentStep - 1);
      }
    }

    // Validate step
    function validateStep(stepNumber) {
      let isValid = true;
      const stepElement = document.getElementById(`step${stepNumber}`);

      if (!stepElement) return true;

      const requiredFields = stepElement.querySelectorAll('[required]');
      for (const field of requiredFields) {
        if (!field.value.trim()) {
          isValid = false;
          field.classList.add('error');

          // Show error message
          const errorDiv = field.nextElementSibling;
          if (errorDiv && errorDiv.classList.contains('error-message')) {
            errorDiv.textContent = 'This field is required';
            errorDiv.classList.remove('hidden');
          }
        } else {
          field.classList.remove('error');
          const errorDiv = field.nextElementSibling;
          if (errorDiv && errorDiv.classList.contains('error-message')) {
            errorDiv.classList.add('hidden');
          }
        }
      }

      // Additional validation for specific steps
      if (stepNumber === 1) {
        const emailField = document.getElementById('email_address');
        if (emailField && emailField.value.trim()) {
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!emailRegex.test(emailField.value)) {
            isValid = false;
            emailField.classList.add('error');
            showToast('Please enter a valid email address', 'error');
          }
        }

        const rateField = document.getElementById('rate_per_day');
        if (rateField && rateField.value) {
          const rateValue = parseFloat(rateField.value);
          if (isNaN(rateValue) || rateValue <= 0) {
            isValid = false;
            rateField.classList.add('error');
            showToast('Rate per day must be a positive number', 'error');
          }
        }
      }

      if (!isValid) {
        showToast('Please fill in all required fields correctly', 'error');
      }

      return isValid;
    }

    // =========================================
    // 4. PAGINATION
    // =========================================

    // Go to page
    function goToPage(page) {
      const url = new URL(window.location.href);
      url.searchParams.set('page', page);
      window.location.href = url.toString();
    }

    // =========================================
    // 5. DEBUG UTILITIES
    // =========================================

    // Debug logging function
    function debugLog(message, data = null) {
      const debugOverlay = document.getElementById('debugOverlay');
      if (debugOverlay && debugOverlay.style.display !== 'none') {
        const timestamp = new Date().toLocaleTimeString();
        let logMessage = `[${timestamp}] ${message}`;

        if (data) {
          logMessage += `: ${JSON.stringify(data, null, 2)}`;
        }

        debugOverlay.innerHTML = logMessage + '<br>' + debugOverlay.innerHTML;
      }
      console.log(message, data || '');
    }

    // =========================================
    // 6. FORM VALIDATION
    // =========================================

    // Validate add form before submission
    function validateAddForm() {
      // Validate all steps
      for (let i = 1; i <= totalSteps; i++) {
        if (!validateStep(i)) {
          showStep(i); // Go to the step with errors
          return false;
        }
      }

      // Check for duplicate employee ID (client-side check)
      const employeeIdField = document.getElementById('employee_id');
      if (employeeIdField && employeeIdField.value.trim()) {
        const employeeId = employeeIdField.value.trim().toUpperCase();
        const existingIds = Array.from(document.querySelectorAll('td:nth-child(2)')).map(td => td.textContent.trim());

        if (existingIds.includes(employeeId)) {
          showToast(`Employee ID "${employeeId}" already exists. Please use a different ID.`, 'error');
          employeeIdField.focus();
          showStep(1);
          return false;
        }
      }

      // Show confirmation dialog
      const employeeName = document.getElementById('employee_name').value;
      if (!confirm(`Are you sure you want to add "${employeeName}" as a new job order employee?`)) {
        return false;
      }

      // Show loading notification
      showNotification('Adding employee to database...', 'info', 'Processing');

      return true;
    }

    // Validate edit form before submission
    function validateEditForm() {
      const form = document.getElementById('editEmployeeForm');
      if (!form) return false;

      // Validate required fields
      const requiredFields = form.querySelectorAll('[required]');
      let isValid = true;

      for (const field of requiredFields) {
        if (!field.value.trim()) {
          isValid = false;
          field.classList.add('error');
        } else {
          field.classList.remove('error');
        }
      }

      // Validate email format
      const emailField = document.querySelector('#edit_email_address');
      if (emailField && emailField.value.trim()) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailField.value)) {
          isValid = false;
          emailField.classList.add('error');
          showToast('Please enter a valid email address', 'error');
        }
      }

      // Validate rate per day
      const rateField = document.querySelector('#edit_rate_per_day');
      if (rateField && rateField.value) {
        const rateValue = parseFloat(rateField.value);
        if (isNaN(rateValue) || rateValue <= 0) {
          isValid = false;
          rateField.classList.add('error');
          showToast('Rate per day must be a positive number', 'error');
        }
      }

      if (!isValid) {
        showToast('Please fill in all required fields correctly', 'error');
        return false;
      }

      const employeeName = document.querySelector('#edit_employee_name').value;
      if (!confirm(`Are you sure you want to update "${employeeName}"?`)) {
        return false;
      }

      showNotification('Updating employee information...', 'info', 'Processing');
      return true;
    }

    // =========================================
    // 7. PAYROLL DROPDOWN - SIMPLIFIED VERSION
    // =========================================

    function togglePayrollDropdown() {
      const dropdown = document.getElementById('payroll-dropdown');
      const chevron = document.querySelector('#payroll-toggle .chevron');

      if (dropdown && chevron) {
        dropdown.classList.toggle('open');
        chevron.classList.toggle('rotated');
      }
    }

    function initializePayrollDropdown() {
      const payrollToggle = document.getElementById('payroll-toggle');
      const payrollDropdown = document.getElementById('payroll-dropdown');

      if (payrollToggle && payrollDropdown) {
        // Set initial state
        payrollDropdown.classList.remove('open');

        // Add click event
        payrollToggle.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          payrollDropdown.classList.toggle('open');

          // Toggle chevron rotation
          const chevron = this.querySelector('.chevron');
          if (chevron) {
            chevron.classList.toggle('rotated');
          }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
          if (!payrollToggle.contains(event.target) && !payrollDropdown.contains(event.target)) {
            payrollDropdown.classList.remove('open');
            const chevron = payrollToggle.querySelector('.chevron');
            if (chevron) {
              chevron.classList.remove('rotated');
            }
          }
        });
      }
    }

    // =========================================
    // 8. SIDEBAR TOGGLE
    // =========================================

    function initializeSidebar() {
      const sidebarToggle = document.getElementById('sidebar-toggle');
      const sidebarContainer = document.getElementById('sidebar-container');
      const overlay = document.getElementById('overlay');

      if (sidebarToggle && sidebarContainer && overlay) {
        sidebarToggle.addEventListener('click', function() {
          sidebarContainer.classList.toggle('active');
          overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', function() {
          sidebarContainer.classList.remove('active');
          overlay.classList.remove('active');
        });
      }
    }

    // ===============================================
    // SEARCH FUNCTIONALITY
    // ===============================================
    function searchEmployees() {
      const searchInput = document.getElementById('simple-search');
      if (!searchInput) return;

      const searchTerm = searchInput.value.toLowerCase();
      const rows = document.querySelectorAll('tbody tr');
      const paginationContainer = document.getElementById('paginationContainer');
      const paginationInfo = document.getElementById('paginationInfo');
      const paginationButtons = document.getElementById('paginationButtons');

      let visibleRows = 0;
      let totalVisibleRows = 0;

      // First pass: count all rows that match the search
      rows.forEach(row => {
        // Skip if it's the "no data" row or "no search results" row
        if (row.querySelector('td[colspan]')) {
          return;
        }

        let shouldShow = false;

        // Get all cells in the row for searching
        const cells = row.querySelectorAll('td');

        // Check each cell's content for the search term
        cells.forEach(cell => {
          if (cell.textContent.toLowerCase().includes(searchTerm)) {
            shouldShow = true;
          }
        });

        if (shouldShow || searchTerm === '') {
          totalVisibleRows++;
        }
      });

      // Second pass: show/hide rows
      rows.forEach(row => {
        // Skip if it's the "no data" row or "no search results" row
        if (row.querySelector('td[colspan]')) {
          if (searchTerm === '') {
            row.style.display = ''; // Show the "no data" row when search is empty
          } else {
            row.style.display = 'none'; // Hide "no data" row when searching
          }
          return;
        }

        let shouldShow = false;

        // Get all cells in the row for searching
        const cells = row.querySelectorAll('td');

        // Check each cell's content for the search term
        cells.forEach(cell => {
          if (cell.textContent.toLowerCase().includes(searchTerm)) {
            shouldShow = true;
          }
        });

        // Toggle row visibility
        if (shouldShow || searchTerm === '') {
          row.style.display = '';
          visibleRows++;
        } else {
          row.style.display = 'none';
        }
      });

      // Update pagination visibility based on search
      if (paginationContainer && paginationInfo && paginationButtons) {
        if (searchTerm !== '') {
          // When searching, hide server-side pagination and show client-side results info
          paginationContainer.style.display = 'none';

          // Create custom pagination info for search results
          const searchInfo = document.createElement('div');
          searchInfo.className = 'pagination-info';
          searchInfo.innerHTML = `
                Showing ${visibleRows} of ${totalVisibleRows} search results
                <span class="text-blue-600">(Search: "${searchTerm}")</span>
            `;

          // Remove existing custom info if it exists
          const existingSearchInfo = document.getElementById('searchResultsInfo');
          if (existingSearchInfo) {
            existingSearchInfo.remove();
          }

          searchInfo.id = 'searchResultsInfo';
          paginationContainer.parentNode.insertBefore(searchInfo, paginationContainer.nextSibling);
        } else {
          // When not searching, show server-side pagination
          paginationContainer.style.display = 'flex';

          // Remove custom search info if it exists
          const existingSearchInfo = document.getElementById('searchResultsInfo');
          if (existingSearchInfo) {
            existingSearchInfo.remove();
          }
        }
      }

      // If no rows are visible and we're searching, show a message
      const tbody = document.querySelector('tbody');
      if (visibleRows === 0 && searchTerm !== '') {
        // Check if "no results" row already exists
        let noResultsRow = document.getElementById('no-search-results');
        if (!noResultsRow) {
          noResultsRow = document.createElement('tr');
          noResultsRow.id = 'no-search-results';
          noResultsRow.innerHTML = `
                <td colspan="12" class="px-6 py-8 text-center">
                    <div class="flex flex-col items-center justify-center text-gray-500">
                        <i class="fas fa-search text-4xl mb-4 opacity-50"></i>
                        <p class="text-lg font-medium mb-2">No matching employees found</p>
                        <p class="text-sm">Try different search terms</p>
                    </div>
                </td>
            `;
          tbody.appendChild(noResultsRow);
        }
      } else {
        // Remove "no results" row if it exists
        const noResultsRow = document.getElementById('no-search-results');
        if (noResultsRow) {
          noResultsRow.remove();
        }
      }
    }

    // =========================================
    // 9. INITIALIZATION
    // =========================================

    document.addEventListener('DOMContentLoaded', function() {
      // Search functionality
      const searchInput = document.getElementById('simple-search');
      if (searchInput) {
        searchInput.addEventListener('input', function() {
          searchEmployees();
        });

        // Also trigger search on page load to handle any existing search terms
        // But only if there's a search term in the URL
        const urlParams = new URLSearchParams(window.location.search);
        const searchParam = urlParams.get('search');
        if (searchParam) {
          searchInput.value = searchParam;
          setTimeout(() => {
            searchEmployees();
          }, 100);
        }
      }

      console.log('DOM fully loaded');

      const urlParams = new URLSearchParams(window.location.search);

      // Show PHP messages
      <?php if (!empty($success_message)): ?>
        showNotification('<?php echo addslashes($success_message); ?>', 'success');
      <?php endif; ?>

      <?php if (!empty($error_message)): ?>
        showNotification('<?php echo addslashes($error_message); ?>', 'error');
      <?php endif; ?>

      if (urlParams.has('success')) {
        const successType = urlParams.get('success');
        switch (successType) {
          case '1':
            showNotification('Employee added successfully!', 'success');
            break;
          case 'update':
            showNotification('Employee updated successfully!', 'success');
            break;
          case 'archive':
            const action = urlParams.get('action');
            showNotification(`Employee ${action === 'archive' ? 'archived' : 'restored'} successfully!`, 'success');
            break;
        }

        // Clean URL
        const cleanUrl = window.location.pathname + window.location.search.replace(/[?&]success=[^&]+/, '').replace(/^&/, '?');
        window.history.replaceState({}, document.title, cleanUrl);
      }

      if (urlParams.has('error')) {
        const errorMsg = urlParams.get('error');
        showNotification(decodeURIComponent(errorMsg), 'error');

        // Clean URL
        const cleanUrl = window.location.pathname + window.location.search.replace(/[?&]error=[^&]+/, '').replace(/^&/, '?');
        window.history.replaceState({}, document.title, cleanUrl);
      }

      // Update date and time
      function updateDateTime() {
        const now = new Date();
        const dateElement = document.getElementById('current-date');
        const timeElement = document.getElementById('current-time');

        if (dateElement) {
          dateElement.textContent = now.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
          });
        }

        if (timeElement) {
          timeElement.textContent = now.toLocaleTimeString('en-US', {
            hour12: true,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
          });
        }
      }

      updateDateTime();
      setInterval(updateDateTime, 1000);

      // Initialize components
      initializePayrollDropdown();
      initializeSidebar();

      // File upload handling
      document.querySelectorAll('.file-upload-zone').forEach(zone => {
        const fileInput = zone.querySelector('input[type="file"]');
        const preview = zone.querySelector('.file-preview');

        if (fileInput) {
          zone.addEventListener('click', function(e) {
            if (e.target !== fileInput && !e.target.closest('.file-preview')) {
              fileInput.click();
            }
          });

          fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
              const file = this.files[0];
              if (preview) {
                preview.innerHTML = `
                                    <i class="fas fa-file"></i>
                                    <span>${file.name}</span>
                                    <button type="button" onclick="removeFile(this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                `;
                preview.classList.remove('hidden');
              }
            }
          });
        }
      });

      // Remove file function
      window.removeFile = function(button) {
        const preview = button.closest('.file-preview');
        const fileInput = preview.parentElement.querySelector('input[type="file"]');
        if (fileInput) fileInput.value = '';
        preview.classList.add('hidden');
      };

      // Open add employee modal
      const openAddModalBtn = document.getElementById('openAddEmployeeModal');
      if (openAddModalBtn) {
        openAddModalBtn.addEventListener('click', function() {
          openModal('addEmployeeModal');
          showStep(1);
        });
      }

      // Edit employee
      document.querySelectorAll('.edit-trigger-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
          const employeeId = this.getAttribute('data-employee-id');
          try {
            const response = await fetch(`?get_employee_data=1&id=${employeeId}`);
            if (response.ok) {
              const employee = await response.json();
              // Populate form fields
              document.getElementById('edit_employee_id').value = employee.id;
              document.getElementById('edit_employee_id_field').value = employee.employee_id || '';
              document.getElementById('edit_employee_name').value = employee.employee_name || '';
              // ... (rest of your edit code remains the same)
              openModal('editEmployeeModal');
            }
          } catch (error) {
            console.error('Error:', error);
            showToast('Failed to load employee data', 'error');
          }
        });
      });

      // Archive employee
      document.querySelectorAll('.archive-trigger-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const employeeId = this.getAttribute('data-employee-id');
          const employeeName = this.getAttribute('data-employee-name');
          document.getElementById('archive_employee_id').value = employeeId;
          document.getElementById('archive_employee_name').textContent = employeeName;
          openModal('archiveEmployeeModal');
        });
      });

      // Restore employee
      document.querySelectorAll('.restore-trigger-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const employeeId = this.getAttribute('data-employee-id');
          const employeeName = this.getAttribute('data-employee-name');
          document.getElementById('restore_employee_id').value = employeeId;
          document.getElementById('restore_employee_name').textContent = employeeName;
          openModal('restoreEmployeeModal');
        });
      });

      // View employee
      document.querySelectorAll('.view-trigger-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const employeeId = this.getAttribute('data-employee-id');
          const url = new URL(window.location.href);
          url.searchParams.set('view_id', employeeId);
          window.location.href = url.toString();
        });
      });

      debugLog('Page initialized');



    });

    // Export functions
    window.showNotification = showNotification;
    window.showToast = showToast;
    window.openModal = openModal;
    window.closeModal = closeModal;
    window.nextStep = nextStep;
    window.prevStep = prevStep;
    window.goToPage = goToPage;
    window.validateAddForm = validateAddForm;
    window.validateEditForm = validateEditForm;
    window.removeFile = removeFile;
    window.togglePayrollDropdown = togglePayrollDropdown;
  </script>
</body>

</html>