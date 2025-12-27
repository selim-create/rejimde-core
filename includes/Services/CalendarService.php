<?php
namespace Rejimde\Services;

/**
 * Calendar Service
 * 
 * Handles business logic for expert calendar, appointments, and availability
 */
class CalendarService {

    /**
     * Get appointments for an expert within a date range
     * 
     * @param int $expertId Expert user ID
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param string|null $status Filter by status (pending, confirmed, all)
     * @return array
     */
    public function getAppointments(int $expertId, string $startDate, string $endDate, ?string $status = null): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointments';
        
        $query = "SELECT * FROM {$table} WHERE expert_id = %d AND appointment_date BETWEEN %s AND %s";
        $params = [$expertId, $startDate, $endDate];
        
        if ($status && $status !== 'all') {
            $query .= " AND status = %s";
            $params[] = $status;
        }
        
        $query .= " ORDER BY appointment_date, start_time";
        
        $appointments = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        
        // Format appointments with client data
        $formatted = [];
        foreach ($appointments as $apt) {
            $client = get_userdata((int) $apt['client_id']);
            
            $formatted[] = [
                'id' => (int) $apt['id'],
                'client' => [
                    'id' => (int) $apt['client_id'],
                    'name' => $client ? $client->display_name : 'Unknown',
                    'avatar' => $client ? (get_user_meta((int) $apt['client_id'], 'avatar_url', true) ?: 'https://placehold.co/150') : 'https://placehold.co/150'
                ],
                'title' => $apt['title'],
                'description' => $apt['description'],
                'date' => $apt['appointment_date'],
                'start_time' => substr($apt['start_time'], 0, 5), // HH:MM format
                'end_time' => substr($apt['end_time'], 0, 5),
                'duration' => (int) $apt['duration'],
                'status' => $apt['status'],
                'type' => $apt['type'],
                'location' => $apt['location'],
                'meeting_link' => $apt['meeting_link'],
                'notes' => $apt['notes']
            ];
        }
        
