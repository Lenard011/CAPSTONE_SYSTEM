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
    font-family: 'Times New Roman', serif;
    font-size: 12pt;
    margin: 0;
    padding: 30px;
    background-color: #f0f0f0;
    display: flex;
    justify-content: center;
}

.document-container {
    width: 8.5in;
    min-height: 11in;
    padding: 1in; /* Standard letter margins */
    background-color: #fff;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    box-sizing: border-box;
    line-height: 1.5;
}

header {
    text-align: center;
    margin-bottom: 30px;
}

header p {
    margin: 0;
    line-height: 1.2;
}

.municipality {
    font-weight: bold;
}

.header-line {
    border: none;
    border-top: 1px solid #000;
    width: 70%;
    margin: 10px auto 0 auto;
}

.date-section {
    text-align: right;
    margin-bottom: 30px;
    position: relative;
    right: 50px; /* Adjust to align with the image */
}

.date-section p {
    margin: 0;
}

.address-section {
    margin-bottom: 30px;
    margin-left: 50px; /* Indent as in the image */
}

.address-section p {
    margin: 0;
}

.salutation {
    margin-left: 50px;
    margin-top: 30px;
    margin-bottom: 15px;
}

.body-text {
    margin-left: 50px;
    margin-bottom: 30px;
}

.body-text p {
    margin: 0;
    text-indent: -20px; /* Hanging indent for the first line */
    padding-left: 20px;
}

.body-text p:first-child {
    text-indent: 0; /* Remove hanging indent for the first paragraph if it's not needed */
    padding-left: 0;
}

