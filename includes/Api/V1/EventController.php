<?php
namespace Rejimde\Api\V1;

use Rejimde\Api\BaseController;
use Rejimde\Services\EventService;
use WP_REST_Request;

/**
 * EventController
 * 
 * Handles event submission endpoint
 */
class EventController extends BaseController {
    
    protected $namespace = 'rejimde/v1';
    protected $base = 'events';
    
    public function register_routes() {
        // POST /rejimde/v1/events - Submit an event
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'submit_event'],
            'permission_callback' => [$this, 'check_can_earn'],
            'args' => [
                'event_type' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Type of event',
                    'validate_callback' => [$this, 'validate_event_type'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'entity_type' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Type of entity (e.g., blog, diet, exercise)',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'entity_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'ID of entity',
                    'sanitize_callback' => 'absint'
                ],
                'metadata' => [
                    'required' => false,
                    'type' => 'object',
                    'description' => 'Additional metadata'
                ],
                'source' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'web',
                    'description' => 'Source of event (web, mobile, etc.)',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
    }
    
    /**
     * POST /rejimde/v1/events
     * Submit an event
     */
    public function submit_event(WP_REST_Request $request) {
        try {
            $user_id = get_current_user_id();
            
            // Get parameters with proper sanitization
            $event_type = sanitize_text_field($request->get_param('event_type'));
            $entity_type = $request->get_param('entity_type') ? sanitize_text_field($request->get_param('entity_type')) : null;
            $entity_id = $request->get_param('entity_id') ? absint($request->get_param('entity_id')) : null;
            $metadata = $request->get_param('metadata') ?: [];
            $source = $request->get_param('source') ? sanitize_text_field($request->get_param('source')) : 'web';
            
            // Validate event_type (already validated by validate_callback, but double-check for safety)
            if (empty($event_type)) {
                return $this->error('event_type is required', 400);
            }
            
            // Role check (double-check even though check_can_earn permission callback should handle this)
            if (!$this->can_earn_points($user_id)) {
                return $this->error('Uzman hesapları puan kazanamaz.', 403);
            }
            
            // Process event through EventService with error handling
            $result = EventService::ingestEvent(
                $user_id,
                $event_type,
                $entity_type,
                $entity_id,
                $metadata,
                $source
            );
            
            // Handle error responses from EventService
            if (isset($result['status']) && $result['status'] === 'error') {
                $error_message = !empty($result['messages']) ? $result['messages'][0] : 'Bir hata oluştu';
                return $this->error(
                    $error_message,
                    $result['code'] ?? 500
                );
            }
            
            // Handle duplicate events
            if (isset($result['status']) && $result['status'] === 'duplicate') {
                return $this->error(
                    $result['message'],
                    $result['code'],
                    [
                        'event_id' => $result['event_id'],
                        'points_awarded' => $result['points_awarded']
                    ]
                );
            }
            
            // Return successful response
            return $this->success([
                'event_id' => $result['event_id'],
                'event_type' => $result['event_type'],
                'awarded_points_total' => $result['awarded_points_total'],
                'awarded_ledger_items' => $result['awarded_ledger_items'],
                'messages' => $result['messages'],
                'daily_remaining' => $result['daily_remaining'],
                'current_balance' => $result['current_balance']
            ], 'Event processed successfully', 200);
        } catch (\Throwable $e) {
            error_log('EventController::submit_event FATAL: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return $this->error('Bir sistem hatası oluştu. Lütfen daha sonra tekrar deneyin.', 500);
        }
    }
    
    /**
     * Validate event_type parameter
     * 
     * @param string $value Event type value
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return bool True if valid, false otherwise
     */
    public function validate_event_type($value, $request, $param) {
        $allowed = [
            'login_success',
            'blog_points_claimed',
            'diet_started',
            'diet_completed',
            'exercise_started',
            'exercise_completed',
            'calculator_saved',
            'rating_submitted',
            'comment_created',
            'comment_liked',
            'follow_accepted',
            'highfive_sent',
            'water_added',
            'steps_logged',
            'meal_photo_uploaded',
            'circle_joined',
            'circle_created'
        ];
        
        return in_array($value, $allowed, true);
    }
}
