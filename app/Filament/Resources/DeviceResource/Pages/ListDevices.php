<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Tambah')
                ->action(function ($data) {
                    Device::addDevice($data['name'], $data['device'], $data['autoread'], $data['personal'], $data['group']);
                    Notification::make()
                        ->title('Device added successfully')
                        ->success()
                        ->send();
                })
                ->form([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('device')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('autoread')
                        ->required(),
                    Toggle::make('personal')
                        ->required(),
                    Toggle::make('group')
                        ->required(),
                ])
                ->slideOver()
                ->label('Tambah')
                ->icon('heroicon-o-plus')
                ->color('success'),

            Actions\Action::make('Sync')
                ->action(function () {
                    Device::syncFromApi();
                })
                ->icon('heroicon-o-arrow-path')
                ->color('info'),
        ];
    }
}
