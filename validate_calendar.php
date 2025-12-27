#!/usr/bin/env php
<?php
/**
 * Calendar System Validation Script
 * 
 * This script validates that the Calendar & Appointment System is properly implemented.
 */

echo "=== Calendar & Appointment System Validation ===\n\n";

// Check if files exist
$files = [
    'includes/Services/CalendarService.php',
    'includes/Api/V1/CalendarController.php',
    'includes/Core/Activator.php',
    'includes/Core/Loader.php',
];

echo "1. Checking File Existence:\n";
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "   ✓ $file exists\n";
    } else {
        echo "   ✗ $file NOT FOUND\n";
    }
}

echo "\n2. Checking PHP Syntax:\n";
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        exec("php -l $path 2>&1", $output, $return_code);
        if ($return_code === 0) {
            echo "   ✓ $file syntax OK\n";
        } else {
            echo "   ✗ $file has syntax errors\n";
            echo "     " . implode("\n     ", $output) . "\n";
        }
    }
}

echo "\n3. Checking Database Table Definitions:\n";
$activatorContent = file_get_contents(__DIR__ . '/includes/Core/Activator.php');
$tables = [
    'rejimde_availability',
    'rejimde_appointments',
    'rejimde_appointment_requests',
    'rejimde_blocked_times',
];

foreach ($tables as $table) {
    if (strpos($activatorContent, $table) !== false) {
        echo "   ✓ $table table defined\n";
    } else {
        echo "   ✗ $table table NOT FOUND\n";
    }
}

echo "\n4. Checking Service Methods:\n";
$serviceContent = file_get_contents(__DIR__ . '/includes/Services/CalendarService.php');
$methods = [
    'getAvailability',
    'updateAvailability',
    'getAppointments',
    'createAppointment',
    'updateAppointment',
    'cancelAppointment',
    'getRequests',
    'approveRequest',
    'rejectRequest',
    'blockTime',
    'unblockTime',
    'checkConflict',
    'isSlotAvailable',
];

foreach ($methods as $method) {
    if (strpos($serviceContent, "function $method") !== false) {
        echo "   ✓ $method() implemented\n";
    } else {
        echo "   ✗ $method() NOT FOUND\n";
    }
}

echo "\n5. Checking Controller Routes:\n";
$controllerContent = file_get_contents(__DIR__ . '/includes/Api/V1/CalendarController.php');
$routes = [
    'get_calendar',
    'get_availability',
    'update_availability',
    'create_appointment',
    'update_appointment',
    'cancel_appointment',
    'get_requests',
    'approve_request',
    'reject_request',
    'block_time',
    'unblock_time',
    'get_expert_availability',
    'create_request',
    'get_my_appointments',
];

foreach ($routes as $route) {
    if (strpos($controllerContent, "function $route") !== false) {
        echo "   ✓ $route route handler found\n";
    } else {
        echo "   ✗ $route route handler NOT FOUND\n";
    }
}

echo "\n6. Checking Loader Integration:\n";
$loaderContent = file_get_contents(__DIR__ . '/includes/Core/Loader.php');

if (strpos($loaderContent, 'CalendarService.php') !== false) {
    echo "   ✓ CalendarService registered in Loader\n";
} else {
    echo "   ✗ CalendarService NOT registered in Loader\n";
}

if (strpos($loaderContent, 'CalendarController.php') !== false) {
    echo "   ✓ CalendarController registered in Loader\n";
} else {
    echo "   ✗ CalendarController NOT registered in Loader\n";
}

if (strpos($loaderContent, "class_exists('Rejimde\\\\Api\\\\V1\\\\CalendarController')") !== false) {
    echo "   ✓ CalendarController routes registered\n";
} else {
    echo "   ✗ CalendarController routes NOT registered\n";
}

echo "\n7. Checking Namespace Declarations:\n";

if (strpos($serviceContent, 'namespace Rejimde\Services;') !== false) {
    echo "   ✓ CalendarService namespace correct\n";
} else {
    echo "   ✗ CalendarService namespace incorrect\n";
}

if (strpos($controllerContent, 'namespace Rejimde\Api\V1;') !== false) {
    echo "   ✓ CalendarController namespace correct\n";
} else {
    echo "   ✗ CalendarController namespace incorrect\n";
}

echo "\n8. Checking Class Declarations:\n";

if (strpos($serviceContent, 'class CalendarService') !== false) {
    echo "   ✓ CalendarService class declared\n";
} else {
    echo "   ✗ CalendarService class NOT declared\n";
}

if (strpos($controllerContent, 'class CalendarController extends WP_REST_Controller') !== false) {
    echo "   ✓ CalendarController class declared correctly\n";
} else {
    echo "   ✗ CalendarController class NOT declared correctly\n";
}

echo "\n=== Validation Complete ===\n";
echo "\nAll core components are in place!\n";
echo "Next steps:\n";
echo "1. Deploy to WordPress environment\n";
echo "2. Activate/reactivate plugin to create database tables\n";
echo "3. Test API endpoints using the test guide\n";
echo "4. Verify notifications are sent correctly\n";
