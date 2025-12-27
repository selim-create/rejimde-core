<?php
namespace Rejimde\Services;

/**
 * FAQ Service
 * 
 * Handles business logic for FAQ management
 */
class FAQService {
    
    /**
     * Get FAQs
     * 
     * @param int $expertId Expert user ID
     * @param bool $publicOnly Get only public FAQs
     * @return array
     */
    public function getFAQs(int $expertId, bool $publicOnly = false): array {
        global $wpdb;
        $table_faq = $wpdb->prefix . 'rejimde_faq';
        
        $query = "SELECT * FROM $table_faq WHERE expert_id = %d";
        $params = [$expertId];
        
        if ($publicOnly) {
            $query .= " AND is_public = 1";
        }
        
        $query .= " ORDER BY sort_order ASC, created_at ASC";
        
        $faqs = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        
        return array_map(function($faq) {
            return [
                'id' => (int) $faq['id'],
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'category' => $faq['category'],
                'is_public' => (bool) $faq['is_public'],
                'sort_order' => (int) $faq['sort_order'],
                'created_at' => $faq['created_at'],
                'updated_at' => $faq['updated_at'],
            ];
        }, $faqs);
    }
    
    /**
     * Get single FAQ
     * 
     * @param int $faqId FAQ ID
     * @param int $expertId Expert user ID
     * @return array|null
     */
    public function getFAQ(int $faqId, int $expertId): ?array {
        global $wpdb;
        $table_faq = $wpdb->prefix . 'rejimde_faq';
        
        $faq = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_faq WHERE id = %d AND expert_id = %d",
            $faqId,
            $expertId
        ), ARRAY_A);
        
        if (!$faq) {
            return null;
        }
        
        return [
            'id' => (int) $faq['id'],
            'question' => $faq['question'],
            'answer' => $faq['answer'],
            'category' => $faq['category'],
            'is_public' => (bool) $faq['is_public'],
            'sort_order' => (int) $faq['sort_order'],
            'created_at' => $faq['created_at'],
            'updated_at' => $faq['updated_at'],
        ];
    }
    
    /**
     * Create FAQ
     * 
     * @param int $expertId Expert user ID
     * @param array $data FAQ data
     * @return int|array FAQ ID or error
     */
    public function createFAQ(int $expertId, array $data) {
        global $wpdb;
        $table_faq = $wpdb->prefix . 'rejimde_faq';
        
        if (empty($data['question']) || empty($data['answer'])) {
            return ['error' => 'Question and answer are required'];
        }
        
        $insertData = [
            'expert_id' => $expertId,
            'question' => sanitize_text_field($data['question']),
            'answer' => wp_kses_post($data['answer']),
            'category' => isset($data['category']) ? sanitize_text_field($data['category']) : null,
            'is_public' => isset($data['is_public']) ? (bool) $data['is_public'] : true,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
        ];
        
        $result = $wpdb->insert($table_faq, $insertData);
        
        if ($result === false) {
            return ['error' => 'Failed to create FAQ'];
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update FAQ
     * 
     * @param int $faqId FAQ ID
     * @param array $data Update data
     * @return bool|array
     */
    public function updateFAQ(int $faqId, array $data) {
        global $wpdb;
        $table_faq = $wpdb->prefix . 'rejimde_faq';
        
        $updateData = [];
        
        if (isset($data['question'])) {
            $updateData['question'] = sanitize_text_field($data['question']);
        }
        if (isset($data['answer'])) {
            $updateData['answer'] = wp_kses_post($data['answer']);
        }
        if (isset($data['category'])) {
            $updateData['category'] = sanitize_text_field($data['category']);
        }
        if (isset($data['is_public'])) {
            $updateData['is_public'] = (bool) $data['is_public'];
        }
        if (isset($data['sort_order'])) {
            $updateData['sort_order'] = (int) $data['sort_order'];
        }
        
        if (empty($updateData)) {
            return ['error' => 'No valid fields to update'];
        }
        
        $result = $wpdb->update($table_faq, $updateData, ['id' => $faqId]);
        
        return $result !== false;
    }
    
    /**
     * Delete FAQ
     * 
     * @param int $faqId FAQ ID
     * @param int $expertId Expert user ID
     * @return bool
     */
    public function deleteFAQ(int $faqId, int $expertId): bool {
        global $wpdb;
        $table_faq = $wpdb->prefix . 'rejimde_faq';
        
        $result = $wpdb->delete($table_faq, [
            'id' => $faqId,
            'expert_id' => $expertId
        ]);
        
        return $result !== false;
    }
    
    /**
     * Reorder FAQs
     * 
     * @param int $expertId Expert user ID
     * @param array $order Array of FAQ IDs in new order
     * @return bool
     */
    public function reorderFAQs(int $expertId, array $order): bool {
        global $wpdb;
        $table_faq = $wpdb->prefix . 'rejimde_faq';
        
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($order as $index => $faqId) {
                $result = $wpdb->update(
                    $table_faq,
                    ['sort_order' => $index],
                    [
                        'id' => (int) $faqId,
                        'expert_id' => $expertId
                    ]
                );
                
                if ($result === false) {
                    throw new \Exception('Failed to update order');
                }
            }
            
            $wpdb->query('COMMIT');
            return true;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }
    
    /**
     * Import template FAQs
     * 
     * @param int $expertId Expert user ID
     * @param string $templatePack Template pack name
     * @return int Number of FAQs imported
     */
    public function importTemplates(int $expertId, string $templatePack): int {
        $templates = $this->getTemplateData($templatePack);
        
        if (empty($templates)) {
            return 0;
        }
        
        $count = 0;
        foreach ($templates as $template) {
            $result = $this->createFAQ($expertId, $template);
            if (is_int($result)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Get template FAQ data
     * 
     * @param string $pack Template pack name
     * @return array
     */
    private function getTemplateData(string $pack): array {
        $templates = [
            'nutrition' => [
                ['question' => 'Randevu nasıl alınır?', 'answer' => 'Randevu almak için takvim bölümünden uygun bir slot seçebilirsiniz.', 'category' => 'Genel'],
                ['question' => 'Ödeme seçenekleri nelerdir?', 'answer' => 'Nakit, kredi kartı ve banka transferi ile ödeme yapabilirsiniz.', 'category' => 'Ödeme'],
                ['question' => 'İptal politikası nedir?', 'answer' => 'Randevudan 24 saat önce ücretsiz iptal yapabilirsiniz.', 'category' => 'Genel'],
            ],
            'fitness' => [
                ['question' => 'Antrenman programı ne kadar sürer?', 'answer' => 'Programlar genellikle 8-12 hafta sürmektedir.', 'category' => 'Program'],
                ['question' => 'Ekipman gerekli mi?', 'answer' => 'Bazı programlar ekipman gerektirirken, bazıları vücut ağırlığı ile yapılabilir.', 'category' => 'Ekipman'],
            ],
        ];
        
        return $templates[$pack] ?? [];
    }
    
    /**
     * Get public FAQs by expert ID (for public profile)
     * 
     * @param int $expertId Expert user ID
     * @return array
     */
    public function getPublicFAQs(int $expertId): array {
        return $this->getFAQs($expertId, true);
    }
}
