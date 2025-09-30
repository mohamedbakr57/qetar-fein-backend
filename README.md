# Qatar Fein Platform - Backend

**قطر فين - "Where's My Train"** - A comprehensive train tracking and community platform for Qatar's railway system.

## Overview

This Laravel-based backend powers a bilingual (Arabic/English) train tracking application with real-time updates, community features, and gamification elements.

## Features

### Core Functionality
- **Train Tracking**: Real-time location and status updates
- **Station Management**: Comprehensive station information
- **Passenger Assignments**: Users can assign themselves to train journeys
- **Community Platform**: Trip-based community messaging
- **Gamification**: Badges, rewards, and leaderboards
- **Bilingual Support**: Arabic (default) and English

### Technical Features
- **API**: RESTful API with versioning (v1)
- **Real-time**: Laravel Reverb for WebSocket connections
- **Authentication**: Phone verification + Social logins
- **Admin Panel**: Filament-based admin dashboard
- **Internationalization**: Spatie Translatable package

## Tech Stack

- **Framework**: Laravel 11
- **Database**: SQLite (Egyptian train data) + MySQL (application data)
- **Cache/Queue**: Redis
- **Real-time**: Laravel Reverb
- **Admin Panel**: Filament v3
- **Localization**: Spatie Laravel Translatable
- **Authentication**: Laravel Sanctum

## Database Structure

The system follows Egyptian National Railways database schema with:
- **Trains**: Train information and specifications
- **Stations**: Station locations and details
- **Stops**: Train journey sequences with station stops
- **Users**: User accounts and profiles
- **Assignments**: Passenger-to-train assignments
- **Communities**: Trip-based messaging communities
- **Rewards**: Gamification and badge system

## Installation

### Prerequisites
- PHP 8.2+
- Composer
- Node.js & NPM
- Redis
- MySQL

### Setup Steps

1. **Clone the repository**
```bash
git clone <repository-url>
cd qatar-fein-backend
```

2. **Install dependencies**
```bash
composer install
npm install
```

3. **Environment configuration**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Database setup**
```bash
php artisan migrate
php artisan db:seed --class=BusinessRulesTrainSeeder
```

5. **Filament admin user**
```bash
php artisan make:filament-user
```

6. **Start services**
```bash
# Laravel application
php artisan serve

# Laravel Reverb (real-time)
php artisan reverb:start

# Queue worker
php artisan queue:work
```

## API Documentation

### Base URL
```
http://localhost:8000/api/v1/
```

### Authentication
- **Phone Verification**: `/auth/phone/send-otp`, `/auth/phone/verify`
- **Social Login**: `/auth/social/{provider}`
- **Profile Management**: `/auth/profile`

### Core Endpoints
- **Trains**: `/trains`, `/trains/{id}`, `/trains/{id}/schedule`
- **Stations**: `/stations`, `/stations/{id}/departures`, `/stations/{id}/arrivals`
- **Assignments**: `/assignments`, `/assignments/{id}`
- **Communities**: `/communities/{tripId}`, `/communities/{tripId}/messages`
- **Rewards**: `/rewards/my`, `/badges/my`, `/leaderboard`

### Headers
```
Accept: application/json
Authorization: Bearer {token}
Accept-Language: ar (default) | en
```

## Project Structure

```
app/
├── Http/Controllers/API/V1/    # API Controllers
├── Models/                     # Eloquent Models
│   ├── Train/                 # Train-related models
│   ├── User/                  # User-related models
│   └── Community/             # Community-related models
├── Services/                  # Business logic services
├── Events/                    # Event classes
└── Filament/                  # Admin panel resources

database/
├── migrations/                # Database migrations
├── seeders/                   # Database seeders
└── factories/                 # Model factories

routes/
├── api.php                    # API routes
└── web.php                    # Web routes (admin)
```

## Business Rules

The system implements Egyptian National Railways structure:
- Trains have direct stops (journey sequences)
- Each stop connects a train to a station with timing
- No complex route/schedule intermediary tables
- Simple Train → Stops → Station relationships

## Development

### Code Standards
- PSR-12 coding standards
- Laravel best practices
- Comprehensive validation
- Bilingual content support

### Testing
```bash
php artisan test
```

### Code Quality
```bash
composer run phpstan
composer run pint
```

## Deployment

### Docker Support
```bash
docker-compose up -d
```

### Production Checklist
- [ ] Environment variables configured
- [ ] Database migrated and seeded
- [ ] Queue workers running
- [ ] Redis cache configured
- [ ] SSL certificates installed
- [ ] Backup strategy implemented

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests
5. Submit a pull request

## License

This project is proprietary. All rights reserved.

## Support

For technical support or questions, please contact the development team.