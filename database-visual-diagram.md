# Database Visual Structure - القطر فين – Where's My Train

## Database ERD (Entity Relationship Diagram)

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                            QATAR FEIN TRAIN TRACKING DATABASE                        │
│                                   Visual Structure                                   │
└─────────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│     USERS       │    │ SOCIAL_LOGINS   │    │ OTP_VERIFICATIONS│
├─────────────────┤    ├─────────────────┤    ├─────────────────┤
│ • id (PK)       │◄──┤ • id (PK)       │    │ • id (PK)       │
│ • phone (UNQ)   │    │ • user_id (FK)  │    │ • phone         │
│ • email         │    │ • provider      │    │ • otp_code      │
│ • name          │    │ • provider_id   │    │ • expires_at    │
│ • avatar        │    │ • created_at    │    │ • verified_at   │
│ • preferred_lang│    └─────────────────┘    │ • attempts      │
│ • ad_free_until │                           └─────────────────┘
│ • reward_points │
│ • status        │    ┌─────────────────────────────────────────┐
│ • created_at    │    │           AUTHENTICATION FLOW           │
└─────────────────┘    │                                         │
         │              │ 1. Phone OTP Verification              │
         │              │ 2. Social Login (Google/Apple/FB)      │
         │              │ 3. JWT Token Generation                │
         │              │ 4. Profile Creation/Update             │
         │              └─────────────────────────────────────────┘
         │
         │              ┌─────────────────┐
         ├─────────────►│ USER_BADGES     │
         │              ├─────────────────┤
         │              │ • id (PK)       │
         │              │ • user_id (FK)  │
         │              │ • badge_id (FK) │
         │              │ • earned_at     │
         │              └─────────────────┘
         │                       │
         │              ┌─────────────────┐
         │              │     BADGES      │
         │              ├─────────────────┤
         │              │ • id (PK)       │
         │              │ • name (JSON)   │◄── Bilingual: {"ar": "مسافر نشط", "en": "Active Traveler"}
         │              │ • description   │
         │              │ • icon          │
         │              │ • category      │
         │              │ • criteria      │
         │              │ • rarity        │
         │              └─────────────────┘
         │
         ├─────────────►┌─────────────────┐
         │              │    REWARDS      │
         │              ├─────────────────┤
         │              │ • id (PK)       │
         │              │ • user_id (FK)  │
         │              │ • type          │
         │              │ • points_earned │
         │              │ • description   │
         │              │ • reference_id  │◄── Polymorphic to assignments, messages, etc.
         │              │ • claimed_at    │
         │              └─────────────────┘
         │
         └─────────────►┌─────────────────┐
                        │ PASSENGER_ASSIGN│
                        ├─────────────────┤
                        │ • id (PK)       │
                        │ • user_id (FK)  │
                        │ • trip_id (FK)  │
                        │ • boarding_stn  │
                        │ • dest_station  │
                        │ • current_lat   │
                        │ • current_lng   │
                        │ • location_share│
                        │ • status        │
                        │ • completion    │
                        │ • reward_points │
                        └─────────────────┘
                                 │
                                 │
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                               TRAIN SYSTEM CORE                                      │
└─────────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│    STATIONS     │    │     TRAINS      │    │     ROUTES      │    │   SCHEDULES     │
├─────────────────┤    ├─────────────────┤    ├─────────────────┤    ├─────────────────┤
│ • id (PK)       │    │ • id (PK)       │    │ • id (PK)       │    │ • id (PK)       │
│ • code (UNQ)    │    │ • number (UNQ)  │    │ • train_id (FK) │◄──┤ • route_id (FK) │
│ • name (JSON)   │◄─┐ │ • name (JSON)   │◄──┤ • origin_stn    │    │ • departure_time│
│ • description   │  │ │ • description   │    │ • dest_station  │    │ • arrival_time  │
│ • latitude      │  │ │ • type          │    │ • distance_km   │    │ • days_of_week  │
│ • longitude     │  │ │ • operator      │    │ • duration_min  │    │ • valid_from    │
│ • city (JSON)   │  │ │ • capacity      │    │ • status        │    │ • price_adult   │
│ • facilities    │  │ │ • amenities     │    └─────────────────┘    │ • booking_class │
│ • status        │  │ │ • status        │             │             │ • status        │
└─────────────────┘  │ └─────────────────┘             │             └─────────────────┘
         │           │          │                      │                      │
         │           │          │                      │                      │
         │           │          │                      ▼                      │
         │           │          │           ┌─────────────────┐               │
         │           │          │           │ ROUTE_STATIONS  │               │
         │           │          │           ├─────────────────┤               │
         │           │          │           │ • id (PK)       │               │
         │           │          │           │ • route_id (FK) │               │
         │           └──────────┼───────────┤ • station_id(FK)│               │
         └────────────────────────────────  │ • sequence_order│               │
                              │           │ • distance_km   │               │
                              │           │ • duration_min  │               │
                              │           │ • platform_num  │               │
                              │           └─────────────────┘               │
                              │                                             │
                              ▼                                             ▼
                    ┌─────────────────┐                          ┌─────────────────┐
                    │   TRAIN_TRIPS   │                          │ TRIP INSTANCES  │
                    ├─────────────────┤                          │   (DAILY)       │
                    │ • id (PK)       │                          └─────────────────┘
                    │ • schedule_id   │◄─────────────────────────│ Each schedule   │
                    │ • trip_date     │                          │ generates daily │
                    │ • actual_dept   │                          │ trip instances  │
                    │ • actual_arr    │                          │ with real-time  │
                    │ • estimated_*   │                          │ tracking data   │
                    │ • delay_minutes │                          └─────────────────┘
                    │ • current_stn   │
                    │ • current_lat   │
                    │ • current_lng   │
                    │ • speed_kmh     │
                    │ • status        │
                    │ • passenger_cnt │
                    └─────────────────┘
                             │
                             │
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                            COMMUNITY & MESSAGING                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘

                    ┌─────────────────┐
                    │   COMMUNITIES   │
                    ├─────────────────┤
                    │ • id (PK)       │
                    │ • trip_id (FK)  │◄─── One community per trip
                    │ • name (JSON)   │     Auto-generated name
                    │ • member_count  │     Active during trip only
                    │ • message_count │     Auto-archived after 24h
                    │ • status        │
                    │ • auto_archive  │
                    └─────────────────┘
                             │
                             ▼
                    ┌─────────────────┐
                    │ COMMUNITY_MSG   │
                    ├─────────────────┤
                    │ • id (PK)       │
                    │ • community_id  │
                    │ • user_id (FK)  │◄─── NULL for guest users
                    │ • guest_id      │◄─── For guest identification
                    │ • station_id    │◄─── STRUCTURED: Station selection
                    │ • time_passed   │◄─── STRUCTURED: Time in minutes
                    │ • message_type  │◄─── status_update, delay_report, etc.
                    │ • additional    │◄─── JSON for extra structured data
                    │ • is_verified   │◄─── Crowd verification
                    │ • verify_count  │
                    └─────────────────┘
                             │
                             ▼
                    ┌─────────────────┐
                    │ MSG_VERIFICATIONS│
                    ├─────────────────┤
                    │ • id (PK)       │
                    │ • message_id    │
                    │ • user_id (FK)  │
                    │ • verify_type   │◄─── confirm/dispute
                    └─────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────────┐
