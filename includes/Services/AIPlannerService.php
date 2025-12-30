<?php
namespace Rejimde\Services;

/**
 * AI Planner Service
 * 
 * Handles AI-powered plan generation using OpenAI
 */
class AIPlannerService {
    
    private $openAIService;
    
    public function __construct() {
        if (class_exists('Rejimde\\Services\\OpenAIService')) {
            $this->openAIService = new OpenAIService();
        }
    }
    
    /**
     * Generate plan draft using AI
     * 
     * @param int|null $clientId Client user ID (null for template plans)
     * @param string $planType Plan type (diet, workout, flow, rehab, habit)
     * @param array $parameters Plan parameters
     * @return array|null Generated plan or null
     */
    public function generatePlan(?int $clientId, string $planType, array $parameters): ?array {
        if (!$this->openAIService) {
            return ['error' => 'AI service not available'];
        }
        
        // Get client information if clientId is provided
        $client = null;
        if ($clientId) {
            $client = get_userdata($clientId);
            if (!$client) {
                return ['error' => 'Client not found'];
            }
        }
        
        // Build prompt based on plan type
        $prompt = $this->buildPrompt($planType, $parameters, $client);
        
        // Call OpenAI
        $response = $this->openAIService->generateCompletion([
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional health and fitness expert. Generate detailed, personalized plans in Turkish.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 2000,
            'temperature' => 0.7,
        ]);
        
        if (!$response || isset($response['error'])) {
            return ['error' => $response['error'] ?? 'AI generation failed'];
        }
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        
        // Parse the response into structured data
        $planData = $this->parseAIResponse($content, $planType);
        
        return [
            'draft_plan' => $planData,
            'suggestions' => $this->generateSuggestions($planType, $parameters),
            'tokens_used' => $response['usage']['total_tokens'] ?? 0,
        ];
    }
    
    /**
     * Generate draft message/response
     * 
     * @param array $context Context data
     * @return array|null
     */
    public function generateDraft(array $context): ?array {
        if (!$this->openAIService) {
            return ['error' => 'AI service not available'];
        }
        
        $prompt = "Bağlam: " . ($context['context'] ?? '') . "\n\n";
        $prompt .= "Lütfen bu duruma uygun, profesyonel ve empatik bir yanıt oluştur.";
        
        $response = $this->openAIService->generateCompletion([
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional health coach. Write empathetic, motivating responses in Turkish.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.8,
        ]);
        
        if (!$response || isset($response['error'])) {
            return ['error' => $response['error'] ?? 'AI generation failed'];
        }
        
        return [
            'draft' => $response['choices'][0]['message']['content'] ?? '',
            'tokens_used' => $response['usage']['total_tokens'] ?? 0,
        ];
    }
    
    /**
     * Analyze client progress
     * 
     * @param int $clientId Client user ID
     * @param array $progressData Progress data
     * @return array|null
     */
    public function analyzeProgress(int $clientId, array $progressData): ?array {
        if (!$this->openAIService) {
            return ['error' => 'AI service not available'];
        }
        
        $prompt = "Danışan ilerleme verileri:\n";
        $prompt .= json_encode($progressData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        $prompt .= "Lütfen bu verileri analiz ederek:\n";
        $prompt .= "1. Genel ilerleme özeti\n";
        $prompt .= "2. Güçlü yönler\n";
        $prompt .= "3. İyileştirme alanları\n";
        $prompt .= "4. Öneriler\n";
        $prompt .= "sağla.";
        
        $response = $this->openAIService->generateCompletion([
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a data analyst specializing in health and fitness progress. Provide insights in Turkish.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.6,
        ]);
        
        if (!$response || isset($response['error'])) {
            return ['error' => $response['error'] ?? 'AI analysis failed'];
        }
        
        return [
            'analysis' => $response['choices'][0]['message']['content'] ?? '',
            'tokens_used' => $response['usage']['total_tokens'] ?? 0,
        ];
    }
    
    /**
     * Get AI usage statistics
     * 
     * @param int $expertId Expert user ID
     * @return array
     */
    public function getUsageStats(int $expertId): array {
        // This would track AI usage per expert
        // For now, return mock data
        return [
            'total_requests' => 0,
            'total_tokens' => 0,
            'this_month' => [
                'requests' => 0,
                'tokens' => 0,
            ],
            'by_type' => [
                'plan_generation' => 0,
                'draft_generation' => 0,
                'progress_analysis' => 0,
            ],
        ];
    }
    
    /**
     * Build prompt for plan generation
     * 
     * @param string $planType Plan type
     * @param array $parameters Parameters
     * @param \WP_User|null $client Client user object (null for template plans)
     * @return string
     */
    private function buildPrompt(string $planType, array $parameters, $client): string {
        $prompts = [
            'diet' => "Lütfen aşağıdaki bilgilere göre kişiye özel bir diyet planı oluştur:\n",
            'workout' => "Lütfen aşağıdaki bilgilere göre kişiye özel bir egzersiz programı oluştur:\n",
            'flow' => "Lütfen aşağıdaki bilgilere göre kişiye özel bir yoga/pilates akışı oluştur:\n",
            'rehab' => "Lütfen aşağıdaki bilgilere göre kişiye özel bir rehabilitasyon programı oluştur:\n",
            'habit' => "Lütfen aşağıdaki bilgilere göre kişiye özel bir alışkanlık planı oluştur:\n",
        ];
        
        $prompt = $prompts[$planType] ?? $prompts['diet'];
        
        if ($client) {
            $prompt .= "Danışan: " . $client->display_name . "\n";
        }
        
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $prompt .= ucfirst($key) . ": " . implode(', ', $value) . "\n";
            } else {
                $prompt .= ucfirst($key) . ": " . $value . "\n";
            }
        }
        
        $prompt .= "\nLütfen detaylı, uygulanabilir ve kişiye özel bir plan oluştur.";
        
        return $prompt;
    }
    
    /**
     * Parse AI response into structured data
     * 
     * @param string $content AI response content
     * @param string $planType Plan type
     * @return array
     */
    private function parseAIResponse(string $content, string $planType): array {
        // Simple parsing - in production this would be more sophisticated
        $lines = explode("\n", trim($content));
        
        return [
            'title' => $lines[0] ?? 'AI Generated Plan',
            'type' => $planType,
            'content' => $content,
            'items' => array_filter($lines), // Simplified
        ];
    }
    
    /**
     * Generate suggestions based on plan type
     * 
     * @param string $planType Plan type
     * @param array $parameters Parameters
     * @return array
     */
    private function generateSuggestions(string $planType, array $parameters): array {
        $suggestions = [
            'diet' => [
                'Su tüketimini artır',
                'Öğün aralarını düzenli tut',
                'Porsiyon kontrolüne dikkat et',
            ],
            'workout' => [
                'Isınma ve soğuma hareketlerini atlamayın',
                'Düzenli ilerleme kayıtları tut',
                'Dinlenme günlerini ihmal etme',
            ],
        ];
        
        return $suggestions[$planType] ?? ['Düzenli takip yap', 'Hedeflerini gözden geçir'];
    }
}
