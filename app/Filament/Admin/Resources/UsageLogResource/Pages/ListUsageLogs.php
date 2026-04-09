<?php

namespace App\Filament\Admin\Resources\UsageLogResource\Pages;

use App\Filament\Admin\Resources\UsageLogResource;
use Filament\Resources\Pages\ListRecords;

class ListUsageLogs extends ListRecords
{
    protected static string $resource = UsageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
