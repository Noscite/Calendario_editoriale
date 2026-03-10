<?php

namespace App\Filament\Admin\Resources\UsageLogResource\Pages;

use App\Filament\Admin\Resources\UsageLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUsageLog extends EditRecord
{
    protected static string $resource = UsageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
