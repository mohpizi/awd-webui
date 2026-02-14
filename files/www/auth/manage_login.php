<?php
session_start();

require_once '/data/adb/php7/files/www/auth/auth_functions.php';

// If login is disabled, set the current page but do not redirect to login
if (isset($_SESSION['login_disabled']) && $_SESSION['login_disabled'] === true) {
    // Login is disabled, handle accordingly
    // You can show a message or just let the user stay on the page
    //echo "<p>Login is currently disabled.</p>";
} else {
    // Proceed to check if the user is logged in
    checkUserLogin();
}

// Load the current configuration
$config = json_decode(file_get_contents('config.json'), true);

// Handle form submission to update the configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config['LOGIN_ENABLED'] = isset($_POST['login_enabled']);
    
    // Save the updated configuration back to the JSON file
    file_put_contents('config.json', json_encode($config, JSON_PRETTY_PRINT));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Login Settings</title>
    <!-- Include Materialize CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e88e5;
            --primary-dark: #1565c0;
            --background: #f5f5f5;
            --card-bg: #ffffff;
            --text-primary: #212121;
            --text-secondary: #757575;
            --divider: #e0e0e0;
        }

        body.dark-mode {
            --primary-color: #2196f3;
            --primary-dark: #1976d2;
            --background: #121212;
            --card-bg: #1e1e1e;
            --text-primary: #f5f5f5;
            --text-secondary: #bdbdbd;
            --divider: #424242;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            transition: all 0.3s ease;
        }

        .content {
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 15px 20px;
            font-size: 1.4rem;
            font-weight: 500;
            color: var(--text-primary);
            border-bottom: 1px solid var(--divider);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 500;
            margin: 20px 0 10px 0;
            color: var(--text-primary);
            padding-left: 5px;
        }

        .card-content {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .switch {
            margin-top: 10px;
        }

        .switch label {
            font-size: 1rem;
            color: var(--text-primary);
        }

        .btn {
            background-color: var(--primary-color);
            color: white;
            border-radius: 4px;
            padding: 0 16px;
            height: 36px;
            line-height: 36px;
            text-transform: none;
            font-weight: 500;
            letter-spacing: normal;
            box-shadow: none;
            margin-top: 10px;
        }

        .btn:hover {
            background-color: var(--primary-dark);
        }

        .switch label .lever {
            background-color: #b0b0b0;
        }

        .switch label input[type="checkbox"]:checked + .lever {
            background-color: var(--primary-color);
        }

        .switch label input[type="checkbox"]:checked + .lever:after {
            background-color: white;
        }

        .theme-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 999;
        }
    </style>
</head>
<body class="light-mode">
    <div class="content">
        <h2 class="section-title">Login Settings</h2>
        
        <div class="card">
            <div class="card-header">Login Configuration</div>
            <div class="card-content">
                <form method="POST">
                    <div class="form-group">
                        <label>Login System Status</label>
                        <div class="switch">
                            <label>
                                Disabled
                                <input type="checkbox" name="login_enabled" <?php echo $config['LOGIN_ENABLED'] ? 'checked' : ''; ?>>
                                <span class="lever"></span>
                                Enabled
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn waves-effect">
                        Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="theme-toggle">
        <a class="btn-floating btn-large waves-effect waves-light" onclick="toggleTheme()">
            <i class="material-icons">brightness_4</i>
        </a>
    </div>

    <!-- Include Materialize JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        // Check for saved theme preference or use preferred color scheme
        function applyTheme() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                document.body.className = savedTheme + '-mode';
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.body.className = 'dark-mode';
            } else {
                document.body.className = 'light-mode';
            }
        }

        // Toggle between light and dark theme
        function toggleTheme() {
            if (document.body.classList.contains('light-mode')) {
                document.body.className = 'dark-mode';
                localStorage.setItem('theme', 'dark');
            } else {
                document.body.className = 'light-mode';
                localStorage.setItem('theme', 'light');
            }
        }

        // Apply theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            applyTheme();
            
            // Initialize Materialize components
            M.AutoInit();
        });
    </script>
<body>

    <footer style="text-align: center; margin-top: 20px; color: #34C759 font-size: 13px;">
        <a href="https://t.me/On_Progressss" target="_blank" style="color: #007AFF; text-decoration: none;">
            Telegram @Sogek1ng
        </a>
    </footer>
</body>
</html>