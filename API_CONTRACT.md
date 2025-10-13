# Qetar Fein Mobile App - API Contract

## Overview
This document defines the complete API contract for the Qetar Fein mobile application. The backend follows Egyptian National Railways business rules with Train → Stops → Station structure.

**Authentication:** Bearer Token (Laravel Sanctum)
**Default Language:** Arabic (ar)
**Content-Type:** `application/json`
**Production API:** `https://qetar.elbenaa.com/api/v1`

### Simplified Configuration
The app uses a single production-only configuration for simplicity:

```dart
// lib/core/config/api_config.dart
class ApiConfig {
  // Base URL - Production only
  static const String baseUrl = 'https://qetar.elbenaa.com/api/v1';

  // WebSocket URL
  static const String webSocketUrl = 'wss://qetar.elbenaa.com';

  // Reverb configuration
  static const String reverbAppKey = 'qetarfein-key';
  static const String reverbHost = 'qetar.elbenaa.com';
  static const int reverbPort = 443;
  static const String reverbScheme = 'https';

  // Feature flags
  static const bool enableLogging = false;
  static const bool enableDebugMode = false;

  // Timeouts (in milliseconds)
  static const int connectTimeout = 30000;
  static const int receiveTimeout = 30000;
  static const int sendTimeout = 30000;

  // API endpoints
  static const String authEndpoint = '/auth';
  static const String trainsEndpoint = '/trains';
  static const String stationsEndpoint = '/stations';
  static const String assignmentsEndpoint = '/assignments';
  static const String communitiesEndpoint = '/communities';
  static const String rewardsEndpoint = '/rewards';
  static const String notificationsEndpoint = '/notifications';

  // API Keys
  static const String googleMapsApiKey = 'AIzaSyPROD_GOOGLE_MAPS_KEY';
  static const String firebaseProjectId = 'qetarfein-prod';

  // Cache configuration
  static const Duration cacheExpiry = Duration(minutes: 15);
  static const int maxCacheSize = 50 * 1024 * 1024; // 50MB

  // Pagination
  static const int defaultPageSize = 20;
  static const int maxPageSize = 100;

  // Location settings
  static const double defaultLocationAccuracy = 100.0; // meters
  static const Duration locationUpdateInterval = Duration(seconds: 30);

  // Push notification topics
  static const String generalNotificationsTopic = 'general';
  static const String trainUpdatesNotificationsTopic = 'train_updates';
  static const String emergencyNotificationsTopic = 'emergency';

  // Validation rules
  static const int minPhoneNumberLength = 8;
  static const int maxPhoneNumberLength = 15;
  static const int otpLength = 6;
  static const Duration otpExpiry = Duration(minutes: 5);

  // App configuration
  static const String appName = 'Qetar Fein';
  static const String appVersion = '1.0.0';
  static const String supportEmail = 'support@qetarfein.com';
  static const String privacyPolicyUrl = 'https://qetarfein.com/privacy';
  static const String termsOfServiceUrl = 'https://qetarfein.com/terms';
}

// Custom exception class
class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final dynamic data;

  ApiException(this.message, {this.statusCode, this.data});

  @override
  String toString() => 'ApiException: $message';
}
```

### Android Build Configuration

**android/app/build.gradle.kts:**
```kotlin
android {
    namespace = "com.qetarfein.app"
    compileSdk = 36

    defaultConfig {
        applicationId = "com.qetarfein.app"
        minSdk = 24
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName

        // Multilocale support
        resConfigs("ar", "en")
    }

    buildTypes {
        debug {
            isMinifyEnabled = false
            isDebuggable = true
            versionNameSuffix = "-debug"
        }

        release {
            isMinifyEnabled = true
            isDebuggable = false
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
            signingConfig = signingConfigs.getByName("debug")
        }
    }

    buildFeatures {
        buildConfig = true
    }
}
```

