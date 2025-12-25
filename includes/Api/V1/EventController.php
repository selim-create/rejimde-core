<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;

/**
 * Event Controller
 * 
 * Handles event dispatching endpoints for gamification
 */
class EventController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'events';

    public function register_routes() {
        // Event Dispatch - Main endpoint for frontend
        register_rest_route($this->namespace, '/' . $this->base . '/dispatch', [
            'methods' => 'POST',
            'callback' => [$this, 'dispatch_event'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    /**
     * Dispatch an event
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function dispatch_event($request) {
        $params = $request->get_json_params();
        
        // Get event type
        $eventType = sanitize_text_field($params['event_type'] ?? $params['action'] ?? '');
        
        if (empty($eventType)) {
            return $this->error('Event type is required', 400);
        }
        
        // Build payload for EventDispatcher
        $payload = [
            'user_id' => get_current_user_id(),
            'entity_type' => sanitize_text_field($params['entity_type'] ?? null),
            'entity_id' => isset($params['entity_id']) ? (int) $params['entity_id'] : (isset($params['ref_id']) ? (int) $params['ref_id'] : null),
            'context' => []
        ];
        
        // Add any additional context
        if (isset($params['context']) && is_array($params['context'])) {
            // Sanitize context data
            $sanitized_context = [];
            foreach ($params['context'] as $key => $value) {
                $sanitized_key = sanitize_key($key);
                if (is_array($value)) {
                    $sanitized_context[$sanitized_key] = array_map('sanitize_text_field', $value);
                } elseif (is_bool($value)) {
                    $sanitized_context[$sanitized_key] = (bool) $value;
                } elseif (is_numeric($value)) {
                    $sanitized_context[$sanitized_key] = is_float($value) ? (float) $value : (int) $value;
                } else {
                    $sanitized_context[$sanitized_key] = sanitize_text_field($value);
                }
            }
            $payload['context'] = $sanitized_context;
        }
        
        // Support for follow events
        if (isset($params['follower_id'])) {
            $payload['follower_id'] = (int) $params['follower_id'];
        }
        if (isset($params['followed_id'])) {
            $payload['followed_id'] = (int) $params['followed_id'];
        }
        
        // Support for comment events
        if (isset($params['comment_id'])) {
            $payload['comment_id'] = (int) $params['comment_id'];
        }
        
        // Dispatch event
        $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
        $result = $dispatcher->dispatch($eventType, $payload);
        
        if ($result['success']) {
            return $this->success($result);
        } else {
            return $this->error($result['message'], 400);
        }
    }

    public function check_auth($request) {
        return is_user_logged_in();
    }

    protected function success($data = null) {
        return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
    }

    protected function error($message = 'Error', $code = 400) {
        return new WP_REST_Response(['status' => 'error', 'message' => $message], $code);
    }
}
