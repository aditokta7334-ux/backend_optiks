<?php

// Fix SCRIPT_NAME so Laravel does not strip "/api" as the base path on Vercel
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

// Forward Vercel requests to Laravel's public entry point
require __DIR__ . '/../public/index.php';
