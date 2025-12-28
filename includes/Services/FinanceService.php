<?php
namespace Rejimde\Services;

/**
 * Finance Service
 * 
 * Handles business logic for income tracking, payments, and services
 */
class FinanceService {
    
    /**
     * Get dashboard data for expert
     * 
     * @param int $expertId Expert user ID
     * @param string $period Period type: this_month, last_month, this_year, custom
     * @param string|null $startDate Start date for custom period (YYYY-MM-DD)
     * @param string|null $endDate End date for custom period (YYYY-MM-DD)
     * @return array Dashboard data
     */
    public function getDashboard(int $expertId, string $period, ?string $startDate = null, ?string $endDate = null): array {
        global $wpdb;
        
        // Calculate date range
        $dateRange = $this->calculateDateRange($period, $startDate, $endDate);
        
        // Get summary stats
        $summary = $this->calculateTotals($expertId, $dateRange['start'], $dateRange['end']);
        
        // Get monthly comparison
        $monthlyComparison = $this->getMonthlyComparison($expertId);
        
        // Get revenue by service
        $revenueByService = $this->getRevenueByService($expertId, $dateRange['start'], $dateRange['end']);
        
        // Get revenue chart data
        $revenueChart = $this->getRevenueChart($expertId, $dateRange['start'], $dateRange['end']);
        
        // Get recent payments
        $recentPayments = $this->getPayments($expertId, [
            'limit' => 5,
            'order_by' => 'payment_date',
            'order' => 'DESC'
        ]);
        
        return [
            'summary' => $summary,
            'monthly_comparison' => $monthlyComparison,
            'revenue_by_service' => $revenueByService,
            'revenue_chart' => $revenueChart,
            'recent_payments' => $recentPayments['data'] ?? []
        ];
    }
    
    /**
     * Get payments list with filters
     * 
     * @param int $expertId Expert user ID
     * @param array $filters Filters (status, client_id, start_date, end_date, limit, offset)
     * @return array
     */
    public function getPayments(int $expertId, array $filters = []): array {
        global $wpdb;
        
        try {
            $table_payments = $wpdb->prefix . 'rejimde_payments';
            $table_services = $wpdb->prefix . 'rejimde_services';
            
            // Build query with LEFT JOIN to get service name
            $query = "SELECT p.*, s.name as service_name FROM $table_payments p 
                      LEFT JOIN $table_services s ON p.service_id = s.id 
                      WHERE p.expert_id = %d";
            $params = [$expertId];
            
            // Filter by status
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $query .= " AND p.status = %s";
                $params[] = $filters['status'];
            }
            
            // Filter by client
            if (!empty($filters['client_id'])) {
                $query .= " AND p.client_id = %d";
                $params[] = $filters['client_id'];
            }
            
            // Filter by date range
            if (!empty($filters['start_date'])) {
                $query .= " AND p.payment_date >= %s";
                $params[] = $filters['start_date'];
            }
            if (!empty($filters['end_date'])) {
                $query .= " AND p.payment_date <= %s";
                $params[] = $filters['end_date'];
            }
            
            // Order by
            $orderBy = $filters['order_by'] ?? 'payment_date';
            $order = $filters['order'] ?? 'DESC';
            $query .= " ORDER BY p.$orderBy $order";
            
            // Pagination
            $limit = $filters['limit'] ?? 30;
            $offset = $filters['offset'] ?? 0;
            $query .= " LIMIT %d OFFSET %d";
            $params[] = $limit;
            $params[] = $offset;
            
            $payments = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
            
            // Handle database errors
            if ($wpdb->last_error) {
                error_log('Rejimde Finance: getPayments DB error occurred');
                return [
                    'data' => [],
                    'meta' => [
                        'total' => 0,
                        'total_amount' => 0,
                        'paid_amount' => 0,
                        'pending_amount' => 0
                    ]
                ];
            }
            
            // Ensure payments is an array
            if (!is_array($payments)) {
                $payments = [];
            }
            
            // Format payments
            $data = [];
            foreach ($payments as $payment) {
                $data[] = $this->formatPayment($payment);
            }
            
            // Get totals
            $totalsQuery = "SELECT 
                COUNT(*) as total,
                SUM(amount) as total_amount,
                SUM(paid_amount) as paid_amount
            FROM $table_payments 
            WHERE expert_id = %d";
            $totalsParams = [$expertId];
            
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $totalsQuery .= " AND status = %s";
                $totalsParams[] = $filters['status'];
            }
            if (!empty($filters['start_date'])) {
                $totalsQuery .= " AND payment_date >= %s";
                $totalsParams[] = $filters['start_date'];
            }
            if (!empty($filters['end_date'])) {
                $totalsQuery .= " AND payment_date <= %s";
                $totalsParams[] = $filters['end_date'];
            }
            
