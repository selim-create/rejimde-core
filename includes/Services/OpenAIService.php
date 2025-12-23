<?php
namespace Rejimde\Services;

class OpenAIService {

    private $api_key;
    private $model;

    public function __construct() {
    // DOĞRU option adları
    $this->api_key = get_option('rejimde_openai_api_key', '');
    $this->model = get_option('rejimde_openai_model', 'gpt-3.5-turbo');
}

    // --- DİYET METODLARI ---
    public function generate_diet_plan($user_data) {
        if (empty($this->api_key)) {
            return $this->get_mock_data($user_data);
        }

        $prompt = $this->build_diet_prompt($user_data);
        return $this->call_openai($prompt);
    }

    // --- EGZERSİZ METODLARI (YENİ) ---
    public function generate_exercise_plan($user_data) {
        if (empty($this->api_key)) {
            return $this->get_mock_exercise_data($user_data);
        }

        $prompt = $this->build_exercise_prompt($user_data);
        return $this->call_openai($prompt);
    }

    // --- ORTAK OPENAI ÇAĞRISI ---
    private function call_openai($prompt) {
        $body = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Sen profesyonel bir diyetisyen ve spor antrenörüsün. Sadece geçerli bir JSON formatında yanıt ver. Markdown veya ek açıklama kullanma.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body_content = wp_remote_retrieve_body($response);
        $data = json_decode($body_content, true);

        if (isset($data['error'])) {
            return new \WP_Error('openai_error', $data['error']['message']);
        }

        if (isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];
            $content = str_replace(['```json', '```'], '', $content); // Temizlik
            return json_decode($content, true);
        }

        return new \WP_Error('invalid_response', 'Yapay zekadan geçersiz yanıt alındı.');
    }

    // --- PROMPT OLUŞTURUCULAR ---

    private function build_diet_prompt($data) {
        return "Aşağıdaki kullanıcı için 3 günlük diyet listesi oluştur. JSON formatında ver.
        Kullanıcı: {$data['age']} yaşında, {$data['gender']}, {$data['weight']}kg, {$data['height']}cm. Hedef: {$data['goal']}. Tercih: {$data['diet_type']}. Alerji: {$data['allergies']}.
        
        JSON Yapısı:
        {
            \"title\": \"Diyet Başlığı\",
            \"description\": \"Açıklama\",
            \"meta\": { \"difficulty\": \"medium\", \"calories\": \"1500\", \"diet_category\": \"Hızlı Sonuç\", \"duration\": \"3\" },
            \"shopping_list\": [\"Elma\", \"Yumurta\"],
            \"tags\": [\"Keto\", \"Hızlı\"],
            \"plan_data\": [
                {
                    \"dayNumber\": 1,
                    \"meals\": [
                        { \"type\": \"breakfast\", \"time\": \"08:00\", \"title\": \"Yumurta\", \"content\": \"2 haşlanmış yumurta\", \"calories\": \"150\" }
                    ]
                }
            ]
        }";
    }

    private function build_exercise_prompt($data) {
        return "Aşağıdaki kullanıcı için 3 günlük egzersiz programı oluştur. JSON formatında ver.
        Kullanıcı: {$data['age']} yaşında, {$data['gender']}, {$data['weight']}kg, {$data['height']}cm.
        Fitness Seviyesi: {$data['fitness_level']} (beginner/intermediate/advanced).
        Hedef: {$data['goal']} (Örn: Kas yapmak, yağ yakmak).
        Ekipman Durumu: {$data['equipment']} (Örn: Sadece vücut ağırlığı, spor salonu).
        Antrenman Süresi: Günde {$data['duration']} dakika.
        Kısıtlamalar/Sakatlıklar: {$data['limitations']}.

        ÖNEMLİ:
        - Hareket isimlerini Türkçe ver (Parantez içinde İngilizcesi olabilir).
        - 'notes' kısmında hareketin nasıl yapılacağını kısaca anlat.
        - 'rest' süresini saniye cinsinden ver (sadece sayı).
        
        JSON Yapısı:
        {
            \"title\": \"Program Başlığı (Örn: 3 Günlük Evde Yağ Yakımı)\",
            \"description\": \"Programın amacı ve kimler için uygun olduğu hakkında kısa bilgi.\",
            \"meta\": {
                \"difficulty\": \"easy/medium/hard\",
                \"calories\": \"Ortalama yakılan kalori\",
                \"exercise_category\": \"Kardiyo/Güç/HIIT vb.\",
                \"duration\": \"30\"
            },
            \"equipment_list\": [\"Mat\", \"Dambıl\"],
            \"tags\": [\"Evde\", \"Ekipmansız\"],
            \"plan_data\": [
                {
                    \"dayNumber\": 1,
                    \"exercises\": [
                        {
                            \"name\": \"Şınav (Push Up)\",
                            \"sets\": \"3\",
                            \"reps\": \"12\",
                            \"rest\": \"60\",
                            \"notes\": \"Vücudunuzu düz tutun, göğsünüzü yere yaklaştırın.\",
                            \"tags\": [\"Göğüs\", \"Triceps\"]
                        }
                    ]
                }
            ]
        }";
    }

    // --- MOCK DATA ---

    private function get_mock_data($data) {
         return [
            'title' => 'AI Destekli Diyet (Demo)',
            'description' => 'API anahtarı girilmediği için demo veri gösteriliyor.',
            'meta' => ['difficulty' => 'medium', 'calories' => '2000', 'diet_category' => 'Standart', 'duration' => '3'],
            'shopping_list' => ['Su', 'Ekmek'],
            'tags' => ['Demo'],
            'plan_data' => []
        ];
    }

    private function get_mock_exercise_data($data) {
        return [
            'title' => 'AI Destekli Egzersiz (Demo)',
            'description' => "Bu bir demo plandır. {$data['goal']} hedefine uygun olarak hazırlanmıştır.",
            'meta' => [
                'difficulty' => 'medium',
                'calories' => '300',
                'exercise_category' => 'Vücut Ağırlığı',
                'duration' => '30'
            ],
            'equipment_list' => ['Mat', 'Su Şişesi'],
            'tags' => ['Evde', 'Ekipmansız'],
            'plan_data' => [
                [
                    'dayNumber' => 1,
                    'exercises' => [
                        [
                            'name' => 'Jumping Jacks',
                            'sets' => '3',
                            'reps' => '20',
                            'rest' => '30',
                            'notes' => 'Kollarınızı ve bacaklarınızı eş zamanlı açıp kapatarak zıplayın.',
                            'tags' => ['Kardiyo', 'Isınma']
                        ],
                        [
                            'name' => 'Squat',
                            'sets' => '3',
                            'reps' => '15',
                            'rest' => '45',
                            'notes' => 'Sırtınızı dik tutun, sandalyeye oturur gibi çömelin.',
                            'tags' => ['Bacak', 'Kalça']
                        ]
                    ]
                ]
            ]
        ];
    }
}