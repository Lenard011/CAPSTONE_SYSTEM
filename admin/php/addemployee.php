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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Document</title>
    <link
      href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../css/output.css" />
    <link rel="stylesheet" href="../css/dasboard.css" />
    <link rel="stylesheet" href="../css/addemployee.css" />
  </head>
  <body style="margin-top: 40px; margin-left: -140px">
    <div class="antialiased bg-gray-50 dark:bg-gray-900">
      <nav
        class="bg-white border-b border-gray-200 px-4 py-2.5 dark:bg-gray-800 dark:border-gray-700 fixed left-0 right-0 top-0 z-50"
      >
        <div class="flex flex-wrap justify-between items-center">
          <div class="flex justify-start items-center">
            <button
              data-drawer-target="drawer-navigation"
              data-drawer-toggle="drawer-navigation"
              aria-controls="drawer-navigation"
              class="p-2 mr-2 text-gray-600 rounded-lg cursor-pointer md:hidden hover:text-gray-900 hover:bg-gray-100 focus:bg-gray-100 dark:focus:bg-gray-700 focus:ring-2 focus:ring-gray-100 dark:focus:ring-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
            >
              <svg
                aria-hidden="true"
                class="w-6 h-6"
                fill="currentColor"
                viewBox="0 0 20 20"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  fill-rule="evenodd"
                  d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                  clip-rule="evenodd"
                ></path>
              </svg>
              <svg
                aria-hidden="true"
                class="hidden w-6 h-6"
                fill="currentColor"
                viewBox="0 0 20 20"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  fill-rule="evenodd"
                  d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                  clip-rule="evenodd"
                ></path>
              </svg>
              <span class="sr-only">Toggle sidebar</span>
            </button>
            <a href="" class="flex items-center justify-between mr-4">
              <img
                style="height: 100px; width: 100px"
                src="../img/logo.png"
                class="mr-3 h-15"
                alt="Flowbite Logo"
              />
              <span
                style="font: italic"
                class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white"
                >Human Resource Office Management System</span
              >
            </a>

            <form action="#" method="GET" class="hidden md:block md:pl-2">
              <label for="topbar-search" class="sr-only">Search</label>
              <div class="relative md:w-96">
                <div
                  class="flex absolute inset-y-0 left-0 items-center pl-3 pointer-events-none"
                >
                  <svg
                    class="w-10 h-5 text-gray-500 dark:text-gray-400"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                    xmlns="http://www.w3.org/2000/svg"
                  >
                    <path
                      fill-rule="evenodd"
                      clip-rule="evenodd"
                      d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                    ></path>
                  </svg>
                </div>
                <input
                  type="text"
                  name="email"
                  id="topbar-search"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                  placeholder="Search"
                />
              </div>
            </form>
          </div>
          <div class="flex items-center lg:order-2">
            <button
              type="button"
              data-drawer-toggle="drawer-navigation"
              aria-controls="drawer-navigation"
              class="p-2 mr-1 text-gray-500 rounded-lg md:hidden hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600"
            >
              <span class="sr-only">Toggle search</span>
              <svg
                aria-hidden="true"
                class="w-6 h-6"
                fill="currentColor"
                viewBox="0 0 20 20"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
              >
                <path
                  clip-rule="evenodd"
                  fill-rule="evenodd"
                  d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                ></path>
              </svg>
            </button>

            <button
              type="button"
              class="flex mx-3 text-sm bg-gray-800 rounded-full md:mr-0 focus:ring-4 focus:ring-gray-300"
              id="user-menu-button"
              aria-expanded="false"
              data-dropdown-toggle="dropdown"
            >
              <span class="sr-only">Open user menu</span>
              <img
                class="w-8 h-8 rounded-full"
                src="../img/admin1.png"
                alt="user photo"
              />
            </button>
            <!-- Dropdown menu -->
            <div
              class="hidden z-50 my-4 w-56 text-base list-none bg-white divide-y divide-gray-100 shadow dark:bg-gray-700 dark:divide-gray-600 rounded-xl"
              id="dropdown"
            >
              <div class="py-3 px-4">
                <span
                  class="block text-sm font-semibold text-gray-900 dark:text-white"
                  >Admin</span
                >
                <span
                  class="block text-sm text-gray-900 truncate dark:text-white"
                  >Paluanpayrollsystem@gmail.com</span
                >
              </div>
              <ul
                class="py-1 text-gray-700 dark:text-gray-300"
                aria-labelledby="dropdown"
              >
                <li>
                  <a
                    href="#"
                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white"
                    >My profile</a
                  >
                </li>
                <li>
                  <a
                    href="#"
                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white"
                    >Account settings</a
                  >
                </li>
              </ul>
              <ul
                class="py-1 text-gray-700 dark:text-gray-300"
                aria-labelledby="dropdown"
              >
                <li>
                  <a
                    href="#"
                    class="flex items-center py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white"
                    ><svg
                      class="mr-2 w-5 h-5 text-gray-400"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"
                      ></path>
                    </svg>
                    Collections</a
                  >
                </li>
                <li>
                  <a
                    href="#"
                    class="flex justify-between items-center py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white"
                  >
                    <span class="flex items-center">
                      <svg
                        aria-hidden="true"
                        class="mr-2 w-5 h-5 text-primary-600 dark:text-primary-500"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                        xmlns="http://www.w3.org/2000/svg"
                      >
                        <path
                          fill-rule="evenodd"
                          d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z"
                          clip-rule="evenodd"
                        ></path>
                      </svg>
                      Pro version
                    </span>
                    <svg
                      aria-hidden="true"
                      class="w-5 h-5 text-gray-400"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                        clip-rule="evenodd"
                      ></path>
                    </svg>
                  </a>
                </li>
              </ul>
              <ul
                class="py-1 text-gray-700 dark:text-gray-300"
                aria-labelledby="dropdown"
              >
                <li>
                  <a
                    href="#"
                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white"
                    >Sign out</a
                  >
                </li>
              </ul>
            </div>
          </div>
        </div>
      </nav>

      <!-- Sidebar -->

      <aside
        style="margin-top: 50px"
        class="fixed top-0 left-0 z-40 w-64 h-screen pt-14 transition-transform -translate-x-full bg-white border-r border-gray-200 md:translate-x-0 dark:bg-gray-800 dark:border-gray-700"
        aria-label="Sidenav"
        id="drawer-navigation"
      >
        <div class="overflow-y-auto py-5 px-3 h-full bg-white dark:bg-gray-800">
          <form action="#" method="GET" class="md:hidden mb-2">
            <label for="sidebar-search" class="sr-only">Search</label>
            <div class="relative">
              <div
                class="flex absolute inset-y-0 left-0 items-center pl-3 pointer-events-none"
              >
                <svg
                  class="w-5 h-5 text-gray-500 dark:text-gray-400"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    fill-rule="evenodd"
                    clip-rule="evenodd"
                    d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                  ></path>
                </svg>
              </div>
              <input
                type="text"
                name="search"
                id="sidebar-search"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                placeholder="Search"
              />
            </div>
          </form>
          <ul class="space-y-2">
            <li>
              <a
                href="../html/dashboard.html"
                class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group"
              >
                <svg
                  aria-hidden="true"
                  class="w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path>
                  <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"></path>
                </svg>
                <span class="ml-3">Dashboard</span>
              </a>
            </li>
            <li>
              <button
                type="button"
                class="flex items-center p-2 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                aria-controls="dropdown-pages"
                data-collapse-toggle="dropdown-pages"
              >
                <svg
                  class="w-6 h-6 text-gray-800 dark:text-white"
                  aria-hidden="true"
                  xmlns="http://www.w3.org/2000/svg"
                  width="24"
                  height="24"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-width="2"
                    d="M16 19h4a1 1 0 0 0 1-1v-1a3 3 0 0 0-3-3h-2m-2.236-4a3 3 0 1 0 0-4M3 18v-1a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Zm8-10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                  />
                </svg>

                <span class="flex-1 ml-3 text-left whitespace-nowrap"
                  >Employee</span
                >
                <svg
                  aria-hidden="true"
                  class="w-6 h-6"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    fill-rule="evenodd"
                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                    clip-rule="evenodd"
                  ></path>
                </svg>
              </button>
              <ul id="dropdown-pages" class="hidden py-2 space-y-2">
                <li>
                  <a
                    href="./permanentlist.html"
                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                    >Permanent</a
                  >
                </li>
                <li>
                  <a
                    href="#"
                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                    >Contractual</a
                  >
                </li>
                <li>
                  <a
                    href=".//joborder.html"
                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                    >Job Order</a
                  >
                </li>
              </ul>
            </li>
            <li>
              <button
                type="button"
                class="flex items-center p-2 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                aria-controls="dropdown-sales"
                data-collapse-toggle="dropdown-sales"
              >
                <svg
                  class="w-6 h-6 text-gray-800 dark:text-white"
                  aria-hidden="true"
                  xmlns="http://www.w3.org/2000/svg"
                  width="24"
                  height="24"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M16 12H4m12 0-4 4m4-4-4-4m3-4h2a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3h-2"
                  />
                </svg>

                <span class="flex-1 ml-3 text-left whitespace-nowrap"
                  >Attendance
                </span>
                <svg
                  aria-hidden="true"
                  class="w-6 h-6"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    fill-rule="evenodd"
                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                    clip-rule="evenodd"
                  ></path>
                </svg>
              </button>
              <ul id="dropdown-sales" class="hidden py-2 space-y-2">
                <li>
                  <a
                    href=""
                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                  >
                    Permanent</a
                  >
                </li>
                <li>
                  <a
                    href="#"
                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                    >On Contractual</a
                  >
                </li>
                <li>
                  <a
                    href="#"
                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                    >Request Job Order</a
                  >
                </li>
              </ul>
            </li>

            <li>
              <button
                type="button"
                class="flex items-center p-2 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                aria-controls="dropdown-authentication"
                data-collapse-toggle="dropdown-authentication"
              >
                <svg
                  aria-hidden="true"
                  class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    fill-rule="evenodd"
                    d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                    clip-rule="evenodd"
                  ></path>
                </svg>
                <span class="flex-1 ml-3 text-left whitespace-nowrap">
                  Payroll Management</span
                >
                <svg
                  aria-hidden="true"
                  class="w-6 h-6"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    fill-rule="evenodd"
                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                    clip-rule="evenodd"
                  ></path>
                </svg>
              </button>
              <ul id="dropdown-authentication" class="hidden py-2 space-y-2">
                <li>
                  <a
                    href="./dailywage.html"
                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                  >
                    Daily Wage</a
                  >
                </li>
                <li>
                  <a
                    href="./summary.html"
                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                  >
                    Summary of Payroll</a
                  >
                </li>
                <li>
                  <a
                    href="./generalpayroll.html"
                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                  >
                    General Payroll</a
                  >
                </li>
              </ul>
            </li>
          </ul>
          <ul
            class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700"
          >
            <li>
              <a
                href="./leaveemployee.html"
                class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group"
              >
                <svg
                  aria-hidden="true"
                  class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                  <path
                    fill-rule="evenodd"
                    d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                    clip-rule="evenodd"
                  ></path>
                </svg>
                <span class="ml-3">Leave</span>
              </a>
            </li>
            <li>
              <a
                href="#"
                class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group"
              >
                <svg
                  aria-hidden="true"
                  class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"
                  ></path>
                </svg>
                <span class="ml-3">Employee Records History</span>
              </a>
            </li>
            <li>
              <a
                href="#"
                class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group"
              >
                <svg
                  aria-hidden="true"
                  class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-2 0c0 .993-.241 1.929-.668 2.754l-1.524-1.525a3.997 3.997 0 00.078-2.183l1.562-1.562C15.802 8.249 16 9.1 16 10zm-5.165 3.913l1.58 1.58A5.98 5.98 0 0110 16a5.976 5.976 0 01-2.516-.552l1.562-1.562a4.006 4.006 0 001.789.027zm-4.677-2.796a4.002 4.002 0 01-.041-2.08l-.08.08-1.53-1.533A5.98 5.98 0 004 10c0 .954.223 1.856.619 2.657l1.54-1.54zm1.088-6.45A5.974 5.974 0 0110 4c.954 0 1.856.223 2.657.619l-1.54 1.54a4.002 4.002 0 00-2.346.033L7.246 4.668zM12 10a2 2 0 11-4 0 2 2 0 014 0z"
                    clip-rule="evenodd"
                  ></path>
                </svg>
                <span class="ml-3">Setting</span>
              </a>
            </li>
          </ul>
        </div>
      </aside>
    </div>

    <!-- MAIN -->

    <main class="p-4 md:ml-64 h-auto pt-20">
      <div class="form-section">
        <h1 class="text-2xl font-semibold text-gray-900 mb-6">
          General Information
        </h1>

        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-900 mb-2"
            >Employee</label
          >
          <div class="avatar-upload-section">
            <div class="avatar-preview">
              <img
                id="avatar-preview-image"
                src="https://via.placeholder.com/96"
                alt="Avatar Preview"
              />
            </div>
            <div class="flex flex-col flex-grow">
              <div class="file-input-wrapper">
                <label for="avatar-file-input" class="file-upload-button"
                  >Choose File</label
                >
                <input type="file" id="avatar-file-input" class="hidden" />
                <span id="file-name-display" class="file-name"
                  >No file chosen</span
                >
              </div>
            </div>
          </div>
        </div>

        <div class="form-grid">
          <div class="input-group">
            <label for="Full Name">Full Name</label>
            <input
              type="text"
              id="Full Name"
              class="flowbite-input block w-full"
              placeholder="Full Name"
            />
          </div>
          <div class="input-group">
            <label for="Email Address">Email Address</label>
            <input
              type="text"
              id="Email Address"
              class="flowbite-input block w-full"
              placeholder="Email Address"
            />
          </div>
          <div class="input-group">
            <label for="age">Age</label>
            <input
              type="text"
              id="age"
              class="flowbite-input block w-full"
              placeholder="Age"
            />
          </div>
          <div class="input-group">
            <label for="Gender"
              >Gender
              <i
                class="fas fa-question-circle tooltip-icon"
                title="Permissions define what actions a user can perform."
              ></i
            ></label>
            <select id="Gender" class="flowbite-select block w-full">
              <option selected>Select Gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="others">others</option>
            </select>
          </div>
          <div class="input-group">
            <label for="date"
              >Birth Date
              <i
                class="fas fa-question-circle tooltip-icon"
                title="Status of the user's email verification."
              ></i
            ></label>
            <input
              datepicker
              id="default-datepicker"
              type="text"
              class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
              placeholder="Select date"
            />
          </div>
          <div class="input-group">
            <label for="Address">Complete Address</label>
            <input
              type="text"
              id="Address"
              class="flowbite-input block w-full"
              placeholder="Complete Address"
            />
          </div>
          <div class="input-group">
            <label for="Employee Status"
              >Employee Status
              <i
                class="fas fa-question-circle tooltip-icon"
                title="The role assigned to the user within the system."
              ></i
            ></label>
            <select id="user-role" class="flowbite-select block w-full">
              <option selected>Select Status</option>
              <option value="member">Regular</option>
              <option value="guest">Contractual</option>
              <option value="guest">Job Order</option>
            </select>
          </div>
          <div class="input-group">
            <label for="Health-Insurance"
              >Health Insurance
              <i
                class="fas fa-question-circle tooltip-icon"
                title="The type of account this user belongs to."
              ></i
            ></label>
            <select id="Health-Insurance" class="flowbite-select block w-full">
              <option selected>Choose type</option>
              <option value="personal">Philhealth</option>
              <option value="business">Pag-Ibig</option>
              <option value="business">GSIS</option>
              <option value="business">SSS</option>
            </select>
          </div>
          <div class="flex justify-center">
            <button
              style="align-items: center"
              type="submit"
              class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
            >
              Add Employee
            </button>
          </div>
        </div>
      </div>

      <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
      <script
        src="https://kit.fontawesome.com/a076d05399.js"
        crossorigin="anonymous"
      ></script>
      <script>
        document
          .getElementById("avatar-file-input")
          .addEventListener("change", function () {
            const file = this.files[0];
            const fileName = file ? file.name : "No file chosen";
            const fileDisplay = document.getElementById("file-name-display");
            const imagePreview = document.getElementById(
              "avatar-preview-image"
            );

            fileDisplay.textContent = fileName;

            if (file) {
              const reader = new FileReader();
              reader.onload = function (e) {
                imagePreview.src = e.target.result;
              };
              reader.readAsDataURL(file);
            }
          });
      </script>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.46.0/dist/apexcharts.min.js"></script>
    <script src="../path/to/flowbite/dist/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="../js/tailwind.config.js"></script>
  </body>
</html>
