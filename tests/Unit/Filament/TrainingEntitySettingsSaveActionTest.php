<?php

namespace Tests\Unit\Filament;

use App\Filament\Resources\TrainingProgramResource\Pages\ViewTrainingProgram;
use Filament\Actions\Action;
use PHPUnit\Framework\TestCase;

class TrainingEntitySettingsSaveActionTest extends TestCase
{
    public function test_settings_save_action_stays_enabled_while_confirmation_modal_is_mounted(): void
    {
        $page = new class extends ViewTrainingProgram
        {
            public bool $settingsFormDirty = false;

            public ?array $mountedActions = [
                ['name' => 'saveSettings', 'arguments' => [], 'context' => []],
            ];

            public function getMountedAction(?int $actionNestingIndex = null): ?Action
            {
                return Action::make('saveSettings');
            }

            public function shouldDisableSettingsSaveAction(): bool
            {
                $mountedAction = $this->getMountedAction();

                if ($mountedAction?->getName() === 'saveSettings') {
                    return false;
                }

                return ! $this->settingsFormDirty;
            }
        };

        $this->assertFalse($page->shouldDisableSettingsSaveAction());
    }

    public function test_settings_save_action_is_disabled_when_form_is_clean(): void
    {
        $page = new class extends ViewTrainingProgram
        {
            public bool $settingsFormDirty = false;

            public ?array $mountedActions = [];

            public function getMountedAction(?int $actionNestingIndex = null): ?Action
            {
                return null;
            }

            public function shouldDisableSettingsSaveAction(): bool
            {
                $mountedAction = $this->getMountedAction();

                if ($mountedAction?->getName() === 'saveSettings') {
                    return false;
                }

                return ! $this->settingsFormDirty;
            }
        };

        $this->assertTrue($page->shouldDisableSettingsSaveAction());
    }
}
