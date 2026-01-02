<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use Rejimde\Services\ClientService;
use Rejimde\Services\InboxService;
use Rejimde\Services\CalendarService;
use Rejimde\Services\FinanceService;

/**
 * Pro Dashboard Controller
 * 
 * Aggregates data from all Pro modules for the dashboard
 */
class ProDashboardController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'pro/dashboard';

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_dashboard'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);
    }

    public function get_dashboard(WP_REST_Request $request) {
        try {
            $expertId = get_current_user_id();
            
            // Initialize services
            $clientService = new ClientService();
            $inboxService = new InboxService();
            $calendarService = new CalendarService();
            $financeService = new FinanceService();
            
            // Get client summary
            $clientsResult = $clientService->getClients($expertId, ['limit' => 1]);
            $clientsMeta = $clientsResult['meta'] ?? ['total' => 0, 'active' => 0, 'pending' => 0];
            
            // Get inbox summary
            $unreadCount = $inboxService->getUnreadCount($expertId);
            
            // Get calendar summary
            $today = date('Y-m-d');
            $weekEnd = date('Y-m-d', strtotime('+7 days'));
            $todayAppointments = $calendarService->getAppointments($expertId, $today, $today, 'confirmed');
            $weekAppointments = $calendarService->getAppointments($expertId, $today, $weekEnd);
            $pendingRequests = $calendarService->getRequests($expertId, 'pending');
            
            // Get finance summary
            $financeDashboard = $financeService->getDashboard($expertId, 'this_month');
            
            // Get at-risk clients count
            global $wpdb;
            $table_relationships = $wpdb->prefix . 'rejimde_relationships';
            
            // Suppress database errors to prevent HTML output in JSON response
            $wpdb->suppress_errors(true);
            
            // Check if risk_status column exists
            $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_relationships LIKE 'risk_status'");
            
            $atRiskCount = 0;
            if ($column_exists) {
                $atRiskCount = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_relationships 
                     WHERE expert_id = %d AND status = 'active' 
                     AND (risk_status = 'warning' OR risk_status = 'danger')",
                    $expertId
                )) ?: 0;
            }
            
            // Re-enable error reporting (restore default)
            $wpdb->suppress_errors(false);
            
            return new WP_REST_Response([
                'status' => 'success',
                'data' => [
                    'clients' => [
                        'total' => $clientsMeta['total'] ?? 0,
                        'active' => $clientsMeta['active'] ?? 0,
                        'pending' => $clientsMeta['pending'] ?? 0,
                        'at_risk' => (int) $atRiskCount
                    ],
                    'inbox' => [
                        'unread_count' => $unreadCount
                    ],
                    'calendar' => [
                        'today_appointments' => count($todayAppointments),
                        'pending_requests' => $pendingRequests['meta']['pending'] ?? 0,
                        'this_week_count' => count($weekAppointments)
                    ],
                    'finance' => [
                        'month_revenue' => $financeDashboard['summary']['total_revenue'] ?? 0,
                        'pending_payments' => $financeDashboard['summary']['total_pending'] ?? 0,
                        'overdue_payments' => $financeDashboard['summary']['total_overdue'] ?? 0
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            error_log('ProDashboard Error: ' . $e->getMessage());
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Failed to load dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
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