## Table of Contents
1. [Authentication](#authentication)
2. [Common Headers](#common-headers)
3. [Response Format](#response-format)
4. [Error Handling](#error-handling)
5. [Trains API](#trains-api)
6. [Stations API](#stations-api)
7. [Assignments API](#assignments-api)
8. [Communities API](#communities-api)
9. [Rewards & Badges API](#rewards--badges-api)
10. [Real-time WebSocket](#real-time-websocket)
11. [Business Rules](#business-rules)
12. [Rate Limiting](#rate-limiting)

---

## Authentication

### Registration Flow (New Users Only)

#### 1. Send OTP
```
POST /auth/phone/send-otp
```

**Request:**
```json
{
    "phone": "01011761786"
}
```

**Response (200):**
```json
{
    "status": "success",
    "message": "OTP sent successfully",
    "data": {
        "expires_in": 300
    }
}
```

**Business Rules:**
- Phone: Egyptian format (11 digits starting with 010, 011, 012, or 015)
- Rate limit: 3 attempts per hour per phone
- OTP expires in 5 minutes
- OTP code is 6 digits

#### 2. Verify OTP (Registration)
```
POST /auth/phone/verify
```

**Request:**
```json
{
    "phone": "01011761786",
    "otp_code": "123456"
}
```

**Response for New User (200):**
```json
{
    "status": "success",
    "message": "Phone verified. Please complete your registration.",
    "data": {
        "phone": "01011761786",
        "verified": true,
        "is_new_user": true
    }
}
```

**Response for Existing User (200):**
```json
{
    "status": "success",
    "message": "Phone verified successfully. You can now reset your password.",
    "data": {
        "phone": "01011761786",
        "verified": true,
        "is_new_user": false
    }
}
```

#### 3. Complete Registration
```
POST /auth/register/complete
```

**Request:**
```json
{
    "phone": "01011761786",
    "name": "Ahmed Mohamed",
    "password": "password123",
    "password_confirmation": "password123",
    "email": "ahmed@example.com",
    "preferred_language": "ar"
}
```

**Response (201):**
```json
{
    "status": "success",
    "message": "Registration completed successfully",
    "data": {
        "user": {
            "id": 1,
            "phone": "01011761786",
            "name": "Ahmed Mohamed",
            "email": "ahmed@example.com",
            "avatar": null,
            "preferred_language": "ar",
            "reward_points": 0,
            "ad_free_until": null
        },
        "token": "1|eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "Bearer"
    }
}
```

**Business Rules:**
- Phone number must be verified via OTP within the last 10 minutes
- Phone: Egyptian format (11 digits starting with 010, 011, 012, or 015)
- Name: 3-255 characters
- Password: Minimum 8 characters
- Email: Must be unique (optional)
- Preferred language: ar or en (optional, defaults to ar)

### Login Flow (Existing Users)

#### Login with Phone & Password
```
POST /auth/login
```

**Request:**
```json
{
    "phone": "01011761786",
    "password": "password123"
}
```

**Response (200):**
```json
{
    "status": "success",
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "phone": "01011761786",
            "name": "Ahmed Mohamed",
            "email": "ahmed@example.com",
            "avatar": null,
            "preferred_language": "ar",
            "reward_points": 150,
            "ad_free_until": "2024-02-15T10:30:00Z"
        },
        "token": "1|eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "Bearer"
    }
}
```

**Error Response (401):**
```json
{
    "status": "error",
    "message": "Invalid phone or password"
}
```

**Business Rules:**
- Use phone + password for regular login
- OTP is NOT required for login
- Account must be active

### Password Reset Flow

#### 1. Send OTP
```
POST /auth/phone/send-otp
```
Same as registration flow

#### 2. Verify OTP
```
POST /auth/phone/verify
```
Returns `is_new_user: false` for existing users

#### 3. Reset Password
```
POST /auth/password/reset
```

**Request:**
```json
{
    "phone": "01011761786",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

**Response (200):**
```json
{
    "status": "success",
    "message": "Password reset successfully. You can now login with your new password.",
    "data": null
}
```

**Business Rules:**
- Phone must be verified via OTP within the last 10 minutes
- Password: Minimum 8 characters
- After reset, user must login with new password

### Social Login

#### Google/Apple/Facebook Login
```
POST /auth/social/{provider}
```
**Providers:** `google`, `apple`, `facebook`

**Request:**
```json
{
    "provider_id": "google_user_id_123",
    "email": "user@gmail.com",
    "name": "John Doe",
    "avatar": "https://lh3.googleusercontent.com/avatar.jpg"
}
```

**Response:** Same as phone verification success

### Profile Management

#### Get Profile
```
GET /auth/profile
Authorization: Bearer {token}
```

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "user": {
            "id": 1,
            "phone": "+974123456789",
            "name": "Ahmed Al-Mahmoud",
            "email": "ahmed@example.com",
            "avatar": "https://cdn.qatarfein.com/avatars/1.jpg",
            "preferred_language": "ar",
            "reward_points": 150,
            "total_assignments": 12,
            "successful_assignments": 10,
            "ad_free_until": "2024-02-15T10:30:00Z",
            "created_at": "2024-01-01T10:30:00Z"
        }
    }
}
```

#### Update Profile
```
PUT /auth/profile
Authorization: Bearer {token}
```

**Request:**
```json
{
    "name": "Ahmed Al-Mahmoud",
    "email": "ahmed@example.com",
    "preferred_language": "en"
}
```

#### Logout
```
POST /auth/logout
Authorization: Bearer {token}
```

---

## Common Headers

**Required for all requests:**
```
Accept: application/json
Accept-Language: ar|en (default: ar)
```

**Required for authenticated endpoints:**
```
Authorization: Bearer {token}
```

---

## Response Format

### Success Response
```json
{
    "status": "success",
    "message": {
        "ar": "الرسالة بالعربية",
        "en": "Message in English"
    },
    "data": {
        // Response data
    },
    "meta": {
        // Pagination or additional metadata
        "current_page": 1,
        "last_page": 5,
        "per_page": 20,
        "total": 100
    }
}
```

### Error Response
```json
{
    "status": "error",
    "message": {
        "ar": "رسالة الخطأ بالعربية",
        "en": "Error message in English"
    },
    "errors": {
        "field_name": [
            "Validation error message"
        ]
    },
    "error_code": "VALIDATION_ERROR"
}
```

---

## Error Handling

### HTTP Status Codes
- **200** - Success
- **201** - Created
- **400** - Bad Request / Validation Error
- **401** - Unauthorized
- **403** - Forbidden
- **404** - Not Found
- **429** - Rate Limit Exceeded
- **500** - Internal Server Error

### Error Codes
- `VALIDATION_ERROR` - Input validation failed
- `UNAUTHORIZED` - Invalid or expired token
- `RATE_LIMIT_EXCEEDED` - Too many requests
- `RESOURCE_NOT_FOUND` - Requested resource not found
- `BUSINESS_RULE_VIOLATION` - Business logic constraint violated

---

## Trains API

### Search Trains
```
POST /trains/search
```

**Request:**
```json
{
    "departure_station_id": 53,
    "arrival_station_id": 8,
    "train_type_id": 2
}
```

**Query Parameters:**
- `departure_station_id` (required): Starting station ID
- `arrival_station_id` (required): Destination station ID
- `train_type_id` (optional): Train type ID for filtering

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "available_trains": [
            {
                "train_id": 1,
                "train_number": "389",
                "train_name": {
                    "ar": "قطار 389 (مطور)",
                    "en": "Train 389 (Improved)"
                },
                "train_type": {
                    "id": 2,
                    "name": {
                        "ar": "مطور",
                        "en": "Improved"
                    },
                    "description": null
                },
                "departure": {
                    "station_id": 53,
                    "station_name": "Cairo",
                    "time": "14:25:00",
                    "platform": "Platform 3"
                },
                "arrival": {
                    "station_id": 8,
                    "station_name": "Alexandria",
                    "time": "18:45:00",
                    "platform": "Platform 5"
                },
                "trip_duration": "4h 20m",
                "stops_between": 12,
                "total_stops": 14,
                "amenities": ["seats", "luggage_storage"],
                "capacity": 400
            }
        ],
        "count": 5,
        "search_criteria": {
            "departure_station_id": 53,
            "arrival_station_id": 8,
            "train_type_id": 2
        }
    }
}
```

**Business Rules:**
- Returns trains that have both stations in their route
- Validates that departure station comes before arrival station
- Filters by train type if provided
- Results sorted by departure time

### Get All Trains
```
GET /trains?page=1&per_page=20&status=active&search=cairo
```

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20, max: 50)
- `status` (optional): Filter by status (`active`, `inactive`, `maintenance`)
- `search` (optional): Search by train name or number

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "trains": [
            {
                "id": 1,
                "train_id": 1,
                "number": "1001",
                "name": {
                    "ar": "قطار القاهرة الإسكندرية",
                    "en": "Cairo Alexandria Train"
                },
                "type": "passenger",
                "operator": {
                    "ar": "السكك الحديدية المصرية",
                    "en": "Egyptian National Railways"
                },
                "status": "active",
                "journey": {
                    "origin": {
                        "station_id": 1,
                        "station_name": {
                            "ar": "محطة القاهرة",
                            "en": "Cairo Station"
                        }
                    },
                    "destination": {
                        "station_id": 14,
                        "station_name": {
                            "ar": "محطة الإسكندرية",
                            "en": "Alexandria Station"
                        }
                    },
                    "total_stops": 21,
                    "estimated_duration": "2h 35m"
                }
            }
        ]
    },
    "meta": {
        "current_page": 1,
        "last_page": 37,
        "per_page": 20,
        "total": 730
    }
}
```

### Get Train Details
```
GET /trains/{id}
```

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "train": {
            "id": 1,
            "train_id": 1,
            "number": "1001",
            "name": {
                "ar": "قطار القاهرة الإسكندرية",
                "en": "Cairo Alexandria Train"
            },
            "description": {
                "ar": "قطار ركاب بين القاهرة والإسكندرية",
                "en": "Passenger train between Cairo and Alexandria"
            },
            "type": "passenger",
            "operator": {
                "ar": "السكك الحديدية المصرية",
                "en": "Egyptian National Railways"
            },
            "status": "active",
            "journey": {
                "origin": {
                    "station_id": 1,
                    "station_name": {
                        "ar": "محطة القاهرة",
                        "en": "Cairo Station"
                    }
                },
                "destination": {
                    "station_id": 14,
                    "station_name": {
                        "ar": "محطة الإسكندرية",
                        "en": "Alexandria Station"
                    }
                },
                "total_stops": 21,
                "estimated_duration": "2h 35m"
            }
        }
    }
}
```

### Get Train Schedule (Complete Journey)
```
GET /trains/{id}/schedule
```

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "train": {
            "id": 1,
            "number": "389",
            "name": {
                "ar": "قطار 389 (مطور)",
                "en": "Train 389 (Improved)"
            },
            "train_type": {
                "id": 2,
                "name": {
                    "ar": "مطور",
                    "en": "Improved"
                },
                "description": null
            },
            "status": "active",
            "operator": {
                "ar": "السكك الحديدية المصرية",
                "en": "Egyptian National Railways"
            },
            "amenities": ["seats", "luggage_storage"],
            "capacity": 400
        },
        "schedule": [
            {
                "stop_number": 1,
                "station": {
                    "id": 1,
                    "name": {
                        "ar": "محطة القاهرة",
                        "en": "Cairo Station"
                    },
                    "code": "CAI",
                    "coordinates": {
                        "latitude": 30.0626,
                        "longitude": 31.2497
                    }
                },
                "arrival_time": null,
                "departure_time": "06:00:00",
                "platform": "Platform 1",
                "stop_duration_minutes": 0,
                "is_major_stop": true,
                "notes": {
                    "ar": "المحطة الرئيسية - جميع الخدمات متاحة",
                    "en": "Main terminal station - all services available"
                }
            },
            {
                "stop_number": 2,
                "station": {
                    "id": 2,
                    "name": {
                        "ar": "محطة الجيزة",
                        "en": "Giza Station"
                    },
                    "code": "GIZ",
                    "coordinates": {
                        "latitude": 30.0131,
                        "longitude": 31.2089
                    }
                },
                "arrival_time": "06:15:00",
                "departure_time": "06:17:00",
                "platform": "Platform 2",
                "stop_duration_minutes": 2,
                "is_major_stop": false,
                "notes": null
            }
        ],
        "journey_summary": {
            "origin": {
                "station_name": "Cairo",
                "departure_time": "14:25:00"
            },
            "destination": {
                "station_name": "Alexandria",
                "arrival_time": "18:45:00"
            },
            "total_stops": 14,
            "major_stops": 3,
            "estimated_duration": "4h 20m"
        }
    }
}
```

### Get Train Schedule (Journey Segment)
```
GET /trains/{id}/schedule?departure_station_id=53&arrival_station_id=8
```

**Use Case:** When user clicks on a train from search results, show only the relevant portion of their journey.

**Query Parameters:**
- `departure_station_id` (optional): Starting station ID
- `arrival_station_id` (optional): Ending station ID

**Behavior:**
- If both station IDs provided: Shows only stops from departure to arrival station
- If neither provided: Shows complete journey (all stops)
- Validates that departure comes before arrival in train's route

**Response (200):**
Same structure as complete schedule, but `schedule` array contains only the journey segment between specified stations.

### Get Train Live Location
```
GET /trains/{id}/location
```

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "train_id": 1,
        "trip_id": 123,
        "current_location": {
            "latitude": 30.0444,
            "longitude": 31.2357,
            "accuracy": 5.0,
            "last_update": "2024-01-15T10:30:00Z"
        },
        "current_station": {
            "id": 5,
            "name": {
                "ar": "محطة بنها",
                "en": "Benha Station"
            }
        },
        "next_station": {
            "id": 6,
            "name": {
                "ar": "محطة طنطا",
                "en": "Tanta Station"
            },
            "estimated_arrival": "2024-01-15T10:45:00Z"
        },
        "status": "in_transit",
        "speed_kmh": 85.5,
        "delay_minutes": 5,
        "passenger_count": 342
    }
}
```

### Get Live Trains
```
GET /trains/live?status=in_transit
```

**Query Parameters:**
- `status` (optional): Filter by trip status (`scheduled`, `departed`, `in_transit`, `arrived`)

---

## Stations API

### Get All Stations
```
GET /stations?search=cairo&page=1
```

**Query Parameters:**
- `search` (optional): Search by station name
- `page` (optional): Page number
- `per_page` (optional): Items per page

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "stations": [
            {
                "id": 1,
                "code": "CAI",
                "name": {
                    "ar": "محطة القاهرة",
                    "en": "Cairo Station"
                },
                "coordinates": {
                    "latitude": 30.0626,
                    "longitude": 31.2497
                },
                "city": {
                    "ar": "القاهرة",
                    "en": "Cairo"
                },
                "region": {
                    "ar": "مصر",
                    "en": "Egypt"
                },
                "facilities": ["wifi", "restaurant", "parking", "atm"],
                "status": "active"
            }
        ]
    },
    "meta": {
        "current_page": 1,
        "last_page": 33,
        "per_page": 20,
        "total": 658
    }
}
```

### Get Station Details
```
GET /stations/{id}
```

### Get Station Departures
```
GET /stations/{id}/departures?date=2024-01-15&limit=10
```

**Query Parameters:**
- `date` (optional): Date in YYYY-MM-DD format (default: today)
- `limit` (optional): Number of results (default: 20, max: 50)

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "station": {
            "id": 1,
            "name": {
                "ar": "محطة القاهرة",
                "en": "Cairo Station"
            }
        },
        "departures": [
            {
                "train": {
                    "id": 1,
                    "number": "1001",
                    "name": {
                        "ar": "قطار القاهرة الإسكندرية",
                        "en": "Cairo Alexandria Train"
                    }
                },
                "departure_time": "06:00:00",
                "platform": "Platform 1",
                "destination": {
                    "station_id": 14,
                    "station_name": {
                        "ar": "محطة الإسكندرية",
                        "en": "Alexandria Station"
                    }
                },
                "status": "on_time",
                "delay_minutes": 0
            }
        ]
    }
}
```

### Get Station Arrivals
```
GET /stations/{id}/arrivals?date=2024-01-15&limit=10
```

**Response:** Similar structure to departures but with arrival information

---

## Assignments API

### Create Assignment
```
POST /assignments
Authorization: Bearer {token}
```

**Request:**
```json
{
    "trip_id": 123,
    "boarding_station_id": 1,
    "destination_station_id": 14
}
```

**Response (201):**
```json
{
    "status": "success",
    "message": {
        "ar": "تم تسجيل الرحلة بنجاح",
        "en": "Assignment created successfully"
    },
    "data": {
        "assignment": {
            "id": 456,
            "trip": {
                "id": 123,
                "train": {
                    "id": 1,
                    "number": "1001",
                    "name": {
                        "ar": "قطار القاهرة الإسكندرية",
                        "en": "Cairo Alexandria Train"
                    }
                },
                "trip_date": "2024-01-15",
                "status": "active"
            },
            "boarding_station": {
                "id": 1,
                "name": {
                    "ar": "محطة القاهرة",
                    "en": "Cairo Station"
                }
            },
            "destination_station": {
                "id": 14,
                "name": {
                    "ar": "محطة الإسكندرية",
                    "en": "Alexandria Station"
                }
            },
            "status": "active",
            "location_sharing_enabled": false,
            "created_at": "2024-01-15T05:30:00Z"
        }
    }
}
```

**Business Rules:**
- Only one active assignment per user per trip
- Stations must be on the train's route
- Trip must be active and available

### Get My Assignments
```
GET /assignments?status=active&from_date=2024-01-01&page=1
Authorization: Bearer {token}
```

**Query Parameters:**
- `status` (optional): `active`, `completed`, `cancelled`
- `from_date` (optional): Filter from date (YYYY-MM-DD)
- `to_date` (optional): Filter to date (YYYY-MM-DD)
- `page` (optional): Page number

### Get Active Assignment
```
GET /assignments/active
Authorization: Bearer {token}
```

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "assignment": {
            "id": 456,
            "trip": {
                "id": 123,
                "train": {
                    "id": 1,
                    "number": "1001",
                    "name": {
                        "ar": "قطار القاهرة الإسكندرية",
                        "en": "Cairo Alexandria Train"
                    }
                },
                "current_location": {
                    "latitude": 30.0444,
                    "longitude": 31.2357,
                    "last_update": "2024-01-15T10:30:00Z"
                },
                "status": "in_transit"
            },
            "boarding_station": {
                "id": 1,
                "name": {
                    "ar": "محطة القاهرة",
                    "en": "Cairo Station"
                }
            },
            "destination_station": {
                "id": 14,
                "name": {
                    "ar": "محطة الإسكندرية",
                    "en": "Alexandria Station"
                }
            },
            "status": "active",
            "location_sharing_enabled": true,
            "current_location": {
                "latitude": 30.0444,
                "longitude": 31.2357,
                "accuracy": 5.0,
                "last_update": "2024-01-15T10:30:00Z"
            }
        }
    }
}
```

### Update Location
```
PUT /assignments/{id}/location
Authorization: Bearer {token}
```

**Request:**
```json
{
    "latitude": 30.0444,
    "longitude": 31.2357,
    "accuracy": 5.0,
    "speed": 45.5,
    "heading": 180.0
}
```

**Response (200):**
```json
{
    "status": "success",
    "message": {
        "ar": "تم تحديث الموقع",
        "en": "Location updated"
    }
}
```

### Enable/Disable Location Sharing
```
POST /assignments/{id}/enable-location
POST /assignments/{id}/disable-location
Authorization: Bearer {token}
```

### Complete Assignment
```
POST /assignments/{id}/complete
Authorization: Bearer {token}
```

**Response (200):**
```json
{
    "status": "success",
    "message": {
        "ar": "تم إنهاء الرحلة بنجاح",
        "en": "Assignment completed successfully"
    },
    "data": {
        "rewards": {
            "points_earned": 10,
            "badge_earned": null,
            "ad_free_days": 0
        }
    }
}
```

### Cancel Assignment
```
POST /assignments/{id}/cancel
Authorization: Bearer {token}
```

---

## Communities API

### Get Community
```
GET /communities/{tripId}
```

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "community": {
            "id": 789,
            "trip": {
                "id": 123,
                "train": {
                    "id": 1,
                    "number": "1001",
                    "name": {
                        "ar": "قطار القاهرة الإسكندرية",
                        "en": "Cairo Alexandria Train"
                    }
                },
                "trip_date": "2024-01-15"
            },
            "name": {
                "ar": "مجتمع رحلة قطار القاهرة الإسكندرية",
                "en": "Cairo Alexandria Train Trip Community"
            },
            "member_count": 45,
            "message_count": 12,
            "status": "active"
        }
    }
}
```

### Get Community Messages
```
GET /communities/{tripId}/messages?type=status_update&station_id=5&page=1
```

**Query Parameters:**
- `type` (optional): Message type filter
- `station_id` (optional): Filter by station
- `verified_only` (optional): Show only verified messages
- `page` (optional): Page number

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "messages": [
            {
                "id": 123,
                "user": {
                    "id": 1,
                    "name": "Ahmed Al-Mahmoud",
                    "avatar": "https://cdn.qatarfein.com/avatars/1.jpg"
                },
                "guest_name": null,
                "station": {
                    "id": 5,
                    "name": {
                        "ar": "محطة بنها",
                        "en": "Benha Station"
                    }
                },
                "time_passed_minutes": 45,
                "message_type": "status_update",
                "additional_data": {
                    "crowd_level": "moderate",
                    "amenities": ["wifi", "food"]
                },
                "is_verified": true,
                "verification_count": 5,
                "created_at": "2024-01-15T10:30:00Z"
            }
        ]
    },
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 10,
        "total": 25
    }
}
```

### Post Message
```
POST /communities/{tripId}/messages
Authorization: Bearer {token} (optional for guest users)
```

**Request (Authenticated User):**
```json
{
    "station_id": 5,
    "time_passed_minutes": 45,
    "message_type": "status_update",
    "additional_data": {
        "crowd_level": "moderate",
        "delay_reason": "station_congestion"
    }
}
```

**Request (Guest User):**
```json
{
    "guest_name": "محمد",
    "station_id": 5,
    "time_passed_minutes": 45,
    "message_type": "delay_report",
    "additional_data": {
        "delay_minutes": 10,
        "reason": "signal_maintenance"
    }
}
```

**Message Types:**
- `status_update` - General status update
- `delay_report` - Delay information
- `arrival_confirmation` - Confirmed arrival at station
- `departure_confirmation` - Confirmed departure from station
- `crowd_level` - Passenger count information
- `amenity_status` - Service availability (WiFi, food, etc.)

### Verify Message
```
POST /communities/{tripId}/messages/{messageId}/verify
Authorization: Bearer {token}
```

**Request:**
```json
{
    "verification_type": "confirm"
}
```

**Verification Types:**
- `confirm` - Confirm the message is accurate
- `dispute` - Dispute the message accuracy

### Get Message Types
```
GET /message-types
```

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "message_types": [
            {
                "type": "status_update",
                "name": {
                    "ar": "تحديث الحالة",
                    "en": "Status Update"
                },
                "description": {
                    "ar": "معلومات عامة عن الرحلة",
                    "en": "General trip information"
                },
                "icon": "📍",
                "fields": ["crowd_level", "amenities"]
            },
            {
                "type": "delay_report",
                "name": {
                    "ar": "تقرير تأخير",
                    "en": "Delay Report"
                },
                "description": {
                    "ar": "معلومات عن التأخير",
                    "en": "Delay information"
                },
                "icon": "⏰",
                "fields": ["delay_minutes", "reason"]
            }
        ]
    }
}
```

---

## Rewards & Badges API

### Get My Rewards
```
GET /rewards/my?type=assignment_completion&from_date=2024-01-01&page=1
Authorization: Bearer {token}
```

**Query Parameters:**
- `type` (optional): Reward type filter
- `from_date` (optional): Filter from date
- `to_date` (optional): Filter to date
- `page` (optional): Page number

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "rewards": [
            {
                "id": 123,
                "type": "assignment_completion",
                "points_earned": 10,
                "description": {
                    "ar": "إنجاز رحلة بنجاح",
                    "en": "Successfully completed trip"
                },
                "reference": {
                    "type": "assignment",
                    "id": 456
                },
                "created_at": "2024-01-15T10:30:00Z"
            }
        ],
        "summary": {
            "total_points": 150,
            "total_rewards": 12,
            "ad_free_until": "2024-02-15T10:30:00Z"
        }
    },
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 10,
        "total": 25
    }
}
```

