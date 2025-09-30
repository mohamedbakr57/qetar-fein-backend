# Filament Admin Dashboard - قطر فين

## 1. Installation and Setup

### A. Install Filament Panel

```bash
# Create admin panel
php artisan filament:install --panels

# Create admin user
php artisan make:filament-user

# Install Filament plugins for enhanced functionality
composer require filament/spatie-laravel-translatable-plugin
composer require filament/spatie-laravel-media-library-plugin
```

### B. Panel Configuration

```php
<?php
// app/Providers/Filament/AdminPanelProvider.php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('/admin')
            ->login()
            ->colors([
                'primary' => Color::Green, // Saudi Green
                'gray' => Color::Slate,
            ])
            ->brandName('القطر فين - Where\'s My Train')
            ->brandLogo(asset('images/logo.png'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
                \App\Filament\Widgets\TrainSystemOverview::class,
                \App\Filament\Widgets\UserActivityChart::class,
                \App\Filament\Widgets\CommunityActivityChart::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->spa(); // Enable SPA mode for better performance
    }
}
```

## 2. Resource Examples with Bilingual Support

### A. Station Resource

```php
<?php
// app/Filament/Resources/StationResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\StationResource\Pages;
use App\Models\Train\Station;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Tables\Filters\SelectFilter;
use Filament\Support\Colors\Color;

class StationResource extends Resource
{
    protected static ?string $model = Station::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Train System';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(10)
                            ->placeholder('RUH')
                            ->helperText('Station code (e.g., RUH for Riyadh)'),

                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive', 
                                'maintenance' => 'Under Maintenance',
                            ])
                            ->default('active'),

                        Forms\Components\TextInput::make('order_index')
                            ->numeric()
                            ->default(0)
                            ->helperText('Order in station list'),
                    ])->columns(3),

                Tabs::make('Translations')
                    ->tabs([
                        Tabs\Tab::make('Arabic')
                            ->schema([
                                Forms\Components\TextInput::make('name.ar')
                                    ->label('Name (Arabic)')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('محطة الرياض المركزية'),

                                Forms\Components\Textarea::make('description.ar')
                                    ->label('Description (Arabic)')
                                    ->maxLength(1000)
                                    ->placeholder('وصف المحطة بالعربية'),

                                Forms\Components\TextInput::make('city.ar')
                                    ->label('City (Arabic)')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('الرياض'),

                                Forms\Components\TextInput::make('region.ar')
                                    ->label('Region (Arabic)')
                                    ->maxLength(255)
                                    ->placeholder('منطقة الرياض'),
                            ])->columns(2),

                        Tabs\Tab::make('English')
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label('Name (English)')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Riyadh Central Station'),

                                Forms\Components\Textarea::make('description.en')
                                    ->label('Description (English)')
                                    ->maxLength(1000)
                                    ->placeholder('Station description in English'),

                                Forms\Components\TextInput::make('city.en')
                                    ->label('City (English)')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Riyadh'),

                                Forms\Components\TextInput::make('region.en')
                                    ->label('Region (English)')
                                    ->maxLength(255)
                                    ->placeholder('Riyadh Region'),
                            ])->columns(2),
                    ]),

                Section::make('Location & Technical Details')
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->required()
                            ->numeric()
                            ->step(0.00000001)
                            ->placeholder('24.7136'),

                        Forms\Components\TextInput::make('longitude')
                            ->required()
                            ->numeric()
                            ->step(0.00000001)
                            ->placeholder('46.6753'),

                        Forms\Components\TextInput::make('elevation')
                            ->numeric()
                            ->suffix('meters')
                            ->placeholder('600'),

                        Forms\Components\TextInput::make('country_code')
                            ->default('SA')
                            ->maxLength(2),

                        Forms\Components\TextInput::make('timezone')
                            ->default('Asia/Riyadh')
                            ->maxLength(50),

                        Forms\Components\CheckboxList::make('facilities')
                            ->options([
                                'wifi' => 'WiFi',
                                'restaurant' => 'Restaurant',
                                'parking' => 'Parking',
                                'prayer_area' => 'Prayer Area',
                                'family_area' => 'Family Area',
                                'atm' => 'ATM',
                                'pharmacy' => 'Pharmacy',
                                'shopping' => 'Shopping',
                                'disabled_access' => 'Disabled Access',
                            ])
                            ->columns(3)
                            ->gridDirection('row'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->getTranslation('name', app()->getLocale())),

                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->formatStateUsing(fn ($record) => $record->getTranslation('city', app()->getLocale())),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'maintenance',
                        'danger' => 'inactive',
                    ]),

                Tables\Columns\TextColumn::make('coordinates')
                    ->label('Location')
                    ->formatStateUsing(fn ($record) => number_format($record->latitude, 4) . ', ' . number_format($record->longitude, 4)),

                Tables\Columns\TagsColumn::make('facilities')
                    ->separator(',')
                    ->limit(3),

                Tables\Columns\TextColumn::make('order_index')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Under Maintenance',
                    ]),
                
                SelectFilter::make('facilities')
                    ->options([
                        'wifi' => 'WiFi',
                        'restaurant' => 'Restaurant',
                        'parking' => 'Parking',
                        'prayer_area' => 'Prayer Area',
                    ])
                    ->query(function ($query, $data) {
                        if ($data['value']) {
                            return $query->whereJsonContains('facilities', $data['value']);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order_index');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStations::route('/'),
            'create' => Pages\CreateStation::route('/create'),
            'view' => Pages\ViewStation::route('/{record}'),
            'edit' => Pages\EditStation::route('/{record}/edit'),
        ];
    }
}
```

