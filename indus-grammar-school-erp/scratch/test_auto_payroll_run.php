<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/PayrollService.php';

echo "Testing normal automatic check (forceRun = false)...\n";
$res = PayrollService::checkAndRunAutoPayroll(false);
print_r($res);
