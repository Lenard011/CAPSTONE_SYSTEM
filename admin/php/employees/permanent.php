<?php
session_start();

// Database configuration
$servername = "localhost";
$username = "root"; // Change as needed
$password = ""; // Change as needed
$database = "hrms_paluan";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// Initialize variables
$show_inactive = isset($_GET['show_inactive']) ? 1 : 0;
$filter_office = isset($_GET['office']) ? $_GET['office'] : '';
$form_submit_success = '';
$form_submit_error = '';
$db_error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            handleAddEmployee($conn);
        } elseif ($_POST['action'] === 'edit') {
            handleEditEmployee($conn);
        }
    }
}

// Handle GET actions
if (isset($_GET['mark_inactive'])) {
    handleStatusChange($conn, $_GET['mark_inactive'], isset($_GET['status']) ? $_GET['status'] : 'Inactive');
}

// Handle AJAX requests for viewing/editing
if (isset($_GET['view_id'])) {
    handleViewRequest($conn, $_GET['view_id']);
    exit;
}

if (isset($_GET['edit_id'])) {
    handleEditRequest($conn, $_GET['edit_id']);
    exit;
}

// ===============================================
// FUNCTION DEFINITIONS
// ===============================================

function handleAddEmployee($conn)
{
    global $form_submit_success, $form_submit_error;

    try {
        // Validate Employee ID
        if (empty($_POST['employee_id'])) {
            throw new Exception("Employee ID is required.");
        }

        // Start transaction
        $conn->begin_transaction();

        // Get employee ID from form input
        $employee_id = mysqli_real_escape_string($conn, trim($_POST['employee_id']));

        // Check if employee ID already exists
        $check_sql = "SELECT id FROM permanent WHERE employee_id = '$employee_id'";
        $check_result = $conn->query($check_sql);
        if ($check_result->num_rows > 0) {
            throw new Exception("Employee ID '$employee_id' already exists. Please use a different Employee ID.");
        }

        // Upload files
        $uploaded_files = uploadFiles();

        // Prepare data - DEFINE THESE FIRST
        $position = mysqli_real_escape_string($conn, $_POST['position']);
        $office = mysqli_real_escape_string($conn, $_POST['office']);
        $monthly_salary = floatval($_POST['monthly_salary']);
        $amount_accrued = floatval($_POST['amount_accrued']);
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $middle_name = mysqli_real_escape_string($conn, $_POST['middle']);
        $mobile_number = mysqli_real_escape_string($conn, $_POST['mobile_number']);
        $email_address = mysqli_real_escape_string($conn, $_POST['email_address']);
        $date_of_birth = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
        $marital_status = mysqli_real_escape_string($conn, $_POST['marital_status']);
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $nationality = mysqli_real_escape_string($conn, $_POST['nationality']);
        $street_address = mysqli_real_escape_string($conn, $_POST['street_address']);
        $city = mysqli_real_escape_string($conn, $_POST['city']);
        $state_region = mysqli_real_escape_string($conn, $_POST['state_region']);
        $ip_code = mysqli_real_escape_string($conn, $_POST['ip_code']);
        $joining_date = mysqli_real_escape_string($conn, $_POST['joining_date']);
        $eligibility = mysqli_real_escape_string($conn, $_POST['eligibility']);
        $status = 'Active';

        // SIMPLE DUPLICATE NAME CHECK - MOVED HERE after variables are defined
        $check_name_sql = "SELECT employee_id, first_name, last_name, middle, status FROM permanent 
                   WHERE LOWER(TRIM(first_name)) = LOWER(TRIM('$first_name')) 
                   AND LOWER(TRIM(last_name)) = LOWER(TRIM('$last_name')) 
                   AND LOWER(TRIM(middle)) = LOWER(TRIM('$middle_name'))";
        $name_result = $conn->query($check_name_sql);
        if ($name_result->num_rows > 0) {
            $dup = $name_result->fetch_assoc();
            throw new Exception("Duplicate entry: Employee with name '$first_name " . substr($middle_name, 0, 1) . ". $last_name' already exists (Employee ID: " . $dup['employee_id'] . ", Status: " . $dup['status'] . ").");
        }

        // ===== CREATE USER ACCOUNT =====
        // Generate a default password
        $default_password = bin2hex(random_bytes(8));
        $password_hash = password_hash($default_password, PASSWORD_DEFAULT);

        // Check what columns exist in your users table
        $table_check = $conn->query("SHOW COLUMNS FROM users");
        $user_columns = [];
        while ($col = $table_check->fetch_assoc()) {
            $user_columns[] = $col['Field'];
        }

        // Build dynamic INSERT query based on existing columns
        $user_fields = [];
        $user_values = [];

        // Always include these basic fields
        $user_fields[] = 'username';
        $user_values[] = "'$employee_id'";

        $user_fields[] = 'email';
        $user_values[] = "'$email_address'";

        $user_fields[] = 'password_hash';
        $user_values[] = "'$password_hash'";

        $user_fields[] = 'status';
        $user_values[] = "'Active'";

        $user_fields[] = 'created_at';
        $user_values[] = 'NOW()';

        // Add role if column exists
        if (in_array('role', $user_columns)) {
            $user_fields[] = 'role';
            $user_values[] = "'employee'";
        }

        // Add role_id if column exists
        if (in_array('role_id', $user_columns)) {
            $user_fields[] = 'role_id';
            $user_values[] = "2";
        }

        // Add full_name if column exists
        $full_name = $first_name . ' ' . (!empty($middle_name) ? substr($middle_name, 0, 1) . '. ' : '') . $last_name;
        if (in_array('full_name', $user_columns)) {
            $user_fields[] = 'full_name';
            $user_values[] = "'" . mysqli_real_escape_string($conn, $full_name) . "'";
        }

        // Build the INSERT query
        $user_sql = "INSERT INTO users (" . implode(', ', $user_fields) . ") 
                     VALUES (" . implode(', ', $user_values) . ")";

        if ($conn->query($user_sql) === TRUE) {
            $user_id = $conn->insert_id;

            // Now insert into permanent table with the user_id
            $sql = "INSERT INTO permanent (
                employee_id, user_id, position, office, monthly_salary, amount_accrued,
                first_name, last_name, middle, mobile_number, email_address, date_of_birth,
                marital_status, gender, nationality, street_address, city, state_region,
                ip_code, joining_date, eligibility, status,
                profile_image_path, doc_id_path, doc_resume_path, doc_service_path,
                doc_appointment_path, doc_transcript_path, doc_eligibility_path,
                created_at, updated_at
            ) VALUES (
                '$employee_id', $user_id, '$position', '$office', $monthly_salary, $amount_accrued,
                '$first_name', '$last_name', '$middle_name', '$mobile_number', '$email_address', '$date_of_birth',
                '$marital_status', '$gender', '$nationality', '$street_address', '$city', '$state_region',
                '$ip_code', '$joining_date', '$eligibility', '$status',
                '" . ($uploaded_files['profile_image'] ?? '') . "',
                '" . ($uploaded_files['doc_id'] ?? '') . "',
                '" . ($uploaded_files['doc_resume'] ?? '') . "',
                '" . ($uploaded_files['doc_service'] ?? '') . "',
                '" . ($uploaded_files['doc_appointment'] ?? '') . "',
                '" . ($uploaded_files['doc_transcript'] ?? '') . "',
                '" . ($uploaded_files['doc_eligibility'] ?? '') . "',
                NOW(), NOW()
            )";

            if ($conn->query($sql) === TRUE) {
                $conn->commit();
                $form_submit_success = "Permanent employee added successfully with Employee ID: " . $employee_id . ". Default password: " . $default_password;

                // Clear POST data
                $_POST = array();
            } else {
                throw new Exception("Error adding employee: " . $conn->error);
            }
        } else {
            throw new Exception("Error creating user account: " . $conn->error);
        }

    } catch (Exception $e) {
        $conn->rollback();
        $form_submit_error = $e->getMessage();
    }
}
function handleEditEmployee($conn)
{
    global $form_submit_success, $form_submit_error;

    try {
        if (!isset($_POST['employee_id_hidden']) || empty($_POST['employee_id_hidden'])) {
            throw new Exception("Employee ID is required for editing.");
        }

        $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id_hidden']);

        // Start transaction
        $conn->begin_transaction();

        // Prepare data
        $position = mysqli_real_escape_string($conn, $_POST['position']);
        $office = mysqli_real_escape_string($conn, $_POST['office']);
        $monthly_salary = floatval($_POST['monthly_salary']);
        $amount_accrued = floatval($_POST['amount_accrued']);
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $middle_name = mysqli_real_escape_string($conn, $_POST['middle']);
        $mobile_number = mysqli_real_escape_string($conn, $_POST['mobile_number']);
        $email_address = mysqli_real_escape_string($conn, $_POST['email_address']);
        $date_of_birth = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
        $marital_status = mysqli_real_escape_string($conn, $_POST['marital_status']);
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $nationality = mysqli_real_escape_string($conn, $_POST['nationality']);
        $street_address = mysqli_real_escape_string($conn, $_POST['street_address']);
        $city = mysqli_real_escape_string($conn, $_POST['city']);
        $state_region = mysqli_real_escape_string($conn, $_POST['state_region']);
        $ip_code = mysqli_real_escape_string($conn, $_POST['ip_code']);
        $joining_date = mysqli_real_escape_string($conn, $_POST['joining_date']);
        $eligibility = mysqli_real_escape_string($conn, $_POST['eligibility']);

        // Upload new files if provided
        $uploaded_files = uploadFiles();

        // ===== UPDATE USER ACCOUNT IF EXISTS =====
        // First, check if this employee has a user_id
        $check_user_sql = "SELECT user_id FROM permanent WHERE employee_id = '$employee_id'";
        $check_user_result = $conn->query($check_user_sql);

        if ($check_user_result && $check_user_result->num_rows > 0) {
            $user_data = $check_user_result->fetch_assoc();
            $user_id = $user_data['user_id'];

            // If user_id exists, update the users table
            if (!empty($user_id)) {
                // Check what columns exist in users table
                $table_check = $conn->query("SHOW COLUMNS FROM users");
                $user_columns = [];
                while ($col = $table_check->fetch_assoc()) {
                    $user_columns[] = $col['Field'];
                }

                // Build dynamic UPDATE query
                $user_update_fields = [];

                if (in_array('email', $user_columns)) {
                    $user_update_fields[] = "email = '$email_address'";
                }

                if (in_array('username', $user_columns) && !empty($employee_id)) {
                    $user_update_fields[] = "username = '$employee_id'";
                }

                // Update password if provided
                if (!empty($_POST['password'])) {
                    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    if (in_array('password_hash', $user_columns)) {
                        $user_update_fields[] = "password_hash = '$password_hash'";
                    }
                }

                // Update full_name if column exists
                $full_name = $first_name . ' ' . (!empty($middle_name) ? substr($middle_name, 0, 1) . '. ' : '') . $last_name;
                if (in_array('full_name', $user_columns)) {
                    $user_update_fields[] = "full_name = '" . mysqli_real_escape_string($conn, $full_name) . "'";
                }

                if (in_array('updated_at', $user_columns)) {
                    $user_update_fields[] = "updated_at = NOW()";
                }

                if (!empty($user_update_fields)) {
                    $update_user_sql = "UPDATE users SET " . implode(', ', $user_update_fields) . " WHERE id = $user_id";
                    $conn->query($update_user_sql);
                }
            }
        }

        // ===== UPDATE PERMANENT TABLE =====
        // Build update query for permanent table
        $sql = "UPDATE permanent SET
            position = '$position',
            office = '$office',
            monthly_salary = $monthly_salary,
            amount_accrued = $amount_accrued,
            first_name = '$first_name',
            last_name = '$last_name',
            middle = '$middle_name',
            mobile_number = '$mobile_number',
            email_address = '$email_address',
            date_of_birth = '$date_of_birth',
            marital_status = '$marital_status',
            gender = '$gender',
            nationality = '$nationality',
            street_address = '$street_address',
            city = '$city',
            state_region = '$state_region',
            ip_code = '$ip_code',
            joining_date = '$joining_date',
            eligibility = '$eligibility',
            updated_at = NOW()";

        // Add profile image if uploaded
        if (isset($uploaded_files['profile_image']) && !empty($uploaded_files['profile_image'])) {
            $sql .= ", profile_image_path = '" . $uploaded_files['profile_image'] . "'";
        }

        // Add document paths if uploaded
        $doc_fields = ['doc_id', 'doc_resume', 'doc_service', 'doc_appointment', 'doc_transcript', 'doc_eligibility'];
        foreach ($doc_fields as $doc_field) {
            if (isset($uploaded_files[$doc_field]) && !empty($uploaded_files[$doc_field])) {
                $db_field = $doc_field . '_path'; // Keep original field name with _path suffix
                $sql .= ", $db_field = '" . $uploaded_files[$doc_field] . "'";
            }
        }

        $sql .= " WHERE employee_id = '$employee_id'";

        if ($conn->query($sql) === TRUE) {
            $conn->commit();
            $form_submit_success = "Employee updated successfully!";
        } else {
            throw new Exception("Error updating employee: " . $conn->error);
        }

    } catch (Exception $e) {
        $conn->rollback();
        $form_submit_error = $e->getMessage();
    }
}