### B. Train Resource

```php
<?php
// app/Filament/Resources/TrainResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainResource\Pages;
use App\Models\Train\Train;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;

class TrainResource extends Resource
{
    protected static ?string $model = Train::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Train System';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('HH001'),

                        Forms\Components\Select::make('type')
                            ->required()
                            ->options([
                                'passenger' => 'Passenger',
                                'freight' => 'Freight',
                                'high_speed' => 'High Speed',
                                'metro' => 'Metro',
                            ])
                            ->default('passenger'),

                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'maintenance' => 'Under Maintenance',
                            ])
                            ->default('active'),

                        Forms\Components\TextInput::make('capacity')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(2000)
                            ->suffix('passengers'),

                        Forms\Components\TextInput::make('max_speed')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(400)
                            ->suffix('km/h'),
                    ])->columns(3),

                Tabs::make('Translations')
                    ->tabs([
                        Tabs\Tab::make('Arabic')
                            ->schema([
                                Forms\Components\TextInput::make('name.ar')
                                    ->label('Name (Arabic)')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('قطار الحرمين السريع'),

                                Forms\Components\Textarea::make('description.ar')
                                    ->label('Description (Arabic)')
                                    ->maxLength(1000),

                                Forms\Components\TextInput::make('operator.ar')
                                    ->label('Operator (Arabic)')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('الخطوط الحديدية السعودية'),
                            ]),

                        Tabs\Tab::make('English')
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label('Name (English)')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Haramain High Speed Rail'),

                                Forms\Components\Textarea::make('description.en')
                                    ->label('Description (English)')
                                    ->maxLength(1000),

                                Forms\Components\TextInput::make('operator.en')
                                    ->label('Operator (English)')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Saudi Railways Organization'),
                            ]),
                    ]),

                Section::make('Amenities & Features')
                    ->schema([
                        Forms\Components\CheckboxList::make('amenities')
                            ->options([
                                'wifi' => 'WiFi',
                                'dining' => 'Dining Car',
                                'ac' => 'Air Conditioning',
                                'prayer_area' => 'Prayer Area',
                                'family_area' => 'Family Section',
                                'business_class' => 'Business Class',
                                'disabled_access' => 'Disabled Access',
                                'power_outlets' => 'Power Outlets',
                                'entertainment' => 'Entertainment System',
                            ])
                            ->columns(3)
                            ->gridDirection('row'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->getTranslation('name', app()->getLocale())),

                Tables\Columns\TextColumn::make('operator')
                    ->formatStateUsing(fn ($record) => $record->getTranslation('operator', app()->getLocale())),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'passenger',
                        'success' => 'high_speed',
                        'warning' => 'freight',
                        'secondary' => 'metro',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'maintenance',
                        'danger' => 'inactive',
                    ]),

                Tables\Columns\TextColumn::make('capacity')
                    ->suffix(' passengers')
                    ->sortable(),

                Tables\Columns\TextColumn::make('max_speed')
                    ->suffix(' km/h')
                    ->sortable(),

                Tables\Columns\TagsColumn::make('amenities')
                    ->separator(',')
                    ->limit(3),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'passenger' => 'Passenger',
                        'freight' => 'Freight',
                        'high_speed' => 'High Speed',
                        'metro' => 'Metro',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Under Maintenance',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrains::route('/'),
            'create' => Pages\CreateTrain::route('/create'),
            'view' => Pages\ViewTrain::route('/{record}'),
            'edit' => Pages\EditTrain::route('/{record}/edit'),
        ];
    }
}
```

