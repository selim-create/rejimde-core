<?php
namespace Rejimde\Services;

class OpenAIService {

    private $api_key;
    private $model;
    private $pexels_key;
    private $unsplash_key;
    private $image_provider;

    public function __construct() {
        $this->api_key = get_option('rejimde_openai_api_key', '');
        $this->model = get_option('rejimde_openai_model', 'gpt-4o'); 
        $this->pexels_key = get_option('rejimde_pexels_api_key', '');
        $this->unsplash_key = get_option('rejimde_unsplash_api_key', '');
        $this->image_provider = get_option('rejimde_image_provider', 'pexels');
    }

    // --- DİYET METODLARI ---
    public function generate_diet_plan($user_data) {
        if (empty($this->api_key)) {
            return $this->get_mock_data($user_data);
        }

        $prompt = $this->build_diet_prompt($user_data);
        $ai_response = $this->call_openai($prompt);

        if (is_wp_error($ai_response)) {
            return $ai_response;
        }

        return $this->process_diet_response($ai_response, $user_data);
    }

    // --- EGZERSİZ METODLARI ---
    public function generate_exercise_plan($user_data) {
        if (empty($this->api_key)) {
            return $this->get_mock_exercise_data($user_data);
        }

        $prompt = $this->build_exercise_prompt($user_data);
        $ai_response = $this->call_openai($prompt);

        if (is_wp_error($ai_response)) {
            return $ai_response;
        }

        return $this->process_exercise_response($ai_response, $user_data);
    }

    // --- HELPER: YANIT İŞLEME ---
    private function process_diet_response($json_data, $user_data) {
        if (is_string($json_data)) {
            $json_data = json_decode($json_data, true);
        }

        if (!is_array($json_data)) {
            return new \WP_Error('json_error', 'AI yanıtı geçerli formatta değil.');
        }

        if (isset($json_data['plan_data']) && is_array($json_data['plan_data'])) {
            foreach ($json_data['plan_data'] as &$day) {
                if (!isset($day['id'])) $day['id'] = uniqid('day_');
                
                if (isset($day['meals']) && is_array($day['meals'])) {
                    foreach ($day['meals'] as &$meal) {
                        if (!isset($meal['id'])) $meal['id'] = uniqid('meal_');
                        if (empty($meal['type'])) $meal['type'] = 'snack';
                        if (empty($meal['content']) && !empty($meal['title'])) {
                            $meal['content'] = $meal['title'];
                        }
                    }
                }
            }
        }

        if (isset($json_data['image_keyword']) && !empty($json_data['image_keyword'])) {
            $json_data['featured_image_url'] = $this->fetch_relevant_image($json_data['image_keyword']);
        } else {
            $search = $json_data['title'] ?? 'healthy food diet';
            $json_data['featured_image_url'] = $this->fetch_relevant_image($search);
        }
        
        return $json_data;
    }

    private function process_exercise_response($json_data, $user_data) {
        if (is_string($json_data)) $json_data = json_decode($json_data, true);
        if (!is_array($json_data)) return new \WP_Error('json_error', 'AI yanıtı geçersiz.');

        // Egzersiz ID ve Yapı Kontrolü
        if (isset($json_data['plan_data']) && is_array($json_data['plan_data'])) {
            foreach ($json_data['plan_data'] as &$day) {
                if (!isset($day['id'])) $day['id'] = uniqid('eday_');
                
                if (isset($day['exercises']) && is_array($day['exercises'])) {
                    foreach ($day['exercises'] as &$ex) {
                        if (!isset($ex['id'])) $ex['id'] = uniqid('ex_');
                        // Varsayılan değerler
                        if (empty($ex['sets'])) $ex['sets'] = '3';
                        if (empty($ex['reps'])) $ex['reps'] = '10';
                        if (empty($ex['rest'])) $ex['rest'] = '60';
                    }
                }
            }
        }

        // Görsel
        $keyword = $json_data['image_keyword'] ?? ($json_data['title'] ?? 'fitness workout');
        $json_data['featured_image_url'] = $this->fetch_relevant_image($keyword);

        return $json_data;
    }

    // --- GÖRSEL API (HİBRİT) ---
    private function fetch_relevant_image($keyword) {
        $keyword_encoded = urlencode($keyword);

        // 1. Pexels
        if (($this->image_provider === 'pexels' || empty($this->unsplash_key)) && !empty($this->pexels_key)) {
            $img = $this->fetch_from_pexels($keyword_encoded);
            if ($img) return $img;
        }

        // 2. Unsplash
        if (!empty($this->unsplash_key)) {
            $img = $this->fetch_from_unsplash($keyword_encoded);
            if ($img) return $img;
        }

        // 3. Fallback Pexels
        if ($this->image_provider === 'unsplash' && !empty($this->pexels_key)) {
             $img = $this->fetch_from_pexels($keyword_encoded);
             if ($img) return $img;
        }

        return "https://placehold.co/800x600?text=" . $keyword_encoded;
    }

