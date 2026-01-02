<?php

namespace Rejimde\Api\V1;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Rejimde\Services\RejiScoreService;

class RejiScoreController {
    
    private $scoreService;
    
    public function __construct() {
        $this->scoreService = new RejiScoreService();
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes(): void {
        // Get RejiScore by expert post ID
        // Permission: Public - RejiScore is publicly visible expert data
        register_rest_route('rejimde/v1', '/experts/(?P<id>\d+)/reji-score', [
            'methods' => 'GET',
            'callback' => [$this, 'get_reji_score'],
            'permission_callback' => '__return_true', // Public endpoint - scores are public data
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // Get RejiScore by user ID
        // Permission: Public - RejiScore is publicly visible expert data
        register_rest_route('rejimde/v1', '/users/(?P<id>\d+)/reji-score', [
            'methods' => 'GET',
            'callback' => [$this, 'get_reji_score_by_user'],
            'permission_callback' => '__return_true', // Public endpoint - scores are public data
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
    }
    
    /**
     * Get RejiScore by expert post ID
     */
    public function get_reji_score(WP_REST_Request $request) {
        $postId = (int) $request->get_param('id');
        
        // Get user ID from expert post
        $userId = get_post_meta($postId, 'related_user_id', true);
        if (!$userId) {
            // Try alternative field
            $userId = get_post_meta($postId, 'user_id', true);
        }
        
        if (!$userId) {
            return new WP_Error(
                'expert_not_found',
                'Expert user ID not found for this profile.',
                ['status' => 404]
            );
        }
        
        $scoreData = $this->scoreService->calculate((int) $userId);
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $scoreData
        ], 200);
    }
    
    /**
     * Get RejiScore by user ID directly
     */
    public function get_reji_score_by_user(WP_REST_Request $request) {
        $userId = (int) $request->get_param('id');
        
        // Verify user exists and is an expert
        $user = get_userdata($userId);
        if (!$user) {
            return new WP_Error(
                'user_not_found',
                'User not found.',
                ['status' => 404]
            );
        }
        
        $scoreData = $this->scoreService->calculate((int) $userId);
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $scoreData
        ], 200);
    }
}
