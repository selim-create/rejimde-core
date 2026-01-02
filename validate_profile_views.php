#!/usr/bin/env php
<?php
/**
 * Profile Views API Validation Script
 * 
 * This script validates that the Profile Views tracking feature is properly implemented.
 */

echo "=== Profile Views API Validation ===\n\n";

// Check if files exist
$files = [
    'includes/Api/V1/ProfileViewController.php',
    'includes/Cron/ProfileViewNotifications.php',
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
        $output = [];
    }
}

echo "\n3. Checking Activator.php for profile_views table schema:\n";
$activatorContent = file_get_contents(__DIR__ . '/includes/Core/Activator.php');

// Check for required columns
$requiredColumns = [
    'expert_user_id BIGINT UNSIGNED NOT NULL',
    'expert_slug VARCHAR(255) NOT NULL',
    'viewer_user_id BIGINT UNSIGNED DEFAULT NULL',
    'viewer_ip VARCHAR(45) DEFAULT NULL',
    'viewer_user_agent TEXT DEFAULT NULL',
    'is_member TINYINT(1) DEFAULT 0',
    'viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP',
    'session_id VARCHAR(255) DEFAULT NULL',
];

foreach ($requiredColumns as $column) {
    if (strpos($activatorContent, $column) !== false) {
        echo "   ✓ Column definition found: " . substr($column, 0, 30) . "...\n";
    } else {
        echo "   ✗ Column definition NOT found: " . substr($column, 0, 30) . "...\n";
    }
}

// Check for required indexes
$requiredIndexes = [
    'idx_expert_user_id',
    'idx_expert_slug',
    'idx_viewed_at',
    'idx_viewer_user_id',
];

foreach ($requiredIndexes as $index) {
    if (strpos($activatorContent, $index) !== false) {
        echo "   ✓ Index found: $index\n";
    } else {
        echo "   ✗ Index NOT found: $index\n";
    }
}

echo "\n4. Checking ProfileViewController.php:\n";
$controllerContent = file_get_contents(__DIR__ . '/includes/Api/V1/ProfileViewController.php');

// Check for required methods
$requiredMethods = [
    'track_view',
    'get_my_stats',
    'get_activity',
];

foreach ($requiredMethods as $method) {
    if (strpos($controllerContent, "function $method") !== false) {
        echo "   ✓ Method found: $method\n";
    } else {
        echo "   ✗ Method NOT found: $method\n";
    }
}

// Check for CloudFlare IP detection
if (strpos($controllerContent, 'HTTP_CF_CONNECTING_IP') !== false) {
    echo "   ✓ CloudFlare IP detection implemented\n";
} else {
    echo "   ✗ CloudFlare IP detection NOT found\n";
}

// Check for session throttling (30 minutes)
if (strpos($controllerContent, '-30 minutes') !== false) {
    echo "   ✓ 30-minute session throttling implemented\n";
} else {
    echo "   ✗ 30-minute session throttling NOT found\n";
}

// Check for self-view prevention with strict comparison
if (strpos($controllerContent, 'viewer_user_id === $expert_user_id') !== false || 
    strpos($controllerContent, '$viewer_user_id === $expert_user_id') !== false) {
    echo "   ✓ Self-view prevention implemented (strict comparison)\n";
} elseif (strpos($controllerContent, 'viewer_user_id == $expert_user_id') !== false || 
          strpos($controllerContent, '$viewer_user_id == $expert_user_id') !== false) {
    echo "   ⚠ Self-view prevention implemented (loose comparison - should use ===)\n";
} else {
    echo "   ✗ Self-view prevention NOT found\n";
}

// Check for input sanitization
if (strpos($controllerContent, 'sanitize_text_field') !== false) {
    echo "   ✓ Input sanitization implemented\n";
} else {
    echo "   ⚠ Input sanitization NOT found (recommended for security)\n";
}

// Check for IP validation
if (strpos($controllerContent, 'FILTER_VALIDATE_IP') !== false) {
    echo "   ✓ IP validation implemented\n";
} else {
    echo "   ⚠ IP validation NOT found (recommended for security)\n";
}

// Check for dicebear fallback
if (strpos($controllerContent, 'dicebear') !== false) {
    echo "   ✓ Dicebear avatar fallback implemented\n";
} else {
    echo "   ✗ Dicebear avatar fallback NOT found\n";
}

echo "\n5. Checking ProfileViewNotifications.php:\n";
$cronContent = file_get_contents(__DIR__ . '/includes/Cron/ProfileViewNotifications.php');

// Check for cron job name
if (strpos($cronContent, 'rejimde_weekly_view_summary') !== false) {
    echo "   ✓ Cron job 'rejimde_weekly_view_summary' found\n";
} else {
    echo "   ✗ Cron job 'rejimde_weekly_view_summary' NOT found\n";
}

// Check for notification creation
if (strpos($cronContent, 'profile_view_summary') !== false) {
    echo "   ✓ Notification type 'profile_view_summary' found\n";
} else {
    echo "   ✗ Notification type 'profile_view_summary' NOT found\n";
}

// Check for expert role check
if (strpos($cronContent, 'rejimde_pro') !== false) {
    echo "   ✓ Expert role check implemented\n";
} else {
    echo "   ✗ Expert role check NOT found\n";
}

echo "\n6. Checking Loader.php for registration:\n";
$loaderContent = file_get_contents(__DIR__ . '/includes/Core/Loader.php');

// Check if ProfileViewController is loaded
if (strpos($loaderContent, 'ProfileViewController.php') !== false) {
    echo "   ✓ ProfileViewController loaded in Loader.php\n";
} else {
    echo "   ✗ ProfileViewController NOT loaded in Loader.php\n";
}

// Check if ProfileViewNotifications is loaded
if (strpos($loaderContent, 'ProfileViewNotifications.php') !== false) {
    echo "   ✓ ProfileViewNotifications loaded in Loader.php\n";
} else {
    echo "   ✗ ProfileViewNotifications NOT loaded in Loader.php\n";
}

// Check if routes are registered
if (strpos($loaderContent, "class_exists('Rejimde\\\\Api\\\\V1\\\\ProfileViewController')") !== false) {
    echo "   ✓ ProfileViewController routes registered\n";
} else {
    echo "   ✗ ProfileViewController routes NOT registered\n";
}

// Check if cron job is registered
if (strpos($loaderContent, "class_exists('Rejimde\\\\Cron\\\\ProfileViewNotifications')") !== false) {
    echo "   ✓ ProfileViewNotifications cron registered\n";
} else {
    echo "   ✗ ProfileViewNotifications cron NOT registered\n";
}

echo "\n7. Checking API Endpoints:\n";
$expectedEndpoints = [
    '/rejimde/v1/profile-views/track',
    '/rejimde/v1/profile-views/my-stats',
    '/rejimde/v1/profile-views/activity',
];

foreach ($expectedEndpoints as $endpoint) {
    echo "   Expected: $endpoint\n";
}

echo "\n=== Validation Complete ===\n";
echo "\nTo test the API endpoints, you need to:\n";
echo "1. Activate the plugin in WordPress\n";
echo "2. Use a REST API client to test the endpoints\n";
echo "3. Verify the cron job is scheduled with: wp cron event list\n";