function handleStatusChange($conn, $employee_id, $status)
{
    global $form_submit_success, $form_submit_error;

    try {
        $employee_id = mysqli_real_escape_string($conn, $employee_id);
        $status = mysqli_real_escape_string($conn, $status);

        $sql = "UPDATE permanent SET status = '$status', updated_at = NOW() WHERE id = $employee_id";

        if ($conn->query($sql) === TRUE) {
            if ($conn->affected_rows > 0) {
                $action = $status === 'Active' ? 'activated' : 'deactivated';
                $form_submit_success = "Employee $action successfully!";
            } else {
                $form_submit_error = "Employee not found.";
            }
        } else {
            throw new Exception("Error updating status: " . $conn->error);
        }
    } catch (Exception $e) {
        $form_submit_error = $e->getMessage();
    }

    // Redirect to remove GET parameters
    $redirect_url = "permanent.php";
    if (isset($_GET['show_inactive'])) {
        $redirect_url .= "?show_inactive=1";
    }
    header("Location: $redirect_url");
    exit;
}

function handleViewRequest($conn, $employee_id)
{
    $employee_id = intval($employee_id);

    $sql = "SELECT * FROM permanent WHERE id = $employee_id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $employee = $result->fetch_assoc();

        // Format dates for display
        $date_of_birth = date('F j, Y', strtotime($employee['date_of_birth']));
        $joining_date = date('F j, Y', strtotime($employee['joining_date']));
        $created_at = date('F j, Y, g:i A', strtotime($employee['created_at']));
        $updated_at = date('F j, Y, g:i A', strtotime($employee['updated_at']));

        // Calculate age
        $birthDate = new DateTime($employee['date_of_birth']);
        $today = new DateTime();
        $age = $birthDate->diff($today)->y;

        // Generate HTML for view modal
        $html = '
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Left Column: Profile -->
            <div class="md:col-span-1">
                <div class="bg-gray-50 p-6 rounded-lg shadow-sm">
                    <div class="text-center">
                        <div class="w-48 h-48 mx-auto bg-gray-200 rounded-full flex items-center justify-center mb-4 overflow-hidden">
        ';

        if (!empty($employee['profile_image_path'])) {
            $html .= '<img src="' . htmlspecialchars($employee['profile_image_path']) . '" class="w-full h-full object-cover rounded-full" alt="Profile Image">';
        } else {
            $html .= '<i class="fas fa-user text-gray-400 text-6xl"></i>';
        }

        $html .= '
                        </div>
                        <h4 class="text-2xl font-bold text-gray-900">' . htmlspecialchars($employee['full_name']) . '</h4>
                        <p class="text-gray-600 mt-1">' . htmlspecialchars($employee['position']) . '</p>
                        <div class="mt-3">
                            <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full ' . ($employee['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800') . '">
                                ' . htmlspecialchars($employee['status']) . '
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-6 space-y-4">
                        <div class="flex items-center">
                            <i class="fas fa-id-card text-blue-600 w-6"></i>
                            <span class="ml-3 font-medium">' . htmlspecialchars($employee['employee_id']) . '</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-building text-blue-600 w-6"></i>
                            <span class="ml-3">' . htmlspecialchars($employee['office']) . '</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-money-bill-wave text-blue-600 w-6"></i>
                            <span class="ml-3 font-semibold">₱' . number_format($employee['monthly_salary'], 2) . '</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Details -->
            <div class="md:col-span-2">
                <div class="space-y-6">
                    <!-- Personal Information -->
                    <div class="bg-white p-6 rounded-lg shadow-sm">
                        <h5 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b">Personal Information</h5>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm text-gray-500">First Name</label>
                                <p class="font-medium">' . htmlspecialchars($employee['first_name']) . '</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-500">Last Name</label>
                                <p class="font-medium">' . htmlspecialchars($employee['last_name']) . '</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-500">Date of Birth</label>
                                <p class="font-medium">' . $date_of_birth . ' (' . $age . ' years old)</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-500">Gender</label>
                                <p class="font-medium">' . htmlspecialchars($employee['gender']) . '</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-500">Marital Status</label>
                                <p class="font-medium">' . htmlspecialchars($employee['marital_status']) . '</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-500">Nationality</label>
                                <p class="font-medium">' . htmlspecialchars($employee['nationality']) . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="bg-white p-6 rounded-lg shadow-sm">
                        <h5 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b">Contact Information</h5>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm text-gray-500">Mobile Number</label>
                                <p class="font-medium">' . htmlspecialchars($employee['mobile_number']) . '</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-500">Email Address</label>
                                <p class="font-medium">' . htmlspecialchars($employee['email_address']) . '</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-sm text-gray-500">Address</label>
                                <p class="font-medium">' . htmlspecialchars($employee['street_address']) . ', ' . htmlspecialchars($employee['city']) . ', ' . htmlspecialchars($employee['state_region']) . ' ' . htmlspecialchars($employee['ip_code']) . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Employment Information -->
                    <div class="bg-white p-6 rounded-lg shadow-sm">
                        <h5 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b">Employment Information</h5>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm text-gray-500">Joining Date</label>
                                <p class="font-medium">' . $joining_date . '</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-500">Eligibility Status</label>
                                <p class="font-medium">' . htmlspecialchars($employee['eligibility']) . '</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-500">Amount Accrued</label>
                                <p class="font-medium">₱' . number_format($employee['amount_accrued'], 2) . '</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-500">Record Created</label>
                                <p class="font-medium">' . $created_at . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Documents -->
                    <div class="bg-white p-6 rounded-lg shadow-sm">
                        <h5 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b">Documents</h5>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        ';

        // Document list with links
        $documents = [
            'Government ID' => $employee['doc_id_path'],
            'Resume/CV' => $employee['doc_resume_path'],
            'Service Record' => $employee['doc_service_path'],
            'Appointment Paper' => $employee['doc_appointment_path'],
            'Transcript' => $employee['doc_transcript_path'],
            'Eligibility Cert' => $employee['doc_eligibility_path']
        ];

        foreach ($documents as $label => $path) {
            $html .= '
                            <div class="text-center p-3 bg-gray-50 rounded-lg">
                                <i class="fas fa-file text-blue-500 text-xl mb-2"></i>
                                <p class="text-sm font-medium text-gray-700">' . $label . '</p>
            ';

            if (!empty($path)) {
                $html .= '
                                <a href="' . htmlspecialchars($path) . '" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 mt-1 inline-block">
                                    <i class="fas fa-download mr-1"></i>View
                                </a>
                ';
            } else {
                $html .= '
                                <p class="text-xs text-gray-500 mt-1">Not uploaded</p>
                ';
            }

            $html .= '
                            </div>
            ';
        }

        $html .= '
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-6 pt-6 border-t flex justify-end">
            <button onclick="editFromView(' . $employee['id'] . ')" class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5">
                <i class="fas fa-edit mr-2"></i>Edit Employee
            </button>
        </div>
        ';

        echo json_encode([
            'success' => true,
            'html' => $html
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found'
        ]);
    }
}

