<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ReviewResource\Pages;

use App\Filament\Tenant\Resources\ReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        // M1 read-only: nessuna azione di creazione manuale
        return [];
    }
}
