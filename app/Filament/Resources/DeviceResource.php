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
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Actions\ActionGroup;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\Http;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'Device';

    protected static ?string $recordTitleAttribute = 'name';

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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->device)
                    ->icon('heroicon-o-user-circle'),
                Tables\Columns\TextColumn::make('package')
                    ->badge()
                    ->visibleFrom('md')
                    ->color(fn (string $state): string => match ($state) {
                        'premium' => 'success',
                        'free' => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('quota')
                    ->numeric()
                    ->icon('heroicon-o-chart-bar-square'),
                Tables\Columns\TextColumn::make('autoread')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'on' => 'Aktif',
                        'off' => 'Non Aktif',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'on' => 'success',
                        'off' => 'danger',
                    })
                    ->visibleFrom('md')
                    ->label('Auto Read'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'connected' => 'success',
                        'disconnect' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'connected' => 'Connected',
                        'disconnect' => 'Disconnected',
                        default => $state,
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('showQr')
                    ->icon('heroicon-o-qr-code')
                    ->tooltip('Show QR Code')
                    ->outlined()
                    ->button()
                    ->label(false)
                    ->modalContent(function ($record) {
                        $result = Device::getQrCode($record->device);

                        if (!$result['status']) {
                            return view('filament.resources.device-resource.pages.qr-error', [
                                'message' => $result['message']
                            ]);
                        }

                        return view('filament.resources.device-resource.pages.qr-code', [
                            'qrHtml' => $result['html']
                        ]);
                    })
                    ->modalWidth('sm'),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('disconnectDevice')
                        ->icon('heroicon-o-arrow-left-start-on-rectangle')
                        ->modalHeading('Disconnect Device')
                        ->modalSubheading('Are you sure you want to disconnect this device?')
                        ->modalButton('Disconnect')
                        ->modalWidth('lg')
                        ->label('Disconnect')
                        ->action(function ($record) {
                            $result = Device::disconnectDevice($record->device);
                        }),
                    Tables\Actions\Action::make('requestOtp')
                        ->label('Request OTP')
                        ->icon('heroicon-o-arrow-down-tray')
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
                        ->icon('heroicon-o-trash')
                        ->label('Delete')
                        ->requiresConfirmation()
                        ->modalHeading('Delete Device')
                        ->modalDescription('Are you sure you want to delete this device? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete')
                        ->modalWidth('lg')
                        ->form([
                            TextInput::make('otp')
                                ->label('OTP')
                                ->required()
                                ->numeric()
                        ])
                        ->action(function ($record, $data) {
                            $result = Device::deleteDevice($record->device, $data);

                            Notification::make()
                                ->title($result['message'])
                                ->success($result['status'])
                                ->danger(!$result['status'])
                                ->send();

                            if ($result['status']) {
                                $record->delete();
                            }
                        }),
                ])
                ->icon('heroicon-o-bars-3-center-left')
                ->link()
                ->label(' ')
                ->tooltip('Actions'),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
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
            // 'create' => Pages\CreateDevice::route('/create'),
            // 'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }
}
