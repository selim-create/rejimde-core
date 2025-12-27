# Calendar & Appointment System API - Test Scenarios

This document provides test scenarios for all Calendar & Appointment System endpoints.

## Base URL
```
/wp-json/rejimde/v1
```

## Test Data Setup

### Prerequisites
- Expert user with `user_type` = 'expert' (Expert ID: 123)
- Client user (Client ID: 456)
- Service/Package ID (if applicable)

## Expert Endpoints

### 1. Get Availability Template
**Endpoint:** `GET /pro/calendar/availability`
**Auth:** Expert required
**Expected Response:**
```json
{
  "status": "success",
  "data": {
    "slot_duration": 60,
    "buffer_time": 15,
    "schedule": [
      {
        "day": 1,
        "day_name": "Pazartesi",
        "slots": []
      },
      ...
    ]
  }
}
```

### 2. Update Availability Template
**Endpoint:** `POST /pro/calendar/availability`
**Auth:** Expert required
**Request Body:**
```json
{
  "slot_duration": 60,
  "buffer_time": 15,
  "schedule": [
    { "day": 1, "start_time": "09:00", "end_time": "12:00" },
    { "day": 1, "start_time": "14:00", "end_time": "18:00" },
    { "day": 2, "start_time": "09:00", "end_time": "17:00" }
  ]
}
```
**Expected Response:**
```json
{
  "status": "success",
  "message": "Availability updated successfully"
}
```

### 3. Get Calendar (Appointments & Blocked Times)
**Endpoint:** `GET /pro/calendar?start_date=2025-12-27&end_date=2026-01-03&status=all`
**Auth:** Expert required
**Expected Response:**
```json
{
  "status": "success",
  "data": {
    "appointments": [],
    "blocked_times": []
  }
}
```

### 4. Create Appointment (Manual)
**Endpoint:** `POST /pro/calendar/appointments`
**Auth:** Expert required
**Request Body:**
```json
{
  "client_id": 456,
  "service_id": 1,
  "title": "Online Danışmanlık",
  "date": "2025-12-30",
  "start_time": "10:00",
  "duration": 60,
  "type": "online",
  "notes": "İlk görüşme"
}
```
**Expected Response:**
```json
{
  "status": "success",
  "message": "Appointment created",
  "data": {
    "id": 1,
    "message": "Appointment created successfully"
  }
}
```

### 5. Update Appointment
**Endpoint:** `PATCH /pro/calendar/appointments/1`
**Auth:** Expert required
**Request Body:**
```json
{
  "date": "2025-12-31",
  "start_time": "11:00",
  "status": "confirmed"
}
```
**Expected Response:**
```json
{
  "status": "success",
  "message": "Appointment updated successfully"
}
```

### 6. Cancel Appointment
**Endpoint:** `POST /pro/calendar/appointments/1/cancel`
**Auth:** Expert required
**Request Body:**
```json
{
  "reason": "Danışan talebi"
}
```
**Expected Response:**
```json
{
  "status": "success",
  "message": "Appointment cancelled successfully"
}
```

### 7. Complete Appointment
**Endpoint:** `POST /pro/calendar/appointments/1/complete`
**Auth:** Expert required
**Expected Response:**
```json
{
  "status": "success",
  "message": "Appointment marked as completed"
}
```

### 8. Mark No-Show
**Endpoint:** `POST /pro/calendar/appointments/1/no-show`
**Auth:** Expert required
**Expected Response:**
```json
{
  "status": "success",
  "message": "Appointment marked as no-show"
}
```

### 9. Get Appointment Requests
**Endpoint:** `GET /pro/calendar/requests?status=pending`
**Auth:** Expert required
**Expected Response:**
```json
{
  "status": "success",
  "data": [],
  "meta": {
    "total": 0,
    "pending": 0
  }
}
```

### 10. Approve Appointment Request
**Endpoint:** `POST /pro/calendar/requests/1/approve`
**Auth:** Expert required
**Request Body:**
```json
{
  "date": "2025-12-28",
  "start_time": "10:00",
  "type": "online",
  "meeting_link": "https://meet.google.com/xxx"
}
```
**Expected Response:**
```json
{
  "status": "success",
  "data": {
    "appointment_id": 2,
    "message": "Request approved and appointment created"
  }
}
```

### 11. Reject Appointment Request
**Endpoint:** `POST /pro/calendar/requests/1/reject`
**Auth:** Expert required
**Request Body:**
```json
{
  "reason": "Bu tarihte müsait değilim"
}
```
**Expected Response:**
```json
{
  "status": "success",
  "message": "Request rejected"
}
```

### 12. Block Time
**Endpoint:** `POST /pro/calendar/block`
**Auth:** Expert required
**Request Body (Partial Day):**
```json
{
  "date": "2025-12-30",
  "start_time": "09:00",
  "end_time": "12:00",
  "reason": "Toplantı"
}
```
**Request Body (All Day):**
```json
{
  "date": "2025-12-31",
  "all_day": true,
  "reason": "Yılbaşı tatili"
}
```
**Expected Response:**
```json
{
  "status": "success",
  "message": "Time blocked",
  "data": {
    "id": 1,
    "message": "Time blocked successfully"
  }
}
```

### 13. Unblock Time
**Endpoint:** `DELETE /pro/calendar/block/1`
**Auth:** Expert required
**Expected Response:**
```json
{
  "status": "success",
  "message": "Time unblocked successfully"
}
```

## Client/Public Endpoints

