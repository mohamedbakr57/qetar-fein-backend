<?php

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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStations::route('/'),
            'create' => Pages\CreateStation::route('/create'),
            'edit' => Pages\EditStation::route('/{record}/edit'),
        ];
    }
}
