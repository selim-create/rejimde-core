<?php
namespace Rejimde\Services;

/**
 * User Dashboard Service
 * 
 * Handles user-side data retrieval (Mirror Logic)
 */
class UserDashboardService {

    /**
     * Get user's connected experts
     */
    public function getMyExperts(int $userId, array $options = []): array {
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $table_packages = $wpdb->prefix . 'rejimde_client_packages';
        $table_appointments = $wpdb->prefix . 'rejimde_appointments';
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        $table_messages = $wpdb->prefix . 'rejimde_messages';

        $status = $options['status'] ?? null;
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;

        $where = "r.client_id = %d";
        $params = [$userId];

        if ($status && $status !== 'all') {
            $where .= " AND r.status = %s";
            $params[] = $status;
        }

        $query = $wpdb->prepare(
            "SELECT r.*, 
                    p.package_name, p.package_type, p.total_sessions, p.used_sessions, 
                    p.start_date, p.end_date, p.price,
                    (SELECT COUNT(*) FROM $table_appointments a 
                     WHERE a.client_id = r.client_id AND a.expert_id = r.expert_id 
                     AND a.status = 'confirmed' AND a.appointment_date >= CURDATE()) as upcoming_appointments,
                    (SELECT COUNT(*) FROM $table_threads t 
                     INNER JOIN $table_messages m ON t.id = m.thread_id 
                     WHERE t.relationship_id = r.id AND m.is_read = 0 AND m.sender_type = 'expert') as unread_messages
             FROM $table_relationships r
             LEFT JOIN $table_packages p ON r.id = p.relationship_id AND p.status = 'active'
             WHERE $where
             ORDER BY r.status = 'active' DESC, r.created_at DESC
             LIMIT %d OFFSET %d",
            array_merge($params, [$limit, $offset])
        );

        $relationships = $wpdb->get_results($query, ARRAY_A);

        $result = [];
        foreach ($relationships as $rel) {
            $expert = get_userdata((int) $rel['expert_id']);
            if (!$expert) continue;

            // Next appointment
            $nextAppointment = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_appointments 
                 WHERE client_id = %d AND expert_id = %d AND status = 'confirmed' 
                 AND appointment_date >= CURDATE()
                 ORDER BY appointment_date ASC, start_time ASC LIMIT 1",
                $userId, $rel['expert_id']
            ), ARRAY_A);

            $total = $rel['total_sessions'] ? (int) $rel['total_sessions'] : null;
            $used = (int) ($rel['used_sessions'] ?? 0);
            $remaining = $total ? $total - $used : null;