## 3. Custom Dashboard Widgets

### A. Train System Overview Widget

```php
<?php
// app/Filament/Widgets/TrainSystemOverview.php

namespace App\Filament\Widgets;

use App\Models\Train\Train;
use App\Models\Train\Station;
use App\Models\Train\TrainTrip;
use App\Models\PassengerAssignment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TrainSystemOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $activeTrains = Train::where('status', 'active')->count();
        $activeStations = Station::where('status', 'active')->count();
        $todayTrips = TrainTrip::whereDate('trip_date', today())->count();
        $activeAssignments = PassengerAssignment::whereIn('status', ['assigned', 'boarded', 'in_transit'])->count();

        // Calculate delay statistics
        $delayedTrips = TrainTrip::whereDate('trip_date', today())
            ->where('delay_minutes', '>', 0)
            ->count();

        $avgDelay = TrainTrip::whereDate('trip_date', today())
            ->where('delay_minutes', '>', 0)
            ->avg('delay_minutes');

        return [
            Stat::make('Active Trains', $activeTrains)
                ->description('Currently operational')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Active Stations', $activeStations)
                ->description('Serving passengers')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),

            Stat::make('Today\'s Trips', $todayTrips)
                ->description($delayedTrips > 0 ? "{$delayedTrips} delayed" : 'All on time')
                ->descriptionIcon($delayedTrips > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                ->color($delayedTrips > 0 ? 'warning' : 'success'),

            Stat::make('Active Passengers', $activeAssignments)
                ->description('Currently traveling')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Average Delay', $avgDelay ? round($avgDelay, 1) . ' min' : '0 min')
                ->description('Today\'s performance')
                ->descriptionIcon('heroicon-m-clock')
                ->color($avgDelay > 15 ? 'danger' : ($avgDelay > 5 ? 'warning' : 'success')),
        ];
    }
}
```

### B. User Activity Chart Widget

```php
<?php
// app/Filament/Widgets/UserActivityChart.php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\PassengerAssignment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class UserActivityChart extends ChartWidget
{
    protected static ?string $heading = 'User Activity (Last 7 Days)';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo)->toDateString();
            
            return [
                'date' => $date,
                'registrations' => User::whereDate('created_at', $date)->count(),
                'assignments' => PassengerAssignment::whereDate('created_at', $date)->count(),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'New Registrations',
                    'data' => $data->pluck('registrations')->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                ],
                [
                    'label' => 'Trip Assignments',
                    'data' => $data->pluck('assignments')->toArray(),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'borderColor' => 'rgb(16, 185, 129)',
                ],
            ],
            'labels' => $data->pluck('date')->map(fn($date) => \Carbon\Carbon::parse($date)->format('M d'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

## 4. Custom Pages

### A. Real-time Train Monitoring Page

```php
<?php
// app/Filament/Pages/TrainMonitoring.php

namespace App\Filament\Pages;

use App\Models\Train\TrainTrip;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Livewire\Attributes\On;

