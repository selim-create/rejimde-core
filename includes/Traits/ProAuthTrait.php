<?php
namespace Rejimde\Traits;

trait ProAuthTrait {
    
    /**
     * Check if current user is authenticated as expert
     */
    public function check_expert_auth(): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('rejimde_pro', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles);
    }
    
    /**
     * Check if current user is authenticated (any role)
     */
    public function check_auth(): bool {
        return is_user_logged_in();
    }
    
    /**
     * Success response helper
     */
    protected function success($data = null, string $message = 'Success', int $code = 200, ?array $meta = null): \WP_REST_Response {
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ];
        
        if ($meta !== null) {
            $response['meta'] = $meta;
        }
        
        return new \WP_REST_Response($response, $code);
    }
    
    /**
     * Error response helper
     */
    protected function error(string $message = 'Error', int $code = 400): \WP_REST_Response {
        return new \WP_REST_Response([
            'status' => 'error',
            'message' => $message
        ], $code);
    }
}
