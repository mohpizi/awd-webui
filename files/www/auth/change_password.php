<?php
session_start();

require_once '/data/adb/php7/files/www/auth/auth_functions.php';
if (isset($_SESSION['login_disabled']) && $_SESSION['login_disabled'] === true) {
} else {
    checkUserLogin();
}

$credentials = include 'credentials.php';
$stored_username = $credentials['username'];
$stored_hashed_password = $credentials['hashed_password'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_username = $_POST['new_username'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    if ($new_password === $confirm_new_password) {
        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $credentials_content = "<?php\n";
        $credentials_content .= "if (basename(__FILE__) == basename(\$_SERVER['PHP_SELF'])) {\n";
        $credentials_content .= "    header('Location: /');\n";
        $credentials_content .= "    exit;\n";
        $credentials_content .= "}\n";
        $credentials_content .= "return [\n";
        $credentials_content .= "    'username' => '" . addslashes($new_username) . "',\n";
        $credentials_content .= "    'hashed_password' => '" . addslashes($new_hashed_password) . "',\n";
        $credentials_content .= "];\n";

        file_put_contents('credentials.php', $credentials_content);
        $success = 'Username and password have been updated successfully.';
    } else {
        $error = 'New passwords do not match.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #3a7afe;
            --background: #f9fafc;
            --card-bg: #ffffff;
            --text: #4a5568;
            --text-light: #718096;
            --border: #e2e8f0;
            --error: #ff4d4f;
            --success: #52c41a;
            --radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--background);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 0;
        }

        .container {
            width: 100%;
            padding: 20px 15px;
            background: var(--card-bg);
            border-radius: 0;
            box-shadow: none;
            border: none;
            margin: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-top: 15px;
        }

        .header h1 {
            font-size: 22px;
            margin: 0 0 8px 0;
            color: var(--text);
        }

        .header p {
            color: var(--text-light);
            margin: 0;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
            width: 100%;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text);
        }

        .form-control {
            width: 100%;
            padding: 14px 15px;
            font-size: 15px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-light);
            background: none;
            border: none;
            padding: 5px;
        }

        .btn {
            width: 100%;
            padding: 15px;
            font-size: 16px;
            color: white;
            background-color: var(--primary);
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn i {
            margin-right: 8px;
        }

        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: var(--radius);
            font-size: 14px;
            width: 100%;
        }

        .alert-error {
            background-color: #fff1f0;
            color: var(--error);
        }

        .alert-success {
            background-color: #f6ffed;
            color: var(--success);
        }

        footer {
            text-align: center;
            padding: 15px;
            color: #34C759;
            font-size: 13px;
            width: 100%;
            margin-top: auto;
        }

        footer a {
            color: #007AFF;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Administration</h1>
            <p>Update your UI username and password</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="post" action="change_password.php">
            <div class="form-group">
                <label for="new_username">New Username</label>
                <input type="text" class="form-control" name="new_username" id="new_username" required>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="password-wrapper">
                    <input type="password" class="form-control" name="new_password" id="new_password" required>
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('new_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_new_password">Confirm Password</label>
                <div class="password-wrapper">
                    <input type="password" class="form-control" name="confirm_new_password" id="confirm_new_password" required>
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_new_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </form>
    </div>

    <footer>
        <a href="https://t.me/On_Progressss" target="_blank">
            Telegram @Sogek1ng
        </a>
    </footer>

    <script>
        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentElement.querySelector('i');
            
            if (field.type === "password") {
                field.type = "text";
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                field.type = "password";
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>