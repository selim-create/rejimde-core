<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use Rejimde\Services\FinanceService;

/**
 * Finance Controller
 * 
 * Handles finance endpoints for income tracking and payments
 */
class FinanceController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'pro/finance';
    private $financeService;

    public function __construct() {
        $this->financeService = new FinanceService();
    }

    public function register_routes() {
        // Dashboard
        register_rest_route($this->namespace, '/' . $this->base . '/dashboard', [
            'methods' => 'GET',
            'callback' => [$this, 'get_dashboard'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // Payments
        register_rest_route($this->namespace, '/' . $this->base . '/payments', [
            'methods' => 'GET',
            'callback' => [$this, 'get_payments'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/payments', [
            'methods' => 'POST',
            'callback' => [$this, 'create_payment'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/payments/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_payment'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/payments/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_payment'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/payments/(?P<id>\d+)/mark-paid', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_paid'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/payments/(?P<id>\d+)/partial', [
            'methods' => 'POST',
            'callback' => [$this, 'record_partial'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // Services
        register_rest_route($this->namespace, '/' . $this->base . '/services', [
            'methods' => 'GET',
            'callback' => [$this, 'get_services'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/services', [
            'methods' => 'POST',
            'callback' => [$this, 'create_service'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/services/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_service'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/services/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_service'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // Reports
        register_rest_route($this->namespace, '/' . $this->base . '/reports/monthly', [
            'methods' => 'GET',
            'callback' => [$this, 'get_monthly_report'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/reports/yearly', [
            'methods' => 'GET',
            'callback' => [$this, 'get_yearly_report'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/export', [
            'methods' => 'GET',
            'callback' => [$this, 'export_data'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);
    }

    /**
     * Get dashboard data
     */
    public function get_dashboard(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $period = $request->get_param('period') ?? 'this_month';
        $startDate = $request->get_param('start_date');
        $endDate = $request->get_param('end_date');
        
        $data = $this->financeService->getDashboard($expertId, $period, $startDate, $endDate);
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    /**
     * Get payments list
     */
    public function get_payments(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $filters = [
            'status' => $request->get_param('status') ?? 'all',
            'client_id' => $request->get_param('client_id'),
            'start_date' => $request->get_param('start_date'),
            'end_date' => $request->get_param('end_date'),
            'limit' => $request->get_param('limit') ?? 30,
            'offset' => $request->get_param('offset') ?? 0,
        ];
        
        $result = $this->financeService->getPayments($expertId, $filters);
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $result['data'],
            'meta' => $result['meta']
        ], 200);
    }

    /**
     * Create payment
     */
    public function create_payment(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $data = [
            'client_id' => $request->get_param('client_id'),
            'relationship_id' => $request->get_param('relationship_id'),
            'package_id' => $request->get_param('package_id'),
            'service_id' => $request->get_param('service_id'),
            'amount' => $request->get_param('amount'),
            'currency' => $request->get_param('currency') ?? 'TRY',
            'payment_method' => $request->get_param('payment_method') ?? 'cash',
            'payment_date' => $request->get_param('payment_date'),
            'due_date' => $request->get_param('due_date'),
            'status' => $request->get_param('status') ?? 'pending',
            'paid_amount' => $request->get_param('paid_amount') ?? 0,
            'description' => $request->get_param('description'),
            'notes' => $request->get_param('notes'),
        ];
        
        $result = $this->financeService->createPayment($expertId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => $result['error']
            ], 400);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => ['id' => $result],
            'message' => 'Payment created successfully'
        ], 201);
    }

    /**
     * Update payment
     */
    public function update_payment(WP_REST_Request $request): WP_REST_Response {
        $paymentId = (int) $request->get_param('id');
        $expertId = get_current_user_id();
        
        // Verify ownership
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_payments WHERE id = %d AND expert_id = %d",
            $paymentId,
            $expertId
        ));
        
        if (!$payment) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Payment not found or access denied'
            ], 404);
        }
        
        // Get update data from request
        $data = [];
        $allowedFields = ['client_id', 'service_id', 'amount', 'currency', 'payment_method', 
                          'payment_date', 'due_date', 'status', 'paid_amount', 'description', 'notes'];
        
        foreach ($allowedFields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }
        
        $result = $this->financeService->updatePayment($paymentId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => $result['error']
            ], 400);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Payment updated successfully'
        ], 200);
    }

    /**
     * Delete payment
     */
    public function delete_payment(WP_REST_Request $request): WP_REST_Response {
        $paymentId = (int) $request->get_param('id');
        $expertId = get_current_user_id();
        
        $result = $this->financeService->deletePayment($paymentId, $expertId);
        
        if (!$result) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Payment not found or access denied'
            ], 404);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Payment deleted successfully'
        ], 200);
    }

    /**
     * Mark payment as paid
     */
    public function mark_paid(WP_REST_Request $request): WP_REST_Response {
        $paymentId = (int) $request->get_param('id');
        
        $data = [
            'paid_amount' => $request->get_param('paid_amount'),
            'payment_method' => $request->get_param('payment_method'),
            'payment_date' => $request->get_param('payment_date'),
        ];
        
        $result = $this->financeService->markAsPaid($paymentId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => $result['error']
            ], 400);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Payment marked as paid'
        ], 200);
    }

    /**
     * Record partial payment
     */
    public function record_partial(WP_REST_Request $request): WP_REST_Response {
        $paymentId = (int) $request->get_param('id');
        $amount = (float) $request->get_param('amount');
        $method = $request->get_param('payment_method') ?? 'cash';
        
        if ($amount <= 0) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Amount must be greater than 0'
            ], 400);
        }
        
        $result = $this->financeService->recordPartialPayment($paymentId, $amount, $method);
        
        if (is_array($result) && isset($result['error'])) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => $result['error']
            ], 400);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Partial payment recorded'
        ], 200);
    }

    /**
     * Get services list
     */
    public function get_services(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $data = $this->financeService->getServices($expertId);
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    /**
     * Create service
     */
    public function create_service(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $data = [
            'name' => $request->get_param('name'),
            'description' => $request->get_param('description'),
            'type' => $request->get_param('type') ?? 'session',
            'price' => $request->get_param('price'),
            'currency' => $request->get_param('currency') ?? 'TRY',
            'duration_minutes' => $request->get_param('duration_minutes') ?? 60,
            'session_count' => $request->get_param('session_count'),
            'validity_days' => $request->get_param('validity_days'),
            'is_active' => $request->get_param('is_active') ?? true,
            'is_featured' => $request->get_param('is_featured') ?? false,
            'color' => $request->get_param('color') ?? '#3B82F6',
            'sort_order' => $request->get_param('sort_order') ?? 0,
        ];
        
        $result = $this->financeService->createService($expertId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => $result['error']
            ], 400);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => ['id' => $result],
            'message' => 'Service created successfully'
        ], 201);
    }

    /**
     * Update service
     */
    public function update_service(WP_REST_Request $request): WP_REST_Response {
        $serviceId = (int) $request->get_param('id');
        $expertId = get_current_user_id();
        
        // Verify ownership
        global $wpdb;
        $table_services = $wpdb->prefix . 'rejimde_services';
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_services WHERE id = %d AND expert_id = %d",
            $serviceId,
            $expertId
        ));
        
        if (!$service) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Service not found or access denied'
            ], 404);
        }
        
        // Get update data from request
        $data = [];
        $allowedFields = ['name', 'description', 'type', 'price', 'currency', 'duration_minutes',
                          'session_count', 'validity_days', 'is_active', 'is_featured', 'color', 'sort_order'];
        
        foreach ($allowedFields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }
        
        $result = $this->financeService->updateService($serviceId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => $result['error']
            ], 400);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Service updated successfully'
        ], 200);
    }

    /**
     * Delete service (soft delete)
     */
    public function delete_service(WP_REST_Request $request): WP_REST_Response {
        $serviceId = (int) $request->get_param('id');
        $expertId = get_current_user_id();
        
        $result = $this->financeService->deleteService($serviceId, $expertId);
        
        if (!$result) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Service not found or access denied'
            ], 404);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Service deactivated successfully'
        ], 200);
    }

    /**
     * Get monthly report
     */
    public function get_monthly_report(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $year = (int) ($request->get_param('year') ?? date('Y'));
        $month = (int) ($request->get_param('month') ?? date('n'));
        
        $data = $this->financeService->getMonthlyReport($expertId, $year, $month);
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    /**
     * Get yearly report
     */
    public function get_yearly_report(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $year = (int) ($request->get_param('year') ?? date('Y'));
        
        $data = $this->financeService->getYearlyReport($expertId, $year);
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    /**
     * Export data
     * 
     * Note: Currently returns JSON format only. CSV/Excel export to be implemented in future version.
     */
    public function export_data(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $format = $request->get_param('format') ?? 'json';
        $type = $request->get_param('type') ?? 'payments';
        $startDate = $request->get_param('start_date');
        $endDate = $request->get_param('end_date');
        
        // Get data based on type
        if ($type === 'payments') {
            $filters = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'limit' => 1000
            ];
            $result = $this->financeService->getPayments($expertId, $filters);
            $data = $result['data'];
        } elseif ($type === 'services') {
            $data = $this->financeService->getServices($expertId);
        } else {
            $dateRange = $startDate && $endDate ? [$startDate, $endDate] : [date('Y-m-01'), date('Y-m-t')];
            $data = $this->financeService->calculateTotals($expertId, $dateRange[0], $dateRange[1]);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $data,
            'format' => $format,
            'type' => $type,
            'note' => 'JSON format only. CSV/Excel export coming soon.'
        ], 200);
    }

    /**
     * Check if user is authenticated as expert
     */
    public function check_expert_auth(WP_REST_Request $request): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $userId = get_current_user_id();
        $isProfessional = get_user_meta($userId, 'is_professional', true);
        
        return (bool) $isProfessional;
    }
}
