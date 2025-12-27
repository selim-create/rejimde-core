# Calendar & Appointment System - Quick Reference

## Overview
The Calendar & Appointment System provides comprehensive functionality for experts to manage their availability, appointments, and client appointment requests.

## Database Tables

### 1. `wp_rejimde_availability`
Stores expert's weekly availability template.
- `expert_id`: Expert user ID
- `day_of_week`: 0=Sunday, 1=Monday, ..., 6=Saturday
- `start_time`, `end_time`: Time range for availability
- `slot_duration`: Default 60 minutes
- `buffer_time`: Default 15 minutes between appointments

### 2. `wp_rejimde_appointments`
Stores all appointments.
- `expert_id`, `client_id`: Participants
- `appointment_date`, `start_time`, `end_time`: Scheduling
- `status`: pending, confirmed, cancelled, completed, no_show
- `type`: online, in_person, phone
- `meeting_link`: For online appointments

### 3. `wp_rejimde_appointment_requests`
Stores appointment requests from clients/guests.
- `expert_id`: Target expert
- `requester_id`: NULL for guests
- `preferred_date/time`: Primary choice
- `alternative_date/time`: Backup choice
- `status`: pending, approved, rejected, expired

### 4. `wp_rejimde_blocked_times`
Stores times when expert is unavailable.
- `expert_id`: Expert user ID
- `blocked_date`: Date to block
- `start_time`, `end_time`: NULL for all-day block
- `reason`: Optional note

## API Endpoints Summary

### Expert Endpoints (`/pro/calendar`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/pro/calendar` | Get appointments & blocked times |
| GET | `/pro/calendar/availability` | Get availability template |
| POST | `/pro/calendar/availability` | Update availability template |
| POST | `/pro/calendar/appointments` | Create appointment |
| PATCH | `/pro/calendar/appointments/{id}` | Update appointment |
| POST | `/pro/calendar/appointments/{id}/cancel` | Cancel appointment |
| POST | `/pro/calendar/appointments/{id}/complete` | Mark completed |
| POST | `/pro/calendar/appointments/{id}/no-show` | Mark no-show |
| GET | `/pro/calendar/requests` | List appointment requests |
| POST | `/pro/calendar/requests/{id}/approve` | Approve request |
| POST | `/pro/calendar/requests/{id}/reject` | Reject request |
| POST | `/pro/calendar/block` | Block time |
| DELETE | `/pro/calendar/block/{id}` | Unblock time |

### Client/Public Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/experts/{expertId}/availability` | Get available slots |
| POST | `/appointments/request` | Create appointment request |
| GET | `/me/appointments` | Get my appointments |
| POST | `/me/appointments/{id}/cancel` | Cancel my appointment |

## Service Methods (`CalendarService`)

### Availability
```php
getAvailability(int $expertId): array
updateAvailability(int $expertId, array $data): bool
getAvailableSlots(int $expertId, string $date): array
getAvailableSlotsRange(int $expertId, string $startDate, string $endDate): array
```

### Appointments
```php
getAppointments(int $expertId, string $startDate, string $endDate, ?string $status): array
createAppointment(int $expertId, array $data): int|array
updateAppointment(int $appointmentId, array $data): bool|array
cancelAppointment(int $appointmentId, int $cancelledBy, ?string $reason): bool
completeAppointment(int $appointmentId): bool
markNoShow(int $appointmentId): bool
```

### Requests
```php
getRequests(int $expertId, ?string $status): array
approveRequest(int $requestId, array $appointmentData): int|array
rejectRequest(int $requestId, ?string $reason): bool
createRequest(array $data): int|array
```

### Blocked Times
```php
getBlockedTimes(int $expertId, string $startDate, string $endDate): array
blockTime(int $expertId, array $data): int|false
unblockTime(int $blockId, int $expertId): bool
```

### Utilities
```php
checkConflict(int $expertId, string $date, string $startTime, string $endTime, ?int $excludeId): bool
isSlotAvailable(int $expertId, string $date, string $time): bool
```

## Notification Integration

The system automatically sends notifications for:
- New appointment request received (to expert)
- Appointment request approved (to client)
- Appointment request rejected (to client)
- Appointment created manually (to client)
- Appointment cancelled (to other party)

## Usage Examples

### 1. Expert Sets Weekly Availability
```php
POST /pro/calendar/availability
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

### 2. Client Views Available Slots
```php
GET /experts/123/availability?date=2025-12-30
// Returns: ["09:00", "10:00", "11:00", "14:00", "15:00", "16:00"]
```

### 3. Client Requests Appointment
```php
POST /appointments/request
{
  "expert_id": 123,
  "preferred_date": "2025-12-30",
  "preferred_time": "10:00",
  "name": "Ahmet Yılmaz",
  "email": "ahmet@email.com"
}
```

### 4. Expert Approves Request
```php
POST /pro/calendar/requests/1/approve
{
  "date": "2025-12-30",
  "start_time": "10:00",
  "type": "online",
  "meeting_link": "https://meet.google.com/xxx"
}
```

### 5. Expert Blocks Time
```php
// Block specific hours
POST /pro/calendar/block
{
  "date": "2025-12-30",
  "start_time": "09:00",
  "end_time": "12:00",
  "reason": "Toplantı"
}

// Block entire day
POST /pro/calendar/block
{
  "date": "2025-12-31",
  "all_day": true,
  "reason": "Tatil"
}
```

## Key Features

✅ **Availability Templates**: Weekly recurring schedule
✅ **Flexible Scheduling**: Set custom slot duration and buffer time
✅ **Conflict Prevention**: Automatic overlap checking
✅ **Guest Requests**: Non-members can request appointments
✅ **Blocked Times**: Block specific hours or entire days
✅ **Appointment Lifecycle**: pending → confirmed → completed/no_show
✅ **Notifications**: Automatic notifications for all key events
✅ **Client Portal**: Clients can view and cancel their appointments

## Security

- Expert endpoints require `user_type = 'expert'`
- Clients can only cancel their own appointments
- Request approval requires expert authentication
- All dates and times are validated

## Performance

- Indexed queries for fast availability lookup
- Efficient conflict checking using database indexes
- Date range queries optimized with composite indexes

## Future Enhancements

- [ ] Recurring appointments
- [ ] Appointment reminders (24h, 1h before)
- [ ] Calendar sync (Google Calendar, iCal)
- [ ] Video meeting integration
- [ ] Payment integration
- [ ] Cancellation policies
- [ ] Waitlist functionality