│                            REAL-TIME TRACKING                                        │
└─────────────────────────────────────────────────────────────────────────────────────┘

                    ┌─────────────────┐
                    │ LOCATION_HISTORY│
                    ├─────────────────┤
                    │ • id (PK)       │
                    │ • trip_id (FK)  │◄─── Links to specific trip
                    │ • latitude      │
                    │ • longitude     │
                    │ • speed_kmh     │
                    │ • heading       │
                    │ • altitude_m    │
                    │ • accuracy_m    │
                    │ • reported_by   │◄─── Crowdsourced locations
                    │ • recorded_at   │
                    └─────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────────┐
│                            NOTIFICATIONS SYSTEM                                      │
└─────────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────┐                    ┌─────────────────┐
│ SYSTEM_NOTIFICATIONS                 │ USER_NOTIFICATIONS│
├─────────────────┤                    ├─────────────────┤
│ • id (PK)       │                    │ • id (PK)       │
│ • title (JSON)  │◄── Bilingual      │ • user_id (FK)  │
│ • message (JSON)│                    │ • notification_id│
│ • type          │                    │ • title         │
│ • target_type   │                    │ • message       │
│ • target_criteria│                   │ • type          │
│ • scheduled_at  │                    │ • data (JSON)   │
│ • expires_at    │                    │ • read_at       │
│ • is_active     │                    └─────────────────┘
└─────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────────┐
│                               DATA FLOW EXAMPLE                                      │
└─────────────────────────────────────────────────────────────────────────────────────┘

1. TRAIN SETUP:
   Station (Riyadh) → Train (Haramain) → Route (Riyadh-Makkah) → Schedule (Daily 8AM)

2. DAILY OPERATION:
   Schedule → Trip Instance (2024-01-15) → Real-time tracking → Location updates

3. PASSENGER JOURNEY:
   User → Assignment (Riyadh→Jeddah) → Location sharing → Community participation → Rewards

4. COMMUNITY FLOW:
   Trip → Community → Structured Messages (Station + Time) → Verification → Badges

5. REWARD SYSTEM:
   10 Successful assignments → 1 Week ad-free → Badge earning → Points accumulation

┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              BILINGUAL SUPPORT                                       │
└─────────────────────────────────────────────────────────────────────────────────────┘

JSON Structure for all translatable fields:
{
  "ar": "محطة الرياض المركزية",
  "en": "Riyadh Central Station"
}

Affected Tables:
• stations (name, description, city)
• trains (name, description, operator)
• routes (name, description)
• badges (name, description)
• system_notifications (title, message)
• app_settings (value, description)

┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              PERFORMANCE INDEXES                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘

Critical Indexes:
• users(phone, phone_verified_at)
• passenger_assignments(user_id, status, created_at)
• train_trips(trip_date, status)
• community_messages(community_id, created_at)
• train_location_history(trip_id, recorded_at)
• schedules(route_id, departure_time, valid_from, valid_until)

Composite Indexes for complex queries:
• Route planning: route_stations(route_id, sequence_order)
• Location tracking: train_location_history(trip_id, recorded_at)
• Community activity: community_messages(community_id, created_at, user_id)
• Reward calculation: rewards(user_id, type, claimed_at)
```

## Key Database Features

### 1. **Bilingual Architecture**
- All user-facing content stored in JSON format
- Automatic language switching based on user preference
- Fallback to Arabic if English translation missing

### 2. **Real-time Capabilities**
- Separate location history table for GPS tracking
- Trip-based communities for temporary interactions
- Structured messaging system (no free text)

### 3. **Gamification System**
- Badge earning with criteria tracking
- Point-based reward system
- Ad-free period management (10 assignments = 1 week)

### 4. **Scalability Features**
- Partitioned location history by trip_id
- Indexed for high-performance queries
- Polymorphic relationships for flexibility

### 5. **Security & Privacy**
- Phone verification with OTP expiration
- Guest user support for community viewing
- Location sharing opt-in during trips only

This database structure supports the complete "القطر فين – Where's My Train" system with full Arabic/English bilingual support, real-time tracking, community features, and gamification.