### Get My Badges
```
GET /badges/my
Authorization: Bearer {token}
```

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "earned_badges": [
            {
                "id": 1,
                "name": {
                    "ar": "مسافر نشط",
                    "en": "Active Traveler"
                },
                "description": {
                    "ar": "أكمل 10 رحلات",
                    "en": "Complete 10 trips"
                },
                "icon": "🚂",
                "category": "travel",
                "rarity": "common",
                "earned_at": "2024-01-15T10:30:00Z"
            }
        ],
        "available_badges": [
            {
                "id": 2,
                "name": {
                    "ar": "خبير القطارات",
                    "en": "Train Expert"
                },
                "description": {
                    "ar": "أكمل 50 رحلة",
                    "en": "Complete 50 trips"
                },
                "icon": "🎯",
                "category": "travel",
                "rarity": "rare",
                "progress": {
                    "current": 12,
                    "required": 50,
                    "percentage": 24
                }
            }
        ]
    }
}
```

### Get Leaderboard
```
GET /leaderboard?type=points&timeframe=this_month
```

**Query Parameters:**
- `type`: `points`, `assignments`, `badges`
- `timeframe`: `all_time`, `this_month`, `this_week`

**Response (200):**
```json
{
    "status": "success",
    "data": {
        "leaderboard": [
            {
                "rank": 1,
                "user": {
                    "id": 5,
                    "name": "Ahmed Al-Mahmoud",
                    "avatar": "https://cdn.qatarfein.com/avatars/5.jpg"
                },
                "score": 850,
                "recent_badges": [
                    {
                        "name": {
                            "ar": "مسافر نشط",
                            "en": "Active Traveler"
                        },
                        "icon": "🚂"
                    }
                ]
            }
        ],
        "user_position": {
            "rank": 24,
            "score": 150
        }
    }
}
```

### Get All Badges
```
GET /badges
```

### Get Badge Details
```
GET /badges/{id}
```

---

## Real-time WebSocket

### Connection
```
wss://qetar.elbenaa.com/app/{app_key}
```

**Authentication:**
```javascript
// Send after connection
{
    "event": "pusher:connection_init",
    "data": {
        "auth": "Bearer {token}"
    }
}
```

### Channels

#### Public Channels (No auth required)
```javascript
// Global train updates
channel: "trains.live.updates"
events: ["train.location.updated", "train.status.changed", "train.delay.reported"]

