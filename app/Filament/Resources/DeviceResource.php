<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
use App\Filament\Resources\DeviceResource\RelationManagers;
use App\Models\Device;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Actions\ActionGroup;
use Illuminate\Support\Facades\Http;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

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
                Tables\Columns\TextColumn::make('No')
                    ->rowIndex(),
                Tables\Columns\TextColumn::make('autoread')
                    ->searchable(),
                Tables\Columns\TextColumn::make('device')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('package')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quota')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('token')
                //     ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('requestOtp')
                    ->color('info')
                    ->button()
                    ->label(false)
                    ->icon('heroicon-o-arrow-left-end-on-rectangle')
                    ->action(function ($record) {
                        $result = Device::requestOtp($record->device);

                        if ($result['status']) {
                            Notification::make()
                                ->title($result['message'])
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title($result['message'])
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('deleteDevice')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->button()
                    ->label(false)
                    ->requiresConfirmation()
                    ->modalHeading('Delete Device')
                    ->modalSubheading('Are you sure you want to delete this device?')
                    ->modalButton('Delete')
                    ->modalWidth('lg')
                    ->form([
                        TextInput::make('otp')
                            ->label('OTP')
                            ->required()
                            ->numeric() // Jika OTP hanya angka
                    ])
                    ->action(function ($record, $data) {
                        $result = Device::deleteDevice($record->device, $data);

                        Notification::make()
                            ->title($result['message'])
                            ->success($result['status'])
                            ->danger(!$result['status'])
                            ->send();

                        if ($result['status']) {
                            $record->delete(); // Hapus record dari database jika sukses
                        }
                    }),
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
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }
}
