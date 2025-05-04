<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Textarea;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected static ?string $title = 'Device List';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Tambah')
                ->action(function ($data) {
                    Device::addDevice($data['name'], $data['device'], $data['autoread'], $data['personal'], $data['group']);
                    Notification::make()
                        ->title('Success')
                        ->body('Perangkat berhasil ditambahkan')
                        ->success()
                        ->send();
                })
                ->form([
                    Split::make([
                        Section::make([
                            TextInput::make('name')
                                ->required()
                                ->label('Nama')
                                ->placeholder('Nama Perangkat')
                                ->maxLength(255),
                            TextInput::make('device')
                                ->required()
                                ->label('Whatsapp')
                                ->placeholder('+62 ***')
                                ->maxLength(255),
                        ]),
                        Section::make([
                            Toggle::make('autoread')
                                ->required()
                                ->label('Auto Read')
                                ->default(false),
                            Toggle::make('personal')
                                ->required()
                                ->label('Personal')
                                ->default(false),
                            Toggle::make('group')
                                ->required()
                                ->label('Group')
                                ->default(false),
                        ])->grow(true),
                    ])->from('md'),
                ])
                ->slideOver()
                ->label('Tambah')
                ->icon('heroicon-o-plus')
                ->color('success'),

            Actions\Action::make('Sync')
                ->action(function () {
                    try {
                        $result = Device::syncFromApi();

                        // Notifikasi sukses
                        Notification::make()
                            ->title('Sinkronisasi Berhasil')
                            ->body('Data perangkat telah diperbarui')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        // Notifikasi error
                        Notification::make()
                            ->title('Sinkronisasi Gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation() // opsional: tambahkan konfirmasi
                ->modalHeading('Sinkronisasi Perangkat')
                ->modalDescription('Apakah Anda yakin ingin melakukan sinkronisasi data perangkat?')
                ->modalButton('Ya, Sinkronisasi')
        ];
    }
}
