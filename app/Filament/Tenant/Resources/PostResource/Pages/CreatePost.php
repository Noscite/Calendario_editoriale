<?php

namespace App\Filament\Tenant\Resources\PostResource\Pages;

use App\Filament\Tenant\Resources\PostResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;
}
