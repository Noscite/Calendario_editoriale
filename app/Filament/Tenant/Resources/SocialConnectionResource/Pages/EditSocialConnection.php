<?php

namespace App\Filament\Tenant\Resources\SocialConnectionResource\Pages;

use App\Filament\Tenant\Resources\SocialConnectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSocialConnection extends EditRecord
{
    protected static string $resource = SocialConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
