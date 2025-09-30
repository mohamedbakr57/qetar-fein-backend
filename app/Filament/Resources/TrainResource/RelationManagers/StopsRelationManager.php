<?php

namespace App\Filament\Resources\TrainResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StopsRelationManager extends RelationManager
{
    protected static string $relationship = 'stops';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('station_id')
                    ->relationship('station', 'name')
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('stop_number')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                Forms\Components\TimePicker::make('arrival_time')
                    ->seconds(false),
                Forms\Components\TimePicker::make('departure_time')
                    ->seconds(false),
                Forms\Components\TextInput::make('platform')
                    ->maxLength(10),
                Forms\Components\TextInput::make('stop_duration_minutes')
                    ->numeric()
                    ->default(5)
                    ->suffix('minutes'),
                Forms\Components\Toggle::make('is_major_stop')
                    ->label('Major Stop'),
                Forms\Components\Textarea::make('notes')
                    ->maxLength(500),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('stop_number')
            ->columns([
                Tables\Columns\TextColumn::make('stop_number')
                    ->label('Stop #')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('station.name')
                    ->label('Station')
                    ->formatStateUsing(function ($record) {
                        if (!$record->station) return 'N/A';

                        $name = $record->station->name;

                        // Try to decode as JSON first
                        $decoded = json_decode($name, true);
                        if ($decoded && is_array($decoded)) {
                            return $decoded[app()->getLocale()] ?? $decoded['en'] ?? $decoded['ar'] ?? $name;
                        }

                        // If not JSON, return the raw name
                        return $name;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('arrival_time')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('departure_time')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('platform')
                    ->badge()
                    ->color('secondary'),

                Tables\Columns\TextColumn::make('formatted_stop_duration')
                    ->label('Stop Duration'),

                Tables\Columns\IconColumn::make('is_major_stop')
                    ->label('Major')
                    ->boolean()
                    ->trueIcon('heroicon-m-star')
                    ->falseIcon('heroicon-m-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('notes')
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();

                        if (strlen($state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return $state;
                    }),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_major_stop')
                    ->label('Major Stops Only'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('stop_number');
    }
}
