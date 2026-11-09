<?php
require_once __DIR__ . '/config/app.php';
header('Location: ' . url('auth/login.php'));
exit;
