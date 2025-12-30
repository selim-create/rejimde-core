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
        
        // If client is provided, get their data
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
     * Build AI prompt based on plan type
     * Each plan type has its own specialized prompt structure
     */
    private function buildPrompt(string $planType, array $parameters, $client): string {
        // Get client meta data
        $clientName = $client ? ($client->display_name ?? 'Danışan') : ($parameters['client_name'] ?? 'Danışan');
        $gender = $client ? (get_user_meta($client->ID, 'gender', true) ?: 'belirtilmemiş') : ($parameters['gender'] ?? 'belirtilmemiş');
        $age = $client ? $this->calculateAge(get_user_meta($client->ID, 'birth_date', true)) : ($parameters['age'] ?? 'belirtilmemiş');
        $height = $client ? (get_user_meta($client->ID, 'height', true) ?: 'belirtilmemiş') : ($parameters['height'] ?? 'belirtilmemiş');
        $weight = $client ? (get_user_meta($client->ID, 'current_weight', true) ?: 'belirtilmemiş') : ($parameters['weight'] ?? 'belirtilmemiş');
        $targetWeight = $client ? (get_user_meta($client->ID, 'target_weight', true) ?: 'belirtilmemiş') : ($parameters['target_weight'] ?? 'belirtilmemiş');
        $activityLevel = $client ? (get_user_meta($client->ID, 'activity_level', true) ?: 'orta') : ($parameters['activity_level'] ?? 'orta');
        
        // Extract parameters
        $goal = $parameters['goal'] ?? 'genel sağlık';
        $durationDays = $parameters['duration_days'] ?? 7;
        $restrictions = $parameters['restrictions'] ?? [];
        $preferences = $parameters['preferences'] ?? [];
        $notes = $parameters['additional_notes'] ?? '';
        
        switch ($planType) {
            case 'diet':
                return $this->buildDietPrompt($clientName, $gender, $age, $height, $weight, $targetWeight, $activityLevel, $goal, $durationDays, $restrictions, $preferences, $notes);
                
            case 'workout':
                return $this->buildWorkoutPrompt($clientName, $gender, $age, $height, $weight, $activityLevel, $goal, $durationDays, $restrictions, $preferences, $notes);
                
            case 'flow':
                return $this->buildFlowPrompt($clientName, $gender, $age, $goal, $durationDays, $restrictions, $preferences, $notes);
                
            case 'rehab':
                return $this->buildRehabPrompt($clientName, $gender, $age, $goal, $durationDays, $restrictions, $preferences, $notes);
                
            case 'habit':
                return $this->buildHabitPrompt($clientName, $gender, $age, $goal, $durationDays, $preferences, $notes);
                
            default:
                return $this->buildGenericPrompt($clientName, $planType, $goal, $durationDays, $notes);
        }
    }
    
    /**
     * DIET PLAN PROMPT
     * Generates meal-based nutrition plans with calories, macros, timing
     */
    private function buildDietPrompt($name, $gender, $age, $height, $weight, $targetWeight, $activityLevel, $goal, $days, $restrictions, $preferences, $notes): string {
        $restrictionsText = !empty($restrictions) ? implode(', ', $restrictions) : 'yok';
        $preferencesText = !empty($preferences) ? json_encode($preferences, JSON_UNESCAPED_UNICODE) : 'belirtilmemiş';
        
        return <<<PROMPT
Sen deneyimli bir diyetisyensin. Aşağıdaki danışan için {$days} günlük kişiselleştirilmiş beslenme planı oluştur.

## DANIŞAN BİLGİLERİ
- İsim: {$name}
- Cinsiyet: {$gender}
- Yaş: {$age}
- Boy: {$height} cm
- Mevcut Kilo: {$weight} kg
- Hedef Kilo: {$targetWeight} kg
- Aktivite Seviyesi: {$activityLevel}
- Hedef: {$goal}
- Kısıtlamalar/Alerjiler: {$restrictionsText}
- Tercihler: {$preferencesText}
- Ek Notlar: {$notes}

## PLAN GEREKSİNİMLERİ
1. Her gün için 5-6 öğün planla (kahvaltı, ara öğün, öğle, ara öğün, akşam, isteğe bağlı gece)
2. Her öğün için şunları belirt:
   - Öğün tipi (breakfast, morning_snack, lunch, afternoon_snack, dinner, evening_snack)
   - Saat önerisi
   - Yemek başlığı
   - Detaylı tarif/içerik
   - Yaklaşık kalori
   - Porsiyon önerisi
   - Pratik ipuçları
3. Günlük toplam kaloriyi hesapla
4. Haftanın her günü için farklı menü oluştur
5. Türk mutfağına uygun, ulaşılabilir malzemeler kullan
6. Hazırlama kolaylığını göz önünde bulundur

## ÇIKTI FORMATI
Yanıtını SADECE aşağıdaki JSON formatında ver, başka açıklama ekleme:

{
  "days": [
    {
      "day": 1,
      "day_name": "Pazartesi",
      "total_calories": 1800,
      "meals": [
        {
          "type": "breakfast",
          "time": "08:00",
          "title": "Yulaf Ezmeli Kahvaltı",
          "content": "1 su bardağı yulaf ezmesi, 1 yemek kaşığı bal, yarım muz, 10 adet badem",
          "calories": 350,
          "portion": "1 kase",
          "tip": "Yulafı bir gece önceden hazırlayabilirsiniz"
        }
      ]
    }
  ],
  "weekly_notes": "Haftalık genel öneriler...",
  "shopping_list": ["yulaf ezmesi", "bal", "muz", "badem"]
}
PROMPT;
    }

    /**
     * WORKOUT PLAN PROMPT
     * Generates exercise plans with sets, reps, rest periods
     */
    private function buildWorkoutPrompt($name, $gender, $age, $height, $weight, $activityLevel, $goal, $days, $restrictions, $preferences, $notes): string {
        $restrictionsText = !empty($restrictions) ? implode(', ', $restrictions) : 'yok';
        $equipment = $preferences['equipment'] ?? 'temel ekipman (dambıl, mat)';
        $location = $preferences['location'] ?? 'ev veya spor salonu';
        $duration = $preferences['session_duration'] ?? '45-60 dakika';
        
        return <<<PROMPT
Sen deneyimli bir fitness koçusun. Aşağıdaki danışan için {$days} günlük kişiselleştirilmiş egzersiz programı oluştur.

## DANIŞAN BİLGİLERİ
- İsim: {$name}
- Cinsiyet: {$gender}
- Yaş: {$age}
- Boy: {$height} cm
- Kilo: {$weight} kg
- Fitness Seviyesi: {$activityLevel}
- Hedef: {$goal}
- Fiziksel Kısıtlamalar: {$restrictionsText}
- Mevcut Ekipman: {$equipment}
- Antrenman Yeri: {$location}
- Seans Süresi: {$duration}
- Ek Notlar: {$notes}

## PLAN GEREKSİNİMLERİ
1. Her gün için antrenman tipi belirle (push, pull, legs, full body, cardio, rest)
2. Her egzersiz için şunları belirt:
   - Egzersiz adı
   - Hedef kas grubu
   - Set sayısı
   - Tekrar sayısı veya süre
   - Dinlenme süresi
   - Teknik ipuçları
   - Video referansı için anahtar kelime
3. Isınma ve soğuma hareketlerini dahil et
4. Progresyon önerileri ekle
5. Her seans için tahmini süre belirt

## ÇIKTI FORMATI
Yanıtını SADECE aşağıdaki JSON formatında ver:

{
  "days": [
    {
      "day": 1,
      "day_name": "Pazartesi",
      "workout_type": "Push Day - Göğüs & Omuz",
      "duration_minutes": 50,
      "warmup": [
        {
          "name": "Jumping Jacks",
          "duration": "2 dakika"
        }
      ],
      "exercises": [
        {
          "name": "Dumbbell Bench Press",
          "muscle_group": "Göğüs",
          "sets": 4,
          "reps": "10-12",
          "rest_seconds": 90,
          "technique_tip": "Dirsekleri 45 derece açıda tutun",
          "video_keyword": "dumbbell bench press"
        }
      ],
      "cooldown": [
        {
          "name": "Göğüs Germe",
          "duration": "30 saniye"
        }
      ],
      "notes": "Ağırlıkları zorlanmadan kaldırabildiğinizden emin olun"
    }
  ],
  "progression_notes": "Her hafta ağırlıkları %5 artırmayı hedefleyin",
  "equipment_needed": ["dambıl seti", "düz bench", "mat"]
}
PROMPT;
    }

    /**
     * FLOW PLAN PROMPT (Yoga/Pilates)
     * Generates pose sequences with timing and transitions
     */
    private function buildFlowPrompt($name, $gender, $age, $goal, $days, $restrictions, $preferences, $notes): string {
        $restrictionsText = !empty($restrictions) ? implode(', ', $restrictions) : 'yok';
        $style = $preferences['style'] ?? 'Hatha Yoga';
        $level = $preferences['level'] ?? 'başlangıç-orta';
        $duration = $preferences['session_duration'] ?? '30-45 dakika';
        $focus = $preferences['focus'] ?? 'genel esneklik ve rahatlama';
        
        return <<<PROMPT
Sen deneyimli bir yoga/pilates eğitmenisin. Aşağıdaki danışan için {$days} günlük kişiselleştirilmiş akış programı oluştur.

## DANIŞAN BİLGİLERİ
- İsim: {$name}
- Cinsiyet: {$gender}
- Yaş: {$age}
- Hedef: {$goal}
- Stil Tercihi: {$style}
- Seviye: {$level}
- Seans Süresi: {$duration}
- Odak Alanı: {$focus}
- Fiziksel Kısıtlamalar: {$restrictionsText}
- Ek Notlar: {$notes}

## PLAN GEREKSİNİMLERİ
1. Her gün için tematik bir akış oluştur (sabah enerjisi, akşam rahatlaması, güç, esneklik vb.)
2. Her poz için şunları belirt:
   - Poz adı (Türkçe ve Sanskrit)
   - Kalış süresi veya nefes sayısı
   - Sağ/sol taraf bilgisi (varsa)
   - Alignment ipuçları
   - Modifikasyon seçenekleri
   - Geçiş bilgisi
3. Nefes çalışmalarını (pranayama) dahil et
4. Meditasyon/savasana ile bitir
5. Zorluk seviyesini kademeli artır

## ÇIKTI FORMATI
Yanıtını SADECE aşağıdaki JSON formatında ver:

{
  "days": [
    {
      "day": 1,
      "day_name": "Pazartesi",
      "theme": "Sabah Uyanış Akışı",
      "duration_minutes": 35,
      "intention": "Güne enerji ve farkındalıkla başlamak",
      "props_needed": ["yoga matı", "blok (opsiyonel)"],
      "warmup": [
        {
          "name": "Nefes Farkındalığı",
          "duration": "2 dakika",
          "cue": "Derin karın nefesi ile başlayın"
        }
      ],
      "poses": [
        {
          "name_tr": "Kedi-İnek",
          "name_sanskrit": "Marjaryasana-Bitilasana",
          "duration": "5 nefes",
          "side": "bilateral",
          "alignment": "Omurga nötr pozisyonda, nefesle hareket senkronize",
          "modification": "Dizlerin altına battaniye koyabilirsiniz",
          "transition": "Nefes verin ve aşağı bakan köpeğe geçin"
        }
      ],
      "pranayama": {
        "name": "Ujjayi Nefes",
        "duration": "3 dakika",
        "instruction": "Boğazı hafifçe daraltarak okyanus sesi çıkarın"
      },
      "savasana_duration": "5 dakika",
      "closing_intention": "Bu pratiğin enerjisini gününüze taşıyın"
    }
  ],
  "weekly_focus": "Bu hafta omurga hareketliliği ve nefes farkındalığına odaklanıyoruz",
  "home_practice_tips": "Pratik öncesi 2 saat yemek yemeyin"
}
PROMPT;
    }

    /**
     * REHAB PLAN PROMPT
     * Generates rehabilitation/recovery exercise plans
     */
    private function buildRehabPrompt($name, $gender, $age, $goal, $days, $restrictions, $preferences, $notes): string {
        $condition = $preferences['condition'] ?? 'genel toparlanma';
        $painLevel = $preferences['pain_level'] ?? 'hafif';
        $phase = $preferences['phase'] ?? 'erken rehabilitasyon';
        $affectedArea = $preferences['affected_area'] ?? 'belirtilmemiş';
        $restrictionsText = !empty($restrictions) ? implode(', ', $restrictions) : 'yok';
        
        return <<<PROMPT
Sen deneyimli bir fizyoterapist/rehabilitasyon uzmanısın. Aşağıdaki danışan için {$days} günlük kişiselleştirilmiş rehabilitasyon programı oluştur.

## ÖNEMLİ UYARI
Bu plan genel rehberlik amaçlıdır. Danışan mutlaka doktor/fizyoterapist kontrolünde olmalıdır.

## DANIŞAN BİLGİLERİ
- İsim: {$name}
- Cinsiyet: {$gender}
- Yaş: {$age}
- Durum/Yaralanma: {$condition}
- Etkilenen Bölge: {$affectedArea}
- Ağrı Seviyesi: {$painLevel}
- Rehabilitasyon Fazı: {$phase}
- Hedef: {$goal}
- Kısıtlamalar: {$restrictionsText}
- Ek Notlar: {$notes}

## PLAN GEREKSİNİMLERİ
1. Rehabilitasyon fazına uygun egzersizler seç
2. Her egzersiz için şunları belirt:
   - Egzersiz adı
   - Hedef/amaç
   - Pozisyon
   - Hareket açıklaması
   - Tekrar sayısı
   - Set sayısı
   - Tutma süresi (varsa)
   - Ağrı eşiği uyarısı
   - İlerleme kriterleri
3. Günlük frekans öner
4. Buz/sıcak uygulama zamanlaması
5. Kırmızı bayrakları (warning signs) belirt

## ÇIKTI FORMATI
Yanıtını SADECE aşağıdaki JSON formatında ver:

{
  "days": [
    {
      "day": 1,
      "day_name": "Pazartesi",
      "phase": "Faz 1 - Ağrı Kontrolü ve Hareket Açıklığı",
      "sessions_per_day": 2,
      "duration_minutes": 20,
      "exercises": [
        {
          "name": "Ayak Bileği Pompalama",
          "purpose": "Kan dolaşımını artırmak, ödem kontrolü",
          "position": "Sırt üstü yatış, bacak düz",
          "description": "Ayak parmakları yukarı-aşağı hareket ettirin",
          "sets": 3,
          "reps": 15,
          "hold_seconds": 0,
          "pain_threshold": "Hafif gerginlik olabilir, keskin ağrı olmamalı",
          "progression": "Ağrısız yapabildiğinizde direnç bandı ekleyin"
        }
      ],
      "ice_heat": {
        "type": "buz",
        "duration": "15 dakika",
        "timing": "Egzersiz sonrası",
        "note": "Bezi arada tutun, doğrudan cilde değmesin"
      },
      "daily_tips": "Gün içinde her saat ayağa kalkıp birkaç adım atın"
    }
  ],
  "red_flags": [
    "Egzersiz sırasında keskin, batan ağrı",
    "Şişlik veya morlukta artış",
    "Uyuşma veya karıncalanma",
    "Ateş veya kızarıklık"
  ],
  "progression_criteria": "Bir fazdan diğerine geçiş için 3 gün ağrısız egzersiz gerekli",
  "follow_up_note": "Haftada bir fizyoterapist kontrolü önerilir"
}
PROMPT;
    }

    /**
     * HABIT PLAN PROMPT
     * Generates daily habit checklists and behavior change plans
     */
    private function buildHabitPrompt($name, $gender, $age, $goal, $days, $preferences, $notes): string {
        $currentHabits = $preferences['current_habits'] ?? 'belirtilmemiş';
        $targetHabits = $preferences['target_habits'] ?? 'sağlıklı yaşam alışkanlıkları';
        $challenges = $preferences['challenges'] ?? 'zaman yönetimi';
        $motivation = $preferences['motivation_style'] ?? 'pozitif pekiştirme';
        
        return <<<PROMPT
Sen deneyimli bir yaşam koçu ve davranış değişikliği uzmanısın. Aşağıdaki danışan için {$days} günlük kişiselleştirilmiş alışkanlık geliştirme planı oluştur.

## DANIŞAN BİLGİLERİ
- İsim: {$name}
- Cinsiyet: {$gender}
- Yaş: {$age}
- Ana Hedef: {$goal}
- Mevcut Alışkanlıklar: {$currentHabits}
- Hedef Alışkanlıklar: {$targetHabits}
- Zorluklar: {$challenges}
- Motivasyon Tarzı: {$motivation}
- Ek Notlar: {$notes}

## PLAN GEREKSİNİMLERİ
1. Her gün için günlük kontrol listesi oluştur
2. Her alışkanlık için şunları belirt:
   - Alışkanlık adı
   - Kategori (uyku, beslenme, hareket, zihin, sosyal, üretkenlik)
   - Önerilen zaman
   - Süre veya miktar
   - Tetikleyici (cue)
   - Küçük kazanım (reward)
   - Başarısızlık stratejisi
3. Haftalık ilerleme hedefleri koy
4. Motivasyon mesajları ekle
5. "Habit stacking" tekniğini kullan (mevcut alışkanlıklara yeni eklemek)

## ÇIKTI FORMATI
Yanıtını SADECE aşağıdaki JSON formatında ver:

{
  "days": [
    {
      "day": 1,
      "day_name": "Pazartesi",
      "theme": "Sabah Rutini Temeli",
      "morning_message": "Yeni bir haftaya güçlü başlıyoruz!",
      "habits": [
        {
          "name": "Su İç",
          "category": "beslenme",
          "time": "07:00",
          "target": "1 bardak ılık su",
          "cue": "Yataktan kalktığında",
          "reward": "Kendini taze hissetmek",
          "if_skip": "Bir sonraki öğünde 2 bardak iç",
          "why": "Metabolizmayı uyandırır, toksinleri atar",
          "difficulty": "kolay"
        },
        {
          "name": "5 Dakika Germe",
          "category": "hareket",
          "time": "07:15",
          "target": "5 dakika hafif esneme",
          "cue": "Su içtikten sonra",
          "reward": "Vücudun uyanık hissetmesi",
          "if_skip": "Öğlen 2 dakikalık germe yap",
          "why": "Kan dolaşımını artırır, enerji verir",
          "difficulty": "kolay"
        }
      ],
      "evening_reflection": "Bugün hangi alışkanlığı en kolay yaptın? Neden?",
      "daily_challenge": "Telefonu yatak odasına sokmadan uyumayı dene"
    }
  ],
  "weekly_goals": [
    {
      "week": 1,
      "focus": "Sabah rutini oluşturma",
      "success_criteria": "7 günün 5'inde sabah rutinini tamamla"
    }
  ],
  "habit_stack_suggestions": [
    "Kahve içerken → Günlük 3 şükür yaz",
    "Dişlerini fırçalarken → 30 saniye tek ayakta dur"
  ],
  "accountability_tips": "Her akşam bir arkadaşına günün özeti at"
}
PROMPT;
    }

    /**
     * Generic prompt for unknown plan types
     */
    private function buildGenericPrompt($name, $planType, $goal, $days, $notes): string {
        return <<<PROMPT
Aşağıdaki danışan için {$days} günlük {$planType} planı oluştur.

Danışan: {$name}
Hedef: {$goal}
Notlar: {$notes}

Yanıtını JSON formatında ver, her gün için detaylı aktiviteler içersin.
PROMPT;
    }

    /**
     * Calculate age from birth date
     */
    private function calculateAge(?string $birthDate): string {
        if (!$birthDate) return 'belirtilmemiş';
        
        try {
            $birth = new \DateTime($birthDate);
            $today = new \DateTime();
            $age = $today->diff($birth)->y;
            return (string) $age;
        } catch (\Exception $e) {
            return 'belirtilmemiş';
        }
    }

    /**
     * Parse AI response into structured data
     */
    private function parseAIResponse(string $content, string $planType): array {
        // Extract JSON from response (handle markdown code blocks)
        $json = $content;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $json = $matches[1];
        }
        
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to fix common JSON issues
            $json = preg_replace('/,\s*}/', '}', $json);
            $json = preg_replace('/,\s*\]/', ']', $json);
            $data = json_decode($json, true);
        }
        
        if (!$data || !isset($data['days'])) {
            return ['error' => 'Failed to parse AI response', 'raw' => $content];
        }
        
        return $data;
    }
    
    /**
     * Generate suggestions based on plan type
     * 
     * @param string $planType Plan type
     * @param array $parameters Parameters
     * @return array
     */
    private function generateSuggestions(string $planType, array $parameters): array {
        $suggestions = [];
        
        switch ($planType) {
            case 'diet':
                $suggestions = [
                    'Haftalık alışveriş listesi hazırlayın',
                    'Öğünleri önceden hazırlamak zaman kazandırır',
                    'Su tüketimini takip etmeyi unutmayın'
                ];
                break;
            case 'workout':
                $suggestions = [
                    'Her antrenmandan önce 5-10 dakika ısının',
                    'İlerlemenizi kaydedin',
                    'Yeterli uyku almaya özen gösterin'
                ];
                break;
            case 'flow':
                $suggestions = [
                    'Sakin ve sessiz bir ortam hazırlayın',
                    'Pratik öncesi hafif atıştırın',
                    'Nefese odaklanın'
                ];
                break;
            case 'rehab':
                $suggestions = [
                    'Ağrı sınırlarınızı dinleyin',
                    'Düzenlilik iyileşmeyi hızlandırır',
                    'Doktorunuzla iletişimde kalın'
                ];
                break;
            case 'habit':
                $suggestions = [
                    'Küçük adımlarla başlayın',
                    'İlerlemenizi görünür kılın',
                    'Kendinize sabırlı olun'
                ];
                break;
        }
        
        return $suggestions;
    }
}
