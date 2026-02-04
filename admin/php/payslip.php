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
<style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }
        .container {
            width: 800px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border: 1px solid #ccc;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .header img {
            height: 100px;
            margin-right: 20px;
        }
        .header-text {
            text-align: center;
            flex-grow: 1;
        }
        .header-text h3, .header-text h4 {
            margin: 0; margin-left: -30px;
            /* align-items: center;
            display: flex;
            justify-content: center; */
        }
        .section-title {
            background-color: #007bff;
            color: white;
            padding: 5px;
            margin-top: 20px;
            font-weight: bold;
            text-align: center;
        }
        .section {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
        }
        .form-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            align-items: center;
        }
        .form-field {
            flex-grow: 1;
            margin-right: 20px;
        }
        .form-field:last-child {
            margin-right: 0;
        }
        .form-field label {
            font-weight: bold;
            width: 150px;
            display: inline-block;
        }
        .form-field input[type="text"] {
            width: calc(100% - 160px);
            padding: 5px;
            border: none;
            border-bottom: 1px solid #ccc;
        }
        .form-half {
            width: 48%;
        }
        .form-two-col {
            display: flex;
            justify-content: space-between;
        }
        .earnings-table, .deductions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .earnings-table td, .deductions-table td {
            padding: 5px;
        }
        .earnings-table td:first-child, .deductions-table td:first-child {
            width: 70%;
        }
        .earnings-table td:last-child, .deductions-table td:last-child {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
        }
        .total-row input {
            font-weight: bold;
            text-align: right;
            border: none;
            background: transparent;
        }
        .net-pay {
            background-color: #007bff;
            color: white;
            text-align: center;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        .net-pay h4, .net-pay input {
            margin: 0;
            color: white;
            font-size: 1.2em;
            font-weight: bold;
            background: transparent;
            border: none;
            text-align: right;
        }
        .buttons {
            text-align: center;
            margin-top: 20px;
        }
        .buttons button {
            padding: 10px 20px;
            margin: 0 10px;
            font-size: 16px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
        }
        .buttons button.save {
            background-color: #28a745;
            color: white;
        }
        .buttons button.print {
            background-color: #17a2b8;
            color: white;
        }
        .buttons button.edit {
            background-color: #ffc107;
            color: black;
        }
        @media print {
            .buttons, .header img {
                display: none;
            }
            body {
                background: none;
            }
            .container {
                box-shadow: none;
                border: none;
            }
        }
    </style>
<body>
  <div class="antialiased bg-gray-50 dark:bg-gray-900">
    <nav 
      class="bg-white border-b border-gray-200 px-4 py-2.5 dark:bg-gray-800 dark:border-gray-700 fixed left-0 right-0 top-0 z-50">
      <div class="flex flex-wrap justify-between items-center">
        <div class="flex justify-start items-center">
          <button data-drawer-target="drawer-navigation" data-drawer-toggle="drawer-navigation"
            aria-controls="drawer-navigation"
            class="p-2 mr-2 text-gray-600 rounded-lg cursor-pointer md:hidden hover:text-gray-900 hover:bg-gray-100 focus:bg-gray-100 dark:focus:bg-gray-700 focus:ring-2 focus:ring-gray-100 dark:focus:ring-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
            <svg aria-hidden="true" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
              xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd"
                d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                clip-rule="evenodd"></path>
            </svg>
            <svg aria-hidden="true" class="hidden w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
              xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd"
                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                clip-rule="evenodd"></path>
            </svg>
            <span class="sr-only">Toggle sidebar</span>
          </button>
          <a href="" class="flex items-center justify-between mr-4">
            <img style="height: 100px; width: 100px;" src="../img/logo.png" class="mr-3 h-15" alt="Flowbite Logo" />
            <span style="font: italic;"
              class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white">Human Resource Office
              Management System</span>
          </a>
          <form action="#" method="GET" class="hidden md:block md:pl-2">
            <label for="topbar-search" class="sr-only">Search</label>
            <div class="relative md:w-64 md:w-96">
              <div class="flex absolute inset-y-0 left-0 items-center pl-3 pointer-events-none">
                <svg class="w-10 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20"
                  xmlns="http://www.w3.org/2000/svg">
                  <path fill-rule="evenodd" clip-rule="evenodd"
                    d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z">
                  </path>
                </svg>
              </div>
              <input type="text" name="email" id="topbar-search"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                placeholder="Search" />
            </div>
          </form>
        </div>
        <div class="flex items-center lg:order-2">
          <button type="button" data-drawer-toggle="drawer-navigation" aria-controls="drawer-navigation"
            class="p-2 mr-1 text-gray-500 rounded-lg md:hidden hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600">
            <span class="sr-only">Toggle search</span>
            <svg aria-hidden="true" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
              xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path clip-rule="evenodd" fill-rule="evenodd"
                d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z">
              </path>
            </svg>
          </button>



          <button type="button"
            class="flex mx-3 text-sm bg-gray-800 rounded-full md:mr-0 focus:ring-4 focus:ring-gray-300 "
            id="user-menu-button" aria-expanded="false" data-dropdown-toggle="dropdown">
            <span class="sr-only">Open user menu</span>
            <img class="w-8 h-8 rounded-full" src="../img/admin1.png" alt="user photo" />
          </button>
          <!-- Dropdown menu -->
          <div
            class="hidden z-50 my-4 w-56 text-base list-none bg-white rounded divide-y divide-gray-100 shadow dark:bg-gray-700 dark:divide-gray-600 rounded-xl"
            id="dropdown">
            <div class="py-3 px-4">
              <span class="block text-sm font-semibold text-gray-900 dark:text-white">Admin</span>
              <span class="block text-sm text-gray-900 truncate dark:text-white">Paluanpayrollsystem@gmail.com</span>
            </div>
            <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
              <li>
                <a href="#"
                  class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">My
                  profile</a>
              </li>
              <li>
                <a href="updateadmininfo.php"
                  class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">Account
                  settings</a>
              </li>
            </ul>
            
            <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
              <li>
                <a href="homepage.html"
                  class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Sign
                  out</a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </nav>

    <!-- Sidebar -->

    <aside style="margin-top: 50px;"
      class="fixed top-0 left-0 z-40 w-64 h-screen pt-14 transition-transform -translate-x-full bg-white border-r border-gray-200 md:translate-x-0 dark:bg-gray-800 dark:border-gray-700"
      aria-label="Sidenav" id="drawer-navigation">
      <div class="overflow-y-auto py-5 px-3 h-full bg-white dark:bg-gray-800">
        <form action="#" method="GET" class="md:hidden mb-2">
          <label for="sidebar-search" class="sr-only">Search</label>
          <div class="relative">
            <div class="flex absolute inset-y-0 left-0 items-center pl-3 pointer-events-none">
              <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20"
                xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd"
                  d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z">
                </path>
              </svg>
            </div>
            <input type="text" name="search" id="sidebar-search"
              class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
              placeholder="Search" />
          </div>
        </form>
        <ul class="space-y-2">
          <li>
            <a href="dashboard.php"  
              class="flex items-center p-2 text-base font-medium text-gray-900 dark:text-white hover:bg-white-100 dark:hover:bg-white-700 group">
              <svg class="w-[27px] h-[27px] text-gray-800 dark:text-white" aria-hidden="true"
                xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd"
                  d="M4.857 3A1.857 1.857 0 0 0 3 4.857v4.286C3 10.169 3.831 11 4.857 11h4.286A1.857 1.857 0 0 0 11 9.143V4.857A1.857 1.857 0 0 0 9.143 3H4.857Zm10 0A1.857 1.857 0 0 0 13 4.857v4.286c0 1.026.831 1.857 1.857 1.857h4.286A1.857 1.857 0 0 0 21 9.143V4.857A1.857 1.857 0 0 0 19.143 3h-4.286Zm-10 10A1.857 1.857 0 0 0 3 14.857v4.286C3 20.169 3.831 21 4.857 21h4.286A1.857 1.857 0 0 0 11 19.143v-4.286A1.857 1.857 0 0 0 9.143 13H4.857Zm10 0A1.857 1.857 0 0 0 13 14.857v4.286c0 1.026.831 1.857 1.857 1.857h4.286A1.857 1.857 0 0 0 21 19.143v-4.286A1.857 1.857 0 0 0 19.143 13h-4.286Z"
                  clip-rule="evenodd" />
              </svg>

              <span class="ml-3">Dashboard</span>
            </a>
          </li>
          <li>


            <ul>
              <li>
                <a href="employees/Employee.php"
                  class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group">
                  <svg class="w-[33px] h-[33px] text-gray-800 dark:text-white" aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd"
                      d="M12 6a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm-1.5 8a4 4 0 0 0-4 4 2 2 0 0 0 2 2h7a2 2 0 0 0 2-2 4 4 0 0 0-4-4h-3Zm6.82-3.096a5.51 5.51 0 0 0-2.797-6.293 3.5 3.5 0 1 1 2.796 6.292ZM19.5 18h.5a2 2 0 0 0 2-2 4 4 0 0 0-4-4h-1.1a5.503 5.503 0 0 1-.471.762A5.998 5.998 0 0 1 19.5 18ZM4 7.5a3.5 3.5 0 0 1 5.477-2.889 5.5 5.5 0 0 0-2.796 6.293A3.501 3.501 0 0 1 4 7.5ZM7.1 12H6a4 4 0 0 0-4 4 2 2 0 0 0 2 2h.5a5.998 5.998 0 0 1 3.071-5.238A5.505 5.505 0 0 1 7.1 12Z"
                      clip-rule="evenodd" />
                  </svg>

                  <span class="ml-3">Employees</span>
                </a>
              </li>
            </ul>


            <ul>
              <li>
                <a href="attendance.html"
                  class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group">
                  <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 18 18">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M16 1H2a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1Zm0 0-6 6M5 10h10M5 5h5" />
                  </svg>

                  <span class="ml-3">Attendance</span>
                </a>
              </li>
            </ul>


          <li>
            <button type="button"
              class="flex items-center p-2 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
              aria-controls="dropdown-authentication" data-collapse-toggle="dropdown-authentication">
              <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                fill="none" viewBox="0 0 20 16">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M5 2a1 1 0 0 0-1 1v1h12V3a1 1 0 0 0-1-1H5ZM4 12V6h12v6m-4 0v2" />
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 1v14h14V1H3Z" />
              </svg>
              <span class="flex-1 ml-3 text-left whitespace-nowrap"> Payroll Management</span>
              <svg aria-hidden="true" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
                xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd"
                  d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                  clip-rule="evenodd"></path>
              </svg>
            </button>
            <ul id="dropdown-authentication" class="hidden py-2 space-y-2">
              <li>
                <a href="../php/Payrollmanagement/contractualpayrolltable1.html"
                  class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">
                  Contractual Payroll</a>
              </li>
              <li>
                <a href="../php/Payrollmanagement/joboerderpayrolltable1.html"
                  class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">
                  Job Order Payroll</a>
              </li>
              <li>
                <a href="../php/Payrollmanagement/permanentpayrolltable1.html"
                  class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">
                  Permanent Payroll</a>
              </li>
            </ul>
          </li>
        </ul>
        <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
          <li>
            <a href="leaveemployee.html"
              class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group">
              <svg style="color: white;" aria-hidden="true"
                class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                <path fill-rule="evenodd"
                  d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                  clip-rule="evenodd"></path>
              </svg>
              <span class="ml-3">Leave</span>
            </a>
          </li>

          <li>
            <a href="paysliplist.html"
              class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group">
              <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                fill="none" viewBox="0 0 16 20">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M1 17V2a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a2 2 0 0 0-2 2Zm12-3h-2.5a1 1 0 0 1 0-2H13a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v2c0 .2.08.38.23.53l.63.63H3m1-4h8V5H4v4Zm-1-4h1v1H3V5Zm2-1h1v1H5V4Zm2-1h1v1H7V3Zm2-1h1v1H9V2Z" />
              </svg>
              <span class="ml-3">Reports</span>
            </a>
          </li>
          <li>
            <a href="sallarypayheads.html"
              class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group">
              <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a9 9 0 0 0-5.5-2.52M12 21V12m0 0a9 9 0 0 0 5.5-2.52M12 12V3" />
              </svg>
              <span class="ml-3">Salary</span>
            </a>
          </li>
          <li>
            <a href="aboutus.html"
              class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group">
              <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd"
                  d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm9.408-5.5a1 1 0 1 0 0 2h.01a1 1 0 1 0 0-2h-.01ZM10 10a1 1 0 1 0 0 2h1v3h-1a1 1 0 1 0 0 2h4a1 1 0 1 0 0-2h-1v-4a1 1 0 0 0-1-1h-2Z"
                  clip-rule="evenodd" />
              </svg>


              <span class="ml-3">About us</span>
            </a>
          </li>
          <li>
            <a href="settings.html"
              class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group">
              <svg class="w-[27px] h-[27px] text-gray-800 dark:text-white" aria-hidden="true"
                xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd"
                  d="M9.586 2.586A2 2 0 0 1 11 2h2a2 2 0 0 1 2 2v.089l.473.196.063-.063a2.002 2.002 0 0 1 2.828 0l1.414 1.414a2 2 0 0 1 0 2.827l-.063.064.196.473H20a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-.089l-.196.473.063.063a2.002 2.002 0 0 1 0 2.828l-1.414 1.414a2 2 0 0 1-2.828 0l-.063-.063-.473.196V20a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-.089l-.473-.196-.063.063a2.002 2.002 0 0 1-2.828 0l-1.414-1.414a2 2 0 0 1 0-2.827l.063-.064L4.089 15H4a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h.09l.195-.473-.063-.063a2 2 0 0 1 0-2.828l1.414-1.414a2 2 0 0 1 2.827 0l.064.063L9 4.089V4a2 2 0 0 1 .586-1.414ZM8 12a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z"
                  clip-rule="evenodd" />
              </svg>

              <span class="ml-3">Setting</span>
            </a>
          </li>
        </ul>
      </div>
    </aside>

  </div>

  <!-- MAIN -->
<main style="margin-top: 130PX; margin-left: 150PX;">
    <body>

    <div class="container">
        <div class="header">
            <img src="../img/logo.png" alt="Paluan Logo">
            <div class="header-text">
                <h3>Republic of the Philippines</h3>
                <h4>PROVINCE OF OCCIDENTAL MINDORO</h4>
                <h4>Municipality of Paluan</h4>
            </div>
        </div>

        <div class="section-title" style="background-color: #007bff;">EMPLOYEE INFORMATION</div>
        <div class="section">
            <form id="payStubForm">
                <div class="form-row">
                    <div class="form-field form-half">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" value="VILLAROZA, VEXTER D." readonly>
                    </div>
                    <div class="form-field form-half">
                        <label for="payType">Pay Type</label>
                        <input type="text" id="payType" name="payType" value="Monthly" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field form-half">
                        <label for="tin">TIN</label>
                        <input type="text" id="tin" name="tin" value="" readonly>
                    </div>
                    <div class="form-field form-half">
                        <label for="period">Period</label>
                        <input type="text" id="period" name="period" value="" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="position">Position</label>
                        <input type="text" id="position" name="position" value="Administrative Assistant I" readonly>
                    </div>
                    <div class="form-field form-half">
                        <label for="idNumber">ID Number</label>
                        <input type="text" id="idNumber" name="idNumber" value="" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="salaryGrade">Salary Grade</label>
                        <input type="text" id="salaryGrade" name="salaryGrade" value="7" readonly>
                    </div>
                    <div class="form-field">
                        <label for="step">Step</label>
                        <input type="text" id="step" name="step" value="1" readonly>
                    </div>
                </div>
            </form>
        </div>

        <div class="section-title" style="background-color: #007bff;">EARNINGS</div>
        <div class="section">
            <table class="earnings-table">
                <tr>
                    <td>Basic Salary</td>
                    <td><input type="text" value="15,492.00" readonly></td>
                </tr>
                <tr>
                    <td></td>
                    <td><input type="text" value="2,000.00" readonly></td>
                </tr>
                <tr>
                    <td></td>
                    <td><input type="text" value="3,000.00" readonly></td>
                </tr>
                <tr class="total-row">
                    <td style="background-color: #007bff; color: white;">GROSS PAY</td>
                    <td style="background-color: #007bff; color: white;"><input type="text" value="20,492.00" readonly></td>
                </tr>
            </table>
        </div>

        <div class="section-title" style="background-color: #007bff;">DEDUCTIONS</div>
        <div class="section">
            <table class="deductions-table">
                <tr><td>W Tax</td><td><input type="text" value="0.00" readonly></td></tr>
                <tr><td>Pag-ibig Loan</td><td><input type="text" value="0.00" readonly></td></tr>
                <tr><td>Conso. Loan</td><td><input type="text" value="0.00" readonly></td></tr>
                <tr><td>Policy Loan</td><td><input type="text" value="0.00" readonly></td></tr>
                <tr><td>Philhealth Share</td><td><input type="text" value="587.50" readonly></td></tr>
                <tr><td>GSIS Share</td><td><input type="text" value="1,394.28" readonly></td></tr>
                <tr><td>Emergency Loan</td><td><input type="text" value="0.00" readonly></td></tr>
                <tr><td>GFAL</td><td><input type="text" value="0.00" readonly></td></tr>
                <tr><td>Educ. Loan</td><td><input type="text" value="0.00" readonly></td></tr>
                <tr><td>LBP Loan</td><td><input type="text" value="0.00" readonly></td></tr>
                <tr><td>Comptroller Loan</td><td><input type="text" value="0.00" readonly></td></tr>
                <tr><td>GSIS MPL</td><td><input type="text" value="0.00" readonly></td></tr>
                <tr><td>Pag-Ibig Share</td><td><input type="text" value="100.00" readonly></td></tr>
                <tr><td>SSS Cont.</td><td><input type="text" value="0.00" readonly></td></tr>
                <tr><td>City Savings</td><td><input type="text" value="0.00" readonly></td></tr>
                <tr class="total-row">
                    <td>TOTAL DEDUCTIONS</td>
                    <td><input type="text" value="1,981.58" readonly></td>
                </tr>
            </table>
        </div>

        <div class="net-pay">
            <h4>NET PAY</h4>
            <input type="text" value="18,510.42" readonly>
        </div>

        <div class="buttons">
            <button class="save" onclick="saveForm()">Save</button>
            <button class="print" onclick="window.print()">Print</button>
            <button class="edit" onclick="editForm()">Edit</button>
        </div>
    </div>
    
    

</body>
</main>
  
  <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.46.0/dist/apexcharts.min.js"></script>
  <script src="../path/to/flowbite/dist/flowbite.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
  <script src="../js/tailwind.config.js"></script>

</body>
<script>
        function toggleReadOnly(readOnly) {
            const formInputs = document.querySelectorAll('#payStubForm input[type="text"]');
            formInputs.forEach(input => {
                input.readOnly = readOnly;
            });
            const tableInputs = document.querySelectorAll('.earnings-table input, .deductions-table input');
            tableInputs.forEach(input => {
                input.readOnly = readOnly;
            });
        }
        
        function saveForm() {
            alert('Form saved!');
            toggleReadOnly(true);
        }

        function editForm() {
            alert('You can now edit the form. Note: This is a frontend-only function.');
            toggleReadOnly(false);
        }
    </script>
</html>

