<?php
// password_generator.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator - Municipality of Paluan HRMO</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --royal-blue: #2563eb;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            min-height: 100vh;
        }
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(30, 64, 175, 0.15);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--royal-blue), var(--primary-blue));
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.3);
        }
        .hash-display {
            font-family: 'Courier New', monospace;
            background: #f8fafc;
            border-left: 4px solid var(--royal-blue);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="card w-full max-w-2xl p-8">
        <div class="text-center mb-8">
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl flex items-center justify-center">
                    <i class="fas fa-lock text-white text-2xl"></i>
                </div>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Password Hash Generator</h1>
            <p class="text-gray-600">Generate secure password hashes for your user accounts</p>
            <p class="text-sm text-gray-500 mt-2">Municipality of Paluan HRMO</p>
        </div>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2" for="password">
                    <i class="fas fa-key mr-2"></i>Enter Password
                </label>
                <div class="relative">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="w-full px-4 py-3 pl-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                           placeholder="Enter password to hash"
                           required>
                    <i class="fas fa-lock absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <button type="button" 
                            onclick="togglePassword()"
                            class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i id="eyeIcon" class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="mt-2 text-sm text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    Password will be hashed using PHP's password_hash() with PASSWORD_DEFAULT
                </div>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2" for="algorithm">
                    <i class="fas fa-cogs mr-2"></i>Hash Algorithm
                </label>
                <select id="algorithm" name="algorithm" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="PASSWORD_DEFAULT" selected>PASSWORD_DEFAULT (Recommended)</option>
                    <option value="PASSWORD_BCRYPT">PASSWORD_BCRYPT</option>
                    <option value="PASSWORD_ARGON2I">PASSWORD_ARGON2I</option>
                    <option value="PASSWORD_ARGON2ID">PASSWORD_ARGON2ID</option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2" for="cost">
                        <i class="fas fa-sliders-h mr-2"></i>BCRYPT Cost
                    </label>
                    <input type="range" 
                           id="cost" 
                           name="cost" 
                           min="4" 
                           max="31" 
                           value="12"
                           class="w-full"
                           oninput="costValue.innerText = this.value">
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Fast (4)</span>
                        <span id="costValue">12</span>
                        <span>Secure (31)</span>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">
                        <i class="fas fa-bolt mr-2"></i>Options
                    </label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="include_sql" checked class="mr-2">
                            <span class="text-sm">Include SQL INSERT statement</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="verify_hash" checked class="mr-2">
                            <span class="text-sm">Verify hash after generation</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex space-x-4">
                <button type="submit" 
                        name="generate"
                        class="btn-primary text-white font-semibold py-3 px-6 rounded-lg flex-1">
                    <i class="fas fa-hammer mr-2"></i> Generate Hash
                </button>
                <button type="button" 
                        onclick="generateRandomPassword()"
                        class="bg-gray-100 text-gray-700 font-semibold py-3 px-6 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-dice mr-2"></i> Random Password
                </button>
            </div>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
            $password = $_POST['password'] ?? '';
            $algorithm = $_POST['algorithm'] ?? 'PASSWORD_DEFAULT';
            $cost = $_POST['cost'] ?? 12;
            
            if (!empty($password)) {
                $options = [];
                
                if ($algorithm === 'PASSWORD_BCRYPT') {
                    $options['cost'] = (int)$cost;
                }
                
                // Generate hash
                $hash = password_hash($password, constant($algorithm), $options);
                
                // Verify hash
                $verify_result = password_verify($password, $hash);
                
                // Get hash info
                $hash_info = password_get_info($hash);
                
                echo '<div class="mt-8 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200">';
                echo '<h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">';
                echo '<i class="fas fa-check-circle text-green-500 mr-2"></i> Hash Generated Successfully';
                echo '</h3>';
                
                echo '<div class="space-y-4">';
                
                // Original Password
                echo '<div>';
                echo '<label class="block text-sm font-semibold text-gray-700 mb-1">Original Password</label>';
                echo '<div class="hash-display p-3 rounded">' . htmlspecialchars($password) . '</div>';
                echo '</div>';
                
                // Generated Hash
                echo '<div>';
                echo '<label class="block text-sm font-semibold text-gray-700 mb-1">Generated Hash</label>';
                echo '<div class="hash-display p-3 rounded break-all">' . htmlspecialchars($hash) . '</div>';
                echo '</div>';
                
                // Hash Info
                echo '<div class="grid grid-cols-2 gap-4">';
                echo '<div class="bg-white p-3 rounded border">';
                echo '<label class="block text-xs font-semibold text-gray-500 mb-1">Algorithm</label>';
                echo '<div class="text-sm font-medium">' . $hash_info['algoName'] . '</div>';
                echo '</div>';
                
                echo '<div class="bg-white p-3 rounded border">';
                echo '<label class="block text-xs font-semibold text-gray-500 mb-1">Options</label>';
                echo '<div class="text-sm font-medium">';
                if (isset($hash_info['options']['cost'])) {
                    echo 'Cost: ' . $hash_info['options']['cost'];
                } elseif (isset($hash_info['options']['memory_cost'])) {
                    echo 'Memory: ' . $hash_info['options']['memory_cost'];
                }
                echo '</div>';
                echo '</div>';
                
                echo '<div class="bg-white p-3 rounded border">';
                echo '<label class="block text-xs font-semibold text-gray-500 mb-1">Hash Length</label>';
                echo '<div class="text-sm font-medium">' . strlen($hash) . ' characters</div>';
                echo '</div>';
                
                echo '<div class="bg-white p-3 rounded border">';
                echo '<label class="block text-xs font-semibold text-gray-500 mb-1">Verification</label>';
                echo '<div class="text-sm font-medium ' . ($verify_result ? 'text-green-600' : 'text-red-600') . '">';
                echo $verify_result ? '✓ Valid' : '✗ Invalid';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                
                // SQL Statement
                if (isset($_POST['include_sql'])) {
                    echo '<div class="mt-4">';
                    echo '<label class="block text-sm font-semibold text-gray-700 mb-1">SQL INSERT Statement</label>';
                    echo '<div class="hash-display p-3 rounded text-sm">';
                    echo "INSERT INTO users (email, password, full_name, is_admin) VALUES (<br>";
                    echo "&nbsp;&nbsp;'user@example.com',<br>";
                    echo "&nbsp;&nbsp;'" . htmlspecialchars($hash) . "',<br>";
                    echo "&nbsp;&nbsp;'User Name',<br>";
                    echo "&nbsp;&nbsp;1<br>";
                    echo ");";
                    echo '</div>';
                    echo '</div>';
                }
                
                // Copy to clipboard button
                echo '<div class="mt-4">';
                echo '<button onclick="copyToClipboard(\'' . addslashes($hash) . '\')" ';
                echo 'class="bg-blue-100 text-blue-700 hover:bg-blue-200 font-medium py-2 px-4 rounded transition flex items-center">';
                echo '<i class="fas fa-copy mr-2"></i> Copy Hash to Clipboard';
                echo '</button>';
                echo '</div>';
                
                echo '</div>';
                echo '</div>';
            }
        }
        ?>

        <div class="mt-8 p-6 bg-yellow-50 border-l-4 border-yellow-400 rounded">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-lightbulb text-yellow-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-800">How to Use</h3>
                    <ul class="mt-2 text-gray-600 space-y-2">
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                            <span>Enter a password and click "Generate Hash"</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                            <span>Copy the generated hash to your database</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                            <span>Use <code>password_verify('password', $hash)</code> to verify passwords in PHP</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="mt-6 text-center text-gray-500 text-sm">
            <p><i class="fas fa-shield-alt mr-1"></i> Secure Password Storage System</p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                eyeIcon.className = 'fas fa-eye';
            }
        }
        
        function generateRandomPassword() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
            let password = '';
            
            // Generate 12-character password
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            document.getElementById('password').value = password;
            document.getElementById('password').type = 'text';
            document.getElementById('eyeIcon').className = 'fas fa-eye-slash';
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Hash copied to clipboard!');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }
        
        // Auto-generate on page load for demo
        window.addEventListener('load', function() {
            if (!document.getElementById('password').value) {
                generateRandomPassword();
            }
        });
    </script>
</body>
</html>