function handleEditRequest($conn, $employee_id)
{
    $employee_id = intval($employee_id);

    $sql = "SELECT * FROM permanent WHERE id = $employee_id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $employee = $result->fetch_assoc();

        // Generate HTML for edit form (similar to add form but with pre-filled values)
        $html = '
        <!-- Edit Step Navigation -->
        <div class="flex mb-4 border-b sticky top-0 bg-white z-10 pt-2 overflow-x-auto">
            <button type="button" class="edit-step-nav flex-1 py-2 px-4 text-center font-medium border-b-2 border-blue-600 text-blue-600 whitespace-nowrap" data-step="1">Professional</button>
            <button type="button" class="edit-step-nav flex-1 py-2 px-4 text-center font-medium border-b-2 border-transparent text-gray-500 whitespace-nowrap" data-step="2">Personal</button>
            <button type="button" class="edit-step-nav flex-1 py-2 px-4 text-center font-medium border-b-2 border-transparent text-gray-500 whitespace-nowrap" data-step="3">Documents</button>
        </div>
        
        <form id="editEmployeeForm" action="permanent.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="employee_id_hidden" value="' . htmlspecialchars($employee['employee_id']) . '">
        
            <!-- Step 1: Professional Information -->
            <div id="edit-step1" class="edit-form-step active">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Professional Details</h2>
                
                <div class="grid gap-4 mb-4">
                    <div>
                        <label for="edit_employee_id" class="block mb-2 text-sm font-medium text-gray-900">Employee ID</label>
                        <input type="text" id="edit_employee_id" value="' . htmlspecialchars($employee['employee_id']) . '" class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" readonly>
                        <p class="text-xs text-gray-500 mt-1">Employee ID cannot be changed</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="edit_first_name" class="block mb-2 text-sm font-medium text-gray-900">First Name *</label>
                            <input type="text" name="first_name" id="edit_first_name" value="' . htmlspecialchars($employee['first_name']) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label for="edit_last_name" class="block mb-2 text-sm font-medium text-gray-900">Last Name *</label>
                            <input type="text" name="last_name" id="edit_last_name" value="' . htmlspecialchars($employee['last_name']) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label for="edit_middle" class="block mb-2 text-sm font-medium text-gray-900">Middle Initial *</label>
                            <input type="text" name="middle" id="edit_middle" value="' . htmlspecialchars($employee['middle']) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_position" class="block mb-2 text-sm font-medium text-gray-900">Position *</label>
                            <input type="text" name="position" id="edit_position" value="' . htmlspecialchars($employee['position']) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label for="edit_office" class="block mb-2 text-sm font-medium text-gray-900">Office/Department *</label>
                            <select name="office" id="edit_office" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                                <option value="">Select Department</option>
                                <option value="Office of the Municipal Mayor"' . ($employee['office'] == 'Office of the Municipal Mayor' ? ' selected' : '') . '>Office of the Municipal Mayor</option>
                                <option value="Human Resource Management Division"' . ($employee['office'] == 'Human Resource Management Division' ? ' selected' : '') . '>Human Resource Management Division</option>
                                <option value="Business Permit and Licensing Division"' . ($employee['office'] == 'Business Permit and Licensing Division' ? ' selected' : '') . '>Business Permit and Licensing Division</option>
                                <option value="Sangguniang Bayan Office"' . ($employee['office'] == 'Sangguniang Bayan Office' ? ' selected' : '') . '>Sangguniang Bayan Office</option>
                                <option value="Office of the Municipal Accountant"' . ($employee['office'] == 'Office of the Municipal Accountant' ? ' selected' : '') . '>Office of the Municipal Accountant</option>
                                <option value="Office of the Assessor"' . ($employee['office'] == 'Office of the Assessor' ? ' selected' : '') . '>Office of the Assessor</option>
                                <option value="Municipal Budget Office"' . ($employee['office'] == 'Municipal Budget Office' ? ' selected' : '') . '>Municipal Budget Office</option>
                                <option value="Municipal Planning and Development Office"' . ($employee['office'] == 'Municipal Planning and Development Office' ? ' selected' : '') . '>Municipal Planning and Development Office</option>
                                <option value="Municipal Engineering Office"' . ($employee['office'] == 'Municipal Engineering Office' ? ' selected' : '') . '>Municipal Engineering Office</option>
                                <option value="Municipal Disaster Risk Reduction and Management Office"' . ($employee['office'] == 'Municipal Disaster Risk Reduction and Management Office' ? ' selected' : '') . '>Municipal Disaster Risk Reduction and Management Office</option>
                                <option value="Municipal Social Welfare and Development Office"' . ($employee['office'] == 'Municipal Social Welfare and Development Office' ? ' selected' : '') . '>Municipal Social Welfare and Development Office</option>
                                <option value="Municipal Environment and Natural Resources Office"' . ($employee['office'] == 'Municipal Environment and Natural Resources Office' ? ' selected' : '') . '>Municipal Environment and Natural Resources Office</option>
                                <option value="Office of the Municipal Agriculturist"' . ($employee['office'] == 'Office of the Municipal Agriculturist' ? ' selected' : '') . '>Office of the Municipal Agriculturist</option>
                                <option value="Municipal General Services Office"' . ($employee['office'] == 'Municipal General Services Office' ? ' selected' : '') . '>Municipal General Services Office</option>
                                <option value="Municipal Public Employment Service Office"' . ($employee['office'] == 'Municipal Public Employment Service Office' ? ' selected' : '') . '>Municipal Public Employment Service Office</option>
                                <option value="Municipal Health Office"' . ($employee['office'] == 'Municipal Health Office' ? ' selected' : '') . '>Municipal Health Office</option>
                                <option value="Municipal Treasurer\'s Office"' . ($employee['office'] == 'Municipal Treasurer\'s Office' ? ' selected' : '') . '>Municipal Treasurer\'s Office</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_monthly_salary" class="block mb-2 text-sm font-medium text-gray-900">Monthly Salary *</label>
                            <input type="number" name="monthly_salary" id="edit_monthly_salary" step="0.01" min="0" value="' . number_format($employee['monthly_salary'], 2, '.', '') . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label for="edit_amount_accrued" class="block mb-2 text-sm font-medium text-gray-900">Amount Accrued *</label>
                            <input type="number" name="amount_accrued" id="edit_amount_accrued" step="0.01" min="0" value="' . number_format($employee['amount_accrued'], 2, '.', '') . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" class="edit-next-step text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 w-full md:w-auto" data-next="2">
                        Next <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>
            
            <!-- Step 2: Personal Information -->
            <div id="edit-step2" class="edit-form-step hidden">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Personal Details</h2>
                
                <div class="grid gap-4 mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-1">
                            <div class="flex flex-col items-center">
                                <div id="editProfileImageContainer" class="w-32 h-32 bg-gray-200 rounded-full flex items-center justify-center mb-4 overflow-hidden">';

        if (!empty($employee['profile_image_path'])) {
            $html .= '<img src="' . htmlspecialchars($employee['profile_image_path']) . '" class="w-full h-full object-cover rounded-full" alt="Profile Image">';
        } else {
            $html .= '<i class="fas fa-user text-gray-400 text-4xl"></i>';
        }

        $html .= '
                                </div>
                                <input type="file" name="profile_image" id="edit_profile_image" accept="image/*" class="hidden">
                                <label for="edit_profile_image" class="cursor-pointer text-blue-600 hover:text-blue-800 text-sm font-medium text-center">
                                    <i class="fas fa-upload mr-1"></i>Change Photo
                                </label>
                                <input type="hidden" id="edit_current_profile_image" name="current_profile_image" value="' . htmlspecialchars($employee['profile_image_path'] ?? '') . '">
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_mobile_number" class="block mb-2 text-sm font-medium text-gray-900">Mobile Number *</label>
                            <input type="text" name="mobile_number" id="edit_mobile_number" value="' . htmlspecialchars($employee['mobile_number']) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label for="edit_email_address" class="block mb-2 text-sm font-medium text-gray-900">Email Address *</label>
                            <input type="email" name="email_address" id="edit_email_address" value="' . htmlspecialchars($employee['email_address']) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_date_of_birth" class="block mb-2 text-sm font-medium text-gray-900">Date of Birth *</label>
                            <input type="date" name="date_of_birth" id="edit_date_of_birth" value="' . htmlspecialchars($employee['date_of_birth']) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label for="edit_marital_status" class="block mb-2 text-sm font-medium text-gray-900">Marital Status *</label>
                            <select name="marital_status" id="edit_marital_status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                                <option value="">Select Status</option>
                                <option value="Single"' . ($employee['marital_status'] == 'Single' ? ' selected' : '') . '>Single</option>
                                <option value="Married"' . ($employee['marital_status'] == 'Married' ? ' selected' : '') . '>Married</option>
                                <option value="Divorced"' . ($employee['marital_status'] == 'Divorced' ? ' selected' : '') . '>Divorced</option>
                                <option value="Widowed"' . ($employee['marital_status'] == 'Widowed' ? ' selected' : '') . '>Widowed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_gender" class="block mb-2 text-sm font-medium text-gray-900">Gender *</label>
                            <select name="gender" id="edit_gender" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                                <option value="">Select Gender</option>
                                <option value="Male"' . ($employee['gender'] == 'Male' ? ' selected' : '') . '>Male</option>
                                <option value="Female"' . ($employee['gender'] == 'Female' ? ' selected' : '') . '>Female</option>
                                <option value="Other"' . ($employee['gender'] == 'Other' ? ' selected' : '') . '>Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="edit_nationality" class="block mb-2 text-sm font-medium text-gray-900">Nationality *</label>
                            <input type="text" name="nationality" id="edit_nationality" value="' . htmlspecialchars($employee['nationality']) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                    </div>
                    
                    <div>
                        <label for="edit_street_address" class="block mb-2 text-sm font-medium text-gray-900">Street Address *</label>
                        <input type="text" name="street_address" id="edit_street_address" value="' . htmlspecialchars($employee['street_address']) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="edit_city" class="block mb-2 text-sm font-medium text-gray-900">City *</label>
                            <input type="text" name="city" id="edit_city" value="' . htmlspecialchars($employee['city']) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label for="edit_state_region" class="block mb-2 text-sm font-medium text-gray-900">State/Region *</label>
                            <input type="text" name="state_region" id="edit_state_region" value="' . htmlspecialchars($employee['state_region']) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label for="edit_ip_code" class="block mb-2 text-sm font-medium text-gray-900">IP Code *</label>
                            <input type="text" name="ip_code" id="edit_ip_code" value="' . htmlspecialchars($employee['ip_code']) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                    </div>
                                   
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_joining_date" class="block mb-2 text-sm font-medium text-gray-900">Joining Date *</label>
                            <input type="date" name="joining_date" id="edit_joining_date" value="' . htmlspecialchars($employee['joining_date']) . '" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Civil Service Eligibility *</label>
                            <div class="flex flex-col sm:flex-row sm:space-x-4 space-y-2 sm:space-y-0">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="eligibility" value="Eligible" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500"' . ($employee['eligibility'] == 'Eligible' ? ' checked' : '') . '>
                                    <span class="ml-2 text-sm font-medium text-gray-900">Eligible</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="eligibility" value="Not Eligible" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500"' . ($employee['eligibility'] == 'Not Eligible' ? ' checked' : '') . '>
                                    <span class="ml-2 text-sm font-medium text-gray-900">Not Eligible</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row justify-between gap-3">
                    <button type="button" class="edit-prev-step text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 w-full sm:w-auto">
                        <i class="fas fa-arrow-left mr-2"></i>Previous
                    </button>
                    <button type="button" class="edit-next-step text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 w-full sm:w-auto" data-next="3">
                        Next <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>
            
            <!-- Step 3: Documents -->
            <div id="edit-step3" class="edit-form-step hidden">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Documents (Upload new files to replace existing ones)</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Government ID -->
                    <div class="file-drop-zone p-6 text-center border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 transition-colors cursor-pointer" data-file-input="edit_doc_id">
                        <i class="fas fa-id-card text-4xl text-blue-400 mb-3"></i>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Government Issued ID</h4>
                        <p class="text-sm text-gray-600 mb-2">Current: ' . (empty($employee['doc_id_path']) ? 'Not uploaded' : basename($employee['doc_id_path'])) . '</p>
                        <p class="text-xs text-gray-500 mb-3">Supported: JPG, JPEG, PDF, PNG</p>
                        <input type="file" name="doc_id" id="edit_doc_id" accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                        <div class="file-status text-sm text-green-600 truncate"></div>
                    </div>
                    
                    <!-- Resume -->
                    <div class="file-drop-zone p-6 text-center border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 transition-colors cursor-pointer" data-file-input="edit_doc_resume">
                        <i class="fas fa-file-alt text-4xl text-blue-400 mb-3"></i>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Resume / CV</h4>
                        <p class="text-sm text-gray-600 mb-2">Current: ' . (empty($employee['doc_resume_path']) ? 'Not uploaded' : basename($employee['doc_resume_path'])) . '</p>
                        <p class="text-xs text-gray-500 mb-3">Supported: JPG, JPEG, PDF, PNG</p>
                        <input type="file" name="doc_resume" id="edit_doc_resume" accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                        <div class="file-status text-sm text-green-600 truncate"></div>
                    </div>
                    
                    <!-- Service Record -->
                    <div class="file-drop-zone p-6 text-center border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 transition-colors cursor-pointer" data-file-input="edit_doc_service">
                        <i class="fas fa-history text-4xl text-blue-400 mb-3"></i>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Service Record</h4>
                        <p class="text-sm text-gray-600 mb-2">Current: ' . (empty($employee['doc_service_path']) ? 'Not uploaded' : basename($employee['doc_service_path'])) . '</p>
                        <p class="text-xs text-gray-500 mb-3">Supported: JPG, JPEG, PDF, PNG</p>
                        <input type="file" name="doc_service" id="edit_doc_service" accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                        <div class="file-status text-sm text-green-600 truncate"></div>
                    </div>
                    
                    <!-- Appointment Paper -->
                    <div class="file-drop-zone p-6 text-center border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 transition-colors cursor-pointer" data-file-input="edit_doc_appointment">
                        <i class="fas fa-file-contract text-4xl text-blue-400 mb-3"></i>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Appointment Paper</h4>
                        <p class="text-sm text-gray-600 mb-2">Current: ' . (empty($employee['doc_appointment_path']) ? 'Not uploaded' : basename($employee['doc_appointment_path'])) . '</p>
                        <p class="text-xs text-gray-500 mb-3">Supported: JPG, JPEG, PDF, PNG</p>
                        <input type="file" name="doc_appointment" id="edit_doc_appointment" accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                        <div class="file-status text-sm text-green-600 truncate"></div>
                    </div>
                    
                    <!-- Transcript -->
                    <div class="file-drop-zone p-6 text-center border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 transition-colors cursor-pointer" data-file-input="edit_doc_transcript">
                        <i class="fas fa-graduation-cap text-4xl text-blue-400 mb-3"></i>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Transcript of Records</h4>
                        <p class="text-sm text-gray-600 mb-2">Current: ' . (empty($employee['doc_transcript_path']) ? 'Not uploaded' : basename($employee['doc_transcript_path'])) . '</p>
                        <p class="text-xs text-gray-500 mb-3">Supported: JPG, JPEG, PDF, PNG</p>
                        <input type="file" name="doc_transcript" id="edit_doc_transcript" accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                        <div class="file-status text-sm text-green-600 truncate"></div>
                    </div>
                    
                    <!-- Eligibility Certificate -->
                    <div class="file-drop-zone p-6 text-center border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 transition-colors cursor-pointer" data-file-input="edit_doc_eligibility">
                        <i class="fas fa-award text-4xl text-blue-400 mb-3"></i>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Eligibility Certificate</h4>
                        <p class="text-sm text-gray-600 mb-2">Current: ' . (empty($employee['doc_eligibility_path']) ? 'Not uploaded' : basename($employee['doc_eligibility_path'])) . '</p>
                        <p class="text-xs text-gray-500 mb-3">Supported: JPG, JPEG, PDF, PNG</p>
                        <input type="file" name="doc_eligibility" id="edit_doc_eligibility" accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                        <div class="file-status text-sm text-green-600 truncate"></div>
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row justify-between gap-3">
                    <button type="button" class="edit-prev-step text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 w-full sm:w-auto">
                        <i class="fas fa-arrow-left mr-2"></i>Previous
                    </button>
                    <button type="submit" class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 w-full sm:w-auto">
                        <i class="fas fa-save mr-2"></i>Update Employee
                    </button>
                </div>
            </div>
        </form>
        ';

        echo json_encode([
            'success' => true,
            'html' => $html
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found'
        ]);
    }
}