    private function fetch_from_pexels($keyword) {
        $response = wp_remote_get("https://api.pexels.com/v1/search?query={$keyword}&per_page=1&orientation=landscape&size=large", [
            'headers' => ['Authorization' => $this->pexels_key],
            'timeout' => 5
        ]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return $body['photos'][0]['src']['large'] ?? false;
        }
        return false;
    }

    private function fetch_from_unsplash($keyword) {
        $response = wp_remote_get("https://api.unsplash.com/search/photos?query={$keyword}&per_page=1&orientation=landscape&client_id={$this->unsplash_key}", [
            'timeout' => 5
        ]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return $body['results'][0]['urls']['regular'] ?? false;
        }
        return false;
    }

    /**
     * Generic completion API call
     * Used by AIPlannerService for plan generation
     * 
     * @param array $params OpenAI API parameters
     * @return array|null Response data or null on error
     */
    public function generateCompletion(array $params): ?array {
        if (empty($this->api_key)) {
            return ['error' => 'OpenAI API key not configured'];
        }
        
        $messages = $params['messages'] ?? [];
        $maxTokens = $params['max_tokens'] ?? 2000;
        $temperature = $params['temperature'] ?? 0.7;
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ]),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return ['error' => $data['error']['message'] ?? 'OpenAI API error'];
        }
        
        return $data;
    }

    // --- OPENAI CALL ---
    private function call_openai($prompt) {
        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'Sen Rejimde.com için çalışan uzman bir spor antrenörü ve diyetisyensin. JSON formatında yanıt ver.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'response_format' => ['type' => 'json_object']
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 180 
        ]);

        if (is_wp_error($response)) {
            error_log('OpenAI Error: ' . $response->get_error_message());
            return $response;
        }

        $body_content = wp_remote_retrieve_body($response);
        $data = json_decode($body_content, true);

        if (isset($data['error'])) return new \WP_Error('openai_error', $data['error']['message']);

        $content = $data['choices'][0]['message']['content'] ?? '';
        return preg_replace('/^```json\s*|\s*```$/s', '', $content); 
    }

    // --- PROMPTLAR ---
    
    private function build_diet_prompt($data) {
        $days = isset($data['days']) ? intval($data['days']) : 3;
        if (!in_array($days, [1, 3, 7, 15])) $days = 3;

        // Puan Hesaplama Logic'i (Frontend'deki isteğe göre)
        $scoreReward = 100;
        if ($days == 1) $scoreReward = 5;
        elseif ($days == 3) $scoreReward = 15;
        elseif ($days == 7) $scoreReward = 25;
        elseif ($days == 15) $scoreReward = 50;

        $chronic = isset($data['chronic_diseases']) ? $data['chronic_diseases'] : 'Yok';
        $meds = isset($data['medications']) ? $data['medications'] : 'Yok';
        $allergies = isset($data['allergies']) ? $data['allergies'] : 'Yok';
        $dislikes = isset($data['dislikes']) ? $data['dislikes'] : 'Yok';
        $mealsCount = isset($data['meals_count']) ? $data['meals_count'] : '3';
        
        return "Aşağıdaki kullanıcı için TAM OLARAK {$days} GÜNLÜK, eksiksiz bir diyet planı oluştur. Gün sayısı kesinlikle {$days} olmalıdır.

        KULLANICI PROFİLİ:
        - Hedef: {$data['goal']} 
        - Özellikler: {$data['gender']}, {$data['age']} yaş, {$data['weight']}kg, {$data['height']}cm
        - Aktivite: {$data['activity_level']}
        - Diyet Tipi: {$data['diet_type']} ({$data['cuisine']} Mutfağı ağırlıklı)
        - Öğün Sayısı: {$mealsCount} (Eğer {$mealsCount} belirtilmediyse, diyete uygun olarak 2 ile 6 arasında ideal sayıyı belirle).

        SAĞLIK & KISITLAMALAR:
        - Kronik Hastalıklar: {$chronic}
        - İlaçlar: {$meds}
        - Alerjiler: {$allergies}
        - Sevilmeyenler: {$dislikes}

        İSTENEN ÇIKTI FORMATI (JSON):
        {
            \"title\": \"Diyet Planı Başlığı\",
            \"description\": \"Planın genel stratejisi ve motivasyon yazısı.\",
            \"image_keyword\": \"diet plan cover image keyword english\",
            \"shopping_list\": [\"Malzeme 1\", \"Malzeme 2\"],
            \"tags\": [\"etiket1\", \"etiket2\"],
            \"meta\": {
                \"difficulty\": \"medium\", 
                \"calories\": \"1650\", // SADECE RAKAM YAZ. '1500-1800' YAZMA. Tek bir ortalama değer.
                \"duration\": \"{$days}\",
                \"score_reward\": \"{$scoreReward}\", // Otomatik hesaplandı
                \"diet_category\": \"{$data['diet_type']}\",
                \"rank_math_title\": \"SEO Başlığı\",
                \"rank_math_description\": \"SEO Açıklaması\"
            },
            \"plan_data\": [
                // BURADA TAM {$days} ADET GÜN OLMALIDIR.
                {
                    \"dayNumber\": 1,
                    \"meals\": [
                        {
                            \"type\": \"breakfast\", // SADECE ŞUNLARDAN BİRİ OLABİLİR: 'breakfast', 'lunch', 'dinner', 'snack', 'pre-workout', 'post-workout'
                            \"time\": \"08:00\",
                            \"title\": \"Öğün Adı\",
                            \"content\": \"Detaylı içerik...\",
                            \"calories\": \"300\",
                            \"tags\": [\"Protein\"],
                            \"tip\": \"Püf noktası\"
                        }
                    ]
                }
            ]
        }";
    }

    private function build_exercise_prompt($data) {
        // 1. Program İskeleti
        $days_duration = isset($data['days']) ? intval($data['days']) : 7; // Varsayılan 7 gün
        $freq = isset($data['days_per_week']) ? intval($data['days_per_week']) : 3;
        $days_available = isset($data['available_days']) ? (is_array($data['available_days']) ? implode(', ', $data['available_days']) : $data['available_days']) : 'Esnek';
        
        // 2. Hedef & Öncelik
        $goal = $data['goal'] ?? 'Genel Fitness';
        $priority = $data['priority'] ?? 'Dengeli';
        $target_area = $data['target_area'] ?? 'Tüm Vücut';
        
        // 3. Ekipman & Ortam
        $equipment_main = $data['equipment'] ?? 'Vücut Ağırlığı';
        $equipment_details = isset($data['equipment_details']) ? (is_array($data['equipment_details']) ? implode(', ', $data['equipment_details']) : $data['equipment_details']) : 'Yok';
        $silent_mode = !empty($data['silent_mode']) ? 'Evet (Apartman Modu: Zıplama/Gürültü yok)' : 'Hayır';
        
        // 4. Kısıtlar & Seviye
        $level = $data['fitness_level'] ?? 'beginner';
        $limitations = $data['limitations'] ?? 'Yok';
        $limitations_detail = isset($data['limitations_detail']) ? $data['limitations_detail'] : '';
        $capacity = ($data['capacity_pushups'] ?? '?') . ' şınav, ' . ($data['capacity_plank'] ?? '?') . ' plank';
        
        // 5. Tercihler
        $split = $data['split_preference'] ?? 'Full Body';
        $workout_type = $data['workout_type'] ?? 'Karışık';
        $dislikes = isset($data['disliked_exercises']) ? (is_array($data['disliked_exercises']) ? implode(', ', $data['disliked_exercises']) : $data['disliked_exercises']) : 'Yok';
        $warmup = !empty($data['warmup']) ? 'Evet (Planın başına ekle)' : 'Hayır';
        $duration_daily = $data['duration'] ?? '30';

        // Puanlama
        $scoreReward = $days_duration <= 3 ? 15 : ($days_duration <= 7 ? 25 : ($days_duration <= 14 ? 50 : 100));

        // İzin verilen kategoriler
        $allowed_categories = [
            "Kardiyo", "Güç Antrenmanı", "HIIT", "Yoga", 
            "Pilates", "CrossFit", "Vücut Ağırlığı", 
            "Esneklik", "Rehabilitasyon", "Başlangıç Seviyesi"
        ];
        $categories_str = implode(', ', $allowed_categories);

        return "Sen uzman bir spor antrenörüsün. Aşağıdaki kullanıcı profiline göre EXCEL kalitesinde, periyotlaması yapılmış, detaylı bir antrenman programı oluştur.

        KULLANICI PROFİLİ:
        - Program Süresi: {$days_duration} GÜN
        - Sıklık: Haftada {$freq} gün ({$days_available})
        - Seviye: {$level} (Test Sonucu: {$capacity})
        - Ana Hedef: {$goal} (Öncelik: {$priority})
        - Hedef Bölge: {$target_area}
        - Antrenman Tarzı: {$workout_type} (Tercih edilen split: {$split})
        - Ekipman Durumu: {$equipment_main} (Detay: {$equipment_details})
        - Kısıtlamalar/Sakatlıklar: {$limitations} {$limitations_detail}
        - Sevilmeyen Hareketler: {$dislikes}
        - Ortam: Sessiz mod {$silent_mode}
        - Isınma/Soğuma: {$warmup}
        - Günlük Süre: {$duration_daily} dk
        - Biyometrik: {$data['gender']}, {$data['age']} yaş, {$data['weight']}kg, {$data['height']}cm

        KURALLAR:
        1. JSON formatında yanıt ver.
        2. 'exercise_category' alanı SADECE şu listeden biri olabilir: [{$categories_str}].
        3. Hareket isimleri Türkçe olsun (Parantez içinde İngilizcesi).
        4. Her hareket için 'notes' kısmına form ipuçları, nefes taktiği veya tempoyu ekle.
        5. 'rest' süresi saniye cinsinden (örn: 60).
        6. 'image_keyword' alanı program kapağı için İngilizce terim.
        7. Haftalık programa göre günleri 'dayNumber' 1'den {$days_duration}'a kadar sırala. Dinlenme günlerini de 'Dinlenme' başlığıyla boş egzersiz listesi olarak ekleyebilirsin veya sadece antrenman günlerini verebilirsin (Kullanıcı sıklığına göre). Önerim: Kullanıcının seçtiği {$days_duration} günlük süre boyunca yapması gerekenleri listele.

        İSTENEN JSON FORMATI:
        {
            \"title\": \"Program Başlığı (Örn: {$days_duration} Günlük Evde Güç İnşası)\",
            \"description\": \"Programın stratejisi, split mantığı ve kime uygun olduğu hakkında profesyonel özet.\",
            \"image_keyword\": \"fitness training gym weights cover\",
            \"equipment_list\": [\"Dambıl\", \"Mat\"],
            \"tags\": [\"Güç\", \"Evde\", \"Hipertrofi\"],
            \"meta\": {
                \"difficulty\": \"{$level}\", 
                \"calories\": \"350\", // Ortalama yakılan (Sadece rakam)
                \"duration\": \"{$duration_daily}\", // Seans süresi (dk)
                \"program_duration\": \"{$days_duration}\",
                \"score_reward\": \"{$scoreReward}\",
                \"exercise_category\": \"Güç Antrenmanı\", // Listeden seç
                \"rank_math_title\": \"SEO Başlığı\",
                \"rank_math_description\": \"SEO Açıklaması\"
            },
            \"plan_data\": [
                {
                    \"dayNumber\": 1,
                    \"title\": \"Gün 1: İtiş (Push) Odaklı\",
                    \"exercises\": [
                        {
                            \"name\": \"Şınav (Push Up)\",
                            \"sets\": \"3\",
                            \"reps\": \"12-15\",
                            \"rest\": \"60\",
                            \"notes\": \"Dirsekler 45 derece, core sıkı. İnerken nefes al.\",
                            \"tags\": [\"Göğüs\", \"Triceps\"]
                        }
                    ]
                }
            ]
        }";
    }

  // --- MOCK DATA ---
    private function get_mock_data($data) {
        $days = isset($data['days']) ? intval($data['days']) : 3;
        $mock_days = [];
        
        for ($i = 1; $i <= $days; $i++) {
            $mock_days[] = [
                'dayNumber' => $i,
                'id' => uniqid('day_'),
                'meals' => [
                    [
                        'id' => uniqid('m'), 'type' => 'breakfast', 'time' => '08:00', 'title' => 'Örnek Kahvaltı', 
                        'content' => '2 Yumurta, Yeşillik', 'calories' => '250', 'tags' => ['Protein'], 'tip' => 'Bol su için'
                    ],
                    [
                        'id' => uniqid('m'), 'type' => 'lunch', 'time' => '13:00', 'title' => 'Izgara Tavuk', 
                        'content' => '150g Tavuk, Salata', 'calories' => '400', 'tags' => ['Hafif'], 'tip' => ''
                    ]
                ]
            ];
        }

        return [
            'title' => 'Örnek Diyet Planı (Demo)',
            'description' => 'API anahtarı girilmediği için demo veri.',
            'featured_image_url' => 'https://placehold.co/800x600?text=Demo+Plan',
            'shopping_list' => ['Yumurta', 'Tavuk', 'Yeşillik'],
            'tags' => ['Demo'],
            'meta' => ['difficulty'=>'medium', 'duration'=> (string)$days, 'calories'=>'1500', 'score_reward'=>'15'],
            'plan_data' => $mock_days
        ];
    }

    private function get_mock_exercise_data($data) {
        return [
            'title' => 'Demo Egzersiz (API Key Yok)',
            'description' => 'Demo veri.',
            'featured_image_url' => 'https://placehold.co/800x600?text=Workout',
            'meta' => ['duration' => '30', 'calories' => '300'],
            'plan_data' => [
                [
                    'dayNumber' => 1,
                    'exercises' => [['name' => 'Squat', 'sets' => '3', 'reps' => '15', 'rest' => '60', 'notes' => 'Dik durun']]
                ]
            ]
        ];
    }
}