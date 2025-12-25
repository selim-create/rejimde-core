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
            'permission_callback' => [$this, 'check_auth'],
            'args' => [
                'event_type' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Type of event'
                ],
                'entity_type' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Type of entity (e.g., blog, diet, exercise)'
                ],
                'entity_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'ID of entity'
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
                    'description' => 'Source of event (web, mobile, etc.)'
                ]
            ]
        ]);
    }
    
    /**
     * POST /rejimde/v1/events
     * Submit an event
     */
    public function submit_event(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        
        $event_type = $request->get_param('event_type');
        $entity_type = $request->get_param('entity_type');
        $entity_id = $request->get_param('entity_id');
        $metadata = $request->get_param('metadata') ?: [];
        $source = $request->get_param('source') ?: 'web';
        
        // Validate event_type
        if (empty($event_type)) {
            return $this->error('event_type is required', 400);
        }
        
        // Check if user can earn points
        if (!$this->can_earn_points($user_id)) {
            return $this->error('Uzmanlar puan kazanamaz', 403);
        }
        
        // Process event through EventService with error handling
        try {
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
            error_log('EventController::submit_event exception: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return $this->error('Bir hata oluştu. Lütfen tekrar deneyin.', 500);
        }
    }
}
