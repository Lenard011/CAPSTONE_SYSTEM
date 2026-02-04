<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centered and Styled Registration Form</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="..." crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/20GKWJvI1r9V3k/8fA7F/d8G1/d7f7e9f/a1f8" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background-image:  url('../img/backpic-login.png') ; /* Replace with your image URL */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh; 
           
            margin: 0;
        }
        .password-toggle {
            margin-top: 15px;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9ca3af;
        }
      
        
        .form-container {
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background-color: white;
            width: 100%;
            max-width: 400px;
            margin-left: 250px;
            margin-top: 300px;
        }
        .password-container {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9ca3af; 
        }
    </style>
</head>
<body>
    
<div class="container-wrapper">
    <div class="form-container">
        <form class="max-w-sm mx-auto">
            <h2 class="text-2xl font-bold text-center mb-6 text-gray-900 dark:text-white">Create an Account</h2>
            <div class="mb-5">
                <label for="email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Your email</label>
                <input type="email" id="email" class="shadow-xs bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 dark:shadow-xs-light" placeholder="name@gmail.com" required />
            </div>

            <div class="mb-5 password-container">
                <label for="password" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Your password</label>
                <input type="password" id="password" class="shadow-xs bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 dark:shadow-xs-light" required />
                <i class="fa-solid fa-eye password-toggle" id="togglePassword"></i>
            </div>
            
            <div class="mb-5 password-container">
                <label for="repeat-password" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Repeat password</label>
                <input type="password" id="repeat-password" class="shadow-xs bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 dark:shadow-xs-light" required />
                <i class="fa-solid fa-eye password-toggle" id="toggleRepeatPassword"></i>
            </div>

            <div class="flex items-start mb-5">
                <div class="flex items-center h-5">
                    <input id="terms" type="checkbox" value="" class="w-4 h-4 border border-gray-300 rounded-sm bg-gray-50 focus:ring-3 focus:ring-blue-300 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-blue-600 dark:ring-offset-gray-800 dark:focus:ring-offset-gray-800" required />
                </div>
                <label for="terms" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">I agree with the <a href="#" class="text-blue-600 hover:underline dark:text-blue-500">terms and conditions</a></label>
            </div>
            <div class="flex justify-center">
                <a href="./dashboard.html"></a>
                <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">Register new account</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
<script>
    function setupPasswordToggle(inputId, toggleId) {
        const passwordInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(toggleId);

        toggleIcon.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }

   
    setupPasswordToggle('password', 'togglePassword');
    setupPasswordToggle('repeat-password', 'toggleRepeatPassword');
</script>
</body>
</html>