            $totals = $wpdb->get_row($wpdb->prepare($totalsQuery, ...$totalsParams), ARRAY_A);
            
            // Ensure totals has valid data
            if (!is_array($totals)) {
                $totals = ['total' => 0, 'total_amount' => 0, 'paid_amount' => 0];
            }
            
            return [
                'data' => $data,
                'meta' => [
                    'total' => (int) ($totals['total'] ?? 0),
                    'total_amount' => (float) ($totals['total_amount'] ?? 0),
                    'paid_amount' => (float) ($totals['paid_amount'] ?? 0),
                    'pending_amount' => (float) (($totals['total_amount'] ?? 0) - ($totals['paid_amount'] ?? 0))
                ]
            ];
            
        } catch (\Exception $e) {
            error_log('Rejimde Finance: getPayments exception - ' . $e->getMessage());
            return [
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'pending_amount' => 0
                ]
            ];
        }
    }
    
    /**
     * Create new payment
     * 
     * @param int $expertId Expert user ID
     * @param array $data Payment data
     * @return int|array Payment ID or error array
     */
    public function createPayment(int $expertId, array $data): int|array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        // Validate required fields
        if (empty($data['client_id'])) {
            return ['error' => 'client_id is required'];
        }
        if (!isset($data['amount'])) {
            return ['error' => 'amount is required'];
        }
        
        // Prepare data
        $paymentData = [
            'expert_id' => $expertId,
            'client_id' => (int) $data['client_id'],
            'relationship_id' => !empty($data['relationship_id']) ? (int) $data['relationship_id'] : null,
            'package_id' => !empty($data['package_id']) ? (int) $data['package_id'] : null,
            'service_id' => !empty($data['service_id']) ? (int) $data['service_id'] : null,
            'amount' => (float) $data['amount'],
            'currency' => $data['currency'] ?? 'TRY',
            'payment_method' => $data['payment_method'] ?? 'cash',
            'payment_date' => $data['payment_date'] ?? date('Y-m-d'),
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'paid_amount' => (float) ($data['paid_amount'] ?? 0),
            'description' => $data['description'] ?? null,
            'receipt_url' => $data['receipt_url'] ?? null,
            'notes' => $data['notes'] ?? null
        ];
        
        // Insert payment
        $result = $wpdb->insert($table_payments, $paymentData);
        
        if ($result === false) {
            return ['error' => 'Failed to create payment: ' . $wpdb->last_error];
        }
        
        $paymentId = $wpdb->insert_id;
        
        if (!$paymentId) {
            return ['error' => 'Failed to create payment'];
        }
        
        return $paymentId;
    }
    
    /**
     * Update payment
     * 
     * @param int $paymentId Payment ID
     * @param array $data Payment data to update
     * @return bool|array True on success, error array on failure
     */
    public function updatePayment(int $paymentId, array $data): bool|array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        // Remove fields that shouldn't be updated
        unset($data['id'], $data['expert_id'], $data['created_at']);
        
        if (empty($data)) {
            return ['error' => 'No data to update'];
        }
        
        $result = $wpdb->update(
            $table_payments,
            $data,
            ['id' => $paymentId]
        );
        
        if ($result === false) {
            return ['error' => 'Failed to update payment'];
        }
        
        return true;
    }
    
    /**
     * Delete payment
     * 
     * @param int $paymentId Payment ID
     * @param int $expertId Expert user ID (for security check)
     * @return bool
     */
    public function deletePayment(int $paymentId, int $expertId): bool {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        // Security check - verify ownership
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_payments WHERE id = %d AND expert_id = %d",
            $paymentId,
            $expertId
        ));
        
        if (!$payment) {
            return false;
        }
        
        $result = $wpdb->delete($table_payments, ['id' => $paymentId]);
        
        return $result !== false;
    }
    
    /**
     * Mark payment as paid
     * 
     * @param int $paymentId Payment ID
     * @param array $data Payment data (paid_amount, payment_method, payment_date)
     * @return bool|array
     */
    public function markAsPaid(int $paymentId, array $data): bool|array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        // Get current payment
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_payments WHERE id = %d",
            $paymentId
        ), ARRAY_A);
        
        if (!$payment) {
            return ['error' => 'Payment not found'];
        }
        
        $updateData = [
            'status' => 'paid',
            'paid_amount' => $data['paid_amount'] ?? $payment['amount'],
            'payment_date' => $data['payment_date'] ?? date('Y-m-d')
        ];
        
        if (!empty($data['payment_method'])) {
            $updateData['payment_method'] = $data['payment_method'];
        }
        
        $result = $wpdb->update(
            $table_payments,
            $updateData,
            ['id' => $paymentId]
        );
        
        if ($result === false) {
            return ['error' => 'Failed to update payment'];
        }
        
        return true;
    }
    
    /**
     * Record partial payment
     * 
     * @param int $paymentId Payment ID
     * @param float $amount Partial amount
     * @param string $method Payment method
     * @return bool|array
     */
    public function recordPartialPayment(int $paymentId, float $amount, string $method): bool|array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        // Get current payment
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_payments WHERE id = %d",
            $paymentId
        ), ARRAY_A);
        
        if (!$payment) {
            return ['error' => 'Payment not found'];
        }
        
        $newPaidAmount = (float) $payment['paid_amount'] + $amount;
        $totalAmount = (float) $payment['amount'];
        
        $status = 'partial';
        if ($newPaidAmount >= $totalAmount) {
            $status = 'paid';
            $newPaidAmount = $totalAmount;
        }
        
        $result = $wpdb->update(
            $table_payments,
            [
                'paid_amount' => $newPaidAmount,
                'status' => $status,
                'payment_method' => $method
            ],
            ['id' => $paymentId]
        );
        
        if ($result === false) {
            return ['error' => 'Failed to record partial payment'];
        }
        
        return true;
    }
    
    /**
     * Get services list
     * 
     * @param int $expertId Expert user ID
     * @return array
     */
    public function getServices(int $expertId): array {
        global $wpdb;
        
        try {
            $table_services = $wpdb->prefix . 'rejimde_services';
            $table_payments = $wpdb->prefix . 'rejimde_payments';
            
            $services = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_services WHERE expert_id = %d ORDER BY sort_order ASC, created_at DESC",
                $expertId
            ), ARRAY_A);
            
            // Handle database errors
            if ($wpdb->last_error) {
                error_log('Rejimde Finance: getServices DB error occurred');
                return [];
            }
            
            // Ensure services is an array
            if (!is_array($services)) {
                return [];
            }
            
            // Get usage counts in a single query
            $usageCounts = [];
            if (!empty($services)) {
                $serviceIds = array_column($services, 'id');
                $placeholders = implode(',', array_fill(0, count($serviceIds), '%d'));
                $usageResults = $wpdb->get_results($wpdb->prepare(
                    "SELECT service_id, COUNT(*) as count FROM $table_payments WHERE service_id IN ($placeholders) GROUP BY service_id",
                    ...$serviceIds
                ), ARRAY_A);
                
                if (is_array($usageResults)) {
                    foreach ($usageResults as $row) {
                        if (isset($row['service_id']) && isset($row['count'])) {
                            $usageCounts[(int) $row['service_id']] = (int) $row['count'];
                        }
                    }
                }
            }
            
            // Format services and add usage count
            foreach ($services as &$service) {
                $service['id'] = (int) ($service['id'] ?? 0);
                $service['expert_id'] = (int) ($service['expert_id'] ?? 0);
                $service['price'] = (float) ($service['price'] ?? 0);
                $service['duration_minutes'] = (int) ($service['duration_minutes'] ?? 60);
                $service['session_count'] = isset($service['session_count']) && $service['session_count'] ? (int) $service['session_count'] : null;
                $service['validity_days'] = isset($service['validity_days']) && $service['validity_days'] ? (int) $service['validity_days'] : null;
                $service['capacity'] = (int) ($service['capacity'] ?? 1);
                $service['is_active'] = (bool) ($service['is_active'] ?? false);
                $service['is_featured'] = (bool) ($service['is_featured'] ?? false);
                $service['sort_order'] = (int) ($service['sort_order'] ?? 0);
                
                // Add usage count from cache
                $service['usage_count'] = $usageCounts[$service['id']] ?? 0;
            }
            
            return $services;
            
        } catch (\Exception $e) {
            error_log('Rejimde Finance: getServices exception - ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create service
     * 
     * @param int $expertId Expert user ID
     * @param array $data Service data
     * @return int|array Service ID or error array
     */
    public function createService(int $expertId, array $data): int|array {
        global $wpdb;
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        // Validate required fields
        if (empty($data['name'])) {
            return ['error' => 'name is required'];
        }
        if (!isset($data['price'])) {
            return ['error' => 'price is required'];
        }
        
        // Prepare data
        $serviceData = [
            'expert_id' => $expertId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'session',
            'price' => (float) $data['price'],
            'currency' => $data['currency'] ?? 'TRY',
            'duration_minutes' => (int) ($data['duration_minutes'] ?? 60),
            'session_count' => !empty($data['session_count']) ? (int) $data['session_count'] : null,
            'validity_days' => !empty($data['validity_days']) ? (int) $data['validity_days'] : null,
            'capacity' => (int) ($data['capacity'] ?? 1),
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'is_featured' => isset($data['is_featured']) ? (int) $data['is_featured'] : 0,
            'color' => $data['color'] ?? '#3B82F6',
            'sort_order' => (int) ($data['sort_order'] ?? 0)
        ];
        
        $result = $wpdb->insert($table_services, $serviceData);
        
        if ($result === false) {
            return ['error' => 'Failed to create service: ' . $wpdb->last_error];
        }
        
        $serviceId = $wpdb->insert_id;
        
        if (!$serviceId) {
            return ['error' => 'Failed to create service'];
        }
        
        return $serviceId;
    }
    
    /**
     * Update service
     * 
     * @param int $serviceId Service ID
     * @param array $data Service data to update
     * @return bool|array
     */
    public function updateService(int $serviceId, array $data): bool|array {
        global $wpdb;
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        // Remove fields that shouldn't be updated
        unset($data['id'], $data['expert_id'], $data['created_at']);
        
        if (empty($data)) {
            return ['error' => 'No data to update'];
        }
        
        $result = $wpdb->update(
            $table_services,
            $data,
            ['id' => $serviceId]
        );
        
        if ($result === false) {
            return ['error' => 'Failed to update service'];
        }
        
        return true;
    }
    
    /**
     * Delete service (soft delete - mark as inactive)
     * 
     * @param int $serviceId Service ID
     * @param int $expertId Expert user ID (for security check)
     * @return bool
     */
    public function deleteService(int $serviceId, int $expertId): bool {
        global $wpdb;
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        // Security check - verify ownership
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_services WHERE id = %d AND expert_id = %d",
            $serviceId,
            $expertId
        ));
        
        if (!$service) {
            return false;
        }
        
        // Soft delete - mark as inactive
        $result = $wpdb->update(
            $table_services,
            ['is_active' => 0],
            ['id' => $serviceId]
        );
        
        return $result !== false;
    }
    
    /**
     * Force delete service (hard delete)
     * 
     * @param int $serviceId Service ID
     * @param int $expertId Expert user ID (for security check)
     * @return bool
     */
    public function forceDeleteService(int $serviceId, int $expertId): bool {
        global $wpdb;
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        // Security check - verify ownership
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_services WHERE id = %d AND expert_id = %d",
            $serviceId,
            $expertId
        ));
        
        if (!$service) {
            return false;
        }
        
        // Hard delete
        $result = $wpdb->delete($table_services, ['id' => (int) $serviceId]);
        
        return $result !== false;
    }
    
    /**
     * Get monthly report
     * 
     * @param int $expertId Expert user ID
     * @param int $year Year
     * @param int $month Month (1-12)
     * @return array
     */
    public function getMonthlyReport(int $expertId, int $year, int $month): array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        $startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        // Summary
        $summary = $this->calculateTotals($expertId, $startDate, $endDate);
        
        // Get session count
        $sessionCount = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_payments WHERE expert_id = %d AND payment_date BETWEEN %s AND %s AND status = 'paid'",
            $expertId, $startDate, $endDate
        ));
        
        // Get unique clients
        $uniqueClients = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT client_id) FROM $table_payments WHERE expert_id = %d AND payment_date BETWEEN %s AND %s AND status = 'paid'",
            $expertId, $startDate, $endDate
        ));
        
        $summary['total_sessions'] = (int) $sessionCount;
        $summary['unique_clients'] = (int) $uniqueClients;
        $summary['average_per_client'] = $uniqueClients > 0 ? $summary['total_revenue'] / $uniqueClients : 0;
        
        // By week
        $byWeek = $this->getRevenueByWeek($expertId, $year, $month);
        
        // By service
        $byService = $this->getRevenueByService($expertId, $startDate, $endDate);
        
        // By payment method
        $byPaymentMethod = $this->getRevenueByPaymentMethod($expertId, $startDate, $endDate);
        
        // Top clients
        $topClients = $this->getTopClients($expertId, $startDate, $endDate);
        
        return [
            'period' => "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT),
            'summary' => $summary,
            'by_week' => $byWeek,
            'by_service' => $byService,
            'by_payment_method' => $byPaymentMethod,
            'top_clients' => $topClients
        ];
    }
    
    /**
     * Get yearly report
     * 
     * @param int $expertId Expert user ID
     * @param int $year Year
     * @return array
     */
    public function getYearlyReport(int $expertId, int $year): array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        $startDate = "$year-01-01";
        $endDate = "$year-12-31";
        
        // Summary
        $summary = $this->calculateTotals($expertId, $startDate, $endDate);
        
        // By month
        $byMonth = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthStart = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
            $monthEnd = date('Y-m-t', strtotime($monthStart));
            
            $monthData = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as count,
                    SUM(paid_amount) as revenue
                FROM $table_payments 
                WHERE expert_id = %d 
                AND payment_date BETWEEN %s AND %s 
                AND status = 'paid'",
                $expertId, $monthStart, $monthEnd
            ), ARRAY_A);
            
            $byMonth[] = [
                'month' => $month,
                'revenue' => (float) ($monthData['revenue'] ?? 0),
                'count' => (int) ($monthData['count'] ?? 0)
            ];
        }
        
        // By service
        $byService = $this->getRevenueByService($expertId, $startDate, $endDate);
        
        return [
            'year' => $year,
            'summary' => $summary,
            'by_month' => $byMonth,
            'by_service' => $byService
        ];
    }
    
    /**
     * Get revenue chart data
     * 
     * @param int $expertId Expert user ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param string $groupBy Group by: day, week, month
     * @return array
     */
    public function getRevenueChart(int $expertId, string $startDate, string $endDate, string $groupBy = 'day'): array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        $dateFormat = match($groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d'
        };
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(payment_date, %s) as period,
                SUM(paid_amount) as amount
            FROM $table_payments 
            WHERE expert_id = %d 
            AND payment_date BETWEEN %s AND %s 
            AND status = 'paid'
            GROUP BY period
            ORDER BY period ASC",
            $dateFormat, $expertId, $startDate, $endDate
        ), ARRAY_A);
        
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'date' => $row['period'],
                'amount' => (float) $row['amount']
            ];
        }
        
        return $data;
    }
    
    /**
     * Calculate totals for a period
     * 
     * @param int $expertId Expert user ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array
     */
    public function calculateTotals(int $expertId, string $startDate, string $endDate): array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN status = 'paid' THEN paid_amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
                SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as total_overdue,
                COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count
            FROM $table_payments 
            WHERE expert_id = %d 
            AND payment_date BETWEEN %s AND %s",
            $expertId, $startDate, $endDate
        ), ARRAY_A);
        
        return [
            'total_revenue' => (float) ($result['total_revenue'] ?? 0),
            'total_pending' => (float) ($result['total_pending'] ?? 0),
            'total_overdue' => (float) ($result['total_overdue'] ?? 0),
            'paid_count' => (int) ($result['paid_count'] ?? 0),
            'pending_count' => (int) ($result['pending_count'] ?? 0),
            'overdue_count' => (int) ($result['overdue_count'] ?? 0)
        ];
    }
    
    /**
     * Get overdue payments
     * 
     * @param int $expertId Expert user ID
     * @return array
     */
    public function getOverduePayments(int $expertId): array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        $payments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_payments 
            WHERE expert_id = %d 
            AND status IN ('pending', 'partial') 
            AND due_date < CURDATE()
            ORDER BY due_date ASC",
            $expertId
        ), ARRAY_A);
        
        // Update status to overdue in bulk
        if (!empty($payments)) {
            $paymentIds = array_column($payments, 'id');
            $placeholders = implode(',', array_fill(0, count($paymentIds), '%d'));
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_payments SET status = 'overdue' WHERE id IN ($placeholders)",
                ...$paymentIds
            ));
        }
        
        $data = [];
        foreach ($payments as $payment) {
            $payment['status'] = 'overdue';
            $data[] = $this->formatPayment($payment);
        }
        
        return $data;
    }
    
    /**
     * Send payment reminder
     * 
     * @param int $paymentId Payment ID
     * @return bool
     */
    public function sendPaymentReminder(int $paymentId): bool {
        // TODO: Implement notification integration
        return true;
    }
    
    // Helper methods
    
    /**
     * Calculate date range based on period
     */
    private function calculateDateRange(string $period, ?string $startDate, ?string $endDate): array {
        $today = date('Y-m-d');
        
        return match($period) {
            'this_month' => [
                'start' => date('Y-m-01'),
                'end' => date('Y-m-t')
            ],
            'last_month' => [
                'start' => date('Y-m-01', strtotime('-1 month')),
                'end' => date('Y-m-t', strtotime('-1 month'))
            ],
            'this_year' => [
                'start' => date('Y-01-01'),
                'end' => date('Y-12-31')
            ],
            'custom' => [
                'start' => $startDate ?? $today,
                'end' => $endDate ?? $today
            ],
            default => [
                'start' => date('Y-m-01'),
                'end' => date('Y-m-t')
            ]
        };
    }
    
    /**
     * Get monthly comparison
     */
    private function getMonthlyComparison(int $expertId): array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        $thisMonthStart = date('Y-m-01');
        $thisMonthEnd = date('Y-m-t');
        $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
        $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
        
        $thisMonth = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(paid_amount) FROM $table_payments WHERE expert_id = %d AND payment_date BETWEEN %s AND %s AND status = 'paid'",
            $expertId, $thisMonthStart, $thisMonthEnd
        )) ?? 0;
        
        $lastMonth = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(paid_amount) FROM $table_payments WHERE expert_id = %d AND payment_date BETWEEN %s AND %s AND status = 'paid'",
            $expertId, $lastMonthStart, $lastMonthEnd
        )) ?? 0;
        
        $changePercent = $lastMonth > 0 ? (($thisMonth - $lastMonth) / $lastMonth) * 100 : 0;
        
        return [
            'current' => (float) $thisMonth,
            'previous' => (float) $lastMonth,
            'change_percent' => round($changePercent, 1)
        ];
    }
    
    /**
     * Get revenue by service
     */
    private function getRevenueByService(int $expertId, string $startDate, string $endDate): array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                p.service_id,
                s.name as service_name,
                SUM(p.paid_amount) as total,
                COUNT(*) as count
            FROM $table_payments p
            LEFT JOIN $table_services s ON p.service_id = s.id
            WHERE p.expert_id = %d 
            AND p.payment_date BETWEEN %s AND %s 
            AND p.status = 'paid'
            GROUP BY p.service_id
            ORDER BY total DESC",
            $expertId, $startDate, $endDate
        ), ARRAY_A);
        
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'service_id' => (int) ($row['service_id'] ?? 0),
                'service_name' => $row['service_name'] ?? 'N/A',
                'total' => (float) $row['total'],
                'count' => (int) $row['count']
            ];
        }
        
        return $data;
    }
    
    /**
     * Get revenue by week
     */
    private function getRevenueByWeek(int $expertId, int $year, int $month): array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        $startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                WEEK(payment_date, 1) - WEEK(DATE_SUB(payment_date, INTERVAL DAYOFMONTH(payment_date)-1 DAY), 1) + 1 as week,
                SUM(paid_amount) as revenue,
                COUNT(*) as sessions
            FROM $table_payments 
            WHERE expert_id = %d 
            AND payment_date BETWEEN %s AND %s 
            AND status = 'paid'
            GROUP BY week
            ORDER BY week ASC",
            $expertId, $startDate, $endDate
        ), ARRAY_A);
        
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'week' => (int) $row['week'],
                'revenue' => (float) $row['revenue'],
                'sessions' => (int) $row['sessions']
            ];
        }
        
        return $data;
    }
    
    /**
     * Get revenue by payment method
     */
    private function getRevenueByPaymentMethod(int $expertId, string $startDate, string $endDate): array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                payment_method as method,
                SUM(paid_amount) as amount,
                COUNT(*) as count
            FROM $table_payments 
            WHERE expert_id = %d 
            AND payment_date BETWEEN %s AND %s 
            AND status = 'paid'
            GROUP BY payment_method
            ORDER BY amount DESC",
            $expertId, $startDate, $endDate
        ), ARRAY_A);
        
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'method' => $row['method'],
                'amount' => (float) $row['amount'],
                'count' => (int) $row['count']
            ];
        }
        
        return $data;
    }
    
    /**
     * Get top clients
     */
    private function getTopClients(int $expertId, string $startDate, string $endDate, int $limit = 5): array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                client_id,
                SUM(paid_amount) as total
            FROM $table_payments 
            WHERE expert_id = %d 
            AND payment_date BETWEEN %s AND %s 
            AND status = 'paid'
            GROUP BY client_id
            ORDER BY total DESC
            LIMIT %d",
            $expertId, $startDate, $endDate, $limit
        ), ARRAY_A);
        
        $data = [];
        foreach ($results as $row) {
            $client = get_userdata((int) $row['client_id']);
            if ($client) {
                $data[] = [
                    'client_id' => (int) $row['client_id'],
                    'name' => $client->display_name,
                    'total' => (float) $row['total']
                ];
            }
        }
        
        return $data;
    }
    
    /**
     * Format payment for API response
     * Expects payment array to have service_name from a LEFT JOIN
     */
    private function formatPayment(array $payment): array {
        $clientId = (int) ($payment['client_id'] ?? 0);
        $client = null;
        
        if ($clientId > 0) {
            $client = get_userdata($clientId);
        }
        
        $serviceId = (int) ($payment['service_id'] ?? 0);
        // Use service_name from JOIN if available, otherwise fetch it
        $serviceName = $payment['service_name'] ?? null;
        
        if ($serviceId > 0 && !$serviceName) {
            global $wpdb;
            $table_services = $wpdb->prefix . 'rejimde_services';
            $serviceName = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM $table_services WHERE id = %d",
                $serviceId
            ));
        }
        
        return [
            'id' => (int) ($payment['id'] ?? 0),
            'client' => [
                'id' => $clientId,
                'name' => $client ? ($client->display_name ?? 'Unknown') : 'Unknown',
                'avatar' => $client ? (get_user_meta($clientId, 'avatar_url', true) ?: 'https://placehold.co/150') : 'https://placehold.co/150'
            ],
            'service' => $serviceId > 0 ? [
                'id' => $serviceId,
                'name' => $serviceName ?? 'N/A'
            ] : null,
            'amount' => (float) ($payment['amount'] ?? 0),
            'paid_amount' => (float) ($payment['paid_amount'] ?? 0),
            'currency' => $payment['currency'] ?? 'TRY',
            'payment_method' => $payment['payment_method'] ?? 'cash',
            'payment_date' => $payment['payment_date'] ?? null,
            'due_date' => $payment['due_date'] ?? null,
            'status' => $payment['status'] ?? 'pending',
            'description' => $payment['description'] ?? null
        ];
    }
}
