<?php

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

    public static function getRelations(): array
    {
        return [
            // Business rules: Trains have stops (journey sequences)
            \App\Filament\Resources\TrainResource\RelationManagers\StopsRelationManager::class,
        ];
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
