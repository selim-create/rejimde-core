# Bot Simülasyon Sistemi Backend İmplementasyon Özeti

## Genel Bakış
Rejimde platformunun beta aşamasında gerçek kullanıcı davranışlarını simüle eden bot sisteminin backend altyapısı başarıyla oluşturulmuştur.

## Yapılan Değişiklikler

### 1. UserMeta.php - Bot Meta Alanları Eklendi
**Dosya:** `includes/Core/UserMeta.php`

Eklenen Meta Alanları:
- `is_simulation` (Boolean): Kullanıcının simülasyon botu olup olmadığını belirtir
- `simulation_persona` (String): Bot persona tipi (super_active, active, normal, low_activity, dormant, diet_focused, exercise_focused)
- `simulation_batch` (String): Bot'un hangi batch'te oluşturulduğu (örn: batch_1736100000)
- `simulation_active` (Boolean): Bot'un aktif/pasif durumu

Bu alanlar WordPress REST API üzerinden erişilebilir ve güncellenebilir.

### 2. AdminBotController.php - Bot Yönetim Controller'ı Oluşturuldu
**Dosya:** `includes/Api/V1/AdminBotController.php`

#### Endpoint'ler:

**GET `/rejimde/v1/admin/bots/stats`**
- Bot istatistiklerini döndürür
- Toplam bot sayısı, aktif/pasif bot sayısı
- Persona dağılımı
- Batch listesi ve her batch'teki bot sayıları

**POST `/rejimde/v1/admin/bots/toggle-all`**
- Tüm botları aktif/pasif yapar
- Body: `{"active": true/false}`
- Etkilenen bot sayısını döndürür

**POST `/rejimde/v1/admin/bots/toggle-batch/{batch_id}`**
- Belirli bir batch'teki botları aktif/pasif yapar
- Body: `{"active": true/false}`
- Batch ID URL parametresi olarak gönderilir

**GET `/rejimde/v1/admin/bots/exclude-ids`**
- Analytics raporlarında exclude edilecek bot ID'lerini döndürür
- Tüm simulation kullanıcılarının ID listesi

**GET `/rejimde/v1/admin/bots/list`**
- Filtrelenebilir bot listesi
- Query parametreleri:
  - `limit`: Sayfa başına sonuç (varsayılan: 50)
  - `offset`: Başlangıç noktası (varsayılan: 0)
  - `batch_id`: Batch'e göre filtrele
  - `persona`: Persona tipine göre filtrele
  - `active_only`: Sadece aktif botları getir (true/false)

**DELETE `/rejimde/v1/admin/bots/batch/{batch_id}`**
- Belirli bir batch'teki tüm botları kalıcı olarak siler
- Body: `{"confirm": true}` (güvenlik için onay gerekli)
- Silinen bot sayısını döndürür

#### Güvenlik:
- Tüm endpoint'ler `manage_options` yetkisi gerektirir (sadece admin)

### 3. AdminSettingsController.php - Admin Ayarları Controller'ı Oluşturuldu
**Dosya:** `includes/Api/V1/AdminSettingsController.php`

#### Endpoint'ler:

**GET `/rejimde/v1/admin/settings/ai`**
- OpenAI API ayarlarını döndürür
- `openai_api_key`: API anahtarı
- `openai_model`: Kullanılan model (varsayılan: gpt-4o-mini)

**GET `/rejimde/v1/admin/settings/bot-config`**
- Bot sistem konfigürasyonunu döndürür
- API base URL'i
- Persona tipleri ve özellikleri
- Aktif platform özellikleri (water_tracking, steps_tracking, meal_photos)

#### Güvenlik:
- Tüm endpoint'ler `manage_options` yetkisi gerektirir (sadece admin)

### 4. Loader.php - Controller Kayıtları Eklendi
**Dosya:** `includes/Core/Loader.php`

Eklenen kayıtlar:
- AdminBotController dosya yüklemesi
- AdminSettingsController dosya yüklemesi
- AdminBotController route kaydı
- AdminSettingsController route kaydı

### 5. AuthController.php - Bot Field'ları Kabul Edildi
**Dosya:** `includes/Api/V1/AuthController.php`

`register_user` metoduna eklenen özellik:
- Bot simulation field'ları artık kayıt sırasında kabul edilir
- Simülasyon kullanıcıları normal kayıt endpoint'i üzerinden oluşturulabilir
- Meta parametrelerinde `is_simulation`, `simulation_persona`, `simulation_batch`, `simulation_active` alanları gönderilebilir

