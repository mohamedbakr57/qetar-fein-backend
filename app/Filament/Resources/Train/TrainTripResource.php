<?php

namespace App\Filament\Resources\Train;

use App\Filament\Resources\Train\TrainTripResource\Pages;
use App\Filament\Resources\Train\TrainTripResource\RelationManagers;
use App\Models\Train\TrainTrip;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TrainTripResource extends Resource
{
    protected static ?string $model = TrainTrip::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainTrips::route('/'),
            'create' => Pages\CreateTrainTrip::route('/create'),
            'edit' => Pages\EditTrainTrip::route('/{record}/edit'),
        ];
    }
}
