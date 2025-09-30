# القطر فين – Where's My Train - System Architecture

## 1. System Overview

**القطر فين – Where's My Train** is a comprehensive train tracking and community platform consisting of:
- **Mobile App**: Flutter-based iOS/Android app with Arabic/English support (default: Arabic)
- **Admin Dashboard**: Filament-powered Laravel admin panel with bilingual data management
- **Backend API**: Laravel with MCP architecture, versioned APIs, and real-time capabilities
- **Real-time Engine**: Laravel Reverb for live updates and community features

## 2. Architecture Layers

### 2.1 Frontend Layer
- **Mobile App (Flutter)**
  - Platform: iOS & Android
  - State Management: Riverpod/Bloc
  - Localization: Default Arabic, English support
  - Real-time: WebSocket connection to Laravel Reverb
  - Authentication: Phone verification + Social logins

- **Admin Dashboard (Filament)**
  - Framework: Laravel Filament
  - Multi-language: Spatie Translatable integration
  - Role-based access control
  - Real-time monitoring capabilities

### 2.2 Backend Layer
- **API Gateway**: Laravel with versioned routes (/api/v1/)
- **Authentication**: Laravel Sanctum + Social providers
- **Real-time**: Laravel Reverb WebSocket server
- **Queue System**: Redis for background jobs
- **Cache Layer**: Redis for performance optimization
- **File Storage**: Laravel filesystem (local/S3)

### 2.3 Data Layer
- **Primary Database**: MySQL/PostgreSQL
- **Cache Storage**: Redis
- **Session Storage**: Redis
- **File Storage**: Local/S3-compatible storage

## 3. Core Modules

### 3.1 Authentication Module
```
Features:
- Phone number verification (OTP)
- Social logins (Google, Apple, Facebook)
- Guest user support for read-only access
- JWT token management
- Rate limiting for security

Components:
- AuthController
- PhoneVerificationService
- SocialAuthService
- OtpService
```

### 3.2 Train Management Module
```
Features:
- Train information and stop sequences
- Station management with bilingual support
- Real-time location tracking
- Delay detection and alerts
- Historical data analytics

Components:
- TrainController (Business rules compliant)
- StationController (Stop-based departures/arrivals)
- TrackingService
- DelayDetectionService
```

### 3.3 Passenger Assignment Module
```
Features:
- Passenger-to-train assignments
- Location sharing during trips
- Trip completion tracking
- Reward calculation (10 assignments = 1 week ad-free)

Components:
- AssignmentController
- LocationSharingService
- TripTrackingService
- RewardCalculationService
```

### 3.4 Community Module
```
Features:
- Temporary trip-based communities
- Structured messaging (station + time selection)
- Guest viewing, authenticated participation
- Auto-reset per trip
- Activity badges and rewards

Components:
- CommunityController
- MessageController
- BadgeService
- CommunityModerationService
```

### 3.5 Gamification Module
```
Features:
- Badge system for active users
- Reward points calculation
- Ad-free periods management
- Leaderboards and achievements

Components:
- BadgeController
- RewardController
- AchievementService
- LeaderboardService
```

### 3.6 Real-time Module
```
Features:
- Live train location broadcasts
- Community message updates
- Delay notifications
- System announcements

Components:
- ReverbChannels
- TrainLocationBroadcast
- CommunityMessageBroadcast
- NotificationBroadcast
```

## 4. Technology Stack

### 4.1 Backend
- **Framework**: Laravel 10+
- **Database**: MySQL 8.0+ / PostgreSQL 14+
- **Cache/Queue**: Redis 7+
- **Real-time**: Laravel Reverb
- **Search**: Laravel Scout (optional for advanced search)
- **Localization**: Spatie Laravel Translatable
- **Admin Panel**: Filament 3.x
- **API Documentation**: Scribe/L5-Swagger

### 4.2 Frontend
- **Mobile**: Flutter 3.x
- **State Management**: Riverpod or Bloc
- **HTTP Client**: Dio
- **WebSocket**: web_socket_channel
- **Localization**: flutter_localizations
- **Maps**: Google Maps / OpenStreetMap
- **Push Notifications**: Firebase Cloud Messaging

### 4.3 Infrastructure
- **Web Server**: Nginx
- **Application Server**: PHP-FPM
- **Queue Worker**: Laravel Horizon
- **Monitoring**: Laravel Telescope (development)
- **Logging**: Laravel Log channels
- **Deployment**: Docker containers

## 5. API Architecture