## API Kullanım Örnekleri

### Bot Kullanıcı Oluşturma
```bash
POST /rejimde/v1/auth/register
{
  "username": "bot_user_001",
  "email": "bot001@example.com",
  "password": "secure_password",
  "role": "rejimde_user",
  "meta": {
    "name": "Bot Kullanıcı 001",
    "is_simulation": "1",
    "simulation_persona": "super_active",
    "simulation_batch": "batch_1736100000",
    "simulation_active": "1"
  }
}
```

### Bot İstatistikleri Görüntüleme
```bash
GET /rejimde/v1/admin/bots/stats
Authorization: Bearer {admin_token}
```

### Tüm Botları Pasife Alma
```bash
POST /rejimde/v1/admin/bots/toggle-all
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "active": false
}
```

### Belirli Batch'i Aktif Etme
```bash
POST /rejimde/v1/admin/bots/toggle-batch/batch_1736100000
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "active": true
}
```

### Bot Listesini Filtreleyerek Getirme
```bash
GET /rejimde/v1/admin/bots/list?persona=super_active&active_only=true&limit=20
Authorization: Bearer {admin_token}
```

### Analytics için Exclude ID'leri Alma
```bash
GET /rejimde/v1/admin/bots/exclude-ids
Authorization: Bearer {admin_token}
```

## Persona Tipleri

| Persona | Açıklama | AI Desteği |
|---------|----------|------------|
| super_active | Süper Aktif Kullanıcı | ✓ |
| active | Aktif Kullanıcı | - |
| normal | Normal Kullanıcı | - |
| low_activity | Düşük Aktivite | - |
| dormant | Uykuda | - |
| diet_focused | Diyet Odaklı | - |
| exercise_focused | Egzersiz Odaklı | - |

## Veritabanı Sorguları

Bot istatistikleri ve yönetimi için optimize edilmiş SQL sorguları kullanılır:

1. **Toplam Bot Sayısı**: `is_simulation = '1'` meta key'i ile filtreleme
2. **Aktif Bot Sayısı**: `is_simulation = '1'` ve `simulation_active = '1'` JOIN sorgusu
3. **Persona Dağılımı**: `simulation_persona` meta key'ine göre GROUP BY
4. **Batch Listesi**: `simulation_batch` meta key'i ile gruplama ve kullanıcı kayıt tarihi

## Güvenlik Özellikleri

1. **Admin Yetkisi Kontrolü**: Tüm bot yönetim endpoint'leri `manage_options` capability gerektirir
2. **Onay Mekanizması**: Batch silme işlemi `confirm: true` parametresi gerektirir
3. **Input Sanitization**: Tüm kullanıcı girdileri `sanitize_text_field()` ile temizlenir
4. **Prepared Statements**: SQL injection'a karşı koruma için hazır sorgular kullanılır

## Test Durumu

✅ Tüm dosyalar oluşturuldu ve sözdizimi kontrolleri başarılı
✅ Controller sınıfları doğru namespace'te tanımlandı
✅ Tüm gerekli metodlar implement edildi
✅ UserMeta field'ları REST API'ye expose edildi
✅ Loader.php'de controller kayıtları yapıldı
✅ AuthController bot field'larını kabul ediyor
✅ Validation script başarılı

## Sonraki Adımlar

1. **WordPress Ortamında Test**: Endpoint'lerin çalıştığını doğrula
2. **Bot Sistemi Entegrasyonu**: Bot oluşturma ve yönetim sistemini entegre et
3. **Frontend Dashboard**: Admin panelinde bot yönetim arayüzü oluştur
4. **Analytics Entegrasyonu**: Exclude ID'leri kullanarak analytics'te bot kullanıcıları filtrele
5. **Monitoring**: Bot aktivitelerini izleme sistemi kur

## Teknik Notlar

- Bot field'ları boolean değerler kullanır ancak WordPress user meta'da string olarak saklanır ('0' veya '1')
- Batch ID formatı: `batch_{timestamp}` şeklinde önerilir
- Aktif/pasif durumları `simulation_active` meta key'i ile kontrol edilir
- Analytics entegrasyonunda `get_exclude_ids` endpoint'i kullanılmalıdır

## Versiyon Bilgisi
- İmplementasyon Tarihi: 2026-01-05
- Rejimde Core Versiyon: 1.0.3.2
- Etkilenen Dosyalar: 5 dosya (2 yeni, 3 güncelleme)