// Specific train tracking
channel: "trains.{trainId}.location"
events: ["location.updated", "status.changed", "delay.updated"]
```

#### Private Channels (Auth required)
```javascript
// User notifications
channel: "users.{userId}.notifications"
events: ["assignment.created", "reward.earned", "badge.unlocked"]

// Community messages
channel: "communities.{tripId}.messages"
events: ["message.posted", "message.verified"]
```

### Event Examples

#### Train Location Update
```json
{
    "event": "train.location.updated",
    "channel": "trains.1.location",
    "data": {
        "train_id": 1,
        "trip_id": 123,
        "location": {
            "latitude": 30.0444,
            "longitude": 31.2357,
            "speed_kmh": 85.5,
            "heading": 180.0,
            "timestamp": "2024-01-15T10:30:00Z"
        },
        "current_station_id": 5,
        "next_station_id": 6,
        "delay_minutes": 5
    }
}
```

#### New Community Message
```json
{
    "event": "message.posted",
    "channel": "communities.123.messages",
    "data": {
        "message": {
            "id": 789,
            "user": {
                "id": 1,
                "name": "Ahmed Al-Mahmoud"
            },
            "station_id": 5,
            "message_type": "delay_report",
            "time_passed_minutes": 45,
            "additional_data": {
                "delay_minutes": 10
            },
            "created_at": "2024-01-15T10:30:00Z"
        }
    }
}
```

---

## Business Rules

### Train System Structure
- **Trains** have direct **Stops** (journey sequences)
- Each **Stop** connects a train to a station with timing
- No complex route/schedule intermediary tables
- Simple **Train → Stops → Station** relationships

### Assignment Rules
- One active assignment per user per trip
- Stations must be on the train's route (validated via stops)
- Location sharing only during active assignments
- Automatic completion when train reaches destination

### Community Rules
- Trip-based communities (auto-created)
- Guest users can view and post (with name)
- Authenticated users can post and verify
- Rate limit: 5 messages per user per trip
- Auto-archive 24h after trip completion

### Reward System
- **10 points** per completed assignment
- **1 week ad-free** for every 10 completed assignments
- **Badges** for milestones (travel, community, accuracy)
- **Verification bonus** for accurate community messages

### Language Support
- Default language: Arabic (ar)
- Supported: Arabic (ar), English (en)
- All translatable content in JSON format
- Client sends `Accept-Language` header

---

## Rate Limiting

### Authentication
- **OTP requests**: 3 per hour per phone number
- **Login attempts**: 5 per minute per IP

### API Requests
- **Public endpoints**: 100 requests per minute per IP
- **Authenticated endpoints**: 300 requests per minute per user
- **Location updates**: 60 requests per minute per assignment

### Community Features
- **Message posting**: 5 messages per trip per user
- **Message verification**: 10 verifications per minute per user

---

## Data Types & Formats

### Coordinates
```json
{
    "latitude": 30.0626,  // decimal degrees
    "longitude": 31.2497, // decimal degrees
    "accuracy": 5.0       // meters (optional)
}
```

### Time Formats
- **API timestamps**: ISO 8601 format (`2024-01-15T10:30:00Z`)
- **Train times**: 24-hour format (`14:30:00`)
- **Dates**: YYYY-MM-DD format (`2024-01-15`)

### Bilingual Content
```json
{
    "field_name": {
        "ar": "النص بالعربية",
        "en": "Text in English"
    }
}
```

### Pagination
```json
{
    "meta": {
        "current_page": 1,
        "last_page": 10,
        "per_page": 20,
        "total": 200,
        "from": 1,
        "to": 20
    }
}
```

---

## Testing & Development

### Production URL
The app is configured for production-only deployment:

**Production Environment:**
- **API**: `https://qetar.elbenaa.com/api/v1`
- **WebSocket**: `wss://qetar.elbenaa.com`