### 5.1 API Versioning
```
Base URL: https://api.qatarfein.com/api/v1/
Authentication: Bearer token (Sanctum)
Content-Type: application/json
Accept-Language: ar (default) | en
```

### 5.2 Core Endpoints
```
Authentication:
POST /auth/phone/send-otp
POST /auth/phone/verify
POST /auth/social/{provider}
POST /auth/logout

Trains:
GET /trains
GET /trains/{id}
GET /trains/{id}/schedule (shows stops sequence)
GET /trains/{id}/location
GET /stations
GET /stations/{id}/departures (stop-based)
GET /stations/{id}/arrivals (stop-based)

Assignments:
POST /assignments
GET /assignments/my
PUT /assignments/{id}/location
DELETE /assignments/{id}

Community:
GET /communities/{trainId}
POST /communities/{trainId}/messages
GET /communities/{trainId}/messages

Rewards:
GET /rewards/my
GET /badges/my
GET /leaderboard
```

### 5.3 WebSocket Channels
```
Train Tracking:
- trains.{trainId}.location
- trains.{trainId}.delays

Community:
- communities.{tripId}.messages
- communities.{tripId}.users

Notifications:
- users.{userId}.notifications
- global.announcements
```

## 6. Database Schema Overview

### 6.1 Core Tables (Business Rules Structure)
```
users (id, phone, email, name, avatar, verification_status, ad_free_until, created_at, updated_at)
trains (id, train_id, name, type, operator, status, created_at, updated_at)
stations (id, code, name, latitude, longitude, city, created_at, updated_at)
stops (id, train_id, station_id, stop_number, arrival_time, departure_time, platform)
no_stops (id, train_id, stop_number, reason) -- Express behavior
train_trips (id, train_id, trip_date, status, current_station_id, created_at, updated_at)
passenger_assignments (id, user_id, trip_id, boarding_station_id, destination_station_id, status)
communities (id, trip_id, name, member_count, message_count, status, created_at, updated_at)
community_messages (id, community_id, user_id, station_id, time_passed_minutes, message_type)
badges (id, name, description, icon, criteria, rarity, created_at, updated_at)
user_badges (id, user_id, badge_id, earned_at)
rewards (id, user_id, type, points_earned, description, created_at)
```

### 6.2 Translation Support (Egyptian Data)
```
All translatable fields use JSON format for Arabic/English:
- stations: name {"ar": "محطة القاهرة", "en": "Cairo Station"}
- trains: name, type, operator (bilingual JSON)
- badges: name, description (bilingual JSON)
- community_messages: structured data with station context
- no_stops: reason (bilingual explanation for express behavior)
```

## 7. Security Considerations

### 7.1 Authentication & Authorization
- Phone number verification with OTP
- JWT token expiration and refresh
- Rate limiting on sensitive endpoints
- Role-based access control (RBAC)
- Guest user restrictions

### 7.2 Data Protection
- Input validation and sanitization
- SQL injection prevention
- XSS protection
- CSRF protection
- Encrypted sensitive data storage

### 7.3 API Security
- API rate limiting
- Request size limits
- CORS configuration
- SSL/TLS encryption
- API key management for admin access

## 8. Performance Optimizations

### 8.1 Caching Strategy
- Redis for session storage
- Model caching for static data (stations, schedules)
- API response caching
- Real-time data temporary storage

### 8.2 Database Optimization
- Proper indexing on frequently queried fields
- Database query optimization
- Connection pooling
- Read replica for analytics

### 8.3 Real-time Optimization
- Efficient WebSocket connection management
- Channel-based broadcasting
- Background job processing
- Memory usage optimization

## 9. Deployment Architecture

### 9.1 Production Environment
```
Load Balancer (Nginx)
├── Web Servers (Multiple Laravel instances)
├── WebSocket Server (Laravel Reverb)
├── Queue Workers (Laravel Horizon)
├── Database (MySQL Master/Slave)
├── Cache Layer (Redis Cluster)
└── File Storage (S3-compatible)
```

### 9.2 Development Environment
```
Docker Compose:
- Laravel Application Container
- MySQL Container
- Redis Container
- Nginx Container
- Reverb Container
```

## 10. Monitoring & Analytics

### 10.1 Application Monitoring
- Laravel Telescope for debugging
- Error tracking and reporting
- Performance monitoring
- Real-time user activity tracking

### 10.2 Business Analytics
- User engagement metrics
- Train tracking accuracy
- Community participation rates
- Revenue analytics (ads, premium features)

This architecture provides a scalable, maintainable foundation for the "القطر فين – Where's My Train" system with full bilingual support and real-time capabilities.