function uploadFiles()
{
    $uploaded_files = [];

    // Create upload directory if it doesn't exist
    $upload_dir = 'uploads/permanent/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // File upload configurations
    $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif'];
    $allowed_doc_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    $max_file_size = 5 * 1024 * 1024; // 5MB

    // Profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['profile_image']['name'];
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (in_array($file_ext, $allowed_image_types)) {
            if ($_FILES['profile_image']['size'] <= $max_file_size) {
                $new_file_name = 'profile_' . time() . '_' . uniqid() . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp, $destination)) {
                    $uploaded_files['profile_image'] = $destination;
                }
            }
        }
    }

    // Document uploads
    $doc_fields = ['doc_id', 'doc_resume', 'doc_service', 'doc_appointment', 'doc_transcript', 'doc_eligibility'];

    foreach ($doc_fields as $doc_field) {
        if (isset($_FILES[$doc_field]) && $_FILES[$doc_field]['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES[$doc_field]['name'];
            $file_tmp = $_FILES[$doc_field]['tmp_name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_doc_types)) {
                if ($_FILES[$doc_field]['size'] <= $max_file_size) {
                    $new_file_name = $doc_field . '_' . time() . '_' . uniqid() . '.' . $file_ext;
                    $destination = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp, $destination)) {
                        $uploaded_files[$doc_field] = $destination;
                    }
                }
            }
        }
    }

    return $uploaded_files;
}

// Note: generateEmployeeId function is kept for compatibility but not used in add operation
function generateEmployeeId($conn)
{
    $year = date('Y');
    $month = date('m');

    // Get the last employee ID for this month
    $sql = "SELECT employee_id FROM permanent WHERE employee_id LIKE 'P-$year-$month-%' ORDER BY employee_id DESC LIMIT 1";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['employee_id'];
        $parts = explode('-', $last_id);
        $last_number = intval($parts[3]);
        $next_number = str_pad($last_number + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $next_number = '001';
    }

    return "P-$year-$month-$next_number";
}

// ===============================================
// FETCH EMPLOYEES FOR DISPLAY
// ===============================================

// Pagination configuration
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

// Build WHERE clause
$where_clause = "WHERE 1=1";
if ($show_inactive) {
    $where_clause .= " AND status = 'Inactive'";
} else {
    $where_clause .= " AND status = 'Active'";
}

if (!empty($filter_office) && $filter_office !== 'all') {
    $safe_office = mysqli_real_escape_string($conn, $filter_office);
    $where_clause .= " AND office = '$safe_office'";
}

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM permanent $where_clause";
$count_result = $conn->query($count_sql);
if ($count_result) {
    $total_records = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $records_per_page);
} else {
    $total_records = 0;
    $total_pages = 0;
}

// Adjust current page if out of bounds
if ($total_pages > 0 && $current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $records_per_page;
}

// Fetch employees for current page
$sql = "SELECT id, employee_id, first_name, last_name, middle, position, office, monthly_salary, amount_accrued, status 
        FROM permanent 
        $where_clause 
        ORDER BY first_name ASC 
        LIMIT $offset, $records_per_page";

$result = $conn->query($sql);
$employees = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // CONSTRUCT FULL NAME FROM COMPONENTS
        $first_name = isset($row['first_name']) ? trim($row['first_name']) : '';
        $last_name = isset($row['last_name']) ? trim($row['last_name']) : '';
        $middle = isset($row['middle']) ? trim($row['middle']) : '';

        // Build the full name
        $full_name_parts = [];

        if (!empty($first_name)) {
            $full_name_parts[] = $first_name;
        }

        if (!empty($middle)) {
            $full_name_parts[] = substr($middle, 0, 1) . '.';
        }

        if (!empty($last_name)) {
            $full_name_parts[] = $last_name;
        }

        $full_name = !empty($full_name_parts) ? implode(' ', $full_name_parts) : 'No Name Provided';

        // ADD THE CONSTRUCTED FULL NAME TO THE ROW
        $row['full_name'] = $full_name;

        $employees[] = $row;
    }
}

// Add this temporarily to see what's happening
if (isset($_GET['debug'])) {
    echo '<pre>';
    echo "Total employees fetched: " . count($employees) . "\n\n";
    foreach ($employees as $emp) {
        if ($emp['id'] == 89) {
            echo "EMPLOYEE ID 89 FOUND:\n";
            print_r($emp);
            break;
        }
    }
    echo '</pre>';
}

// Add search parameter
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Modify the WHERE clause to include search
$where_clause = "WHERE 1=1";
if ($show_inactive) {
    $where_clause .= " AND status = 'Inactive'";
} else {
    $where_clause .= " AND status = 'Active'";
}

if (!empty($filter_office) && $filter_office !== 'all') {
    $safe_office = mysqli_real_escape_string($conn, $filter_office);
    $where_clause .= " AND office = '$safe_office'";
}

// Add search condition
if (!empty($search_term)) {
    $search_term_escaped = mysqli_real_escape_string($conn, $search_term);
    $where_clause .= " AND (
        employee_id LIKE '%$search_term_escaped%' OR 
        first_name LIKE '%$search_term_escaped%' OR 
        last_name LIKE '%$search_term_escaped%' OR 
        middle LIKE '%$search_term_escaped%' OR 
        CONCAT(first_name, ' ', last_name) LIKE '%$search_term_escaped%' OR
        CONCAT(first_name, ' ', middle, ' ', last_name) LIKE '%$search_term_escaped%' OR
        position LIKE '%$search_term_escaped%' OR 
        office LIKE '%$search_term_escaped%'
    )";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permanent Employees</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.3.0/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.75rem;
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

        /* Employee ID input styling */
        #employee_id {
            font-family: monospace;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        #employee_id:focus {
            border-color: #3b82f6;
            ring-color: #3b82f6;
        }
    </style>
</head>