### Practical Implementation

#### Main Entry Point
**lib/main.dart:**
```dart
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'core/config/api_config.dart';
import 'core/di/service_locator.dart';
import 'core/navigation/app_router.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/bloc/auth_bloc.dart';
import 'features/auth/repositories/auth_repository.dart';
import 'l10n/app_localizations.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize dependency injection
  await setupServiceLocator();

  runApp(const QetarFeinApp());
}

class QetarFeinApp extends StatelessWidget {
  const QetarFeinApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiBlocProvider(
      providers: [
        BlocProvider(
          create: (context) => AuthBloc(
            repository: serviceLocator<AuthRepository>(),
          )..add(AppStarted()),
        ),
      ],
      child: MaterialApp.router(
        title: ApiConfig.appName,
        debugShowCheckedModeBanner: ApiConfig.enableDebugMode,

        // Localization
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        locale: const Locale('ar'), // Default to Arabic

        // Theme
        theme: AppTheme.lightTheme,
        darkTheme: AppTheme.darkTheme,
        themeMode: ThemeMode.light,

        // Routing
        routerConfig: AppRouter.router,
      ),
    );
  }
}
```

#### API Client Implementation
```dart
// lib/core/services/api_client.dart
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:qetar_fein/core/config/api_config.dart';
import '../errors/exceptions.dart';

class ApiClient {
  static final ApiClient _instance = ApiClient._internal();
  factory ApiClient() => _instance;
  ApiClient._internal();

  late final Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  String? _accessToken;
  bool _isInitialized = false;

  Future<void> initialize() async {
    if (_isInitialized) return;

    _dio = Dio(BaseOptions(
      baseUrl: ApiConfig.baseUrl,
      connectTimeout: const Duration(milliseconds: ApiConfig.connectTimeout),
      receiveTimeout: const Duration(milliseconds: ApiConfig.receiveTimeout),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Accept-Language': 'ar', // Default to Arabic
        'X-Requested-With': 'XMLHttpRequest',
      },
    ));

    // Add interceptors
    _dio.interceptors.add(_createAuthInterceptor());
    _dio.interceptors.add(_createLoggingInterceptor());
    _dio.interceptors.add(_createErrorInterceptor());

    _isInitialized = true;
  }

  InterceptorsWrapper _createAuthInterceptor() {
    return InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _getAuthToken();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          // Handle token expiration
          await _handleTokenExpiration();
        }
        handler.next(error);
      },
    );
  }

  Future<String?> _getAuthToken() async {
    // Get token from secure storage
    return null; // Implement token retrieval
  }

  Future<void> _handleTokenExpiration() async {
    // Handle token expiration - logout user
  }

  Dio get dio => _dio;
}

// Usage example
class TrainRepository {
  final ApiClient _apiClient = ApiClient();

  Future<List<Train>> getTrains({
    int page = 1,
    int perPage = 20,
    String? status,
    String? search,
  }) async {
    try {
      final response = await _apiClient.dio.get('/trains', queryParameters: {
        'page': page,
        'per_page': perPage,
        if (status != null) 'status': status,
        if (search != null) 'search': search,
      });

      if (response.data['status'] == 'success') {
        final trains = (response.data['data']['trains'] as List)
            .map((json) => Train.fromJson(json))
            .toList();
        return trains;
      } else {
        throw ApiException(response.data['message']);
      }
    } on DioException catch (e) {
      throw _handleDioException(e);
    }
  }

  ApiException _handleDioException(DioException e) {
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.receiveTimeout:
        return ApiException('Connection timeout');
      case DioExceptionType.badResponse:
        return ApiException(e.response?.data['message'] ?? 'Server error');
      default:
        return ApiException('Network error');
    }
  }
}
```

