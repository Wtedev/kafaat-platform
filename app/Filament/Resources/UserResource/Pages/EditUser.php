<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Pages\RedirectsEditToViewSettingsTab;
use App\Filament\Resources\UserResource;

class EditUser extends RedirectsEditToViewSettingsTab
{
    protected static string $resource = UserResource::class;
}
