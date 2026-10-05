<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_method('GET');
api_check_origin();
api_headers();

$user = require_user();

api_ok(['user' => public_user($user)]);
