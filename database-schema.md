# Database Schema - قطر فين – Where's My Train

## Overview
This document outlines the complete database schema for the train tracking system based on Egyptian National Railways structure with bilingual support using Spatie Translatable.

## Schema Design Principles
- **Business Rules Compliance**: Following Egyptian train database structure
- **Normalization**: Proper 3NF structure to minimize redundancy
- **Indexing**: Strategic indexes for performance optimization
- **Constraints**: Foreign key constraints for data integrity
- **Bilingual Support**: JSON columns for Arabic/English translations
- **Scalability**: Designed for horizontal scaling and partitioning

## Business Rules Implementation

The system follows Egyptian National Railways database structure:
- **Trains** have direct **Stops** (journey sequences)
- Each **Stop** connects a train to a station with timing information
- No complex route/schedule intermediary tables
- Simple **Train → Stops → Station** relationships

## Core Tables

### 1. Users Table
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) UNIQUE NULL,
    phone_verified_at TIMESTAMP NULL,
    email VARCHAR(255) UNIQUE NULL,
    email_verified_at TIMESTAMP NULL,
    name VARCHAR(255) NULL,
    avatar VARCHAR(255) NULL,
    date_of_birth DATE NULL,
    gender ENUM('male', 'female', 'other') NULL,
    preferred_language ENUM('ar', 'en') DEFAULT 'ar',
    notification_preferences JSON NULL,
    ad_free_until TIMESTAMP NULL,
    total_assignments INT DEFAULT 0,
    successful_assignments INT DEFAULT 0,
    reward_points INT DEFAULT 0,
    status ENUM('active', 'suspended', 'banned') DEFAULT 'active',
    last_active_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_phone (phone),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_last_active (last_active_at)
);
```

### 2. Social Logins Table
```sql
CREATE TABLE social_logins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    provider ENUM('google', 'apple', 'facebook') NOT NULL,
    provider_id VARCHAR(255) NOT NULL,
    provider_email VARCHAR(255) NULL,
    provider_name VARCHAR(255) NULL,
    provider_avatar VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_provider_user (provider, provider_id),
    INDEX idx_user_provider (user_id, provider)
);
```

### 3. OTP Verifications Table
```sql
CREATE TABLE otp_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_phone_otp (phone, otp_code),
    INDEX idx_expires_at (expires_at)
);
```

### 4. Stations Table (Bilingual)
```sql
CREATE TABLE stations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) UNIQUE NOT NULL,
    name JSON NOT NULL, -- {"ar": "محطة القاهرة", "en": "Cairo Station"}
    description JSON NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    elevation INT NULL, -- meters above sea level
    city JSON NOT NULL,
    region JSON NULL,
    country_code VARCHAR(2) DEFAULT 'EG',
    timezone VARCHAR(50) DEFAULT 'Africa/Cairo',
    facilities JSON NULL, -- ["wifi", "restaurant", "parking"]
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_code (code),
    INDEX idx_location (latitude, longitude),
    INDEX idx_status (status),
    INDEX idx_order (order_index)
);
```

### 5. Trains Table (Bilingual)
```sql
CREATE TABLE trains (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    train_id INT UNIQUE NOT NULL, -- Egyptian train ID
    name JSON NOT NULL, -- {"ar": "قطار القاهرة الأسكندرية", "en": "Cairo Alexandria Train"}
    description JSON NULL,
    type JSON NULL, -- {"ar": "ركاب", "en": "Passenger"}
    operator JSON NULL, -- {"ar": "هيئة السكك الحديدية المصرية", "en": "Egyptian National Railways"}
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_train_id (train_id),
    INDEX idx_status (status)
);
```

### 6. Stops Table (Business Rules Core)
```sql
CREATE TABLE stops (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    train_id BIGINT UNSIGNED NOT NULL,
    station_id BIGINT UNSIGNED NOT NULL,
    stop_number INT NOT NULL, -- Sequence order in journey
    arrival_time TIME NULL,
    departure_time TIME NOT NULL,
    platform VARCHAR(10) NULL,
    stop_duration_minutes INT DEFAULT 0,
    distance_km DECIMAL(8, 2) NULL,
    is_major_stop BOOLEAN DEFAULT FALSE,
    notes JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (train_id) REFERENCES trains(id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(id),
    UNIQUE KEY unique_train_stop (train_id, stop_number),
    INDEX idx_train_sequence (train_id, stop_number),
    INDEX idx_station_stops (station_id)
);
```

### 7. No Stops Table (Express Behavior)
```sql
CREATE TABLE no_stops (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    train_id BIGINT UNSIGNED NOT NULL,
    stop_number INT NOT NULL, -- Reference to skipped stop
    reason JSON NULL, -- {"ar": "محطة غير تشغيلية", "en": "Station not operational"}
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (train_id) REFERENCES trains(id) ON DELETE CASCADE,
    INDEX idx_train_no_stop (train_id, stop_number)
);
```

### 8. Train Trips Table (Daily Instance)
```sql
CREATE TABLE train_trips (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    train_id BIGINT UNSIGNED NOT NULL,
    trip_date DATE NOT NULL,
    actual_departure_time TIMESTAMP NULL,
    actual_arrival_time TIMESTAMP NULL,
    estimated_departure_time TIMESTAMP NOT NULL,
    estimated_arrival_time TIMESTAMP NOT NULL,
    delay_minutes INT DEFAULT 0,
    current_station_id BIGINT UNSIGNED NULL,
    next_station_id BIGINT UNSIGNED NULL,
    current_latitude DECIMAL(10, 8) NULL,
    current_longitude DECIMAL(11, 8) NULL,
    speed_kmh DECIMAL(5, 2) DEFAULT 0,
    status ENUM('scheduled', 'boarding', 'departed', 'in_transit', 'arrived', 'cancelled', 'delayed') DEFAULT 'scheduled',
    passenger_count INT DEFAULT 0,
    last_location_update TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (train_id) REFERENCES trains(id),
    FOREIGN KEY (current_station_id) REFERENCES stations(id),
    FOREIGN KEY (next_station_id) REFERENCES stations(id),
    UNIQUE KEY unique_train_date (train_id, trip_date),
    INDEX idx_trip_date (trip_date),
    INDEX idx_status (status),
    INDEX idx_location (current_latitude, current_longitude)
);
```

### 9. Passenger Assignments Table
```sql
CREATE TABLE passenger_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    trip_id BIGINT UNSIGNED NOT NULL,
    boarding_station_id BIGINT UNSIGNED NOT NULL,
    destination_station_id BIGINT UNSIGNED NOT NULL,
    assignment_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    boarding_time TIMESTAMP NULL,
    arrival_time TIMESTAMP NULL,
    current_latitude DECIMAL(10, 8) NULL,
    current_longitude DECIMAL(11, 8) NULL,
    location_accuracy DECIMAL(5, 2) NULL,
    speed_kmh DECIMAL(5, 2) NULL,
    heading DECIMAL(5, 2) NULL,
    location_sharing_enabled BOOLEAN DEFAULT FALSE,
    last_location_update TIMESTAMP NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    completed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    reward_points_earned INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trip_id) REFERENCES train_trips(id) ON DELETE CASCADE,
    FOREIGN KEY (boarding_station_id) REFERENCES stations(id),
    FOREIGN KEY (destination_station_id) REFERENCES stations(id),
    INDEX idx_user_assignments (user_id, status),
    INDEX idx_trip_assignments (trip_id, status)
);
```

### 10. Communities Table (Trip-based)
```sql
CREATE TABLE communities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id BIGINT UNSIGNED NOT NULL,
    name JSON NULL, -- Auto-generated based on train
    description JSON NULL,
    member_count INT DEFAULT 0,
    message_count INT DEFAULT 0,
    status ENUM('active', 'archived', 'closed') DEFAULT 'active',
    auto_archive_at TIMESTAMP NULL, -- 24h after trip completion
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (trip_id) REFERENCES train_trips(id) ON DELETE CASCADE,
    UNIQUE KEY unique_trip_community (trip_id),
    INDEX idx_status (status),
    INDEX idx_auto_archive (auto_archive_at)
);
```

### 11. Community Messages Table (Structured)
```sql
CREATE TABLE community_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    community_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL, -- NULL for guest users
    guest_id VARCHAR(10) NULL, -- For guest users
    guest_name VARCHAR(255) NULL, -- Guest display name
    station_id BIGINT UNSIGNED NOT NULL,
    time_passed_minutes INT NOT NULL, -- Minutes since departure
    message_type ENUM('status_update', 'delay_report', 'arrival_confirmation', 'departure_confirmation', 'crowd_level', 'amenity_status') DEFAULT 'status_update',
    additional_data JSON NULL, -- For structured data
    is_verified BOOLEAN DEFAULT FALSE, -- Verified by other passengers
    verification_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (station_id) REFERENCES stations(id),
    INDEX idx_community_time (community_id, created_at),
    INDEX idx_user_messages (user_id),
    INDEX idx_station_messages (station_id, time_passed_minutes)
);
```

### 12. Message Verifications Table
```sql
CREATE TABLE message_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    verification_type ENUM('confirm', 'dispute') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (message_id) REFERENCES community_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_message_verification (user_id, message_id)
);
```

### 13. Badges Table (Bilingual)
```sql
CREATE TABLE badges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name JSON NOT NULL, -- {"ar": "مسافر نشط", "en": "Active Traveler"}
    description JSON NOT NULL,
    icon VARCHAR(255) NOT NULL,
    category ENUM('travel', 'community', 'accuracy', 'milestone') NOT NULL,
    criteria JSON NOT NULL, -- Detailed criteria for earning
    rarity ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') DEFAULT 'common',
    points_reward INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_category (category),
    INDEX idx_rarity (rarity),
    INDEX idx_active (is_active)
);
```

### 14. User Badges Table
```sql
CREATE TABLE user_badges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    badge_id BIGINT UNSIGNED NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress_data JSON NULL, -- For tracking progress towards badge

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_badge (user_id, badge_id),
    INDEX idx_user_badges (user_id, earned_at),
    INDEX idx_badge_users (badge_id, earned_at)
);
```

### 15. Rewards Table
```sql
CREATE TABLE rewards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type ENUM('assignment_completion', 'community_participation', 'accuracy_bonus', 'milestone', 'ad_free_period') NOT NULL,
    points_earned INT DEFAULT 0,
    description JSON NOT NULL,
    reference_id BIGINT UNSIGNED NULL, -- Reference to assignment, message, etc.
    reference_type VARCHAR(50) NULL, -- Model type for polymorphic relation
    expires_at TIMESTAMP NULL,
    claimed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_rewards (user_id, type),
    INDEX idx_expires_at (expires_at),
    INDEX idx_reference (reference_type, reference_id)
);
```

## Relationships Summary

### Core Business Rules Relationships
- **Trains** → Stops (1:N) - Each train has multiple stops in sequence
- **Stations** → Stops (1:N) - Each station can be a stop for multiple trains
- **Trains** → No Stops (1:N) - Express behavior for specific stops
- **Trains** → Train Trips (1:N) - Daily instances of train journeys

### User & Community Relationships
- **Users** → Social Logins (1:N)
- **Users** → Passenger Assignments (1:N)
- **Users** → User Badges (1:N)
- **Users** → Rewards (1:N)
- **Users** → Community Messages (1:N)
- **Train Trips** → Passenger Assignments (1:N)
- **Train Trips** → Communities (1:1)
- **Communities** → Community Messages (1:N)

## Data Seeding

The system uses `BusinessRulesTrainSeeder` which imports data from Egyptian National Railways SQLite database:
- **730 trains** with proper train IDs
- **658 stations** with coordinates and details
- **13,541 stops** representing train journey sequences

## Performance Optimizations

### Critical Indexes
```sql
-- Business rules performance
CREATE INDEX idx_stops_train_sequence ON stops(train_id, stop_number);
CREATE INDEX idx_stops_station_lookup ON stops(station_id, departure_time);
CREATE INDEX idx_trips_train_date ON train_trips(train_id, trip_date);

-- User activity indexes
CREATE INDEX idx_assignments_user_status ON passenger_assignments(user_id, status, created_at);
CREATE INDEX idx_messages_community_time ON community_messages(community_id, created_at);
CREATE INDEX idx_location_updates ON passenger_assignments(trip_id, last_location_update);
```

## Business Rules Constraints

- **Stop Sequences**: Must be sequential (1, 2, 3...) per train
- **Time Logic**: Departure time >= Arrival time (if both exist)
- **Assignment Limits**: One active assignment per user per trip
- **Location Sharing**: Only during active assignments
- **Community Access**: Guest users read-only, authenticated users can post
- **Message Rate Limits**: Max 5 messages per user per trip

This schema provides a solid foundation for the Qatar Fein train tracking system following Egyptian National Railways business rules with comprehensive bilingual support and optimized performance characteristics.