class TrainMonitoring extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static string $view = 'filament.pages.train-monitoring';
    protected static ?string $navigationGroup = 'Real-time Operations';
    protected static ?int $navigationSort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TrainTrip::with(['schedule.route.train', 'currentStation', 'nextStation'])
                    ->whereDate('trip_date', today())
                    ->whereIn('status', ['boarding', 'departed', 'in_transit'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('schedule.route.train.number')
                    ->label('Train')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('schedule.route.train.name')
                    ->label('Train Name')
                    ->formatStateUsing(fn ($record) => $record->schedule->route->train->getTranslation('name', app()->getLocale())),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'boarding',
                        'primary' => 'departed',
                        'success' => 'in_transit',
                    ]),

                Tables\Columns\TextColumn::make('currentStation.name')
                    ->label('Current Station')
                    ->formatStateUsing(fn ($record) => $record->currentStation?->getTranslation('name', app()->getLocale()) ?? 'En Route'),

                Tables\Columns\TextColumn::make('nextStation.name')
                    ->label('Next Station')
                    ->formatStateUsing(fn ($record) => $record->nextStation?->getTranslation('name', app()->getLocale()) ?? 'N/A'),

                Tables\Columns\TextColumn::make('speed_kmh')
                    ->label('Speed')
                    ->suffix(' km/h')
                    ->color(fn ($state) => $state > 80 ? 'success' : ($state > 40 ? 'warning' : 'danger')),

                Tables\Columns\TextColumn::make('delay_minutes')
                    ->label('Delay')
                    ->suffix(' min')
                    ->color(fn ($state) => $state == 0 ? 'success' : ($state <= 10 ? 'warning' : 'danger')),

                Tables\Columns\TextColumn::make('passenger_count')
                    ->label('Passengers')
                    ->suffix(' onboard'),

                Tables\Columns\TextColumn::make('last_location_update')
                    ->label('Last Update')
                    ->since()
                    ->tooltip(fn ($record) => $record->last_location_update?->format('H:i:s')),
            ])
            ->actions([
                Tables\Actions\Action::make('view_map')
                    ->label('View on Map')
                    ->icon('heroicon-o-map')
                    ->url(fn ($record) => route('filament.admin.pages.train-map', ['trip' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->poll('30s') // Auto-refresh every 30 seconds
            ->emptyStateHeading('No Active Trips')
            ->emptyStateDescription('All trains are currently at stations or not in service.')
            ->emptyStateIcon('heroicon-o-truck');
    }

    #[On('train-location-updated')]
    public function refreshTable(): void
    {
        $this->dispatch('$refresh');
    }
}
```

## 5. Custom Actions and Bulk Actions

### A. Station Management Actions

```php
<?php
// In StationResource.php - Custom Actions

Tables\Actions\Action::make('toggle_status')
    ->label('Toggle Status')
    ->icon('heroicon-o-arrow-path')
    ->color('warning')
    ->action(function ($record) {
        $newStatus = $record->status === 'active' ? 'inactive' : 'active';
        $record->update(['status' => $newStatus]);
        
        Notification::make()
            ->title('Status Updated')
            ->body("Station {$record->code} is now {$newStatus}")
            ->success()
            ->send();
    })
    ->requiresConfirmation(),

Tables\Actions\Action::make('view_on_map')
    ->label('View on Map')
    ->icon('heroicon-o-map')
    ->url(fn ($record) => "https://maps.google.com/maps?q={$record->latitude},{$record->longitude}")
    ->openUrlInNewTab(),
```

## 6. Custom Form Components

### A. Coordinates Input Component

```php
<?php
// app/Filament/Components/CoordinatesInput.php

namespace App\Filament\Components;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Group;

class CoordinatesInput extends Component
{
    protected string $view = 'filament.components.coordinates-input';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getChildComponents(): array
    {
        return [
            Group::make([
                TextInput::make('latitude')
                    ->required()
                    ->numeric()
                    ->step(0.00000001)
                    ->placeholder('24.7136')
                    ->helperText('Decimal degrees'),

                TextInput::make('longitude')
                    ->required()
                    ->numeric()
                    ->step(0.00000001)
                    ->placeholder('46.6753')
                    ->helperText('Decimal degrees'),
            ])->columns(2),
        ];
    }
}
```

This Filament setup provides:

1. **Bilingual Admin Interface**: Arabic/English support throughout
2. **Comprehensive Resources**: Stations, trains, users, schedules management
3. **Real-time Monitoring**: Live train tracking and status updates
4. **Interactive Widgets**: Overview stats and activity charts
5. **Custom Actions**: Bulk operations and specialized functions
6. **Responsive Design**: Mobile-friendly admin interface
7. **Role-based Access**: Secure admin panel with authentication
8. **Performance Optimized**: SPA mode and efficient queries