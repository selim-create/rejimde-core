<?php
/**
 * Validation Script for RejiScore User Meta Key Fix
 * 
 * This script tests the getExpertPostId() fallback mechanism
 * to ensure it correctly handles different user meta key scenarios.
 */

// Locate WordPress root directory
$wp_load_path = null;
$search_paths = [
    __DIR__ . '/../../../wp-load.php',  // Standard plugin location
    __DIR__ . '/../../../../wp-load.php', // Alternative nested location
    __DIR__ . '/../../wp-load.php',      // Direct in wp-content
];

foreach ($search_paths as $path) {
    if (file_exists($path)) {
        $wp_load_path = $path;
        break;
    }
}

if (!$wp_load_path) {
    die("Error: Could not locate wp-load.php. Please run this script from the WordPress plugin directory.\n");
}

require_once $wp_load_path;

use Rejimde\Services\RejiScoreService;

echo "=== RejiScore User Meta Key Fix Validation ===\n\n";

// Get RejiScoreService instance via reflection to access private method
$service = new RejiScoreService();
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('getExpertPostId');
$method->setAccessible(true);

// Test 1: Find experts with related_pro_post_id
echo "Test 1: Finding experts with 'related_pro_post_id' meta key\n";
echo "--------------------------------------------------------\n";

global $wpdb;
$experts_with_related_pro = $wpdb->get_results("
    SELECT user_id, meta_value as post_id 
    FROM {$wpdb->usermeta} 
    WHERE meta_key = 'related_pro_post_id' 
    LIMIT 5
");

if ($experts_with_related_pro) {
    foreach ($experts_with_related_pro as $expert) {
        $userId = (int) $expert->user_id;
        $expectedPostId = (int) $expert->post_id;
        $retrievedPostId = $method->invoke($service, $userId);
        
        $status = ($retrievedPostId === $expectedPostId) ? '✓ PASS' : '✗ FAIL';
        echo "User ID: {$userId}, Expected Post ID: {$expectedPostId}, Retrieved: {$retrievedPostId} - {$status}\n";
    }
} else {
    echo "No experts found with 'related_pro_post_id' meta key\n";
}

echo "\n";

// Test 2: Find experts with professional_profile_id
echo "Test 2: Finding experts with 'professional_profile_id' meta key\n";
echo "---------------------------------------------------------------\n";

$experts_with_professional = $wpdb->get_results("
    SELECT user_id, meta_value as post_id 
    FROM {$wpdb->usermeta} 
    WHERE meta_key = 'professional_profile_id' 
    LIMIT 5
");

if ($experts_with_professional) {
    foreach ($experts_with_professional as $expert) {
        $userId = (int) $expert->user_id;
        $expectedPostId = (int) $expert->post_id;
        $retrievedPostId = $method->invoke($service, $userId);
        
        $status = ($retrievedPostId === $expectedPostId) ? '✓ PASS' : '✗ FAIL';
        echo "User ID: {$userId}, Expected Post ID: {$expectedPostId}, Retrieved: {$retrievedPostId} - {$status}\n";
    }
} else {
    echo "No experts found with 'professional_profile_id' meta key\n";
}

echo "\n";

// Test 3: Test reverse lookup from post meta
echo "Test 3: Testing reverse lookup from post meta (related_user_id)\n";
echo "---------------------------------------------------------------\n";

$posts_with_related_user = $wpdb->get_results("
    SELECT post_id, meta_value as user_id 
    FROM {$wpdb->postmeta} 
    WHERE meta_key = 'related_user_id' 
    LIMIT 5
");

if ($posts_with_related_user) {
    foreach ($posts_with_related_user as $post) {
        $userId = (int) $post->user_id;
        $expectedPostId = (int) $post->post_id;
        $retrievedPostId = $method->invoke($service, $userId);
        
        $status = ($retrievedPostId === $expectedPostId) ? '✓ PASS' : '✗ FAIL';
        echo "User ID: {$userId}, Expected Post ID: {$expectedPostId}, Retrieved: {$retrievedPostId} - {$status}\n";
    }
} else {
    echo "No posts found with 'related_user_id' meta key\n";
}

echo "\n";

// Test 4: Test RejiScore calculation for a sample expert
echo "Test 4: RejiScore Calculation Test\n";
echo "-----------------------------------\n";

// Get first expert user
$first_expert = $wpdb->get_var("
    SELECT user_id 
    FROM {$wpdb->usermeta} 
    WHERE meta_key IN ('related_pro_post_id', 'professional_profile_id') 
    LIMIT 1
");

if ($first_expert) {
    $expertId = (int) $first_expert;
    echo "Testing RejiScore calculation for User ID: {$expertId}\n";
    
    try {
        $scoreData = $service->calculate($expertId);
        
        echo "\nRejiScore Results:\n";
        echo "- RejiScore: {$scoreData['reji_score']}\n";
        echo "- Trust Score: {$scoreData['trust_score']}\n";
        echo "- Review Count: {$scoreData['review_count']}\n";
        echo "- Content Count: {$scoreData['content_count']}\n";
        echo "- User Rating: {$scoreData['user_rating']}\n";
        echo "- Verification Bonus: {$scoreData['verification_bonus']}\n";
        echo "- Level: {$scoreData['level']} ({$scoreData['level_label']})\n";
        
        // Check if review_count is no longer stuck at 0
        if ($scoreData['review_count'] > 0) {
            echo "\n✓ SUCCESS: review_count is {$scoreData['review_count']} (not 0!)\n";
        } else {
            echo "\n⚠ WARNING: review_count is still 0 (this expert may have no reviews)\n";
        }
    } catch (Exception $e) {
        echo "✗ ERROR: " . $e->getMessage() . "\n";
    }
} else {
    echo "No expert users found to test\n";
}

echo "\n=== Validation Complete ===\n";