### 14. Get Expert's Available Slots (Single Date)
**Endpoint:** `GET /experts/123/availability?date=2025-12-30`
**Auth:** Public (no auth required)
**Expected Response:**
```json
{
  "status": "success",
  "data": {
    "expert": {
      "id": 123,
      "name": "Dr. Ayşe Kaya",
      "avatar": "https://..."
    },
    "available_slots": {
      "2025-12-30": ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00"]
    },
    "slot_duration": 60
  }
}
```

### 15. Get Expert's Available Slots (Date Range)
**Endpoint:** `GET /experts/123/availability?start_date=2025-12-28&end_date=2025-12-31`
**Auth:** Public (no auth required)
**Expected Response:**
```json
{
  "status": "success",
  "data": {
    "expert": {
      "id": 123,
      "name": "Dr. Ayşe Kaya",
      "avatar": "https://..."
    },
    "available_slots": {
      "2025-12-28": ["09:00", "10:00", "11:00"],
      "2025-12-29": ["10:00", "11:00", "14:00"],
      "2025-12-30": ["09:00", "14:00", "15:00"]
    },
    "slot_duration": 60
  }
}
```

### 16. Create Appointment Request (Member)
**Endpoint:** `POST /appointments/request`
**Auth:** Optional (user can be logged in or guest)
**Request Body:**
```json
{
  "expert_id": 123,
  "service_id": 1,
  "preferred_date": "2025-12-28",
  "preferred_time": "10:00",
  "alternative_date": "2025-12-29",
  "alternative_time": "14:00",
  "name": "Ahmet Yılmaz",
  "email": "ahmet@email.com",
  "phone": "0555...",
  "message": "İlk danışmanlık için randevu almak istiyorum"
}
```
**Expected Response:**
```json
{
  "status": "success",
  "message": "Request created",
  "data": {
    "id": 1,
    "message": "Appointment request created successfully"
  }
}
```

### 17. Get My Appointments (Client View)
**Endpoint:** `GET /me/appointments?status=all`
**Auth:** User required
**Expected Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "expert": {
        "id": 123,
        "name": "Dr. Ayşe Kaya",
        "avatar": "https://..."
      },
      "title": "Online Danışmanlık",
      "date": "2025-12-28",
      "start_time": "10:00",
      "end_time": "11:00",
      "duration": 60,
      "status": "confirmed",
      "type": "online",
      "meeting_link": "https://meet.google.com/xxx"
    }
  ]
}
```

### 18. Cancel My Appointment (Client)
**Endpoint:** `POST /me/appointments/1/cancel`
**Auth:** User required
**Request Body:**
```json
{
  "reason": "Acil bir durumum çıktı"
}
```
**Expected Response:**
```json
{
  "status": "success",
  "message": "Appointment cancelled successfully"
}
```

## Test Scenarios

### Scenario 1: Complete Flow - Expert Sets Availability
1. Expert logs in
2. GET `/pro/calendar/availability` - Check current availability
3. POST `/pro/calendar/availability` - Set weekly schedule
4. GET `/pro/calendar/availability` - Verify schedule saved

### Scenario 2: Complete Flow - Client Requests Appointment
1. Client (guest or member) views expert profile
2. GET `/experts/123/availability?date=2025-12-30` - See available slots
3. POST `/appointments/request` - Submit request
4. Expert receives notification
5. Expert GET `/pro/calendar/requests` - See pending requests
6. Expert POST `/pro/calendar/requests/1/approve` - Approve request
7. Client receives notification
8. Client GET `/me/appointments` - See confirmed appointment

### Scenario 3: Complete Flow - Expert Creates Manual Appointment
1. Expert logs in
2. POST `/pro/calendar/appointments` - Create appointment for client
3. Client receives notification
4. GET `/pro/calendar?start_date=...&end_date=...` - Verify in calendar

### Scenario 4: Conflict Prevention
1. Expert sets availability for Monday 09:00-17:00
2. Create appointment for Monday 10:00-11:00
3. Try to create overlapping appointment for Monday 10:30-11:30
4. Should fail with "Time slot is not available"

### Scenario 5: Block Time
1. Expert logs in
2. POST `/pro/calendar/block` - Block December 31st all day
3. GET `/experts/123/availability?date=2025-12-31` - Should return empty slots
4. Try to create appointment for Dec 31st - Should fail

### Scenario 6: Appointment Lifecycle
1. Create appointment (status: pending)
2. PATCH appointment - Update status to confirmed
3. POST `/appointments/{id}/complete` - Mark as completed
4. Verify status changed to completed

## Notification Integration Test

### Events that should trigger notifications:
1. ✅ New appointment request → Expert notification
2. ✅ Appointment request approved → Client notification
3. ✅ Appointment request rejected → Client notification
4. ✅ Appointment created manually → Client notification
5. ✅ Appointment cancelled by expert → Client notification
6. ✅ Appointment cancelled by client → Expert notification

## Error Cases to Test

1. **Missing required fields**
   - POST appointment without client_id
   - POST appointment without date/time
   - Expected: 400 error with message

2. **Invalid expert auth**
   - Try expert endpoints without being logged in
   - Try expert endpoints as regular user
   - Expected: 401/403 error

3. **Appointment not found**
   - PATCH /appointments/9999
   - Expected: 404 error

4. **Time conflict**
   - Create overlapping appointment
   - Expected: 400 error "Time slot is not available"

5. **Invalid date format**
   - Use incorrect date format
   - Expected: Handled gracefully

## Performance Considerations

- Availability calculation should be efficient for date ranges
- Conflict checking should use indexed queries
- Consider caching for frequently accessed availability data