            $result[] = [
                'id' => (int) $rel['id'],
                'relationship_id' => (int) $rel['id'],
                'expert' => [
                    'id' => (int) $rel['expert_id'],
                    'name' => $expert->display_name,
                    'title' => get_user_meta($rel['expert_id'], 'professional_title', true) ?: '',
                    'avatar' => get_user_meta($rel['expert_id'], 'avatar_url', true) ?: 'https://placehold.co/150',
                    'profession' => get_user_meta($rel['expert_id'], 'profession', true) ?: '',
                ],
                'status' => $rel['status'],
                'package' => $rel['package_name'] ? [
                    'name' => $rel['package_name'],
                    'type' => $rel['package_type'] ?? 'session',
                    'total' => $total,
                    'used' => $used,
                    'remaining' => $remaining,
                    'progress_percent' => $total ? round(($used / $total) * 100) : 0,
                    'expiry_date' => $rel['end_date'],
                ] : null,
                'next_appointment' => $nextAppointment ? [
                    'date' => $nextAppointment['appointment_date'],
                    'time' => substr($nextAppointment['start_time'], 0, 5),
                    'title' => $nextAppointment['title'],
                ] : null,
                'unread_messages' => (int) $rel['unread_messages'],
                'upcoming_appointments' => (int) $rel['upcoming_appointments'],
                'started_at' => $rel['started_at'],
            ];
        }

        return $result;
    }

    /**
     * Get user's active packages
     */
    public function getMyPackages(int $userId): array {
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $table_packages = $wpdb->prefix . 'rejimde_client_packages';

        $query = $wpdb->prepare(
            "SELECT p.*, r.expert_id, r.client_id, r.status as relationship_status
             FROM $table_packages p
             INNER JOIN $table_relationships r ON p.relationship_id = r.id
             WHERE r.client_id = %d
             ORDER BY p.status = 'active' DESC, p.created_at DESC",
            $userId
        );

        $packages = $wpdb->get_results($query, ARRAY_A);

        $result = [];
        foreach ($packages as $pkg) {
            $expert = get_userdata((int) $pkg['expert_id']);
            if (!$expert) continue;

            $total = (int) $pkg['total_sessions'];
            $used = (int) $pkg['used_sessions'];
            $remaining = $total > 0 ? $total - $used : null;
            $progress = $total > 0 ? round(($used / $total) * 100) : 0;

            // Determine status
            $packageStatus = $pkg['status'];
            if ($pkg['relationship_status'] !== 'active') {
                $packageStatus = 'inactive';
            } elseif ($pkg['end_date'] && strtotime($pkg['end_date']) < time()) {
                $packageStatus = 'expired';
            } elseif ($remaining !== null && $remaining <= 0) {
                $packageStatus = 'completed';
            }

            $result[] = [
                'id' => (int) $pkg['id'],
                'relationship_id' => (int) $pkg['relationship_id'],
                'expert' => [
                    'id' => (int) $pkg['expert_id'],
                    'name' => $expert->display_name,
                    'avatar' => get_user_meta($pkg['expert_id'], 'avatar_url', true) ?: 'https://placehold.co/150',
                ],
                'name' => $pkg['package_name'],
                'type' => $pkg['package_type'] ?? 'session',
                'total' => $total ?: null,
                'used' => $used,
                'remaining' => $remaining,
                'progress_percent' => $progress,
                'start_date' => $pkg['start_date'],
                'expiry_date' => $pkg['end_date'],
                'status' => $packageStatus,
                'price' => (float) $pkg['price'],
            ];
        }

        return $result;
    }

    /**
     * Get user's transaction history
     */
    public function getMyTransactions(int $userId, array $options = []): array {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'rejimde_payments';

        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;

        $query = $wpdb->prepare(
            "SELECT * FROM $table_payments 
             WHERE client_id = %d
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $userId, $limit, $offset
        );

        $payments = $wpdb->get_results($query, ARRAY_A);

        $result = [];
        foreach ($payments as $payment) {
            $expert = get_userdata((int) $payment['expert_id']);

            $result[] = [
                'id' => (int) $payment['id'],
                'date' => $payment['payment_date'] ?: $payment['created_at'],
                'expert' => $expert ? [
                    'id' => (int) $payment['expert_id'],
                    'name' => $expert->display_name,
                ] : null,
                'description' => $payment['description'],
                'amount' => (float) $payment['amount'],
                'paid_amount' => (float) $payment['paid_amount'],
                'currency' => $payment['currency'] ?? 'TRY',
                'payment_method' => $payment['payment_method'],
                'status' => $payment['status'],
            ];
        }

        return $result;
    }

    /**
     * Get user's assigned private plans
     */
    public function getMyPrivatePlans(int $userId, array $options = []): array {
        global $wpdb;
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';
        $table_progress = $wpdb->prefix . 'rejimde_plan_progress';

        $type = $options['type'] ?? null;
        $status = $options['status'] ?? null;
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;

        $where = "p.client_id = %d AND p.status IN ('assigned', 'in_progress', 'completed')";
        $params = [$userId];

        if ($type) {
            $where .= " AND p.type = %s";
            $params[] = $type;
        }

        if ($status) {
            $where .= " AND p.status = %s";
            $params[] = $status;
        }

        $query = $wpdb->prepare(
            "SELECT p.*, 
                    pr.completed_items, pr.progress_percent as current_progress
             FROM $table_plans p
             LEFT JOIN $table_progress pr ON p.id = pr.plan_id AND pr.user_id = %d
             WHERE $where
             ORDER BY p.assigned_at DESC
             LIMIT %d OFFSET %d",
            array_merge([$userId], $params, [$limit, $offset])
        );

        $plans = $wpdb->get_results($query, ARRAY_A);

        $result = [];
        foreach ($plans as $plan) {
            $expert = get_userdata((int) $plan['expert_id']);

            $result[] = [
                'id' => (int) $plan['id'],
                'expert' => $expert ? [
                    'id' => (int) $plan['expert_id'],
                    'name' => $expert->display_name,
                    'avatar' => get_user_meta($plan['expert_id'], 'avatar_url', true) ?: 'https://placehold.co/150',
                ] : null,
                'title' => $plan['title'],
                'type' => $plan['type'],
                'status' => $plan['status'],
                'plan_data' => json_decode($plan['plan_data'], true),
                'notes' => $plan['notes'],
                'progress_percent' => (int) ($plan['current_progress'] ?? 0),
                'completed_items' => json_decode($plan['completed_items'] ?? '[]', true),
                'assigned_at' => $plan['assigned_at'],
                'completed_at' => $plan['completed_at'],
            ];
        }

        return $result;
    }

    /**
     * Get single private plan
     */
    public function getMyPrivatePlan(int $userId, int $planId): ?array {
        global $wpdb;
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';
        $table_progress = $wpdb->prefix . 'rejimde_plan_progress';

        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, pr.completed_items, pr.progress_percent
             FROM $table_plans p
             LEFT JOIN $table_progress pr ON p.id = pr.plan_id AND pr.user_id = %d
             WHERE p.id = %d AND p.client_id = %d",
            $userId, $planId, $userId
        ), ARRAY_A);

        if (!$plan) return null;

        $expert = get_userdata((int) $plan['expert_id']);

        return [
            'id' => (int) $plan['id'],
            'expert' => $expert ? [
                'id' => (int) $plan['expert_id'],
                'name' => $expert->display_name,
                'avatar' => get_user_meta($plan['expert_id'], 'avatar_url', true) ?: 'https://placehold.co/150',
                'title' => get_user_meta($plan['expert_id'], 'professional_title', true) ?: '',
            ] : null,
            'title' => $plan['title'],
            'type' => $plan['type'],
            'status' => $plan['status'],
            'plan_data' => json_decode($plan['plan_data'], true),
            'notes' => $plan['notes'],
            'progress_percent' => (int) ($plan['progress_percent'] ?? 0),
            'completed_items' => json_decode($plan['completed_items'] ?? '[]', true),
            'assigned_at' => $plan['assigned_at'],
            'completed_at' => $plan['completed_at'],
            'created_at' => $plan['created_at'],
        ];
    }

    /**
     * Update plan progress
     */
    public function updatePlanProgress(int $userId, int $planId, array $data): bool {
        global $wpdb;
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';
        $table_progress = $wpdb->prefix . 'rejimde_plan_progress';

        // Verify plan belongs to user
        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_plans WHERE id = %d AND client_id = %d",
            $planId, $userId
        ));

        if (!$plan) return false;

        $completedItems = $data['completed_items'] ?? [];
        $progressPercent = $data['progress_percent'] ?? 0;

        // Calculate progress if not provided
        if (!$progressPercent && !empty($completedItems)) {
            $planData = json_decode($plan->plan_data, true);
            $totalItems = $this->countPlanItems($planData);
            if ($totalItems > 0) {
                $progressPercent = round((count($completedItems) / $totalItems) * 100);
            }
        }

        // Upsert progress
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_progress WHERE plan_id = %d AND user_id = %d",
            $planId, $userId
        ));

        if ($existing) {
            $result = $wpdb->update(
                $table_progress,
                [
                    'completed_items' => json_encode($completedItems),
                    'progress_percent' => $progressPercent,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $existing]
            );
            
            if ($result === false) {
                return false;
            }
        } else {
            $result = $wpdb->insert(
                $table_progress,
                [
                    'plan_id' => $planId,
                    'user_id' => $userId,
                    'completed_items' => json_encode($completedItems),
                    'progress_percent' => $progressPercent,
                    'updated_at' => current_time('mysql'),
                ]
            );
            
            if ($result === false) {
                return false;
            }
        }

        // Update plan status if completed
        if ($progressPercent >= 100) {
            $result = $wpdb->update(
                $table_plans,
                [
                    'status' => 'completed',
                    'completed_at' => current_time('mysql'),
                ],
                ['id' => $planId]
            );
            
            if ($result === false) {
                error_log("Failed to update plan status to completed for plan ID: $planId");
            }
        } elseif ($progressPercent > 0 && $plan->status === 'assigned') {
            $result = $wpdb->update(
                $table_plans,
                ['status' => 'in_progress'],
                ['id' => $planId]
            );
            
            if ($result === false) {
                error_log("Failed to update plan status to in_progress for plan ID: $planId");
            }
        }

        return true;
    }

    /**
     * Count total items in a plan
     */
    private function countPlanItems(array $planData): int {
        $count = 0;
        
        // Diet plan: count meals
        if (isset($planData['days'])) {
            foreach ($planData['days'] as $day) {
                if (isset($day['meals'])) {
                    $count += count($day['meals']);
                }
            }
        }
        
        // Workout plan: count exercises
        if (isset($planData['workouts'])) {
            foreach ($planData['workouts'] as $workout) {
                if (isset($workout['exercises'])) {
                    $count += count($workout['exercises']);
                }
            }
        }

        // Flow plan: count poses
        if (isset($planData['flows'])) {
            foreach ($planData['flows'] as $flow) {
                if (isset($flow['poses'])) {
                    $count += count($flow['poses']);
                }
            }
        }

        return $count ?: 1;
    }

    /**
     * Create inbox thread (user initiates)
     */
    public function createInboxThread(int $userId, int $expertId, string $subject, string $content): ?int {
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        $table_messages = $wpdb->prefix . 'rejimde_messages';

        // Verify relationship exists
        $relationship = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_relationships 
             WHERE client_id = %d AND expert_id = %d AND status = 'active'",
            $userId, $expertId
        ));

        if (!$relationship) return null;

        // Create thread
        $result = $wpdb->insert($table_threads, [
            'relationship_id' => $relationship->id,
            'subject' => $subject,
            'status' => 'open',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        if ($result === false) {
            return null;
        }

        $threadId = $wpdb->insert_id;
        if (!$threadId) return null;

        // Create first message
        $result = $wpdb->insert($table_messages, [
            'thread_id' => $threadId,
            'sender_id' => $userId,
            'sender_type' => 'client',
            'content' => $content,
            'content_type' => 'text',
            'is_read' => false,
            'created_at' => current_time('mysql'),
        ]);

        if ($result === false) {
            // Rollback: delete the orphaned thread
            $wpdb->delete($table_threads, ['id' => $threadId]);
            return null;
        }

        return $threadId;
    }

    /**
     * Get dashboard summary (for widgets)
     */
    public function getDashboardSummary(int $userId): array {
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $table_appointments = $wpdb->prefix . 'rejimde_appointments';
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        $table_messages = $wpdb->prefix . 'rejimde_messages';
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';

        // Active experts count
        $activeExperts = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_relationships WHERE client_id = %d AND status = 'active'",
            $userId
        ));

        // Upcoming appointments count
        $upcomingAppointments = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_appointments 
             WHERE client_id = %d AND status = 'confirmed' AND appointment_date >= CURDATE()",
            $userId
        ));

        // Unread messages count
        $unreadMessages = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_messages m
             INNER JOIN $table_threads t ON m.thread_id = t.id
             INNER JOIN $table_relationships r ON t.relationship_id = r.id
             WHERE r.client_id = %d AND m.sender_type = 'expert' AND m.is_read = 0",
            $userId
        ));

        // Active plans count
        $activePlans = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_plans 
             WHERE client_id = %d AND status IN ('assigned', 'in_progress')",
            $userId
        ));

        // Next appointment
        $nextAppointment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, u.display_name as expert_name
             FROM $table_appointments a
             INNER JOIN {$wpdb->users} u ON a.expert_id = u.ID
             WHERE a.client_id = %d AND a.status = 'confirmed' AND a.appointment_date >= CURDATE()
             ORDER BY a.appointment_date ASC, a.start_time ASC LIMIT 1",
            $userId
        ), ARRAY_A);

        return [
            'active_experts' => $activeExperts,
            'upcoming_appointments' => $upcomingAppointments,
            'unread_messages' => $unreadMessages,
            'active_plans' => $activePlans,
            'next_appointment' => $nextAppointment ? [
                'id' => (int) $nextAppointment['id'],
                'title' => $nextAppointment['title'],
                'expert_name' => $nextAppointment['expert_name'],
                'date' => $nextAppointment['appointment_date'],
                'time' => substr($nextAppointment['start_time'], 0, 5),
                'type' => $nextAppointment['type'],
                'meeting_link' => $nextAppointment['meeting_link'],
            ] : null,
        ];
    }

    /**
     * Accept expert invite
     */
    public function acceptInvite(int $userId, string $token): array {
        global $wpdb;
        $table_invites = $wpdb->prefix . 'rejimde_invites';
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $table_packages = $wpdb->prefix . 'rejimde_client_packages';

        // Find invite
        $invite = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_invites 
             WHERE token = %s AND status = 'pending' AND expires_at > NOW()",
            $token
        ));

        if (!$invite) {
            return ['error' => 'Geçersiz veya süresi dolmuş davet linki'];
        }

        // Check if relationship already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_relationships 
             WHERE expert_id = %d AND client_id = %d",
            $invite->expert_id, $userId
        ));

        if ($existing) {
            return ['error' => 'Bu uzmanla zaten bir ilişkiniz var'];
        }

        // Create relationship
        $packageData = json_decode($invite->package_data, true) ?: [];
        
        $result = $wpdb->insert($table_relationships, [
            'expert_id' => $invite->expert_id,
            'client_id' => $userId,
            'status' => 'active',
            'source' => 'invite',
            'started_at' => current_time('mysql'),
            'created_at' => current_time('mysql'),
        ]);

        if ($result === false) {
            return ['error' => 'İlişki oluşturulamadı'];
        }

        $relationshipId = $wpdb->insert_id;
        
        if (!$relationshipId) {
            return ['error' => 'İlişki oluşturulamadı'];
        }

        // Create package if data exists
        if (!empty($packageData['package_name'])) {
            $result = $wpdb->insert($table_packages, [
                'relationship_id' => $relationshipId,
                'package_name' => $packageData['package_name'] ?? 'Genel Paket',
                'package_type' => $packageData['package_type'] ?? 'session',
                'total_sessions' => $packageData['total_sessions'] ?? null,
                'used_sessions' => 0,
                'start_date' => current_time('mysql', false),
                'end_date' => $packageData['duration_months'] 
                    ? date('Y-m-d', strtotime("+{$packageData['duration_months']} months")) 
                    : null,
                'price' => $packageData['price'] ?? 0,
                'status' => 'active',
                'created_at' => current_time('mysql'),
            ]);
            
            if ($result === false) {
                // Rollback: delete the relationship
                $wpdb->delete($table_relationships, ['id' => $relationshipId]);
                return ['error' => 'Paket oluşturulamadı'];
            }
        }

        // Update invite
        $result = $wpdb->update(
            $table_invites,
            [
                'status' => 'accepted',
                'used_by' => $userId,
            ],
            ['id' => $invite->id]
        );
        
        if ($result === false) {
            error_log("Failed to update invite status for invite ID: {$invite->id}");
            // Don't rollback here as the relationship and package are already created
            // Just log the error for manual investigation
        }

        // Get expert info
        $expert = get_userdata($invite->expert_id);

        return [
            'success' => true,
            'relationship_id' => $relationshipId,
            'expert' => [
                'id' => (int) $invite->expert_id,
                'name' => $expert ? $expert->display_name : 'Uzman',
                'avatar' => get_user_meta($invite->expert_id, 'avatar_url', true) ?: 'https://placehold.co/150',
            ],
        ];
    }
}