#### WebSocket Service Implementation
```dart
// lib/core/services/websocket_service.dart
import 'dart:convert';
import 'package:qetar_fein/core/config/api_config.dart';
import 'package:web_socket_channel/web_socket_channel.dart';
import '../utils/logger.dart';

class WebSocketService {
  static final WebSocketService _instance = WebSocketService._internal();
  factory WebSocketService() => _instance;
  WebSocketService._internal();

  WebSocketChannel? _channel;
  final Map<String, List<Function(dynamic)>> _subscriptions = {};

  void connect() {
    try {
      final wsUrl = '${ApiConfig.webSocketUrl}/app/${ApiConfig.reverbAppKey}';
      _channel = WebSocketChannel.connect(Uri.parse(wsUrl));

      _channel!.stream.listen(
        _handleMessage,
        onError: _handleError,
        onDone: _handleDisconnection,
      );

      AppLogger.i('WebSocket connected to: $wsUrl');
    } catch (e) {
      AppLogger.e('WebSocket connection error: $e');
    }
  }

  void subscribe(String channel, Function(dynamic) callback) {
    if (!_subscriptions.containsKey(channel)) {
      _subscriptions[channel] = [];

      // Send subscription message
      _channel?.sink.add(jsonEncode({
        'event': 'pusher:subscribe',
        'data': {'channel': channel}
      }));
    }

    _subscriptions[channel]!.add(callback);
  }

  void unsubscribe(String channel) {
    _subscriptions.remove(channel);

    _channel?.sink.add(jsonEncode({
      'event': 'pusher:unsubscribe',
      'data': {'channel': channel}
    }));
  }

  void _handleMessage(dynamic message) {
    try {
      final data = jsonDecode(message);
      final channel = data['channel'];

      if (_subscriptions.containsKey(channel)) {
        for (final callback in _subscriptions[channel]!) {
          callback(data);
        }
      }
    } catch (e) {
      print('WebSocket message parse error: $e');
    }
  }

  void _handleError(error) {
    print('WebSocket error: $error');
    // Implement reconnection logic
  }

  void _handleDisconnection() {
    print('WebSocket disconnected');
    // Implement reconnection logic
  }

  void disconnect() {
    _channel?.sink.close();
    _subscriptions.clear();
  }
}
```