        return $formatted;
    }

    /**
     * Get blocked times for an expert within a date range
     * 
     * @param int $expertId Expert user ID
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array
     */
    public function getBlockedTimes(int $expertId, string $startDate, string $endDate): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_blocked_times';
        
        $query = "SELECT * FROM {$table} WHERE expert_id = %d AND blocked_date BETWEEN %s AND %s ORDER BY blocked_date, start_time";
        $blocked = $wpdb->get_results($wpdb->prepare($query, $expertId, $startDate, $endDate), ARRAY_A);
        
        $formatted = [];
        foreach ($blocked as $block) {
            $formatted[] = [
                'id' => (int) $block['id'],
                'date' => $block['blocked_date'],
                'start_time' => $block['start_time'] ? substr($block['start_time'], 0, 5) : null,
                'end_time' => $block['end_time'] ? substr($block['end_time'], 0, 5) : null,
                'reason' => $block['reason'],
                'is_all_day' => $block['start_time'] === null
            ];
        }
        
        return $formatted;
    }

    /**
     * Get expert's availability template
     * 
     * @param int $expertId Expert user ID
     * @return array
     */
    public function getAvailability(int $expertId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_availability';
        
        $slots = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE expert_id = %d AND is_active = 1 ORDER BY day_of_week, start_time",
            $expertId
        ), ARRAY_A);
        
        // Get default settings from first slot or use defaults
        $slotDuration = 60;
        $bufferTime = 15;
        if (!empty($slots)) {
            $slotDuration = (int) $slots[0]['slot_duration'];
            $bufferTime = (int) $slots[0]['buffer_time'];
        }
        
        // Group by day of week
        $schedule = [];
        $dayNames = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
        
        // Initialize all days
        for ($day = 0; $day <= 6; $day++) {
            $schedule[$day] = [
                'day' => $day,
                'day_name' => $dayNames[$day],
                'slots' => []
            ];
        }
        
        // Add actual slots
        foreach ($slots as $slot) {
            $day = (int) $slot['day_of_week'];
            $schedule[$day]['slots'][] = [
                'start' => substr($slot['start_time'], 0, 5),
                'end' => substr($slot['end_time'], 0, 5)
            ];
        }
        
        // Reorder to start from Monday
        $orderedSchedule = [];
        for ($day = 1; $day <= 6; $day++) {
            $orderedSchedule[] = $schedule[$day];
        }
        $orderedSchedule[] = $schedule[0]; // Sunday last
        
        return [
            'slot_duration' => $slotDuration,
            'buffer_time' => $bufferTime,
            'schedule' => $orderedSchedule
        ];
    }

    /**
     * Update expert's availability template
     * 
     * @param int $expertId Expert user ID
     * @param array $data Availability data (slot_duration, buffer_time, schedule)
     * @return bool
     */
    public function updateAvailability(int $expertId, array $data): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_availability';
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Delete existing availability
            $wpdb->delete($table, ['expert_id' => $expertId], ['%d']);
            
            // Insert new availability
            $slotDuration = (int) ($data['slot_duration'] ?? 60);
            $bufferTime = (int) ($data['buffer_time'] ?? 15);
            $schedule = $data['schedule'] ?? [];
            
            foreach ($schedule as $slot) {
                $wpdb->insert($table, [
                    'expert_id' => $expertId,
                    'day_of_week' => (int) $slot['day'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'is_active' => 1,
                    'slot_duration' => $slotDuration,
                    'buffer_time' => $bufferTime
                ], ['%d', '%d', '%s', '%s', '%d', '%d', '%d']);
            }
            
            $wpdb->query('COMMIT');
            return true;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }

    /**
     * Get available time slots for an expert on a specific date
     * 
     * @param int $expertId Expert user ID
     * @param string $date Date (YYYY-MM-DD)
     * @return array
     */
    public function getAvailableSlots(int $expertId, string $date): array {
        // Get day of week (0 = Sunday, 1 = Monday, etc.)
        $dayOfWeek = (int) date('w', strtotime($date));
        
        // Get availability template for this day
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_availability';
        
        $slots = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE expert_id = %d AND day_of_week = %d AND is_active = 1 ORDER BY start_time",
            $expertId, $dayOfWeek
        ), ARRAY_A);
        
        if (empty($slots)) {
            return [];
        }
        
        $slotDuration = (int) $slots[0]['slot_duration'];
        $bufferTime = (int) $slots[0]['buffer_time'];
        
        // Generate time slots
        $availableSlots = [];
        foreach ($slots as $slot) {
            $start = strtotime($slot['start_time']);
            $end = strtotime($slot['end_time']);
            
            $current = $start;
            while ($current + ($slotDuration * 60) <= $end) {
                $slotTime = date('H:i', $current);
                $slotDateTime = $date . ' ' . $slotTime;
                
                // Check if slot is available (not booked, not blocked)
                if ($this->isSlotAvailable($expertId, $date, $slotTime)) {
                    $availableSlots[] = $slotTime;
                }
                
                $current += ($slotDuration + $bufferTime) * 60;
            }
        }
        
        return $availableSlots;
    }

    /**
     * Get available time slots for a date range
     * 
     * @param int $expertId Expert user ID
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array
     */
    public function getAvailableSlotsRange(int $expertId, string $startDate, string $endDate): array {
        $result = [];
        $current = strtotime($startDate);
        $end = strtotime($endDate);
        
        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $slots = $this->getAvailableSlots($expertId, $date);
            
            if (!empty($slots)) {
                $result[$date] = $slots;
            }
            
            $current = strtotime('+1 day', $current);
        }
        
        return $result;
    }

    /**
     * Create a new appointment
     * 
     * @param int $expertId Expert user ID
     * @param array $data Appointment data
     * @return int|array Appointment ID on success, error array on failure
     */
    public function createAppointment(int $expertId, array $data): int|array {
        // Validate required fields
        if (empty($data['client_id']) || empty($data['date']) || empty($data['start_time'])) {
            return ['error' => 'Missing required fields'];
        }
        
        $clientId = (int) $data['client_id'];
        $date = $data['date'];
        $startTime = $data['start_time'];
        $duration = (int) ($data['duration'] ?? 60);
        
        // Calculate end time
        $startDateTime = strtotime($date . ' ' . $startTime);
        $endTime = date('H:i:s', $startDateTime + ($duration * 60));
        
        // Check for conflicts
        if ($this->checkConflict($expertId, $date, $startTime, $endTime)) {
            return ['error' => 'Time slot is not available'];
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointments';
        
        $result = $wpdb->insert($table, [
            'expert_id' => $expertId,
            'client_id' => $clientId,
            'relationship_id' => $data['relationship_id'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'title' => $data['title'] ?? 'Randevu',
            'description' => $data['description'] ?? null,
            'appointment_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration' => $duration,
            'status' => $data['status'] ?? 'pending',
            'type' => $data['type'] ?? 'online',
            'location' => $data['location'] ?? null,
            'meeting_link' => $data['meeting_link'] ?? null,
            'notes' => $data['notes'] ?? null
        ], [
            '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'
        ]);
        
        if ($result) {
            return (int) $wpdb->insert_id;
        }
        
        return ['error' => 'Failed to create appointment'];
    }

    /**
     * Update an appointment
     * 
     * @param int $appointmentId Appointment ID
     * @param array $data Update data
     * @return bool|array True on success, error array on failure
     */
    public function updateAppointment(int $appointmentId, array $data): bool|array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointments';
        
        // Get current appointment
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $appointmentId
        ), ARRAY_A);
        
        if (!$appointment) {
            return ['error' => 'Appointment not found'];
        }
        
        $expertId = (int) $appointment['expert_id'];
        $updateData = [];
        $formats = [];
        
        // Check if time is being changed
        if (isset($data['date']) || isset($data['start_time']) || isset($data['duration'])) {
            $date = $data['date'] ?? $appointment['appointment_date'];
            $startTime = $data['start_time'] ?? $appointment['start_time'];
            $duration = (int) ($data['duration'] ?? $appointment['duration']);
            
            $startDateTime = strtotime($date . ' ' . $startTime);
            $endTime = date('H:i:s', $startDateTime + ($duration * 60));
            
            // Check for conflicts (excluding this appointment)
            if ($this->checkConflict($expertId, $date, $startTime, $endTime, $appointmentId)) {
                return ['error' => 'Time slot is not available'];
            }
            
            $updateData['appointment_date'] = $date;
            $updateData['start_time'] = $startTime;
            $updateData['end_time'] = $endTime;
            $updateData['duration'] = $duration;
            $formats = array_merge($formats, ['%s', '%s', '%s', '%d']);
        }
        
        // Update other fields
        $allowedFields = ['title', 'description', 'status', 'type', 'location', 'meeting_link', 'notes'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
                $formats[] = '%s';
            }
        }
        
        if (empty($updateData)) {
            return true; // Nothing to update
        }
        
        $result = $wpdb->update($table, $updateData, ['id' => $appointmentId], $formats, ['%d']);
        
        return $result !== false;
    }

    /**
     * Cancel an appointment
     * 
     * @param int $appointmentId Appointment ID
     * @param int $cancelledBy User ID who cancelled
     * @param string|null $reason Cancellation reason
     * @return bool
     */
    public function cancelAppointment(int $appointmentId, int $cancelledBy, ?string $reason = null): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointments';
        
        $result = $wpdb->update($table, [
            'status' => 'cancelled',
            'cancelled_by' => $cancelledBy,
            'cancellation_reason' => $reason
        ], ['id' => $appointmentId], ['%s', '%d', '%s'], ['%d']);
        
        return $result !== false;
    }

    /**
     * Mark appointment as completed
     * 
     * @param int $appointmentId Appointment ID
     * @return bool
     */
    public function completeAppointment(int $appointmentId): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointments';
        
        $result = $wpdb->update($table, [
            'status' => 'completed'
        ], ['id' => $appointmentId], ['%s'], ['%d']);
        
        return $result !== false;
    }

    /**
     * Mark appointment as no-show
     * 
     * @param int $appointmentId Appointment ID
     * @return bool
     */
    public function markNoShow(int $appointmentId): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointments';
        
        $result = $wpdb->update($table, [
            'status' => 'no_show'
        ], ['id' => $appointmentId], ['%s'], ['%d']);
        
        return $result !== false;
    }

    /**
     * Get appointment requests for an expert
     * 
     * @param int $expertId Expert user ID
     * @param string|null $status Filter by status
     * @return array
     */
    public function getRequests(int $expertId, ?string $status = null): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointment_requests';
        
        $query = "SELECT * FROM {$table} WHERE expert_id = %d";
        $params = [$expertId];
        
        if ($status) {
            $query .= " AND status = %s";
            $params[] = $status;
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $requests = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        
        // Count by status
        $meta = [
            'total' => count($requests),
            'pending' => 0
        ];
        
        $formatted = [];
        foreach ($requests as $req) {
            if ($req['status'] === 'pending') {
                $meta['pending']++;
            }
            
            $requester = null;
            $isMember = false;
            if ($req['requester_id']) {
                $user = get_userdata((int) $req['requester_id']);
                $isMember = (bool) $user;
            }
            
            $formatted[] = [
                'id' => (int) $req['id'],
                'requester' => [
                    'id' => $req['requester_id'] ? (int) $req['requester_id'] : null,
                    'name' => $req['requester_name'],
                    'email' => $req['requester_email'],
                    'phone' => $req['requester_phone'],
                    'is_member' => $isMember
                ],
                'service' => $req['service_id'] ? [
                    'id' => (int) $req['service_id']
                ] : null,
                'preferred_date' => $req['preferred_date'],
                'preferred_time' => substr($req['preferred_time'], 0, 5),
                'alternative_date' => $req['alternative_date'],
                'alternative_time' => $req['alternative_time'] ? substr($req['alternative_time'], 0, 5) : null,
                'message' => $req['message'],
                'status' => $req['status'],
                'created_at' => date('Y-m-d', strtotime($req['created_at']))
            ];
        }
        
        return [
            'data' => $formatted,
            'meta' => $meta
        ];
    }

    /**
     * Approve an appointment request and create appointment
     * 
     * @param int $requestId Request ID
     * @param array $appointmentData Appointment data (date, start_time, type, meeting_link)
     * @return int|array Appointment ID on success, error array on failure
     */
    public function approveRequest(int $requestId, array $appointmentData): int|array {
        global $wpdb;
        $requestTable = $wpdb->prefix . 'rejimde_appointment_requests';
        
        // Get request
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$requestTable} WHERE id = %d",
            $requestId
        ), ARRAY_A);
        
        if (!$request) {
            return ['error' => 'Request not found'];
        }
        
        if ($request['status'] !== 'pending') {
            return ['error' => 'Request is not pending'];
        }
        
        // Create appointment
        $appointmentId = $this->createAppointment((int) $request['expert_id'], [
            'client_id' => $request['requester_id'] ?: 0, // Guest = 0
            'service_id' => $request['service_id'],
            'title' => 'Randevu',
            'date' => $appointmentData['date'],
            'start_time' => $appointmentData['start_time'],
            'duration' => $appointmentData['duration'] ?? 60,
            'type' => $appointmentData['type'] ?? 'online',
            'meeting_link' => $appointmentData['meeting_link'] ?? null,
            'status' => 'confirmed'
        ]);
        
        if (is_array($appointmentId)) {
            return $appointmentId; // Error
        }
        
        // Update request status
        $wpdb->update($requestTable, [
            'status' => 'approved',
            'created_appointment_id' => $appointmentId
        ], ['id' => $requestId], ['%s', '%d'], ['%d']);
        
        return $appointmentId;
    }

    /**
     * Reject an appointment request
     * 
     * @param int $requestId Request ID
     * @param string|null $reason Rejection reason
     * @return bool
     */
    public function rejectRequest(int $requestId, ?string $reason = null): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointment_requests';
        
        $result = $wpdb->update($table, [
            'status' => 'rejected',
            'rejection_reason' => $reason
        ], ['id' => $requestId], ['%s', '%s'], ['%d']);
        
        return $result !== false;
    }

    /**
     * Create an appointment request
     * 
     * @param array $data Request data
     * @return int|array Request ID on success, error array on failure
     */
    public function createRequest(array $data): int|array {
        // Validate required fields
        if (empty($data['expert_id']) || empty($data['name']) || empty($data['email']) || 
            empty($data['preferred_date']) || empty($data['preferred_time'])) {
            return ['error' => 'Missing required fields'];
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_appointment_requests';
        
        $userId = get_current_user_id();
        
        $result = $wpdb->insert($table, [
            'expert_id' => (int) $data['expert_id'],
            'requester_id' => $userId ?: null,
            'requester_name' => $data['name'],
            'requester_email' => $data['email'],
            'requester_phone' => $data['phone'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'preferred_date' => $data['preferred_date'],
            'preferred_time' => $data['preferred_time'],
            'alternative_date' => $data['alternative_date'] ?? null,
            'alternative_time' => $data['alternative_time'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => 'pending'
        ], [
            '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'
        ]);
        
        if ($result) {
            return (int) $wpdb->insert_id;
        }
        
        return ['error' => 'Failed to create request'];
    }

    /**
     * Block a time slot
     * 
     * @param int $expertId Expert user ID
     * @param array $data Block data (date, start_time, end_time, reason, all_day)
     * @return int|false Block ID on success, false on failure
     */
    public function blockTime(int $expertId, array $data): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_blocked_times';
        
        $allDay = !empty($data['all_day']);
        
        $result = $wpdb->insert($table, [
            'expert_id' => $expertId,
            'blocked_date' => $data['date'],
            'start_time' => $allDay ? null : ($data['start_time'] ?? null),
            'end_time' => $allDay ? null : ($data['end_time'] ?? null),
            'reason' => $data['reason'] ?? null
        ], ['%d', '%s', '%s', '%s', '%s']);
        
        if ($result) {
            return (int) $wpdb->insert_id;
        }
        
        return false;
    }

    /**
     * Unblock a time slot
     * 
     * @param int $blockId Block ID
     * @param int $expertId Expert user ID (for verification)
     * @return bool
     */
    public function unblockTime(int $blockId, int $expertId): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_blocked_times';
        
        $result = $wpdb->delete($table, [
            'id' => $blockId,
            'expert_id' => $expertId
        ], ['%d', '%d']);
        
        return $result !== false;
    }

    /**
     * Check if there's a conflict for a time slot
     * 
     * @param int $expertId Expert user ID
     * @param string $date Date (YYYY-MM-DD)
     * @param string $startTime Start time (HH:MM or HH:MM:SS)
     * @param string $endTime End time (HH:MM or HH:MM:SS)
     * @param int|null $excludeId Appointment ID to exclude from check
     * @return bool True if conflict exists
     */
    public function checkConflict(int $expertId, string $date, string $startTime, string $endTime, ?int $excludeId = null): bool {
        global $wpdb;
        
        // Check appointments
        $appointmentTable = $wpdb->prefix . 'rejimde_appointments';
        $query = "SELECT COUNT(*) FROM {$appointmentTable} 
                  WHERE expert_id = %d 
                  AND appointment_date = %s 
                  AND status NOT IN ('cancelled', 'no_show')
                  AND (
                      (start_time < %s AND end_time > %s) OR
                      (start_time >= %s AND start_time < %s) OR
                      (end_time > %s AND end_time <= %s)
                  )";
        $params = [$expertId, $date, $endTime, $startTime, $startTime, $endTime, $startTime, $endTime];
        
        if ($excludeId) {
            $query .= " AND id != %d";
            $params[] = $excludeId;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($query, ...$params));
        
        if ($count > 0) {
            return true;
        }
        
        // Check blocked times
        $blockedTable = $wpdb->prefix . 'rejimde_blocked_times';
        $query = "SELECT COUNT(*) FROM {$blockedTable} 
                  WHERE expert_id = %d 
                  AND blocked_date = %s 
                  AND (
                      start_time IS NULL OR
                      (start_time < %s AND end_time > %s) OR
                      (start_time >= %s AND start_time < %s) OR
                      (end_time > %s AND end_time <= %s)
                  )";
        
        $count = $wpdb->get_var($wpdb->prepare($query, $expertId, $date, $endTime, $startTime, $startTime, $endTime, $startTime, $endTime));
        
        return $count > 0;
    }

    /**
     * Check if a specific time slot is available
     * 
     * @param int $expertId Expert user ID
     * @param string $date Date (YYYY-MM-DD)
     * @param string $time Time (HH:MM)
     * @return bool True if available
     */
    public function isSlotAvailable(int $expertId, string $date, string $time): bool {
        // Get slot duration from availability template
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_availability';
        
        $dayOfWeek = (int) date('w', strtotime($date));
        $slot = $wpdb->get_row($wpdb->prepare(
            "SELECT slot_duration FROM {$table} WHERE expert_id = %d AND day_of_week = %d AND is_active = 1 LIMIT 1",
            $expertId, $dayOfWeek
        ), ARRAY_A);
        
        $duration = $slot ? (int) $slot['slot_duration'] : 60;
        
        $startTime = $time . ':00';
        $endTime = date('H:i:s', strtotime($time) + ($duration * 60));
        
        return !$this->checkConflict($expertId, $date, $startTime, $endTime);
    }
}
