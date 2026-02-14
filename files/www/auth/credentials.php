<?php
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('Location: /');
    exit;
}
return [
    'username' => 'admin',
    'hashed_password' => '$2y$10$lqqImEuCAzlg2qyBVd3Toek3EQR9ozjwo9VWvRJrfR2J6F6TS.W7a',
];