/* Table Styling */
.table-section {
    margin: 20px 50px 30px 50px; /* Adjust margins as needed */
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th, td {
    border: 1px solid #000;
    padding: 8px;
    text-align: left;
    vertical-align: top;
}

th {
    background-color: #f2f2f2;
    font-weight: bold;
    text-align: center;
}

/* Editable elements */
.editable-input {
    border: 1px dashed #999; /* Dashed border to indicate editable */
    padding: 2px 5px;
    font-family: inherit;
    font-size: inherit;
    width: auto;
    min-width: 150px; /* Ensure input is visible */
}

.date-input {
    min-width: 120px;
    text-align: center;
}

[contenteditable="true"] {
    border: 1px dashed #999;
    padding: 2px 5px;
    min-width: 50px; /* Ensure editable area is visible */
    display: inline-block; /* For inline editable spans */
    cursor: text;
}

.editable-cell {
    min-height: 20px; /* Ensure cells have a minimum height */
}


.closing-text {
    margin-left: 50px;
    margin-top: 30px;
    margin-bottom: 50px;
}

.signature-block {
    text-align: right;
    margin-top: 50px;
    margin-right: 50px; /* Adjust to align with the image */
}

.closing {
    margin-bottom: 30px;
}

.signer-name {
    font-weight: bold;
    margin-bottom: 5px;
    text-transform: uppercase;
}

.signer-title {
    margin-top: 0;
    font-size: 11pt;
}

.approval-block {
    margin-top: 80px;
    margin-left: 50px;
}

.approver-name {
    font-weight: bold;
    margin-bottom: 5px;
    text-transform: uppercase;
}

.approver-title {
    margin-top: 0;
    font-size: 11pt;
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
            <img   style="height: 100px; width: 100px;" src="../img/logo.png" class="mr-3 h-15" alt="Flowbite Logo" />
            <span style="font: italic;"
              class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white">Human Resource Office Management System</span>
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
                <a href="#"
                  class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">Account
                  settings</a>
              </li>
            </ul>
            <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">

              <li>
                <a href="#"
                  class="flex items-center py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white"><svg
                    class="mr-2 w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20"
                    xmlns="http://www.w3.org/2000/svg">
                    <path
                      d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z">
                    </path>
                  </svg>
                  Collections</a>
              </li>
              <li>
                <a href="#"
                  class="flex justify-between items-center py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">
                  <span class="flex items-center">
                    <svg aria-hidden="true" class="mr-2 w-5 h-5 text-primary-600 dark:text-primary-500"
                      fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                      <path fill-rule="evenodd"
                        d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z"
                        clip-rule="evenodd"></path>
                    </svg>
                    Pro version
                  </span>
                  <svg aria-hidden="true" class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20"
                    xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd"
                      d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                      clip-rule="evenodd"></path>
                  </svg>
                </a>
              </li>
            </ul>
            <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
              <li>
                <a href="#"
                  class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Sign
                  out</a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </nav>

    <!-- Sidebar -->

 
   <aside style="margin-top: 50px;" class="fixed top-0 left-0 z-40 w-64 h-screen pt-14 transition-transform -translate-x-full bg-green-900 border-r border-gray-200 text-white md:translate-x-0 dark:bg-gray-800 dark:border-gray-700"
    aria-label="Sidenav" id="drawer-navigation">
    <div class="overflow-y-auto py-5 px-3 h-full bg-green-900 dark:bg-blue-900">
        <form action="#" method="GET" class="md:hidden mb-2">
            <label for="sidebar-search" class="sr-only">Search</label>
            <div class="relative">
                <div class="flex absolute inset-y-0 left-0 items-center pl-3 pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20"
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
                    class="flex items-center p-2 text-base font-medium text-white  dark:text-white hover:bg-gray-700 dark:hover:bg-gray-700 bg-gray-700 group">
                    <svg class="w-[27px] h-[27px] text-white dark:text-white" aria-hidden="true"
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
                            class="flex items-center p-2 text-base font-medium text-white rounded-lg transition duration-75 hover:bg-green-700 dark:hover:bg-gray-700 dark:text-white group">
                            <svg class="w-[33px] h-[33px] text-white dark:text-white" aria-hidden="true"
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
                            class="flex items-center p-2 text-base font-medium text-white rounded-lg transition duration-75 hover:bg-green-700 dark:hover:bg-gray-700 dark:text-white group">
                            <svg class="w-6 h-6 text-white dark:text-white" aria-hidden="true"
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
                    class="flex items-center p-2 w-full text-base font-medium text-white rounded-lg transition duration-75 group hover:bg-green-700 dark:text-white dark:hover:bg-gray-700"
                    aria-controls="dropdown-authentication" data-collapse-toggle="dropdown-authentication">
                    <svg class="w-6 h-6 text-white dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
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
                            class="flex items-center p-2 pl-11 w-full text-base font-medium text-white rounded-lg transition duration-75 group hover:bg-green-700 dark:hover:bg-gray-700">
                            Contractual Payroll</a>
                    </li>
                    <li>
                        <a href="../php/Payrollmanagement/joboerderpayrolltable1.html"
                            class="flex items-center p-2 pl-11 w-full text-base font-medium text-white rounded-lg transition duration-75 group hover:bg-green-700 dark:hover:bg-gray-700">
                            Job Order Payroll</a>
                    </li>
                    <li>
                        <a href="../php/Payrollmanagement/permanentpayrolltable1.html"
                            class="flex items-center p-2 pl-11 w-full text-base font-medium text-white rounded-lg transition duration-75 group hover:bg-green-700 dark:hover:bg-gray-700">
                            Permanent Payroll</a>
                    </li>
                </ul>
            </li>
        </ul>
        <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
            <li>
                <a href="leaveemployee.html"
                    class="flex items-center p-2 text-base font-medium text-white rounded-lg transition duration-75 hover:bg-green-700 dark:hover:bg-gray-700 dark:text-white group">
                    <svg style="color: white;" aria-hidden="true"
                        class="flex-shrink-0 w-6 h-6 text-white transition duration-75 dark:text-gray-400 group-hover:text-white dark:group-hover:text-white"
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
                    class="flex items-center p-2 text-base font-medium text-white rounded-lg transition duration-75 hover:bg-green-700 dark:hover:bg-gray-700 dark:text-white group">
                    <svg class="w-6 h-6 text-white dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 16 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M1 17V2a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a2 2 0 0 0-2 2Zm12-3h-2.5a1 1 0 0 1 0-2H13a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v2c0 .2.08.38.23.53l.63.63H3m1-4h8V5H4v4Zm-1-4h1v1H3V5Zm2-1h1v1H5V4Zm2-1h1v1H7V3Zm2-1h1v1H9V2Z" />
                    </svg>
                    <span class="ml-3">Reports</span>
                </a>
            </li>
            <li>
                <a href="sallarypayheads.html"
                    class="flex items-center p-2 text-base font-medium text-white rounded-lg transition duration-75 hover:bg-green-700 dark:hover:bg-gray-700 dark:text-white group">
                    <svg class="w-6 h-6 text-white dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a9 9 0 0 0-5.5-2.52M12 21V12m0 0a9 9 0 0 0 5.5-2.52M12 12V3" />
                    </svg>
                    <span class="ml-3">Salary</span>
                </a>
            </li>
            <li>
                <a href="aboutus.html"
                    class="flex items-center p-2 text-base font-medium text-white rounded-lg transition duration-75 hover:bg-green-700 dark:hover:bg-gray-700 dark:text-white group">
                    <svg class="w-6 h-6 text-white dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
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
                    class="flex items-center p-2 text-base font-medium text-white rounded-lg transition duration-75 hover:bg-green-700 dark:hover:bg-gray-700 dark:text-white group">
                    <svg class="w-[27px] h-[27px] text-white dark:text-white" aria-hidden="true"
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

 <main>
   <main style="margin-top:110px;margin-left: 20px;">
  

<div style="margin-left: -45px;padding-bottom: 20px; " class="inline-flex rounded-md shadow-xs" role="group">
  <a href="./obligationrequest.html">
  <button style="color: #535151;" type="button" class="px-4 py-2 text-sm font-medium text-black-900 bg-white border border-gray-200 rounded-s-lg hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:text-white dark:hover:bg-gray-700 dark:focus:ring-blue-500 dark:focus:text-white">
    Obligation Request
  </button></a>
  <a href="./lettersalary.html">
  <button type="button" class="px-4 py-2 text-sm font-medium text-gray-900 bg-white border-t border-b border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:text-white dark:hover:bg-gray-700 dark:focus:ring-blue-500 dark:focus:text-white">
    Later of Salary
  </button></a>
  <a href="">
  <button type="button" class="px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-e-lg hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-2 focus:ring-blue-700 focus:text-blue-700 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:text-white dark:hover:bg-gray-700 dark:focus:ring-blue-500 dark:focus:text-white">
    Messages
  </button></a>
</div>

    <div style="margin-top: -10px;margin-left:-45px;" class="document-container">
        <header>
            <p>Republic of the Philippines</p>
            <p>Province of Occidental Mindoro</p>
            <p class="municipality">MUNICIPALITY OF PALUAN</p>
            <hr class="header-line">
        </header>

        <section class="date-section">
            <p>
                <input type="text" id="documentDate" value="January 6, 2025" class="editable-input date-input">
            </p>
            <p>Date</p>
        </section>

        <section class="address-section">
            <p>The Municipal Mayor</p>
            <p>Thru the Municipal Treasurer</p>
            <p>Paluan, Occidental Mindoro</p>
        </section>

        <p class="salutation">Sir/Madam:</p>

        <section class="body-text">
            <p>
                I have the honor to submit hereunder the list of <span contenteditable="true" class="editable-text-inline">Clerk</span> working within this municipality
            </p>
            <p>
                from <input type="text" value="January 6 - 31, 2025" class="editable-input">.
            </p>
        </section>

        <section class="table-section">
            <table>
                <thead>
                    <tr>
                        <th>Position</th>
                        <th>Name</th>
                        <th>Educational<br>Attainment</th>
                        <th>Age</th>
                        <th>Res. Cert. No.</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td contenteditable="true" class="editable-cell">CLERK</td>
                        <td contenteditable="true" class="editable-cell">JUAN DELA CRUZ</td>
                        <td contenteditable="true" class="editable-cell"></td>
                        <td contenteditable="true" class="editable-cell"></td>
                        <td contenteditable="true" class="editable-cell"></td>
                    </tr>
                    </tbody>
            </table>
        </section>

        <section class="closing-text">
            <p>Your favorable action will be highly appreciated.</p>
        </section>

        <section class="signature-block">
            <p class="closing">Very truly yours,</p>
            <br>
            <p class="signer-name">ATTY. CHARLOTTE JENNIFER V. VALBUENA-PEDRAZA</p>
            <p class="signer-title">Municipal Administrator</p>
        </section>

        <section class="approval-block">
            <p>APPROVED:</p>
            <br>
            <p class="approver-name">MICHAEL D. DIAZ</p>
            <p class="approver-title">Municipal Mayor</p>
        </section>
    </div>
 </main>

   
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.46.0/dist/apexcharts.min.js"></script>
  <script src="../path/to/flowbite/dist/flowbite.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
  <script src="../js/tailwind.config.js"></script>
</body>

</html>