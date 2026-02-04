<?php
session_start();

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Administrator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom font */
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        /* Style for the custom message box */
        .message-box { transition: opacity 0.3s ease-in-out; }
    </style>
</head>
<body class="p-4 sm:p-8">
    <div class="max-w-4xl mx-auto bg-white p-6 md:p-10 rounded-xl shadow-2xl">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">New Administrator Profile</h1>

   
        
        <form id="admin-form" action="add_user.php" method="POST" enctype="multipart/form-data">
            
            <div class="mb-8 flex items-center space-x-6">
                <img id="avatar-preview" src="https://placehold.co/96x96/10b981/ffffff?text=AVATAR" alt="Avatar Preview" class="w-24 h-24 rounded-full object-cover shadow-md border-4 border-gray-100">
                <div>
                    <label for="avatar_input" class="block text-sm font-medium text-gray-700 mb-1">Upload Profile Photo</label>
                    <input type="file" id="avatar_input" name="avatar" accept="image/*" class="block w-full text-sm text-gray-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-full file:border-0
                        file:text-sm file:font-semibold
                        file:bg-indigo-50 file:text-indigo-600
                        hover:file:bg-indigo-100 cursor-pointer
                    ">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                    <input type="text" id="first_name" name="first_name" required value="<?= htmlspecialchars($first_name ?? '') ?>" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border">
                </div>
                
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" id="last_name" name="last_name" required value="<?= htmlspecialchars($last_name ?? '') ?>" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border">
                </div>
                
                <div>
                    <label for="job_title" class="block text-sm font-medium text-gray-700">Job Title</label>
                    <input type="text" id="job_title" name="job_title" value="<?= htmlspecialchars($job_title ?? '') ?>" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                    <input type="password" id="password" name="password" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border">
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password <span class="text-red-500">*</span></label>
                    <input type="password" id="confirm_password" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border">
                </div>
                
            </div>
            
            <div class="mb-8">
                <label for="biography" class="block text-sm font-medium text-gray-700">Biography</label>
                <textarea id="biography" name="biography" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 border"><?= htmlspecialchars($biography ?? '') ?></textarea>
            </div>

            <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Optional Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                
                <div>
                    <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                    <input type="text" id="country" name="country" value="<?= htmlspecialchars($country ?? '') ?>" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-3 border">
                </div>
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                    <input type="text" id="city" name="city" value="<?= htmlspecialchars($city ?? '') ?>" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-3 border">
                </div>
                <div>
                    <label for="zip" class="block text-sm font-medium text-gray-700">ZIP / Postal</label>
                    <input type="text" id="zip" name="zip" value="<?= htmlspecialchars($zip ?? '') ?>" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-3 border">
                </div>
                
                <div class="md:col-span-3">
                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                    <input type="text" id="address" name="address" value="<?= htmlspecialchars($address ?? '') ?>" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-3 border">
                </div>
                
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                    <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($phone ?? '') ?>" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-3 border">
                </div>
                
                <div>
                    <label for="hire_date" class="block text-sm font-medium text-gray-700">Hire Date</label>
                    <input type="date" id="hire_date" name="hire_date" value="<?= htmlspecialchars($hire_date ?? '') ?>" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-3 border">
                </div>

                <div class="md:col-span-3">
                    <label for="user_role" class="block text-sm font-medium text-gray-700">User Role</label>
                    <select id="user_role" name="user_role" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-3 border bg-white">
                        <option value="Admin" <?= (isset($user_role) && $user_role == 'Admin') ? 'selected' : '' ?>>Administrator</option>
                        <option value="Superadmin" <?= (isset($user_role) && $user_role == 'Superadmin') ? 'selected' : '' ?>>Super Admin </option>
                        <option value="Owner" <?= (isset($user_role) && $user_role == 'Owner') ? 'selected' : '' ?>>Owner</option>
                    </select>
                </div>

            </div>

            <div class="pt-5 border-t mt-8">
                <button type="submit" class="w-full py-3 px-6 border border-transparent rounded-lg shadow-sm text-lg font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out">
                    Create Administrator Account
                </button>
            </div>
        </form>
    </div>

  
</body>
</html>