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
  <title>Document</title>
  <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../css/output.css">
  <link rel="stylesheet" href="../css/dasboard.css">

</head>

<body class="bg-gray-50">
  <!-- Navigation Header -->
  <nav class="px-4 py-2.5 text-black bg-blue fixed left-0 right-0 top-0 z-50 bg-blue-600">
    <div class="flex flex-wrap justify-between items-center">
      <div class="flex justify-start items-center">
        <button data-drawer-target="drawer-navigation" data-drawer-toggle="drawer-navigation"
          aria-controls="drawer-navigation"
          class="p-2 mr-2 text-gray-600 rounded-lg cursor-pointer md:hidden hover:text-gray-900 hover:bg-gray-100 focus:bg-gray-100">
          <svg aria-hidden="true" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
          </svg>
          <span class="sr-only">Toggle sidebar</span>
        </button>
        <a href="#" class="flex items-center justify-between mr-4">
          <img style="height: 60px; width: 60px;" class="md:h-20 md:w-20" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
          <span class="hidden md:block self-center text-xl md:text-4xl font-semibold whitespace-nowrap ml-2 md:ml-4 text-white ">HR Management System</span>
          <span class="md:hidden self-center text-lg font-semibold whitespace-nowrap ml-2">HR System</span>
        </a>
      </div>
      <div class="flex items-center lg:order-2">
        <!-- User menu dropdown -->
        <button type="button" class="flex mx-3 text-sm bg-gray-800 rounded-full md:mr-0 focus:ring-4 focus:ring-gray-300" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="dropdown">
          <span class="sr-only">Open user menu</span>
          <img class="w-8 h-8 rounded-full" src="../img/admin1.png" alt="user photo" />
        </button>
        <!-- Dropdown menu -->
        <div class="hidden z-50 my-4 w-56 text-base list-none bg-white rounded divide-y divide-gray-100 shadow" id="dropdown">
          <div class="py-3 px-4">
            <span class="block text-sm font-semibold text-gray-900">Admin</span>
            <span class="block text-sm text-gray-900 truncate">Paluanpayrollsystem@gmail.com</span>
          </div>
          <ul class="py-1 text-gray-700" aria-labelledby="dropdown">
            <li><a href="#" class="block py-2 px-4 text-sm hover:bg-gray-100">My profile</a></li>
            <li><a href="updateadmininfo.php" class="block py-2 px-4 text-sm hover:bg-gray-100">Account settings</a></li>
          </ul>
          <ul class="py-1 text-gray-700" aria-labelledby="dropdown">
            <li><a href="homepage.html" class="block py-2 px-4 text-sm hover:bg-gray-100">Sign out</a></li>
          </ul>
        </div>
      </div>
    </div>
  </nav>
  <!-- Mobile sidebar overlay -->
  <div class="sidebar-overlay" id="sidebar-overlay"></div>

  <!-- Sidebar -->
  <aside class="fixed top-0 left-0 z-40 w-64 h-screen pt-20 transition-transform -translate-x-full bg-blue-600 border-r border-gray-200 md:translate-x-0" id="drawer-navigation">
    <div class="h-full px-3 pb-4 overflow-y-auto bg-blue-600">
      <ul class="space-y-2 font-medium">
        <!-- Dashboard -->
        <li>
          <a href="../dashboard.php" class="flex items-center p-2 text-white rounded-lg  hover:bg-blue-900 group sidebar-item active">
            <svg class="w-5 h-5 text-white transition duration-75" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 22 21">
              <path d="M16.975 11H10V4.025a1 1 0 0 0-1.066-.998 8.5 8.5 0 1 0 9.039 9.039.999.999 0 0 0-1-1.066h.002Z" />
              <path d="M12.5 0c-.157 0-.311.01-.565.027A1 1 0 0 0 11 1.02V10h8.975a1 1 0 0 0 1-.935c.013-.188.028-.374.028-.565A8.51 8.51 0 0 0 12.5 0Z" />
            </svg>
            <span class="ms-3">Dashboard</span>
          </a>
        </li>

        <!-- Employees -->
        <li>
          <a href="Employee.php" class="flex items-center p-2 text-white rounded-lg  hover:bg-blue-900 group">
            <svg class="flex-shrink-0 w-5 h-5 text-white transition duration-75" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 18">
              <path d="M14 2a3.963 3.963 0 0 0-1.4.267 6.439 6.439 0 0 1-1.331 6.638A4 4 0 1 0 14 2Zm1 9h-1.264A6.957 6.957 0 0 1 15 15v2a2.97 2.97 0 0 1-.184 1H19a1 1 0 0 0 1-1v-1a5.006 5.006 0 0 0-5-5ZM6.5 9a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9ZM8 10H5a5.006 5.006 0 0 0-5 5v2a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-2a5.006 5.006 0 0 0-5-5Z" />
            </svg>
            <span class="flex-1 ms-3 whitespace-nowrap">Employees</span>
          </a>
        </li>

        <!-- Attendance -->
        <li>
          <a href="../attendance.php" class="flex items-center p-2 text-white rounded-lg hover:bg-blue-900 group">
            <svg class="flex-shrink-0 w-5 h-5 text-white transition duration-75" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 18 16">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 1v14h16M5 10h6m-6 4h6m-5-4v4M4 1h10a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1Z" />
            </svg>
            <span class="flex-1 ms-3 whitespace-nowrap">Attendance</span>
          </a>
        </li>

        <!-- Payroll Dropdown -->
        <li>
          <button type="button" class="flex items-center w-full p-2 text-base text-white transition duration-75 rounded-lg group hover:bg-blue-900" aria-controls="dropdown-payroll" data-collapse-toggle="dropdown-payroll">
            <svg class="flex-shrink-0 w-5 h-5 text-white transition duration-75" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 18 16">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 1v14h16M5 10h6m-6 4h6m-5-4v4M4 1h10a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1Z" />
            </svg>
            <span class="flex-1 ms-3 text-left whitespace-nowrap">Payroll</span>
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4" />
            </svg>
          </button>
          <ul id="dropdown-payroll" class="hidden py-2 space-y-2">
            <li>
              <a href="../Payrollmanagement/contractualpayrolltable1.php" class="flex items-center w-full p-2 text-white transition duration-75 rounded-lg pl-11 group hover:bg-blue-900">Contractual</a>
            </li>
            <li>
              <a href="../Payrollmanagement/joboerderpayrolltable1.php" class="flex items-center w-full p-2 text-white transition duration-75 rounded-lg pl-11 group hover:bg-blue-900">Job Order</a>
            </li>
            <li>
              <a href="../Payrollmanagement/permanentpayrolltable1.php" class="flex items-center w-full p-2 text-white transition duration-75 rounded-lg pl-11 group hover:bg-blue-900">Permanent</a>
            </li>
          </ul>
        </li>

        <!-- Leave -->
        <li>
          <a href="leaveemployee.php" class="flex items-center p-2 text-white rounded-lg hover:bg-blue-900 group">
            <svg class="flex-shrink-0 w-5 h-5 text-white transition duration-75" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
              <path d="M5 5V.13a2.96 2.96 0 0 0-1.293.749L.879 3.707A2.96 2.96 0 0 0 .13 5H5Z" />
              <path d="M6.737 11.061a2.961 2.961 0 0 1 .81-1.515l6.117-6.116A4.839 4.839 0 0 1 16 2.141V2a1.97 1.97 0 0 0-1.933-2H7v5a2 2 0 0 1-2 2H0v11a1.969 1.969 0 0 0 1.933 2h12.134A1.97 1.97 0 0 0 16 18v-3.093l-1.546 1.546c-.413.413-.94.695-1.513.81l-3.4.679a2.947 2.947 0 0 1-1.85-.227 2.96 2.96 0 0 1-1.635-3.257l.681-3.397Z" />
              <path d="M8.961 16a.93.93 0 0 0 .189-.019l3.4-.679a.961.961 0 0 0 .49-.263l6.118-6.117a2.884 2.884 0 0 0-4.079-4.078l-6.117 6.117a.96.96 0 0 0-.263.491l-.679 3.4A.961.961 0 0 0 8.961 16Zm7.477-9.8a.958.958 0 0 1 .68.281.961.961 0 0 1 0 1.361l-6.117 6.116a.957.957 0 0 1-1.36 0l-6.117-6.116a.961.961 0 0 1 0-1.36.958.958 0 0 1 1.36 0l5.437 5.437 5.437-5.437a.958.958 0 0 1 .68-.281Z" />
            </svg>
            <span class="flex-1 ms-3 whitespace-nowrap">Leave</span>
          </a>
        </li>

        <!-- Reports -->
        <li>
          <a href="paysliplist.php" class="flex items-center p-2 text-white rounded-lg hover:bg-blue-900 group">
            <svg class="flex-shrink-0 w-5 h-5 text-white transition duration-75" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
              <path d="M5 5V.13a2.96 2.96 0 0 0-1.293.749L.879 3.707A2.96 2.96 0 0 0 .13 5H5Z" />
              <path d="M6.737 11.061a2.961 2.961 0 0 1 .81-1.515l6.117-6.116A4.839 4.839 0 0 1 16 2.141V2a1.97 1.97 0 0 0-1.933-2H7v5a2 2 0 0 1-2 2H0v11a1.969 1.969 0 0 0 1.933 2h12.134A1.97 1.97 0 0 0 16 18v-3.093l-1.546 1.546c-.413.413-.94.695-1.513.81l-3.4.679a2.947 2.947 0 0 1-1.85-.227 2.96 2.96 0 0 1-1.635-3.257l.681-3.397Z" />
            </svg>
            <span class="flex-1 ms-3 whitespace-nowrap">Reports</span>
          </a>
        </li>

        <!-- Salary -->
        <li>
          <a href="sallarypayheads.php" class="flex items-center p-2 text-white rounded-lg hover:bg-blue-900 group">
            <svg class="flex-shrink-0 w-5 h-5 text-white transition duration-75" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm0 14a6 6 0 1 1 0-12 6 6 0 0 1 0 12Z" />
              <path d="M10 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" />
            </svg>
            <span class="flex-1 ms-3 whitespace-nowrap">Salary</span>
          </a>
        </li>

        <!-- About Us -->
        <li>
          <a href="aboutus.php" class="flex items-center p-2 text-white rounded-lg hover:bg-blue-900 group">
            <svg class="flex-shrink-0 w-5 h-5 text-white transition duration-75" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z" />
            </svg>
            <span class="flex-1 ms-3 whitespace-nowrap">About Us</span>
          </a>
        </li>

        <!-- Settings -->
        <li>
          <a href="settings.php" class="flex items-center p-2 text-white rounded-lg bg-blue-800 hover:bg-blue-900 group">
            <svg class="flex-shrink-0 w-5 h-5 text-white transition duration-75" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
              <path d="M5 11.424V1a1 1 0 1 0-2 0v10.424a3.228 3.228 0 0 0 0 6.152V19a1 1 0 1 0 2 0v-1.424a3.228 3.228 0 0 0 0-6.152ZM19.25 14.5A3.243 3.243 0 0 0 17 11.424V1a1 1 0 0 0-2 0v10.424a3.227 3.227 0 0 0 0 6.152V19a1 1 0 1 0 2 0v-1.424a3.243 3.243 0 0 0 2.25-3.076Zm-6-9A3.243 3.243 0 0 0 11 2.424V1a1 1 0 0 0-2 0v1.424a3.228 3.228 0 0 0 0 6.152V19a1 1 0 1 0 2 0V8.576A3.243 3.243 0 0 0 13.25 5.5Z" />
            </svg>
            <span class="flex-1 ms-3 whitespace-nowrap">Settings</span>
          </a>
        </li>
      </ul>
    </div>
  </aside>


  <!-- MAIN -->