<body>
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

    <!-- MAIN CONTENT -->
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
                            <a href="permanent.php" class="ms-1 text-sm font-medium text-blue-600 md:ms-2">Permanent</a>
                        </li>
                        <li>
                            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                            <a href="contractofservice.php"
                                class="ms-1 text-sm font-medium hover:text-blue-600 md:ms-2">Contractual</a>
                        </li>
                        <li>
                            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                            <a href="Job_order.php"
                                class="ms-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ms-2">Job Order</a>
                        </li>
                    </ol>
                </nav>

                <h1 class="text-xl md:text-2xl font-semibold text-gray-900 mb-4 md:mb-6">Permanent Employee</h1>

                <!-- Status Toggle -->
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <span class="text-sm font-medium text-gray-700">Show:</span>
                        <a href="permanent.php"
                            class="px-3 py-2 text-sm font-medium rounded-lg <?php echo !$show_inactive ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                            Active Employees
                        </a>
                        <a href="permanent.php?show_inactive=1"
                            class="px-3 py-2 text-sm font-medium rounded-lg <?php echo $show_inactive ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                            Inactive Employees
                        </a>
                    </div>
                    <div class="text-sm text-gray-600">
                        <?php if ($show_inactive): ?>
                            <span class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                                Showing inactive employees only
                            </span>
                        <?php else: ?>
                            <span class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                Showing active employees only
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if ($form_submit_success): ?>
                    <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                        <span class="font-medium">Success!</span> <?php echo htmlspecialchars($form_submit_success); ?>
                    </div>
                    <script>
                        setTimeout(() => {
                            const element = document.querySelector('.bg-green-50');
                            if (element) element.remove();
                        }, 5000);
                    </script>
                <?php endif; ?>
                <?php if ($form_submit_error): ?>
                    <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                        <span class="font-medium">Error:</span> <?php echo htmlspecialchars($form_submit_error); ?>
                    </div>
                    <script>
                        setTimeout(() => {
                            const element = document.querySelector('.bg-red-50');
                            if (element) element.remove();
                        }, 5000);
                    </script>
                <?php endif; ?>

                <div class="bg-white shadow-md sm:rounded-lg overflow-hidden">
                    <!-- Search and Filter Section -->
                    <!-- Search and Filter Section -->
                    <div
                        class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                        <div class="w-full md:w-1/3">
                            <form class="flex items-center" method="GET" action="permanent.php" id="searchForm">
                                <?php if ($show_inactive): ?>
                                    <input type="hidden" name="show_inactive" value="1">
                                <?php endif; ?>
                                <?php if (!empty($filter_office) && $filter_office !== 'all'): ?>
                                    <input type="hidden" name="office"
                                        value="<?php echo htmlspecialchars($filter_office); ?>">
                                <?php endif; ?>
                                <label for="search-input" class="sr-only">Search</label>
                                <div class="relative w-full">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <i class="fas fa-search text-gray-500"></i>
                                    </div>
                                    <input type="text" id="search-input" name="search"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2"
                                        placeholder="Search for Employee (ID, Name, Position, Department)"
                                        value="<?php echo htmlspecialchars($search_term); ?>">
                                </div>
                            </form>
                        </div>
                        <!-- Rest of your filter section remains exactly the same -->
                        <div
                            class="w-full md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0">
                            <!-- Filter Dropdown -->
                            <div class="relative w-full md:w-auto">
                                <button id="filterDropdownButton" data-dropdown-toggle="filterDropdown"
                                    class="w-full md:w-auto h-10 flex items-center justify-center text-gray-900 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 font-medium rounded-lg text-sm px-4 py-2 text-center inline-flex"
                                    type="button">
                                    <i class="fas fa-filter mr-2"></i>
                                    <span>Filter by Department</span>
                                    <i class="fas fa-chevron-down ml-2"></i>
                                </button>
                                <div id="filterDropdown"
                                    class="z-10 hidden absolute w-48 bg-white rounded-lg shadow-lg mt-1">
                                    <ul class="py-1 text-sm text-gray-700 max-h-64 overflow-y-auto"
                                        aria-labelledby="filterDropdownButton">
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=all<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo ($filter_office === 'all' || empty($filter_office)) ? 'bg-blue-50 text-blue-600' : ''; ?>">All
                                                Departments</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Office of the Municipal Mayor<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Office of the Municipal Mayor' ? 'bg-blue-50 text-blue-600' : ''; ?>">Office
                                                of the Municipal Mayor</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Human Resource Management Division<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Human Resource Management Division' ? 'bg-blue-50 text-blue-600' : ''; ?>">Human
                                                Resource Management Division</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Business Permit and Licensing Division<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Business Permit and Licensing Division' ? 'bg-blue-50 text-blue-600' : ''; ?>">Business
                                                Permit and Licensing Division</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Sangguniang Bayan Office<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Sangguniang Bayan Office' ? 'bg-blue-50 text-blue-600' : ''; ?>">Sangguniang
                                                Bayan Office</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Office of the Municipal Accountant<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Office of the Municipal Accountant' ? 'bg-blue-50 text-blue-600' : ''; ?>">Office
                                                of the Municipal Accountant</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Office of the Assessor<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Office of the Assessor' ? 'bg-blue-50 text-blue-600' : ''; ?>">Office
                                                of the Assessor</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Municipal Budget Office<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Municipal Budget Office' ? 'bg-blue-50 text-blue-600' : ''; ?>">Municipal
                                                Budget Office</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Municipal Planning and Development Office<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Municipal Planning and Development Office' ? 'bg-blue-50 text-blue-600' : ''; ?>">Municipal
                                                Planning and Development Office</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Municipal Engineering Office<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Municipal Engineering Office' ? 'bg-blue-50 text-blue-600' : ''; ?>">Municipal
                                                Engineering Office</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Municipal Disaster Risk Reduction and Management Office<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Municipal Disaster Risk Reduction and Management Office' ? 'bg-blue-50 text-blue-600' : ''; ?>">Municipal
                                                Disaster Risk Reduction and Management Office</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Municipal Social Welfare and Development Office<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Municipal Social Welfare and Development Office' ? 'bg-blue-50 text-blue-600' : ''; ?>">Municipal
                                                Social Welfare and Development Office</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Municipal Environment and Natural Resources Office<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Municipal Environment and Natural Resources Office' ? 'bg-blue-50 text-blue-600' : ''; ?>">Municipal
                                                Environment and Natural Resources Office</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Office of the Municipal Agriculturist<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Office of the Municipal Agriculturist' ? 'bg-blue-50 text-blue-600' : ''; ?>">Office
                                                of the Municipal Agriculturist</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Municipal General Services Office<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Municipal General Services Office' ? 'bg-blue-50 text-blue-600' : ''; ?>">Municipal
                                                General Services Office</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Municipal Public Employment Service Office<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Municipal Public Employment Service Office' ? 'bg-blue-50 text-blue-600' : ''; ?>">Municipal
                                                Public Employment Service Office</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Municipal Health Office<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Municipal Health Office' ? 'bg-blue-50 text-blue-600' : ''; ?>">Municipal
                                                Health Office</a></li>
                                        <li><a href="permanent.php?<?php echo $show_inactive ? 'show_inactive=1&' : ''; ?>office=Municipal Treasurer's Office<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>"
                                                class="block px-4 py-2 hover:bg-gray-100 <?php echo $filter_office === 'Municipal Treasurer\'s Office' ? 'bg-blue-50 text-blue-600' : ''; ?>">Municipal
                                                Treasurer's Office</a></li>
                                    </ul>
                                </div>
                            </div>

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

                            <!-- Add Employee Button -->
                            <?php if (!$show_inactive): ?>
                                <button id="addEmployeeBtn" type="button"
                                    class="w-full md:w-auto flex items-center justify-center text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 h-10">
                                    <i class="fas fa-plus mr-2"></i>
                                    <span>Add new Permanent</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Employees Table -->
                    <div class="overflow-x-auto ">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-white uppercase bg-blue-600">
                                <tr>
                                    <th scope="col" class="p-2 md:p-4">#</th>
                                    <th scope="col" class="px-3 md:px-6 py-3 hidden md:table-cell">EMPLOYEE ID</th>
                                    <th scope="col" class="px-3 md:px-6 py-3">FULL NAME</th>
                                    <th scope="col" class="px-3 md:px-6 py-3 hidden md:table-cell">POSITION</th>
                                    <th scope="col" class="px-3 md:px-6 py-3 hidden md:table-cell">OFFICE</th>
                                    <th scope="col" class="px-3 md:px-6 py-3 hidden md:table-cell">STATUS</th>
                                    <th scope="col" class="px-3 md:px-6 py-3 text-right hidden md:table-cell">MONTHLY
                                        SALARY</th>
                                    <th scope="col" class="px-3 md:px-6 py-3 text-right hidden md:table-cell">AMOUNT
                                        ACCRUED</th>
                                    <th scope="col" class="px-3 md:px-6 py-3 text-center">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($employees)): ?>
                                    <?php $row_index = $offset + 1; ?>
                                    <?php foreach ($employees as $employee): ?>
                                        <tr class="bg-white border-b hover:bg-gray-50">
                                            <td class="w-4 p-2 md:p-4 text-center"><?php echo $row_index++; ?></td>
                                            <td class="px-3 md:px-6 py-4 hidden md:table-cell">
                                                <span
                                                    class="font-medium text-blue-600"><?php echo htmlspecialchars($employee['employee_id'] ?? 'N/A'); ?></span>
                                            </td>
                                            <th scope="row" class="px-3 md:px-6 py-4 font-medium text-gray-900">
                                                <div class="md:hidden">
                                                    <div class="font-semibold">
                                                        <?php echo htmlspecialchars($employee['full_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">Employee ID:
                                                        <?php echo htmlspecialchars($employee['employee_id'] ?? 'N/A'); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($employee['position']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($employee['office']); ?>
                                                    </div>
                                                    <div class="text-sm mt-1">
                                                        <span
                                                            class="status-badge <?php echo ($employee['status'] === 'Active') ? 'status-active' : 'status-inactive'; ?>">
                                                            <?php echo htmlspecialchars($employee['status']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="text-sm font-medium mt-1">
                                                        ₱<?php echo number_format($employee['monthly_salary'], 2); ?> / month
                                                    </div>
                                                    <div class="text-sm text-gray-600 mt-1">Accrued:
                                                        ₱<?php echo number_format($employee['amount_accrued'], 2); ?>
                                                    </div>
                                                </div>
                                                <span class="hidden md:inline">
                                                    <?php echo htmlspecialchars($employee['full_name']); ?>
                                                </span>
                                            </th>
                                            <td class="px-3 md:px-6 py-4 hidden md:table-cell">
                                                <?php echo htmlspecialchars($employee['position']); ?>
                                            </td>
                                            <td class="px-3 md:px-6 py-4 hidden md:table-cell">
                                                <?php echo htmlspecialchars($employee['office']); ?>
                                            </td>
                                            <td class="px-3 md:px-6 py-4 hidden md:table-cell">
                                                <span
                                                    class="status-badge <?php echo ($employee['status'] === 'Active') ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo htmlspecialchars($employee['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-3 md:px-6 py-4 text-right hidden md:table-cell">
                                                ₱<?php echo number_format($employee['monthly_salary'], 2); ?>
                                            </td>
                                            <td class="px-3 md:px-6 py-4 text-right hidden md:table-cell">
                                                ₱<?php echo number_format($employee['amount_accrued'], 2); ?>
                                            </td>
                                            <td class="px-3 md:px-6 py-4 text-center">
                                                <div class="action-buttons">
                                                    <button onclick="viewEmployee(<?php echo $employee['id']; ?>)"
                                                        class="action-btn view" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button onclick="editEmployee(<?php echo $employee['id']; ?>)"
                                                        class="action-btn edit" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($employee['status'] === 'Active'): ?>
                                                        <button onclick="markInactive(<?php echo $employee['id']; ?>)"
                                                            class="action-btn inactive" title="Mark as Inactive">
                                                            <i class="fas fa-user-times"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button onclick="markActive(<?php echo $employee['id']; ?>)"
                                                            class="action-btn active" title="Mark as Active">
                                                            <i class="fas fa-user-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="bg-white border-b">
                                        <td colspan="9" class="px-6 py-4 text-center text-gray-700">
                                            <?php if ($show_inactive): ?>
                                                No inactive employees found.
                                            <?php else: ?>
                                                No active employees found.
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing
                            <span class="font-semibold text-gray-900">
                                <?php echo ($total_records > 0) ? ($offset + 1) : 0; ?>-<?php echo min($offset + count($employees), $total_records); ?>
                            </span>
                            of
                            <span class="font-semibold text-gray-900"><?php echo $total_records; ?></span>
                            <?php if (!empty($filter_office) && $filter_office !== 'all'): ?>
                                <span class="text-blue-600 ml-2">[Filtered by:
                                    <?php echo htmlspecialchars($filter_office); ?>]</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-nav">
                                <!-- First Page Button -->
                                <?php if ($current_page > 1): ?>
                                    <a href="permanent.php?page=1<?php
                                    echo ($show_inactive ? '&show_inactive=1' : '');
                                    echo (!empty($filter_office) && $filter_office !== 'all' ? '&office=' . urlencode($filter_office) : '');
                                    echo (!empty($search_term) ? '&search=' . urlencode($search_term) : '');
                                    ?>" class="pagination-btn" title="First Page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="pagination-btn" disabled>
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- First Page Button -->
                                <?php if ($current_page > 1): ?>
                                    <a href="permanent.php?page=1<?php
                                    echo ($show_inactive ? '&show_inactive=1' : '');
                                    echo (!empty($filter_office) && $filter_office !== 'all' ? '&office=' . urlencode($filter_office) : '');
                                    echo (!empty($search_term) ? '&search=' . urlencode($search_term) : '');
                                    ?>" class="pagination-btn" title="First Page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="pagination-btn" disabled>
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php
                                // Smart page number display
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);

                                // Show first page with ellipsis if needed
                                if ($start_page > 1) {
                                    echo '<a href="permanent.php?page=1' . ($show_inactive ? '&show_inactive=1' : '') . (!empty($filter_office) ? '&office=' . urlencode($filter_office) : '') . '" class="pagination-btn">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span class="pagination-btn pagination-ellipsis">...</span>';
                                    }
                                }

                                // Show page numbers
                                for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                    <a href="permanent.php?page=<?php echo $i; ?><?php echo ($show_inactive ? '&show_inactive=1' : '') . (!empty($filter_office) ? '&office=' . urlencode($filter_office) : ''); ?>"
                                        class="pagination-btn <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="pagination-btn pagination-ellipsis">...</span>';
                                    }
                                    echo '<a href="permanent.php?page=' . $total_pages . ($show_inactive ? '&show_inactive=1' : '') . (!empty($filter_office) ? '&office=' . urlencode($filter_office) : '') . '" class="pagination-btn">' . $total_pages . '</a>';
                                }
                                ?>

                                <!-- First Page Button -->
                                <?php if ($current_page > 1): ?>
                                    <a href="permanent.php?page=1<?php
                                    echo ($show_inactive ? '&show_inactive=1' : '');
                                    echo (!empty($filter_office) && $filter_office !== 'all' ? '&office=' . urlencode($filter_office) : '');
                                    echo (!empty($search_term) ? '&search=' . urlencode($search_term) : '');
                                    ?>" class="pagination-btn" title="First Page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="pagination-btn" disabled>
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- First Page Button -->
                                <?php if ($current_page > 1): ?>
                                    <a href="permanent.php?page=1<?php
                                    echo ($show_inactive ? '&show_inactive=1' : '');
                                    echo (!empty($filter_office) && $filter_office !== 'all' ? '&office=' . urlencode($filter_office) : '');
                                    echo (!empty($search_term) ? '&search=' . urlencode($search_term) : '');
                                    ?>" class="pagination-btn" title="First Page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="pagination-btn" disabled>
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Backdrop -->
    <div id="modalBackdrop" class="modal-backdrop"></div>

    <!-- Add/Edit Employee Modal -->
    <div id="employeeModal" class="modal-container">
        <div class="modal-content w-full max-w-6xl">
            <!-- Modal header -->
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t sticky top-0 bg-white z-10">
                <h3 class="text-xl font-semibold text-gray-900" id="modalTitle">Add New Permanent Employee</h3>
                <button type="button"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center"
                    onclick="closeModal()">
                    <i class="fas fa-times"></i>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>

            <!-- Modal body -->
            <div class="p-4 md:p-5">
                <!-- Step Navigation -->
                <div class="flex mb-4 border-b sticky top-0 bg-white z-10 pt-2 overflow-x-auto">
                    <button type="button"
                        class="step-nav flex-1 py-2 px-4 text-center font-medium border-b-2 border-blue-600 text-blue-600 whitespace-nowrap min-w=[120px]"
                        data-step="1">Professional</button>
                    <button type="button"
                        class="step-nav flex-1 py-2 px-4 text-center font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 whitespace-nowrap min-w=[120px]"
                        data-step="2">Personal</button>
                    <button type="button"
                        class="step-nav flex-1 py-2 px-4 text-center font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 whitespace-nowrap min-w=[120px]"
                        data-step="3">Documents</button>
                </div>

                <!-- Multi-Step Form -->
                <form id="employeeForm" action="permanent.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="employee_id_hidden" id="employeeId" value="">

                    <!-- Step 1: Professional Information -->
                    <div id="step1" class="form-step active">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Professional Details</h2>

                        <div class="grid gap-4 mb-4">
                            <!-- Employee ID Field - User Input -->
                            <div>
                                <label for="employee_id" class="block mb-2 text-sm font-medium text-gray-900">
                                    Employee ID * <span class="text-xs text-gray-500 ml-2">(Format: P-YYYY-MM-XXX or
                                        custom)</span>
                                </label>
                                <div class="relative">
                                    <input type="text" name="employee_id" id="employee_id"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="Enter Employee ID (e.g., P-2024-01-001)" required
                                        pattern="^[A-Za-z0-9\-_]+$"
                                        title="Employee ID can contain letters, numbers, hyphens, and underscores">
                                    <div class="mt-1 text-xs text-gray-500">
                                        Format: P-YYYY-MM-XXX (example: P-2024-01-001) or use your own format
                                    </div>
                                </div>
                                <div class="error-message" id="employee_id_error"></div>
                            </div>

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
                                    <label for="last_name" class="block mb-2 text-sm font-medium text-gray-900">Middle
                                        Initial *</label>
                                    <input type="text" name="middle" id="middle"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="position" class="block mb-2 text-sm font-medium text-gray-900">Position
                                        *</label>
                                    <input type="text" name="position" id="position"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="Job title" required>
                                </div>
                                <div>
                                    <label for="office"
                                        class="block mb-2 text-sm font-medium text-gray-900">Office/Department *</label>
                                    <select name="office" id="office"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                        <option value="">Select Department</option>
                                        <option value="Office of the Municipal Mayor">Office of the Municipal Mayor
                                        </option>
                                        <option value="Human Resource Management Division">Human Resource Management
                                            Division</option>
                                        <option value="Business Permit and Licensing Division">Business Permit and
                                            Licensing Division</option>
                                        <option value="Sangguniang Bayan Office">Sangguniang Bayan Office</option>
                                        <option value="Office of the Municipal Accountant">Office of the Municipal
                                            Accountant</option>
                                        <option value="Office of the Assessor">Office of the Assessor</option>
                                        <option value="Municipal Budget Office">Municipal Budget Office</option>
                                        <option value="Municipal Planning and Development Office">Municipal Planning and
                                            Development Office</option>
                                        <option value="Municipal Engineering Office">Municipal Engineering Office
                                        </option>
                                        <option value="Municipal Disaster Risk Reduction and Management Office">
                                            Municipal Disaster Risk Reduction and Management Office</option>
                                        <option value="Municipal Social Welfare and Development Office">Municipal Social
                                            Welfare and Development Office</option>
                                        <option value="Municipal Environment and Natural Resources Office">Municipal
                                            Environment and Natural Resources Office</option>
                                        <option value="Office of the Municipal Agriculturist">Office of the Municipal
                                            Agriculturist</option>
                                        <option value="Municipal General Services Office">Municipal General Services
                                            Office</option>
                                        <option value="Municipal Public Employment Service Office">Municipal Public
                                            Employment Service Office</option>
                                        <option value="Municipal Health Office">Municipal Health Office</option>
                                        <option value="Municipal Treasurer's Office">Municipal Treasurer's Office
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="monthly_salary"
                                        class="block mb-2 text-sm font-medium text-gray-900">Monthly Salary *</label>
                                    <input type="number" name="monthly_salary" id="monthly_salary" step="0.01" min="0"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="0.00" required>
                                    <div class="error-message" id="monthly_salary_error"></div>
                                </div>
                                <div>
                                    <label for="amount_accrued"
                                        class="block mb-2 text-sm font-medium text-gray-900">Amount Accrued *</label>
                                    <input type="number" name="amount_accrued" id="amount_accrued" step="0.01" min="0"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        placeholder="0.00" required>
                                    <div class="error-message" id="amount_accrued_error"></div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="button"
                                class="next-step text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 w-full md:w-auto"
                                data-next="2">
                                Next <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Personal Information -->
                    <div id="step2" class="form-step hidden">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Personal Details</h2>

                        <div class="grid gap-4 mb-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-1">
                                    <div class="flex flex-col items-center">
                                        <div id="profileImageContainer"
                                            class="w-32 h-32 bg-gray-200 rounded-full flex items-center justify-center mb-4 overflow-hidden">
                                            <i class="fas fa-user text-gray-400 text-4xl"></i>
                                        </div>
                                        <input type="file" name="profile_image" id="profile_image" accept="image/*"
                                            class="hidden">
                                        <label for="profile_image"
                                            class="cursor-pointer text-blue-600 hover:text-blue-800 text-sm font-medium text-center">
                                            <i class="fas fa-upload mr-1"></i>Upload Photo
                                        </label>
                                        <input type="hidden" id="current_profile_image" name="current_profile_image"
                                            value="">
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="mobile_number"
                                        class="block mb-2 text-sm font-medium text-gray-900">Mobile Number *</label>
                                    <input type="text" name="mobile_number" id="mobile_number"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                    <div class="error-message" id="mobile_number_error"></div>
                                </div>
                                <div>
                                    <label for="email_address"
                                        class="block mb-2 text-sm font-medium text-gray-900">Email Address *</label>
                                    <input type="email" name="email_address" id="email_address"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="date_of_birth" class="block mb-2 text-sm font-medium text-gray-900">Date
                                        of Birth *</label>
                                    <input type="date" name="date_of_birth" id="date_of_birth"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                </div>
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
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                </div>
                            </div>

                            <div>
                                <label for="street_address" class="block mb-2 text-sm font-medium text-gray-900">Street
                                    Address *</label>
                                <input type="text" name="street_address" id="street_address"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    required>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="city" class="block mb-2 text-sm font-medium text-gray-900">City
                                        *</label>
                                    <input type="text" name="city" id="city"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                </div>
                                <div>
                                    <label for="state_region"
                                        class="block mb-2 text-sm font-medium text-gray-900">State/Region *</label>
                                    <input type="text" name="state_region" id="state_region"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                </div>
                                <div>
                                    <label for="ip_code" class="block mb-2 text-sm font-medium text-gray-900">IP Code
                                        *</label>
                                    <input type="text" name="ip_code" id="ip_code"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                </div>
                            </div>

                            <!-- <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="password" class="block mb-2 text-sm font-medium text-gray-900">Password
                                        *</label>
                                    <input type="password" name="password" id="password"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                </div>
                                <div>
                                    <label for="confirm_password"
                                        class="block mb-2 text-sm font-medium text-gray-900">Confirm Password *</label>
                                    <input type="password" name="confirm_password" id="confirm_password"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                        required>
                                </div>
                            </div> -->

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
                                    <div class="flex flex-col sm:flex-row sm:space-x-4 space-y-2 sm:space-y-0">
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="eligibility" value="Eligible"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500"
                                                checked>
                                            <span class="ml-2 text-sm font-medium text-gray-900">Eligible</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="eligibility" value="Not Eligible"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500">
                                            <span class="ml-2 text-sm font-medium text-gray-900">Not Eligible</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row justify-between gap-3">
                            <button type="button"
                                class="prev-step text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 w-full sm:w-auto">
                                <i class="fas fa-arrow-left mr-2"></i>Previous
                            </button>
                            <button type="button"
                                class="next-step text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 w-full sm:w-auto"
                                data-next="3">
                                Next <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Documents -->
                    <div id="step3" class="form-step hidden">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Documents</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <!-- Government ID -->
                            <div class="file-drop-zone p-6 text-center border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 transition-colors cursor-pointer"
                                data-file-input="doc_id">
                                <i class="fas fa-id-card text-4xl text-blue-400 mb-3"></i>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">Government Issued ID</h4>
                                <p class="text-sm text-gray-600 mb-2">Upload government-issued identification</p>
                                <p class="text-xs text-gray-500 mb-3">Supported: JPG, JPEG, PDF, PNG</p>
                                <input type="file" name="doc_id" id="doc_id" accept=".jpg,.jpeg,.pdf,.png"
                                    class="hidden">
                                <div class="file-status text-sm text-green-600 truncate"></div>
                            </div>

                            <!-- Resume -->
                            <div class="file-drop-zone p-6 text-center border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 transition-colors cursor-pointer"
                                data-file-input="doc_resume">
                                <i class="fas fa-file-alt text-4xl text-blue-400 mb-3"></i>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">Resume / CV</h4>
                                <p class="text-sm text-gray-600 mb-2">Upload resume or curriculum vitae</p>
                                <p class="text-xs text-gray-500 mb-3">Supported: JPG, JPEG, PDF, PNG</p>
                                <input type="file" name="doc_resume" id="doc_resume" accept=".jpg,.jpeg,.pdf,.png"
                                    class="hidden">
                                <div class="file-status text-sm text-green-600 truncate"></div>
                            </div>

                            <!-- Service Record -->
                            <div class="file-drop-zone p-6 text-center border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 transition-colors cursor-pointer"
                                data-file-input="doc_service">
                                <i class="fas fa-history text-4xl text-blue-400 mb-3"></i>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">Service Record</h4>
                                <p class="text-sm text-gray-600 mb-2">Upload service record document</p>
                                <p class="text-xs text-gray-500 mb-3">Supported: JPG, JPEG, PDF, PNG</p>
                                <input type="file" name="doc_service" id="doc_service" accept=".jpg,.jpeg,.pdf,.png"
                                    class="hidden">
                                <div class="file-status text-sm text-green-600 truncate"></div>
                            </div>

                            <!-- Appointment Paper -->
                            <div class="file-drop-zone p-6 text-center border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 transition-colors cursor-pointer"
                                data-file-input="doc_appointment">
                                <i class="fas fa-file-contract text-4xl text-blue-400 mb-3"></i>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">Appointment Paper</h4>
                                <p class="text-sm text-gray-600 mb-2">Upload appointment paper</p>
                                <p class="text-xs text-gray-500 mb-3">Supported: JPG, JPEG, PDF, PNG</p>
                                <input type="file" name="doc_appointment" id="doc_appointment"
                                    accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                                <div class="file-status text-sm text-green-600 truncate"></div>
                            </div>

                            <!-- Transcript -->
                            <div class="file-drop-zone p-6 text-center border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 transition-colors cursor-pointer"
                                data-file-input="doc_transcript">
                                <i class="fas fa-graduation-cap text-4xl text-blue-400 mb-3"></i>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">Transcript of Records</h4>
                                <p class="text-sm text-gray-600 mb-2">Upload transcript and diploma</p>
                                <p class="text-xs text-gray-500 mb-3">Supported: JPG, JPEG, PDF, PNG</p>
                                <input type="file" name="doc_transcript" id="doc_transcript"
                                    accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                                <div class="file-status text-sm text-green-600 truncate"></div>
                            </div>

                            <!-- Eligibility Certificate -->
                            <div class="file-drop-zone p-6 text-center border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 transition-colors cursor-pointer"
                                data-file-input="doc_eligibility">
                                <i class="fas fa-award text-4xl text-blue-400 mb-3"></i>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">Eligibility Certificate</h4>
                                <p class="text-sm text-gray-600 mb-2">Upload civil service eligibility</p>
                                <p class="text-xs text-gray-500 mb-3">Supported: JPG, JPEG, PDF, PNG</p>
                                <input type="file" name="doc_eligibility" id="doc_eligibility"
                                    accept=".jpg,.jpeg,.pdf,.png" class="hidden">
                                <div class="file-status text-sm text-green-600 truncate"></div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row justify-between gap-3">
                            <button type="button"
                                class="prev-step text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 w-full sm:w-auto">
                                <i class="fas fa-arrow-left mr-2"></i>Previous
                            </button>
                            <button type="submit"
                                class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 w-full sm:w-auto">
                                <i class="fas fa-check-circle mr-2"></i><span id="submitButtonText">Submit
                                    Employee</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>

    <!-- Edit Employee Modal -->
    <div id="editEmployeeModal" class="modal-container">
        <div class="modal-content w-full max-w-6xl">
            <!-- Modal header -->
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t sticky top-0 bg-white z-10">
                <h3 class="text-xl font-semibold text-gray-900">Edit Permanent Employee</h3>
                <button type="button"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center"
                    onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>

            <!-- Modal body -->
            <div class="p-4 md:p-5 edit-form-container">
                <div id="editFormContent">
                    <!-- Content will be loaded via AJAX -->
                    <div class="flex justify-center items-center h-64">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Employee Modal -->
    <div id="viewEmployeeModal" class="modal-container">
        <div class="modal-content w-full max-w-4xl">
            <!-- Modal header -->
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t sticky top-0 bg-white z-10">
                <h3 class="text-xl font-semibold text-gray-900">Employee Details</h3>
                <button type="button"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center"
                    onclick="closeViewModal()">
                    <i class="fas fa-times"></i>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>

            <!-- Modal body -->
            <div class="p-4 md:p-5" id="viewEmployeeContent">
                <!-- Content will be loaded via AJAX -->
                <div class="flex justify-center items-center h-64">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Add debounced search functionality
        let searchTimeout;
        const searchInput = document.getElementById('search-input');
        const searchForm = document.getElementById('searchForm');

        if (searchInput && searchForm) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    searchForm.submit();
                }, 500); // Wait 500ms after user stops typing before submitting
            });

            // Optional: Allow Enter key to submit immediately
            searchInput.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(searchTimeout);
                    searchForm.submit();
                }
            });
        }

        // Update records per page function to preserve search
        function changeRecordsPerPage(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', 1); // Reset to first page
            window.location.href = url.toString();
        }
    </script>
    <!-- Custom JavaScript -->
    <script>
        // ===============================================
        // NAVBAR DATE & TIME FUNCTIONALITY
        // ===============================================
        function updateDateTime() {
            const now = new Date();

            // Format date: Weekday, Month Day, Year
            const optionsDate = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
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
        // EMPLOYEE ID VALIDATION FUNCTION
        // ===============================================
        function validateEmployeeId(input) {
            const value = input.value.trim();

            if (value === '') {
                showInputError(input, 'Employee ID is required');
                return false;
            }

            // Allow alphanumeric, hyphens, underscores
            const regex = /^[A-Za-z0-9\-_]+$/;

            if (!regex.test(value)) {
                showInputError(input, 'Employee ID can only contain letters, numbers, hyphens, and underscores');
                return false;
            }

            // Check minimum length
            if (value.length < 3) {
                showInputError(input, 'Employee ID must be at least 3 characters');
                return false;
            }

            removeInputError(input);
            return true;
        }

        // ===============================================
        // INPUT VALIDATION FUNCTIONS
        // ===============================================
        function validateFullName(input) {
            const value = input.value.trim();
            // Allow letters, spaces, hyphens, apostrophes, and periods
            const regex = /^[A-Za-z\s\-'.]+$/;

            if (value === '') {
                removeInputError(input);
                return true; // Empty validation handled elsewhere
            }

            if (!regex.test(value)) {
                showInputError(input, 'Full name can only contain letters, spaces, hyphens, and apostrophes');
                return false;
            } else {
                removeInputError(input);
                return true;
            }
        }

        function validateMobileNumber(input) {
            const value = input.value.trim();
            // Allow numbers only and limit to 11 digits
            const regex = /^\d{0,11}$/;

            if (value === '') {
                removeInputError(input);
                return true; // Empty validation handled elsewhere
            }

            if (!regex.test(value)) {
                showInputError(input, 'Mobile number must contain numbers only');
                return false;
            } else if (value.length !== 11) {
                showInputError(input, 'Mobile number must be exactly 11 digits');
                return false;
            } else {
                removeInputError(input);
                return true;
            }
        }

        function validateSalary(input) {
            const value = input.value.trim();
            // Allow numbers and decimal point only
            const regex = /^\d*\.?\d*$/;

            if (value === '') {
                removeInputError(input);
                return true; // Empty validation handled elsewhere
            }

            if (!regex.test(value)) {
                showInputError(input, 'Salary must contain numbers only');
                return false;
            } else if (parseFloat(value) <= 0) {
                showInputError(input, 'Salary must be greater than 0');
                return false;
            } else {
                removeInputError(input);
                return true;
            }
        }

        function showInputError(input, message) {
            // Remove existing error
            removeInputError(input);

            // Add error styling
            input.classList.add('border-red-500', 'bg-red-50');

            // Create error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message text-red-500 text-xs mt-1';
            errorDiv.textContent = message;
            errorDiv.id = input.id + '_error';

            // Insert after input
            input.parentNode.appendChild(errorDiv);
        }

        function removeInputError(input) {
            // Remove error styling
            input.classList.remove('border-red-500', 'bg-red-50');

            // Remove error message
            const errorDiv = document.getElementById(input.id + '_error');
            if (errorDiv) {
                errorDiv.remove();
            }
        }

        // ===============================================
        // MODAL FUNCTIONS
        // ===============================================
        let currentStep = 1;
        let editCurrentStep = 1;

        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            const backdrop = document.getElementById('modalBackdrop');

            if (modal && backdrop) {
                modal.style.display = 'flex';
                backdrop.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        function hideModal(modalId) {
            const modal = document.getElementById(modalId);
            const backdrop = document.getElementById('modalBackdrop');

            if (modal && backdrop) {
                modal.style.display = 'none';
                backdrop.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Permanent Employee';
            document.getElementById('formAction').value = 'add';
            document.getElementById('employeeId').value = '';
            document.getElementById('submitButtonText').textContent = 'Submit Employee';

            // Reset form
            document.getElementById('employeeForm').reset();
            resetFormToStep1();

            // Reset file drop zones
            document.querySelectorAll('.file-drop-zone').forEach(zone => {
                const fileStatus = zone.querySelector('.file-status');
                if (fileStatus) {
                    fileStatus.textContent = '';
                }
                zone.classList.remove('border-green-500', 'bg-green-50');
            });

            // Reset profile image
            const profileImageContainer = document.getElementById('profileImageContainer');
            if (profileImageContainer) {
                profileImageContainer.innerHTML = '<i class="fas fa-user text-gray-400 text-4xl"></i>';
            }

            // Remove all error messages and styling
            document.querySelectorAll('.error-message').forEach(error => error.remove());
            document.querySelectorAll('.border-red-500, .bg-red-50').forEach(el => {
                el.classList.remove('border-red-500', 'bg-red-50');
            });

            // Show modal
            showModal('employeeModal');
        }

        async function editEmployee(employeeId) {
            // Ensure all other modals are closed
            closeModal();
            closeViewModal();

            // Show edit modal
            showModal('editEmployeeModal');

            // Show loading state
            document.getElementById('editFormContent').innerHTML = `
        <div class="flex justify-center items-center h-64">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
        </div>
    `;

            // Fetch employee data via AJAX for edit form
            try {
                const response = await fetch(`permanent.php?edit_id=${employeeId}`);
                const data = await response.json();

                if (data.success) {
                    document.getElementById('editFormContent').innerHTML = data.html;
                    editCurrentStep = 1;

                    // IMPORTANT: Setup navigation after content is loaded
                    setTimeout(() => {
                        setupEditFormNavigation();

                        // Show first step
                        showEditStep(1);
                        updateEditStepNavigation();
                    }, 50);

                    // Add form validation for edit form
                    const editForm = document.getElementById('editEmployeeForm');
                    if (editForm) {
                        editForm.addEventListener('submit', function (e) {
                            const submitBtn = editForm.querySelector('button[type="submit"]');
                            if (submitBtn) {
                                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
                                submitBtn.disabled = true;
                            }
                        });
                    }
                } else {
                    alert('Error loading employee data: ' + data.message);
                    closeEditModal();
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error loading employee data. Please try again.');
                closeEditModal();
            }
        }

        function editFromView(employeeId) {
            // Close the view modal first
            closeViewModal();

            // Small delay to ensure modal is closed before opening edit modal
            setTimeout(() => {
                editEmployee(employeeId);
            }, 100);
        }

        async function viewEmployee(employeeId) {
            // Show loading state
            document.getElementById('viewEmployeeContent').innerHTML = `
                <div class="flex justify-center items-center h-64">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            `;

            // Fetch employee data via AJAX
            try {
                const response = await fetch(`permanent.php?view_id=${employeeId}`);
                const data = await response.json();

                if (data.success) {
                    document.getElementById('viewEmployeeContent').innerHTML = data.html;
                    showModal('viewEmployeeModal');
                } else {
                    document.getElementById('viewEmployeeContent').innerHTML = `
                        <div class="text-center p-8">
                            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                            <p class="text-red-600">${data.message}</p>
                        </div>
                    `;
                    showModal('viewEmployeeModal');
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('viewEmployeeContent').innerHTML = `
                    <div class="text-center p-8">
                        <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                        <p class="text-red-600">Error loading employee data. Please try again.</p>
                    </div>
                `;
                showModal('viewEmployeeModal');
            }
        }

        function markInactive(employeeId) {
            if (confirm('Are you sure you want to mark this employee as inactive?\n\nThis will:\n• Prevent them from logging in\n• Hide them from active employee lists\n• Keep their records for reporting\n\nYou can reactivate them later.')) {
                window.location.href = `permanent.php?mark_inactive=${employeeId}&status=Inactive`;
            }
        }

        function markActive(employeeId) {
            if (confirm('Are you sure you want to mark this employee as active?\n\nThis will:\n• Allow them to log in again\n• Show them in active employee lists\n• Include them in payroll calculations')) {
                window.location.href = `permanent.php?mark_inactive=${employeeId}&status=Active`;
            }
        }

        function closeModal() {
            hideModal('employeeModal');
        }

        function closeEditModal() {
            hideModal('editEmployeeModal');
        }

        function closeViewModal() {
            hideModal('viewEmployeeModal');
        }

        // Close modal when clicking on backdrop
        document.getElementById('modalBackdrop').addEventListener('click', function () {
            closeModal();
            closeEditModal();
            closeViewModal();
        });

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

        // Edit Form Navigation Functions
        function showEditStep(stepIndex) {
            document.querySelectorAll('.edit-form-step').forEach(step => {
                step.classList.add('hidden');
                step.classList.remove('active');
            });

            const currentStepElement = document.getElementById(`edit-step${stepIndex}`);
            if (currentStepElement) {
                currentStepElement.classList.remove('hidden');
                currentStepElement.classList.add('active');
            }
        }

        function updateEditStepNavigation() {
            document.querySelectorAll('.edit-step-nav').forEach(nav => {
                const step = parseInt(nav.getAttribute('data-step'));
                nav.classList.remove('border-blue-600', 'text-blue-600', 'border-transparent', 'text-gray-500');

                if (step === editCurrentStep) {
                    nav.classList.add('border-blue-600', 'text-blue-600');
                } else {
                    nav.classList.add('border-transparent', 'text-gray-500');
                }
            });
        }

        function setupEditFormNavigation() {
            // Wait a tiny bit for DOM to update
            setTimeout(() => {
                // Edit step navigation click
                document.querySelectorAll('.edit-step-nav').forEach(nav => {
                    nav.removeEventListener('click', handleEditStepClick);
                    nav.addEventListener('click', handleEditStepClick);
                });

                // Edit next button click
                document.querySelectorAll('.edit-next-step').forEach(button => {
                    button.removeEventListener('click', handleEditNextClick);
                    button.addEventListener('click', handleEditNextClick);
                });

                // Edit previous button click
                document.querySelectorAll('.edit-prev-step').forEach(button => {
                    button.removeEventListener('click', handleEditPrevClick);
                    button.addEventListener('click', handleEditPrevClick);
                });

                // Edit form file upload functionality
                document.querySelectorAll('#editEmployeeModal .file-drop-zone').forEach(zone => {
                    const fileInput = zone.querySelector('input[type="file"]');
                    const fileStatus = zone.querySelector('.file-status');

                    if (fileInput && fileStatus) {
                        // Remove old listeners
                        zone.removeEventListener('click', handleFileZoneClick);
                        fileInput.removeEventListener('change', handleFileChange);
                        zone.removeEventListener('dragover', handleDragOver);
                        zone.removeEventListener('dragleave', handleDragLeave);
                        zone.removeEventListener('drop', handleDrop);

                        // Add new listeners
                        zone.addEventListener('click', handleFileZoneClick);
                        fileInput.addEventListener('change', handleFileChange);
                        zone.addEventListener('dragover', handleDragOver);
                        zone.addEventListener('dragleave', handleDragLeave);
                        zone.addEventListener('drop', handleDrop);
                    }
                });

                // Edit profile image upload
                const editProfileImageInput = document.getElementById('edit_profile_image');
                const editProfileImageContainer = document.getElementById('editProfileImageContainer');

                if (editProfileImageInput && editProfileImageContainer) {
                    editProfileImageInput.removeEventListener('change', handleProfileImageChange);
                    editProfileImageInput.addEventListener('change', handleProfileImageChange);
                }
            }, 100);
        }

        // Separate handler functions
        function handleEditStepClick(e) {
            e.preventDefault();
            const step = parseInt(this.getAttribute('data-step'));
            editCurrentStep = step;
            showEditStep(editCurrentStep);
            updateEditStepNavigation();
        }

        function handleEditNextClick(e) {
            e.preventDefault();
            const nextStep = parseInt(this.getAttribute('data-next'));
            editCurrentStep = nextStep;
            showEditStep(editCurrentStep);
            updateEditStepNavigation();
        }

        function handleEditPrevClick(e) {
            e.preventDefault();
            editCurrentStep--;
            showEditStep(editCurrentStep);
            updateEditStepNavigation();
        }

        function handleFileZoneClick(e) {
            const fileInput = this.querySelector('input[type="file"]');
            if (fileInput && e.target !== fileInput) {
                fileInput.click();
            }
        }

        function handleFileChange(e) {
            const zone = this.closest('.file-drop-zone');
            const fileStatus = zone.querySelector('.file-status');
            if (this.files.length > 0) {
                const fileName = this.files[0].name;
                fileStatus.textContent = `Selected: ${fileName}`;
                zone.classList.add('border-green-500', 'bg-green-50');
            } else {
                fileStatus.textContent = '';
                zone.classList.remove('border-green-500', 'bg-green-50');
            }
        }

        function handleDragOver(e) {
            e.preventDefault();
            this.classList.add('border-blue-500');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            this.classList.remove('border-blue-500');
        }

        function handleDrop(e) {
            e.preventDefault();
            this.classList.remove('border-blue-500');

            const fileInput = this.querySelector('input[type="file"]');
            const fileStatus = this.querySelector('.file-status');

            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                const fileName = e.dataTransfer.files[0].name;
                fileStatus.textContent = `Selected: ${fileName}`;
                this.classList.add('border-green-500', 'bg-green-50');
            }
        }

        function handleProfileImageChange(e) {
            const container = document.getElementById('editProfileImageContainer');
            if (this.files && this.files[0] && container) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    container.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover rounded-full" alt="Profile Image">`;
                };
                reader.readAsDataURL(this.files[0]);
            }
        }
        function validateStep(stepIndex) {
            const currentStepElement = document.getElementById(`step${stepIndex}`);
            if (!currentStepElement) return true;

            const inputs = currentStepElement.querySelectorAll('input[required], select[required]');
            let isValid = true;

            inputs.forEach(input => {
                // Remove existing error messages
                removeInputError(input);

                // Check if empty
                if (!input.value.trim()) {
                    isValid = false;
                    showInputError(input, 'This field is required');
                }

                // Special validation for Employee ID in step 1
                if (stepIndex === 1 && input.id === 'employee_id') {
                    if (!validateEmployeeId(input)) {
                        isValid = false;
                    }
                }

                // Email validation
                if (input.type === 'email' && input.value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(input.value)) {
                        isValid = false;
                        showInputError(input, 'Please enter a valid email address');
                    }
                }

                // Password confirmation validation
                if (stepIndex === 2 && input.id === 'confirm_password' && input.value) {
                    const password = document.getElementById('password');
                    if (password && password.value !== input.value) {
                        isValid = false;
                        showInputError(input, 'Passwords do not match');
                    }
                }

                // Date validation
                if (input.type === 'date' && input.value) {
                    const inputDate = new Date(input.value);
                    const today = new Date();
                    if (inputDate > today) {
                        isValid = false;
                        showInputError(input, 'Date cannot be in the future');
                    }
                }

                // Full name validation
                if (input.id === 'full_name' && input.value) {
                    if (!validateFullName(input)) {
                        isValid = false;
                    }
                }

                // Mobile number validation
                if (input.id === 'mobile_number' && input.value) {
                    if (!validateMobileNumber(input)) {
                        isValid = false;
                    }
                }

                // Salary validation
                if ((input.id === 'monthly_salary' || input.id === 'amount_accrued') && input.value) {
                    if (!validateSalary(input)) {
                        isValid = false;
                    }
                }
            });

            return isValid;
        }

        // ===============================================
        // PAGINATION ENHANCEMENTS
        // ===============================================
        function updatePaginationUI() {
            // Highlight current page
            const currentPage = <?php echo $current_page; ?>;
            document.querySelectorAll('.pagination-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent == currentPage) {
                    btn.classList.add('active');
                }
            });

            // Add loading indicator for pagination clicks
            document.querySelectorAll('.pagination-btn[href]').forEach(link => {
                link.addEventListener('click', function (e) {
                    // Only show loading for page changes, not first/last/prev/next icons
                    if (!this.querySelector('i')) {
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                        this.classList.add('opacity-75');
                    }
                });
            });
        }

        // Initialize pagination UI on load
        document.addEventListener('DOMContentLoaded', function () {
            updatePaginationUI();

            // Keyboard navigation for pagination
            document.addEventListener('keydown', function (e) {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                    return; // Don't interfere with form inputs
                }

                const currentPage = <?php echo $current_page; ?>;
                const totalPages = <?php echo $total_pages; ?>;

                if (e.key === 'ArrowLeft' && currentPage > 1) {
                    window.location.href = `permanent.php?page=${currentPage - 1}<?php echo ($show_inactive ? '&show_inactive=1' : '') . (!empty($filter_office) ? '&office=' . urlencode($filter_office) : ''); ?>`;
                } else if (e.key === 'ArrowRight' && currentPage < totalPages) {
                    window.location.href = `permanent.php?page=${currentPage + 1}<?php echo ($show_inactive ? '&show_inactive=1' : '') . (!empty($filter_office) ? '&office=' . urlencode($filter_office) : ''); ?>`;
                }
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

        // Records per page selector
        const recordsPerPageSelect = document.getElementById('recordsPerPage');
        if (recordsPerPageSelect) {
            recordsPerPageSelect.addEventListener('change', function () {
                const recordsPerPage = this.value;
                const url = new URL(window.location.href);
                url.searchParams.set('per_page', recordsPerPage);
                url.searchParams.set('page', 1); // Reset to first page
                window.location.href = url.toString();
            });
        }

        // ===============================================
        // EVENT LISTENERS
        // ===============================================
        document.addEventListener('DOMContentLoaded', function () {
            // Real-time validation for Employee ID
            const employeeIdInput = document.getElementById('employee_id');
            if (employeeIdInput) {
                employeeIdInput.addEventListener('input', function () {
                    validateEmployeeId(this);
                });

                employeeIdInput.addEventListener('blur', function () {
                    validateEmployeeId(this);
                });
            }

            // Real-time validation for full name
            const fullNameInput = document.getElementById('full_name');
            if (fullNameInput) {
                fullNameInput.addEventListener('input', function () {
                    validateFullName(this);
                });

                fullNameInput.addEventListener('blur', function () {
                    validateFullName(this);
                });
            }

            // Real-time validation for mobile number
            const mobileInput = document.getElementById('mobile_number');
            if (mobileInput) {
                // Prevent non-numeric input
                mobileInput.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '').slice(0, 11);
                    validateMobileNumber(this);
                });

                mobileInput.addEventListener('blur', function () {
                    validateMobileNumber(this);
                });
            }

            // Real-time validation for salary fields
            const monthlySalaryInput = document.getElementById('monthly_salary');
            if (monthlySalaryInput) {
                monthlySalaryInput.addEventListener('input', function () {
                    validateSalary(this);
                });

                monthlySalaryInput.addEventListener('blur', function () {
                    validateSalary(this);
                });
            }

            const amountAccruedInput = document.getElementById('amount_accrued');
            if (amountAccruedInput) {
                amountAccruedInput.addEventListener('input', function () {
                    validateSalary(this);
                });

                amountAccruedInput.addEventListener('blur', function () {
                    validateSalary(this);
                });
            }

            // Step navigation click
            document.querySelectorAll('.step-nav').forEach(nav => {
                nav.addEventListener('click', function () {
                    const step = parseInt(this.getAttribute('data-step'));
                    if (validateStep(currentStep)) {
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
                    if (validateStep(currentStep)) {
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

            // File upload functionality
            document.querySelectorAll('.file-drop-zone').forEach(zone => {
                const fileInput = zone.querySelector('input[type="file"]');
                const fileStatus = zone.querySelector('.file-status');

                if (fileInput && fileStatus) {
                    zone.addEventListener('click', function (e) {
                        if (e.target !== fileInput) {
                            fileInput.click();
                        }
                    });

                    fileInput.addEventListener('change', function () {
                        if (this.files.length > 0) {
                            const fileName = this.files[0].name;
                            fileStatus.textContent = `Selected: ${fileName}`;
                            zone.classList.add('border-green-500', 'bg-green-50');
                        } else {
                            fileStatus.textContent = '';
                            zone.classList.remove('border-green-500', 'bg-green-50');
                        }
                    });

                    // Drag and drop
                    zone.addEventListener('dragover', function (e) {
                        e.preventDefault();
                        zone.classList.add('border-blue-500');
                    });

                    zone.addEventListener('dragleave', function () {
                        zone.classList.remove('border-blue-500');
                    });

                    zone.addEventListener('drop', function (e) {
                        e.preventDefault();
                        zone.classList.remove('border-blue-500');

                        if (e.dataTransfer.files.length) {
                            fileInput.files = e.dataTransfer.files;
                            const fileName = e.dataTransfer.files[0].name;
                            fileStatus.textContent = `Selected: ${fileName}`;
                            zone.classList.add('border-green-500', 'bg-green-50');
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
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            profileImageContainer.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover rounded-full" alt="Profile Image">`;
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }

            // Form submission
            const form = document.getElementById('employeeForm');
            if (form) {
                form.addEventListener('submit', function (e) {
                    // Validate all steps before submission
                    let allValid = true;
                    for (let i = 1; i <= 3; i++) {
                        if (!validateStep(i)) {
                            allValid = false;
                            currentStep = i;
                            showStep(currentStep);
                            updateStepNavigation();
                            break;
                        }
                    }

                    if (!allValid) {
                        e.preventDefault();
                        alert('Please fill all required fields correctly before submitting.');
                    } else {
                        // Show loading state
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
                            submitBtn.disabled = true;
                        }
                    }
                });
            }

            // Search functionality
            const searchInput = document.getElementById('simple-search');
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('tbody tr');

                    rows.forEach(row => {
                        const nameCell = row.querySelector('th');
                        const idCell = row.querySelector('td:nth-child(2)');
                        const shouldShow =
                            (nameCell && nameCell.textContent.toLowerCase().includes(searchTerm)) ||
                            (idCell && idCell.textContent.toLowerCase().includes(searchTerm));

                        if (shouldShow) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }

            // Add employee button
            const addEmployeeBtn = document.getElementById('addEmployeeBtn');
            if (addEmployeeBtn) {
                addEmployeeBtn.addEventListener('click', openAddModal);
            }

            // Sidebar functionality
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

                    // Toggle the 'open' class
                    payrollDropdown.classList.toggle('open');

                    // Toggle chevron rotation
                    const chevron = this.querySelector('.chevron');
                    if (chevron) {
                        chevron.classList.toggle('rotated');
                    }
                });
            }

            // Filter dropdown functionality (Flowbite)
            const filterDropdownButton = document.getElementById('filterDropdownButton');
            const filterDropdown = document.getElementById('filterDropdown');

            if (filterDropdownButton && filterDropdown) {
                // Toggle dropdown when button is clicked
                filterDropdownButton.addEventListener('click', function (e) {
                    e.stopPropagation();
                    filterDropdown.classList.toggle('hidden');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function (e) {
                    if (!filterDropdownButton.contains(e.target) && !filterDropdown.contains(e.target)) {
                        filterDropdown.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</body>

</html>