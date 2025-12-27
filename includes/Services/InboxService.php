<?php
namespace Rejimde\Services;

/**
 * Inbox Service
 * 
 * Handles business logic for inbox and messaging system
 */
class InboxService {
    
    private $notificationService;
    
    // Default avatar URL
    const DEFAULT_AVATAR_URL = 'https://placehold.co/150';
    
    public function __construct() {
        // NotificationService will be instantiated when needed
        $this->notificationService = null;
    }
    
    /**
     * Get threads for expert or client
     * 
     * @param int $userId User ID
     * @param string $userType 'expert' or 'client'
     * @param array $options Filters (status, search, limit, offset)
     * @return array
     */
    public function getThreads(int $userId, string $userType = 'expert', array $options = []): array {
        global $wpdb;
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        // Build query based on user type
        if ($userType === 'expert') {
            $query = "SELECT t.* FROM $table_threads t 
                     INNER JOIN $table_relationships r ON t.relationship_id = r.id 
                     WHERE r.expert_id = %d";
            $params = [$userId];
        } else {
            $query = "SELECT t.* FROM $table_threads t 
                     INNER JOIN $table_relationships r ON t.relationship_id = r.id 
                     WHERE r.client_id = %d";
            $params = [$userId];
        }
        
        // Filter by status
        if (!empty($options['status'])) {
            $query .= " AND t.status = %s";
            $params[] = $options['status'];
        }
        
        // Search by subject or client name
        if (!empty($options['search'])) {
            $search = '%' . $wpdb->esc_like($options['search']) . '%';
            $query .= " AND (t.subject LIKE %s OR EXISTS (
                SELECT 1 FROM {$wpdb->users} u 
                WHERE u.ID = r.client_id 
                AND u.display_name LIKE %s
            ))";
            $params[] = $search;
            $params[] = $search;
        }
        
        $query .= " ORDER BY t.last_message_at DESC";
        
        // Pagination
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $threads = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        
        // Get total and unread counts
        $count_query = "SELECT COUNT(*) as total, 
                        SUM(CASE WHEN " . ($userType === 'expert' ? 't.unread_expert' : 't.unread_client') . " > 0 THEN 1 ELSE 0 END) as unread
                        FROM $table_threads t 
                        INNER JOIN $table_relationships r ON t.relationship_id = r.id 
                        WHERE " . ($userType === 'expert' ? 'r.expert_id' : 'r.client_id') . " = %d";
        
        $count_params = [$userId];
        if (!empty($options['status'])) {
            $count_query .= " AND t.status = %s";
            $count_params[] = $options['status'];
        }
        
        $counts = $wpdb->get_row($wpdb->prepare($count_query, ...$count_params), ARRAY_A);
        
        // Format threads
        $data = [];
        foreach ($threads as $thread) {
            $relationshipId = (int) $thread['relationship_id'];
            $relationship = $this->getRelationship($relationshipId);
            
            if (!$relationship) continue;
            
            $clientId = (int) $relationship['client_id'];
            $client = get_userdata($clientId);
            
            if (!$client) continue;
            
            // Get last message
            $lastMessage = $this->getLastMessage((int) $thread['id']);
            
            $data[] = [
                'id' => (int) $thread['id'],
                'relationship_id' => $relationshipId,
                'client' => [
                    'id' => $clientId,
                    'name' => $client->display_name,
                    'avatar' => get_user_meta($clientId, 'avatar_url', true) ?: self::DEFAULT_AVATAR_URL,
                ],
                'subject' => $thread['subject'],
                'status' => $thread['status'],
                'last_message' => $lastMessage,
                'unread_count' => $userType === 'expert' ? (int) $thread['unread_expert'] : (int) $thread['unread_client'],
                'created_at' => $thread['created_at'],
            ];
        }
        
        return [
            'data' => $data,
            'meta' => [
                'total' => (int) ($counts['total'] ?? 0),
                'unread_total' => (int) ($counts['unread'] ?? 0),
            ]
        ];
    }
    
    /**
     * Get single thread with messages
     * 
     * @param int $userId User ID
     * @param int $threadId Thread ID
     * @param string $userType 'expert' or 'client'
     * @return array|null
     */
    public function getThread(int $userId, int $threadId, string $userType = 'expert'): ?array {
        global $wpdb;
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        // Verify ownership
        if ($userType === 'expert') {
            $thread = $wpdb->get_row($wpdb->prepare(
                "SELECT t.* FROM $table_threads t 
                 INNER JOIN $table_relationships r ON t.relationship_id = r.id 
                 WHERE t.id = %d AND r.expert_id = %d",
                $threadId, $userId
            ), ARRAY_A);
        } else {
            $thread = $wpdb->get_row($wpdb->prepare(
                "SELECT t.* FROM $table_threads t 
                 INNER JOIN $table_relationships r ON t.relationship_id = r.id 
                 WHERE t.id = %d AND r.client_id = %d",
                $threadId, $userId
            ), ARRAY_A);
        }
        
        if (!$thread) {
            return null;
        }
        
        $relationshipId = (int) $thread['relationship_id'];
        $relationship = $this->getRelationship($relationshipId);
        
        if (!$relationship) {
            return null;
        }
        
        $clientId = (int) $relationship['client_id'];
        $expertId = (int) $relationship['expert_id'];
        $client = get_userdata($clientId);
        
        if (!$client) {
            return null;
        }
        
        // Get messages
        $messages = $this->getMessages($threadId);
        
        return [
            'thread' => [
                'id' => (int) $thread['id'],
                'relationship_id' => $relationshipId,
                'client' => [
                    'id' => $clientId,
                    'name' => $client->display_name,
                    'avatar' => get_user_meta($clientId, 'avatar_url', true) ?: self::DEFAULT_AVATAR_URL,
                    'email' => $client->user_email,
                ],
                'subject' => $thread['subject'],
                'status' => $thread['status'],
            ],
            'messages' => $messages,
        ];
    }
    
    /**
     * Get messages for a thread
     * 
     * @param int $threadId Thread ID
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array
     */
    public function getMessages(int $threadId, int $limit = 50, int $offset = 0): array {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'rejimde_messages';
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_messages 
             WHERE thread_id = %d 
             ORDER BY created_at ASC 
             LIMIT %d OFFSET %d",
            $threadId, $limit, $offset
        ), ARRAY_A);
        
        $formatted = [];
        foreach ($messages as $msg) {
            $senderId = (int) $msg['sender_id'];
            $sender = get_userdata($senderId);
            
            $formatted[] = [
                'id' => (int) $msg['id'],
                'sender_id' => $senderId,
                'sender_type' => $msg['sender_type'],
                'sender_name' => $sender ? $sender->display_name : 'Unknown',
                'sender_avatar' => $sender ? (get_user_meta($senderId, 'avatar_url', true) ?: self::DEFAULT_AVATAR_URL) : self::DEFAULT_AVATAR_URL,
                'content' => $msg['content'],
                'content_type' => $msg['content_type'],
                'attachments' => $msg['attachments'] ? json_decode($msg['attachments'], true) : null,
                'is_read' => (bool) $msg['is_read'],
                'is_ai_generated' => (bool) $msg['is_ai_generated'],
                'created_at' => $msg['created_at'],
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Send a message in a thread
     * 
     * @param int $threadId Thread ID
     * @param int $senderId Sender user ID
     * @param string $senderType 'expert' or 'client'
     * @param array $data Message data (content, content_type, attachments, is_ai_generated)
     * @return int|false Message ID or false on failure
     */
    public function sendMessage(int $threadId, int $senderId, string $senderType, array $data) {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'rejimde_messages';
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        
        // Validate required fields
        if (empty($data['content'])) {
            return false;
        }
        
        $contentType = $data['content_type'] ?? 'text';
        $attachments = isset($data['attachments']) ? wp_json_encode($data['attachments']) : null;
        $isAiGenerated = isset($data['is_ai_generated']) ? (int) $data['is_ai_generated'] : 0;
        
        // Insert message
        $result = $wpdb->insert(
            $table_messages,
            [
                'thread_id' => $threadId,
                'sender_id' => $senderId,
                'sender_type' => $senderType,
                'content' => $data['content'],
                'content_type' => $contentType,
                'attachments' => $attachments,
                'is_ai_generated' => $isAiGenerated,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s']
        );
        
        if (!$result) {
            return false;
        }
        
        $messageId = (int) $wpdb->insert_id;
        
        // Update thread metadata
        if ($senderType === 'expert') {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_threads 
                 SET last_message_at = %s, 
                     last_message_by = %d,
                     unread_client = unread_client + 1,
                     updated_at = %s
                 WHERE id = %d",
                current_time('mysql'),
                $senderId,
                current_time('mysql'),
                $threadId
            ));
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_threads 
                 SET last_message_at = %s, 
                     last_message_by = %d,
                     unread_expert = unread_expert + 1,
                     updated_at = %s
                 WHERE id = %d",
                current_time('mysql'),
                $senderId,
                current_time('mysql'),
                $threadId
            ));
        }
        
        // Send notification to recipient
        $this->sendMessageNotification($threadId, $senderId, $senderType);
        
        return $messageId;
    }
    
    /**
     * Create a new thread
     * 
     * @param int $expertId Expert user ID
     * @param int $clientId Client user ID
     * @param string $subject Thread subject
     * @param string $content First message content
     * @return int|false Thread ID or false on failure
     */
    public function createThread(int $expertId, int $clientId, string $subject, string $content) {
        global $wpdb;
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        // Find relationship
        $relationship = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_relationships 
             WHERE expert_id = %d AND client_id = %d",
            $expertId, $clientId
        ), ARRAY_A);
        
        if (!$relationship) {
            return false;
        }
        
        $relationshipId = (int) $relationship['id'];
        
        // Create thread
        $result = $wpdb->insert(
            $table_threads,
            [
                'relationship_id' => $relationshipId,
                'subject' => $subject,
                'status' => 'open',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
        
        if (!$result) {
            return false;
        }
        
        $threadId = (int) $wpdb->insert_id;
        
        // Send first message
        $messageId = $this->sendMessage($threadId, $expertId, 'expert', [
            'content' => $content,
            'content_type' => 'text',
        ]);
        
        if (!$messageId) {
            // Rollback thread creation
            $wpdb->delete($table_threads, ['id' => $threadId], ['%d']);
            return false;
        }
        
        return $threadId;
    }
    
    /**
     * Mark thread as read
     * 
     * @param int $threadId Thread ID
     * @param string $readerType 'expert' or 'client'
     * @return bool
     */
    public function markAsRead(int $threadId, string $readerType): bool {
        global $wpdb;
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        $table_messages = $wpdb->prefix . 'rejimde_messages';
        
        // Update unread count
        $unreadField = $readerType === 'expert' ? 'unread_expert' : 'unread_client';
        $wpdb->update(
            $table_threads,
            [$unreadField => 0],
            ['id' => $threadId],
            ['%d'],
            ['%d']
        );
        
        // Mark messages as read
        $senderType = $readerType === 'expert' ? 'client' : 'expert';
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_messages 
             SET is_read = 1, read_at = %s 
             WHERE thread_id = %d AND sender_type = %s AND is_read = 0",
            current_time('mysql'),
            $threadId,
            $senderType
        ));
        
        return true;
    }
    
    /**
     * Close a thread
     * 
     * @param int $threadId Thread ID
     * @return bool
     */
    public function closeThread(int $threadId): bool {
        global $wpdb;
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        
        $result = $wpdb->update(
            $table_threads,
            ['status' => 'closed'],
            ['id' => $threadId],
            ['%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Archive a thread
     * 
     * @param int $threadId Thread ID
     * @return bool
     */
    public function archiveThread(int $threadId): bool {
        global $wpdb;
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        
        $result = $wpdb->update(
            $table_threads,
            ['status' => 'archived'],
            ['id' => $threadId],
            ['%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Get unread count for user
     * 
     * @param int $userId User ID
     * @param string $userType 'expert' or 'client'
     * @return int
     */
    public function getUnreadCount(int $userId, string $userType = 'expert'): int {
        global $wpdb;
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        $unreadField = $userType === 'expert' ? 't.unread_expert' : 't.unread_client';
        $userField = $userType === 'expert' ? 'r.expert_id' : 'r.client_id';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM($unreadField) 
             FROM $table_threads t 
             INNER JOIN $table_relationships r ON t.relationship_id = r.id 
             WHERE $userField = %d AND t.status = 'open'",
            $userId
        ));
        
        return (int) $count;
    }
    
    /**
     * Get templates for expert
     * 
     * @param int $expertId Expert user ID
     * @return array
     */
    public function getTemplates(int $expertId): array {
        global $wpdb;
        $table_templates = $wpdb->prefix . 'rejimde_message_templates';
        
        $templates = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_templates 
             WHERE expert_id = %d 
             ORDER BY usage_count DESC, created_at DESC",
            $expertId
        ), ARRAY_A);
        
        return array_map(function($template) {
            return [
                'id' => (int) $template['id'],
                'title' => $template['title'],
                'content' => $template['content'],
                'category' => $template['category'],
                'usage_count' => (int) $template['usage_count'],
                'created_at' => $template['created_at'],
            ];
        }, $templates);
    }
    
    /**
     * Create a message template
     * 
     * @param int $expertId Expert user ID
     * @param array $data Template data (title, content, category)
     * @return int|false Template ID or false on failure
     */
    public function createTemplate(int $expertId, array $data) {
        global $wpdb;
        $table_templates = $wpdb->prefix . 'rejimde_message_templates';
        
        if (empty($data['title']) || empty($data['content'])) {
            return false;
        }
        
        $result = $wpdb->insert(
            $table_templates,
            [
                'expert_id' => $expertId,
                'title' => $data['title'],
                'content' => $data['content'],
                'category' => $data['category'] ?? 'general',
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
        
        return $result ? (int) $wpdb->insert_id : false;
    }
    
    /**
     * Delete a template
     * 
     * @param int $templateId Template ID
     * @param int $expertId Expert user ID
     * @return bool
     */
    public function deleteTemplate(int $templateId, int $expertId): bool {
        global $wpdb;
        $table_templates = $wpdb->prefix . 'rejimde_message_templates';
        
        $result = $wpdb->delete(
            $table_templates,
            [
                'id' => $templateId,
                'expert_id' => $expertId,
            ],
            ['%d', '%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Generate AI draft response
     * 
     * @param int $threadId Thread ID
     * @param string $context Context (e.g., 'last_5_messages')
     * @return string
     */
    public function generateAIDraft(int $threadId, string $context = 'last_5_messages'): string {
        // Get recent messages for context
        $messages = $this->getMessages($threadId, 5, 0);
        
        if (empty($messages)) {
            return 'Merhaba, size nasıl yardımcı olabilirim?';
        }
        
        // Build context from messages
        $messageContext = array_map(function($msg) {
            return $msg['sender_type'] . ': ' . $msg['content'];
        }, array_reverse($messages));
        
        $contextString = implode("\n", $messageContext);
        
        // Check if OpenAI is configured and class exists
        $openaiKey = get_option('rejimde_openai_api_key', '');
        
        if (empty($openaiKey) || !class_exists('Rejimde\Services\OpenAIService')) {
            // Return a simple template-based response
            return $this->generateSimpleDraft($messages);
        }
        
        // Use OpenAI to generate draft
        try {
            $openaiService = new OpenAIService();
            $prompt = "Sen bir diyetisyen/fitness uzmanısın. Aşağıdaki mesaj geçmişine dayanarak profesyonel, nazik ve yararlı bir yanıt oluştur:\n\n" . $contextString;
            $response = $openaiService->call_openai($prompt);
            
            if (is_wp_error($response)) {
                return $this->generateSimpleDraft($messages);
            }
            
            if (is_string($response)) {
                return trim($response);
            }
            
            return $this->generateSimpleDraft($messages);
        } catch (\Exception $e) {
            return $this->generateSimpleDraft($messages);
        }
    }
    
    /**
     * Generate simple template-based draft
     * 
     * @param array $messages Recent messages
     * @return string
     */
    private function generateSimpleDraft(array $messages): string {
        $lastMessage = end($messages);
        
        if ($lastMessage && $lastMessage['sender_type'] === 'client') {
            $templates = [
                'Mesajınız için teşekkür ederim. En kısa sürede sizinle ilgileneceğim.',
                'Sorunuzu aldım, detaylı bir yanıt hazırlayıp size döneceğim.',
                'İlginiz için teşekkürler. Konuyla ilgili değerlendirmemi yapıp size dönüş yapacağım.',
            ];
            
            return $templates[array_rand($templates)];
        }
        
        return 'Merhaba, size nasıl yardımcı olabilirim?';
    }
    
    /**
     * Get relationship data
     * 
     * @param int $relationshipId Relationship ID
     * @return array|null
     */
    private function getRelationship(int $relationshipId): ?array {
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        $relationship = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_relationships WHERE id = %d",
            $relationshipId
        ), ARRAY_A);
        
        return $relationship ?: null;
    }
    
    /**
     * Get last message for thread
     * 
     * @param int $threadId Thread ID
     * @return array|null
     */
    private function getLastMessage(int $threadId): ?array {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'rejimde_messages';
        
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_messages 
             WHERE thread_id = %d 
             ORDER BY created_at DESC 
             LIMIT 1",
            $threadId
        ), ARRAY_A);
        
        if (!$message) {
            return null;
        }
        
        return [
            'content' => $message['content'],
            'sender_type' => $message['sender_type'],
            'created_at' => $message['created_at'],
            'is_read' => (bool) $message['is_read'],
        ];
    }
    
    /**
     * Send notification for new message
     * 
     * @param int $threadId Thread ID
     * @param int $senderId Sender user ID
     * @param string $senderType 'expert' or 'client'
     * @return void
     */
    private function sendMessageNotification(int $threadId, int $senderId, string $senderType): void {
        global $wpdb;
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        // Get thread and relationship
        $thread = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, r.expert_id, r.client_id 
             FROM $table_threads t 
             INNER JOIN $table_relationships r ON t.relationship_id = r.id 
             WHERE t.id = %d",
            $threadId
        ), ARRAY_A);
        
        if (!$thread) {
            return;
        }
        
        // Determine recipient
        $recipientId = $senderType === 'expert' ? (int) $thread['client_id'] : (int) $thread['expert_id'];
        
        // Get sender info
        $sender = get_userdata($senderId);
        if (!$sender) {
            return;
        }
        
        // Create notification (if notification system exists)
        if (class_exists('Rejimde\Services\NotificationService')) {
            // Lazy-load notification service
            if ($this->notificationService === null) {
                $this->notificationService = new NotificationService();
            }
            
            // Note: This would require a notification type to be defined in NotificationTypes.php
            // For now, we'll skip creating the notification to avoid errors
            // In a production setup, you'd add 'new_message' type to NotificationTypes.php
            // Example:
            // $this->notificationService->create($recipientId, 'new_message', [
            //     'actor_id' => $senderId,
            //     'entity_type' => 'thread',
            //     'entity_id' => $threadId,
            // ]);
        }
    }
}