### Test Credentials
- **Phone**: 01011761786 (Egyptian format)
- **Password**: password123
- **Admin Login**: admin@qetarfein.com / password123

### Build Commands
```bash
# Debug build
flutter run

# Release build
flutter build apk --release
flutter build appbundle --release
flutter build ios --release
```

### Package Configuration
**pubspec.yaml:**
```yaml
name: qetar_fein
description: Egyptian National Railways tracking app
version: 1.0.0+1

environment:
  sdk: '>=3.0.0 <4.0.0'

dependencies:
  flutter:
    sdk: flutter

  # State Management
  flutter_bloc: ^8.1.6
  equatable: ^2.0.5

  # Networking
  dio: ^5.4.0
  web_socket_channel: ^2.4.3

  # Storage
  flutter_secure_storage: ^9.0.0
  shared_preferences: ^2.2.3

  # Navigation
  go_router: ^12.1.3

  # Localization
  intl: ^0.18.1

  # UI
  cached_network_image: ^3.4.0
  pinput: ^4.0.0

  # Location
  geolocator: ^10.1.1
  google_maps_flutter: ^2.5.3

  # Firebase
  firebase_core: ^2.27.0
  firebase_messaging: ^14.7.19
  flutter_local_notifications: ^17.2.4

  # Utilities
  logger: ^2.6.1
  get_it: ^7.7.0
  json_annotation: ^4.8.1

dev_dependencies:
  flutter_test:
    sdk: flutter
  build_runner: ^2.4.13
  json_serializable: ^6.8.0
  flutter_lints: ^5.0.0
```

---

This API contract provides complete specifications for building the Qetar Fein mobile application with proper integration to the Laravel backend following Egyptian National Railways business rules.

## Summary of Changes

### Simplified Architecture
- **Single Environment**: Production-only configuration, no dev/staging environments
- **Base URL**: `https://qetar.elbenaa.com/api/v1`
- **WebSocket**: `wss://qetar.elbenaa.com`
- **Package Name**: `com.qetarfein.app`
- **App Name**: Qetar Fein (Egyptian Railways)

### Configuration Highlights
- All environment switching removed
- Simplified API config with const values
- No build flavors or environment-specific files
- Direct production deployment focus
- Egyptian phone number format (11 digits: 010/011/012/015)

### Key Features
- **Authentication**: Laravel Sanctum Bearer tokens
- **Primary Language**: Arabic (ar) with English (en) support
- **Real-time**: WebSocket support for live train tracking
- **Rewards**: Points system for completed assignments
- **Community**: Trip-based chat and status updates
- **Location**: GPS tracking for active assignments