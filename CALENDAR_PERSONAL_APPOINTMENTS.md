# Calendar Personal Appointments Feature

## Overview
The calendar system now supports personal/blocked appointments that don't require a client. This is useful for blocking time slots for personal events, breaks, or other non-client activities.

## Changes Made

### 1. Database Schema Update
The `wp_rejimde_appointments` table now allows `NULL` for the `client_id` field:

```sql
client_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL for personal/blocked appointments'
```

### 2. Service Layer Updates

#### CalendarService::createAppointment()
- `client_id` is now optional in the `$data` array
- When `client_id` is null or not provided, the appointment is treated as a personal appointment
- Validation updated to only require `date` and `start_time` (removed `client_id` requirement)

**Example usage:**
```php
// Personal appointment (no client)
$appointmentId = $calendarService->createAppointment($expertId, [
    'date' => '2025-01-15',
    'start_time' => '14:00',
    'duration' => 60,
    'title' => 'Personal Time',
    'description' => 'Lunch break',
    'status' => 'confirmed',
    'type' => 'in_person'
]);

// Regular client appointment
$appointmentId = $calendarService->createAppointment($expertId, [
    'client_id' => 456,
    'date' => '2025-01-15',
    'start_time' => '10:00',
    'duration' => 60,
    'title' => 'Nutrition Consultation'
]);
```

#### CalendarService::getAppointments()
- Returns appointments with proper handling of null `client_id`
- Adds `is_personal` flag to response
- `client` field is null for personal appointments

**Response format:**
```json
{
  "id": 123,
  "client": null,
  "is_personal": true,
  "title": "Personal Time",
  "description": "Lunch break",
  "date": "2025-01-15",
  "start_time": "14:00",
  "end_time": "15:00",
  "duration": 60,
  "status": "confirmed",
  "type": "in_person",
  "location": null,
  "meeting_link": null,
  "notes": null
}
```

### 3. API Endpoints

#### Create Personal Appointment
**Endpoint:** `POST /rejimde/v1/pro/calendar/appointments`

**Request (Personal Appointment):**
```json
{
  "date": "2025-01-15",
  "start_time": "14:00",
  "duration": 60,
  "title": "Personal Time",
  "description": "Lunch break",
  "status": "confirmed"
}
```

**Request (Client Appointment):**
```json
{
  "client_id": 456,
  "date": "2025-01-15",
  "start_time": "10:00",
  "duration": 60,
  "title": "Nutrition Consultation"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Appointment created",
  "data": {
    "id": 123,
    "message": "Appointment created successfully"
  }
}
```

#### Get Appointments
**Endpoint:** `GET /rejimde/v1/pro/calendar?start_date=2025-01-01&end_date=2025-01-31`

**Response:**
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "appointments": [
      {
        "id": 123,
        "client": null,
        "is_personal": true,
        "title": "Personal Time",
        "date": "2025-01-15",
        "start_time": "14:00",
        "end_time": "15:00",
        "status": "confirmed"
      },
      {
        "id": 124,
        "client": {
          "id": 456,
          "name": "John Doe",
          "avatar": "https://..."
        },
        "is_personal": false,
        "title": "Nutrition Consultation",
        "date": "2025-01-15",
        "start_time": "10:00",
        "end_time": "11:00",
        "status": "pending"
      }
    ],
    "blocked_times": []
  }
}
```

## Use Cases

### 1. Block Personal Time
```javascript
// Create a personal appointment for lunch break
fetch('/wp-json/rejimde/v1/pro/calendar/appointments', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer TOKEN',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    date: '2025-01-15',
    start_time: '12:00',
    duration: 60,
    title: 'Lunch Break',
    status: 'confirmed',
    type: 'in_person'
  })
});
```

### 2. Mark Time for Admin Tasks
```javascript
// Block time for administrative work
fetch('/wp-json/rejimde/v1/pro/calendar/appointments', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer TOKEN',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    date: '2025-01-15',
    start_time: '16:00',
    duration: 120,
    title: 'Admin Work',
    description: 'Review client plans and prepare reports',
    status: 'confirmed'
  })
});
```

### 3. Frontend Display Logic
```javascript
function displayAppointments(appointments) {
  return appointments.map(apt => {
    if (apt.is_personal) {
      return `
        <div class="appointment personal">
          <h3>${apt.title}</h3>
          <p>Personal Time</p>
          <time>${apt.start_time} - ${apt.end_time}</time>
        </div>
      `;
    } else {
      return `
        <div class="appointment client">
          <h3>${apt.title}</h3>
          <p>Client: ${apt.client.name}</p>
          <time>${apt.start_time} - ${apt.end_time}</time>
        </div>
      `;
    }
  });
}
```

## Important Notes

1. **Conflict Detection**: Personal appointments are treated the same as client appointments for conflict detection - they will block the time slot

2. **Status**: Personal appointments should typically be created with `status: 'confirmed'` since they don't require approval

3. **Notifications**: Personal appointments (null client_id) won't trigger client notifications in the CalendarController

4. **Filtering**: Frontend can filter appointments by `is_personal` flag to separate personal and client appointments

5. **Backwards Compatibility**: Existing appointments with client_id will continue to work normally. The `is_personal` flag will be `false` for all appointments with a client.

## Migration

No migration is needed for existing data. The database schema change is handled by WordPress's `dbDelta()` function which will update the table structure on plugin activation.

Existing appointments will have:
- `client_id`: existing value (not null)
- `is_personal`: false (in API response)

## Testing

### Test Case 1: Create Personal Appointment
```bash
curl -X POST "https://yoursite.com/wp-json/rejimde/v1/pro/calendar/appointments" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2025-01-15",
    "start_time": "14:00",
    "duration": 60,
    "title": "Personal Time"
  }'
```

### Test Case 2: Verify Personal Appointment in List
```bash
curl -X GET "https://yoursite.com/wp-json/rejimde/v1/pro/calendar?start_date=2025-01-01&end_date=2025-01-31" \
  -H "Authorization: Bearer TOKEN"
```

Expected: Response includes appointment with `"client": null` and `"is_personal": true`

### Test Case 3: Create Client Appointment (Existing Behavior)
```bash
curl -X POST "https://yoursite.com/wp-json/rejimde/v1/pro/calendar/appointments" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 456,
    "date": "2025-01-15",
    "start_time": "10:00",
    "duration": 60,
    "title": "Consultation"
  }'
```

Expected: Response includes appointment with client data and `"is_personal": false`