<main style="margin-top: 200px;margin-left: 300px;">
  <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
       <div class="mb-4 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="default-tab" role="tablist">
                   <a href="settings.php">
                    <li class="mr-2" role="presentation">
                       <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300"
                            id="attendance-tab" type="button" role="tab" aria-controls="attendance"
                            aria-selected="true">Employee History</button>
                    </li></a>
                   <a href="userarchieve.php">
                    <li class="mr-2" role="presentation">
                        <button class="inline-block p-4 text-blue-600 border-b-2 border-blue-600 rounded-t-lg active"
                        
                            id="attendance-tab" type="button" role="tab" aria-controls="attendance"
                            aria-selected="true">User History</button>
                    </li></a>
                    <li class="mr-2" role="presentation">
                        <a href="adminarchieve.php  ">
                        <button
                            class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300"
                            id="request-tab" type="button" role="tab" aria-controls="request"
                            aria-selected="false">Admin History</button></a>
                    </li>
                </ul>
           
    <table style="margin-top: 20px;" class="w-full text-sm text-left rtl:text-right text-black-500 dark:text-black-400">
        <thead class="text-xs text-black-700 uppercase bg-white-50 dark:bg-white-700 dark:text-black-400">
            <tr>
                <th scope="col" class="px-6 py-3">
                    User
                </th>
                <th scope="col" class="px-6 py-3">
                    Created   
                </th>
                <th scope="col" class="px-6 py-3">
                    Status
                </th>
                <th scope="col" class="px-6 py-3">
                    Email
                </th>
                <th scope="col" class="px-6 py-3">
                    <span class="sr-only">Actions</span>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr class="bg-white border-b dark:bg-white-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-black">
                    <div class="flex items-center space-x-3">
                        <img class="w-10 h-10 rounded-full" src="https://flowbite.com/docs/images/people/profile-picture-1.jpg" alt="Mila Kunis avatar">
                        <div>
                            <div class="text-base font-semibold">Mila Kunis</div>
                            <div class="font-normal text-gray-500">Admin</div>
                        </div>
                    </div>
                </th>
                <td class="px-6 py-4">
                    2013/08/08
                </td>
                <td class="px-6 py-4">
                    <span class="bg-red-100 text-red-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded dark:bg-red-900 dark:text-red-300">Inactive</span>
                </td>
                <td class="px-6 py-4 text-blue-600 hover:underline">
                    mila@kunis.com
                </td>
                <td class="px-6 py-4 text-right flex items-center justify-end space-x-2">
                    <button type="button" class="p-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </button>
                    <button type="button" class="p-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-7-7l-2 2 8 8 2-2 4-4-8-8z"></path></svg>
                    </button>
                    <button type="button" class="p-2 text-red-600 hover:text-red-900 dark:text-red-500 dark:hover:text-red-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </td>
            </tr>

              <tr class="bg-white border-b dark:bg-white-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-black">
                    <div class="flex items-center space-x-3">
                        <img class="w-10 h-10 rounded-full" src="https://flowbite.com/docs/images/people/profile-picture-2.jpg" alt="George Clooney avatar">
                        <div>
                            <div class="text-base font-semibold">George Clooney</div>
                            <div class="font-normal text-gray-500">Member</div>
                        </div>
                    </div>
                </th>
                <td class="px-6 py-4">
                    2013/08/12
                </td>
                <td class="px-6 py-4">
                    <span class="bg-green-100 text-green-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded dark:bg-green-900 dark:text-green-300">Active</span>
                </td>
                <td class="px-6 py-4 text-blue-600 hover:underline">
                    marlon@brando.com
                </td>
                <td class="px-6 py-4 text-right flex items-center justify-end space-x-2">
                    <button type="button" class="p-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </button>
                    <button type="button" class="p-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-7-7l-2 2 8 8 2-2 4-4-8-8z"></path></svg>
                    </button>
                    <button type="button" class="p-2 text-red-600 hover:text-red-900 dark:text-red-500 dark:hover:text-red-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </td>
            </tr>

              <tr class="bg-white border-b dark:bg-white-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-black">
                    <div class="flex items-center space-x-3">
                        <img class="w-10 h-10 rounded-full" src="https://flowbite.com/docs/images/people/profile-picture-3.jpg" alt="Ryan Gosling avatar">
                        <div>
                            <div class="text-base font-semibold">Ryan Gosling</div>
                            <div class="font-normal text-gray-500">Registered</div>
                        </div>
                    </div>
                </th>
                <td class="px-6 py-4">
                    2013/03/03
                </td>
                <td class="px-6 py-4">
                    <span class="bg-red-100 text-red-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded dark:bg-red-900 dark:text-red-300">Banned</span>
                </td>
                <td class="px-6 py-4 text-blue-600 hover:underline">
                    jack@nicholson
                </td>
                <td class="px-6 py-4 text-right flex items-center justify-end space-x-2">
                    <button type="button" class="p-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </button>
                    <button type="button" class="p-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-7-7l-2 2 8 8 2-2 4-4-8-8z"></path></svg>
                    </button>
                    <button type="button" class="p-2 text-red-600 hover:text-red-900 dark:text-red-500 dark:hover:text-red-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </td>
            </tr>

             <tr class="bg-white border-b dark:bg-white-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-black">
                    <div class="flex items-center space-x-3">
                        <img class="w-10 h-10 rounded-full" src="https://flowbite.com/docs/images/people/profile-picture-4.jpg" alt="Emma Watson avatar">
                        <div>
                            <div class="text-base font-semibold">Emma Watson</div>
                            <div class="font-normal text-gray-500">Registered</div>
                        </div>
                    </div>
                </th>
                <td class="px-6 py-4">
                    2004/01/24
                </td>
                <td class="px-6 py-4">
                    <span class="bg-yellow-100 text-yellow-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded dark:bg-yellow-900 dark:text-yellow-300">Pending</span>
                </td>
                <td class="px-6 py-4 text-blue-600 hover:underline">
                    humphrey@bogart.com
                </td>
                <td class="px-6 py-4 text-right flex items-center justify-end space-x-2">
                    <button type="button" class="p-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </button>
                    <button type="button" class="p-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-7-7l-2 2 8 8 2-2 4-4-8-8z"></path></svg>
                    </button>
                    <button type="button" class="p-2 text-red-600 hover:text-red-900 dark:text-red-500 dark:hover:text-red-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </td>
            </tr>
        </tbody>
    </table>

  
</div>
</main>


  <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.46.0/dist/apexcharts.min.js"></script>
  <script src="../path/to/flowbite/dist/flowbite.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
  <script src="../js/tailwind.config.js"></script>
</body>

</html>