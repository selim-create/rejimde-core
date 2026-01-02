<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use Rejimde\Services\CalendarService;
use Rejimde\Services\NotificationService;

/**
 * Calendar Controller
 * 
 * Handles calendar and appointment endpoints
 */
class CalendarController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'pro/calendar';
    private $calendarService;
    private $notificationService;

    public function __construct() {
        $this->calendarService = new CalendarService();
        $this->notificationService = new NotificationService();
    }

    public function register_routes() {
        // Expert Endpoints

        // GET /pro/calendar - Get appointments
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_calendar'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/calendar/appointments - Get appointments with filters
        register_rest_route($this->namespace, '/' . $this->base . '/appointments', [
            'methods' => 'GET',
            'callback' => [$this, 'get_appointments'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/calendar/availability - Get availability template
        register_rest_route($this->namespace, '/' . $this->base . '/availability', [
            'methods' => 'GET',
            'callback' => [$this, 'get_availability'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/calendar/availability - Update availability template
        register_rest_route($this->namespace, '/' . $this->base . '/availability', [
            'methods' => 'POST',
            'callback' => [$this, 'update_availability'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/calendar/appointments - Create appointment
        register_rest_route($this->namespace, '/' . $this->base . '/appointments', [
            'methods' => 'POST',
            'callback' => [$this, 'create_appointment'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // PATCH /pro/calendar/appointments/{id} - Update appointment
        register_rest_route($this->namespace, '/' . $this->base . '/appointments/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_appointment'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/calendar/appointments/{id}/cancel - Cancel appointment
        register_rest_route($this->namespace, '/' . $this->base . '/appointments/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel_appointment'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/calendar/appointments/{id}/complete - Mark as completed
        register_rest_route($this->namespace, '/' . $this->base . '/appointments/(?P<id>\d+)/complete', [
            'methods' => 'POST',
            'callback' => [$this, 'complete_appointment'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/calendar/appointments/{id}/no-show - Mark as no-show
        register_rest_route($this->namespace, '/' . $this->base . '/appointments/(?P<id>\d+)/no-show', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_no_show'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/calendar/requests - List appointment requests
        register_rest_route($this->namespace, '/' . $this->base . '/requests', [
            'methods' => 'GET',
            'callback' => [$this, 'get_requests'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/calendar/requests/{id}/approve - Approve request
        register_rest_route($this->namespace, '/' . $this->base . '/requests/(?P<id>\d+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approve_request'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/calendar/requests/{id}/reject - Reject request
        register_rest_route($this->namespace, '/' . $this->base . '/requests/(?P<id>\d+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject_request'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/calendar/block - Block time
        register_rest_route($this->namespace, '/' . $this->base . '/block', [
            'methods' => 'POST',
            'callback' => [$this, 'block_time'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // DELETE /pro/calendar/block/{id} - Unblock time
        register_rest_route($this->namespace, '/' . $this->base . '/block/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'unblock_time'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // Client/Public Endpoints

        // GET /experts/{expertId}/availability - Get expert's available slots
        register_rest_route($this->namespace, '/experts/(?P<expertId>\d+)/availability', [
            'methods' => 'GET',
            'callback' => [$this, 'get_expert_availability'],
            'permission_callback' => '__return_true',
        ]);

        // POST /appointments/request - Create appointment request
        register_rest_route($this->namespace, '/appointments/request', [
            'methods' => 'POST',
            'callback' => [$this, 'create_request'],
            'permission_callback' => '__return_true',
        ]);

        // GET /me/appointments - Get user's appointments
        register_rest_route($this->namespace, '/me/appointments', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_appointments'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // POST /me/appointments/{id}/cancel - Cancel own appointment
        register_rest_route($this->namespace, '/me/appointments/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel_my_appointment'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    /**
     * GET /pro/calendar
     */
    public function get_calendar(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        
        $startDate = $request->get_param('start_date');
        $endDate = $request->get_param('end_date');
        $status = $request->get_param('status');
        
        if (!$startDate || !$endDate) {
            return $this->error('start_date and end_date are required', 400);
        }
        
        $appointments = $this->calendarService->getAppointments($expertId, $startDate, $endDate, $status);
        $blockedTimes = $this->calendarService->getBlockedTimes($expertId, $startDate, $endDate);
        
        return $this->success([
            'appointments' => $appointments,
            'blocked_times' => $blockedTimes
        ]);
    }

    /**
     * GET /pro/calendar/appointments
     */
    public function get_appointments(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        
        $startDate = $request->get_param('start_date');
        $endDate = $request->get_param('end_date');
        $status = $request->get_param('status');
        $limit = $request->get_param('limit');
        
        if (!$startDate || !$endDate) {
            return $this->error('start_date and end_date are required', 400);
        }
        
        $appointments = $this->calendarService->getAppointments($expertId, $startDate, $endDate, $status);
        
        // Apply limit if specified
        if ($limit && is_numeric($limit)) {
            $appointments = array_slice($appointments, 0, (int) $limit);
        }
        
        return $this->success($appointments);
    }

    /**
     * GET /pro/calendar/availability
     */
    public function get_availability(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        $availability = $this->calendarService->getAvailability($expertId);
        
        return $this->success($availability);
    }

    /**
     * POST /pro/calendar/availability
     */
    public function update_availability(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        $data = $request->get_json_params();
        
        $result = $this->calendarService->updateAvailability($expertId, $data);
        
        if ($result) {
            return $this->success(['message' => 'Availability updated successfully']);
        }
        
        return $this->error('Failed to update availability', 500);
    }

    /**
     * POST /pro/calendar/appointments
     */
    public function create_appointment(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        $data = $request->get_json_params();
        
        $result = $this->calendarService->createAppointment($expertId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        // Send notification to client
        if (!empty($data['client_id'])) {
            $this->notificationService->create((int) $data['client_id'], 'appointment_created', [
                'actor_id' => $expertId,
                'entity_type' => 'appointment',
                'entity_id' => $result
            ]);
        }
        
        return $this->success([
            'id' => $result,
            'message' => 'Appointment created successfully'
        ], 'Appointment created', 201);
    }

    /**
     * PATCH /pro/calendar/appointments/{id}
     */
    public function update_appointment(WP_REST_Request $request) {
        $appointmentId = (int) $request->get_param('id');
        $data = $request->get_json_params();
        
        $result = $this->calendarService->updateAppointment($appointmentId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        if ($result) {
            return $this->success(['message' => 'Appointment updated successfully']);
        }
        
        return $this->error('Failed to update appointment', 500);
    }

    /**
     * POST /pro/calendar/appointments/{id}/cancel
     */
    public function cancel_appointment(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        $appointmentId = (int) $request->get_param('id');
        $data = $request->get_json_params();
        $reason = $data['reason'] ?? null;
        
        // Get appointment to send notification to client
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointments';
        $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $appointmentId), ARRAY_A);
        
        $result = $this->calendarService->cancelAppointment($appointmentId, $expertId, $reason);
        
        if ($result) {
            // Send notification to client
            if ($appointment && $appointment['client_id']) {
                $this->notificationService->create((int) $appointment['client_id'], 'appointment_cancelled', [
                    'actor_id' => $expertId,
                    'entity_type' => 'appointment',
                    'entity_id' => $appointmentId
                ]);
            }
            
            return $this->success(['message' => 'Appointment cancelled successfully']);
        }
        
        return $this->error('Failed to cancel appointment', 500);
    }

    /**
     * POST /pro/calendar/appointments/{id}/complete
     */
    public function complete_appointment(WP_REST_Request $request) {
        $appointmentId = (int) $request->get_param('id');
        
        $result = $this->calendarService->completeAppointment($appointmentId);
        
        if ($result) {
            return $this->success(['message' => 'Appointment marked as completed']);
        }
        
        return $this->error('Failed to update appointment', 500);
    }

    /**
     * POST /pro/calendar/appointments/{id}/no-show
     */
    public function mark_no_show(WP_REST_Request $request) {
        $appointmentId = (int) $request->get_param('id');
        
        $result = $this->calendarService->markNoShow($appointmentId);
        
        if ($result) {
            return $this->success(['message' => 'Appointment marked as no-show']);
        }
        
        return $this->error('Failed to update appointment', 500);
    }

    /**
     * GET /pro/calendar/requests
     */
    public function get_requests(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        $status = $request->get_param('status');
        
        $result = $this->calendarService->getRequests($expertId, $status);
        
        return $this->success($result['data'], 'Success', 200, $result['meta']);
    }

    /**
     * POST /pro/calendar/requests/{id}/approve
     */
    public function approve_request(WP_REST_Request $request) {
        $requestId = (int) $request->get_param('id');
        $data = $request->get_json_params();
        
        // Get request to send notification
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointment_requests';
        $appointmentRequest = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $requestId), ARRAY_A);
        
        $result = $this->calendarService->approveRequest($requestId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        // Send notification to requester if member
        if ($appointmentRequest && $appointmentRequest['requester_id']) {
            $this->notificationService->create((int) $appointmentRequest['requester_id'], 'appointment_approved', [
                'actor_id' => get_current_user_id(),
                'entity_type' => 'appointment',
                'entity_id' => $result
            ]);
        }
        
        return $this->success([
            'appointment_id' => $result,
            'message' => 'Request approved and appointment created'
        ]);
    }

    /**
     * POST /pro/calendar/requests/{id}/reject
     */
    public function reject_request(WP_REST_Request $request) {
        $requestId = (int) $request->get_param('id');
        $data = $request->get_json_params();
        $reason = $data['reason'] ?? null;
        
        // Get request to send notification
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointment_requests';
        $appointmentRequest = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $requestId), ARRAY_A);
        
        $result = $this->calendarService->rejectRequest($requestId, $reason);
        
        if ($result) {
            // Send notification to requester if member
            if ($appointmentRequest && $appointmentRequest['requester_id']) {
                $this->notificationService->create((int) $appointmentRequest['requester_id'], 'appointment_rejected', [
                    'actor_id' => get_current_user_id(),
                    'entity_type' => 'appointment_request',
                    'entity_id' => $requestId
                ]);
            }
            
            return $this->success(['message' => 'Request rejected']);
        }
        
        return $this->error('Failed to reject request', 500);
    }

    /**
     * POST /pro/calendar/block
     */
    public function block_time(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        $data = $request->get_json_params();
        
        $result = $this->calendarService->blockTime($expertId, $data);
        
        if ($result) {
            return $this->success([
                'id' => $result,
                'message' => 'Time blocked successfully'
            ], 'Time blocked', 201);
        }
        
        return $this->error('Failed to block time', 500);
    }

    /**
     * DELETE /pro/calendar/block/{id}
     */
    public function unblock_time(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        $blockId = (int) $request->get_param('id');
        
        $result = $this->calendarService->unblockTime($blockId, $expertId);
        
        if ($result) {
            return $this->success(['message' => 'Time unblocked successfully']);
        }
        
        return $this->error('Failed to unblock time', 500);
    }

    /**
     * GET /experts/{expertId}/availability
     */
    public function get_expert_availability(WP_REST_Request $request) {
        $expertId = (int) $request->get_param('expertId');
        
        // Get expert data
        $expert = get_userdata($expertId);
        if (!$expert) {
            return $this->error('Expert not found', 404);
        }
        
        // Get date or date range
        $date = $request->get_param('date');
        $startDate = $request->get_param('start_date');
        $endDate = $request->get_param('end_date');
        
        $availableSlots = [];
        $slotDuration = 60;
        
        if ($date) {
            // Single date
            $slots = $this->calendarService->getAvailableSlots($expertId, $date);
            $availableSlots[$date] = $slots;
        } elseif ($startDate && $endDate) {
            // Date range
            $availableSlots = $this->calendarService->getAvailableSlotsRange($expertId, $startDate, $endDate);
        } else {
            return $this->error('date or start_date/end_date required', 400);
        }
        
        // Get slot duration from availability
        $availability = $this->calendarService->getAvailability($expertId);
        $slotDuration = $availability['slot_duration'] ?? 60;
        
        return $this->success([
            'expert' => [
                'id' => $expertId,
                'name' => $expert->display_name,
                'avatar' => get_user_meta($expertId, 'avatar_url', true) ?: 'https://placehold.co/150'
            ],
            'available_slots' => $availableSlots,
            'slot_duration' => $slotDuration
        ]);
    }

    /**
     * POST /appointments/request
     */
    public function create_request(WP_REST_Request $request) {
        $data = $request->get_json_params();
        
        $result = $this->calendarService->createRequest($data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        // Send notification to expert
        if (!empty($data['expert_id'])) {
            $this->notificationService->create((int) $data['expert_id'], 'appointment_request_received', [
                'actor_id' => get_current_user_id() ?: null,
                'entity_type' => 'appointment_request',
                'entity_id' => $result
            ]);
        }
        
        return $this->success([
            'id' => $result,
            'message' => 'Appointment request created successfully'
        ], 'Request created', 201);
    }

    /**
     * GET /me/appointments
     */
    public function get_my_appointments(WP_REST_Request $request) {
        $userId = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointments';
        
        $status = $request->get_param('status');
        
        $query = "SELECT * FROM {$table} WHERE client_id = %d";
        $params = [$userId];
        
        if ($status && $status !== 'all') {
            $query .= " AND status = %s";
            $params[] = $status;
        }
        
        $query .= " ORDER BY appointment_date DESC, start_time DESC";
        
        $appointments = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        
        $formatted = [];
        foreach ($appointments as $apt) {
            $expert = get_userdata((int) $apt['expert_id']);
            
            $formatted[] = [
                'id' => (int) $apt['id'],
                'expert' => [
                    'id' => (int) $apt['expert_id'],
                    'name' => $expert ? $expert->display_name : 'Unknown',
                    'avatar' => $expert ? (get_user_meta((int) $apt['expert_id'], 'avatar_url', true) ?: 'https://placehold.co/150') : 'https://placehold.co/150'
                ],
                'title' => $apt['title'],
                'description' => $apt['description'],
                'date' => $apt['appointment_date'],
                'start_time' => substr($apt['start_time'], 0, 5),
                'end_time' => substr($apt['end_time'], 0, 5),
                'duration' => (int) $apt['duration'],
                'status' => $apt['status'],
                'type' => $apt['type'],
                'location' => $apt['location'],
                'meeting_link' => $apt['meeting_link'],
                'notes' => $apt['notes']
            ];
        }
        
        return $this->success($formatted);
    }

    /**
     * POST /me/appointments/{id}/cancel
     */
    public function cancel_my_appointment(WP_REST_Request $request) {
        $userId = get_current_user_id();
        $appointmentId = (int) $request->get_param('id');
        $data = $request->get_json_params();
        $reason = $data['reason'] ?? null;
        
        // Verify appointment belongs to user
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointments';
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND client_id = %d",
            $appointmentId, $userId
        ), ARRAY_A);
        
        if (!$appointment) {
            return $this->error('Appointment not found', 404);
        }
        
        $result = $this->calendarService->cancelAppointment($appointmentId, $userId, $reason);
        
        if ($result) {
            // Send notification to expert
            if ($appointment['expert_id']) {
                $this->notificationService->create((int) $appointment['expert_id'], 'appointment_cancelled', [
                    'actor_id' => $userId,
                    'entity_type' => 'appointment',
                    'entity_id' => $appointmentId
                ]);
            }
            
            return $this->success(['message' => 'Appointment cancelled successfully']);
        }
        
        return $this->error('Failed to cancel appointment', 500);
    }

    // Helper methods

    protected function success($data = null, $message = 'Success', $code = 200, $meta = null) {
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ];
        
        if ($meta) {
            $response['meta'] = $meta;
        }
        
        return new WP_REST_Response($response, $code);
    }

    protected function error($message = 'Error', $code = 400, $data = null) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => $message,
            'error_data' => $data
        ], $code);
    }

    public function check_auth() {
        return is_user_logged_in();
    }

    public function check_expert_auth(): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('rejimde_pro', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles);
    }
}
