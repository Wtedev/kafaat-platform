<?php

namespace App\Services\StaffUi;

use App\Models\User;
use App\Services\Rbac\PermissionMatrixCatalog;
use App\Services\Rbac\RbacCatalog;

final class StaffEffectivePermissions
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     sees: bool,
     *     edits: bool,
     *     items: list<array{name: string, label: string, level: ?string, from_role: bool, direct: bool}>
     * }>
     */
    public function grouped(User $user): array
    {
        $fromRole = array_fill_keys($user->getPermissionsViaRoles()->pluck('name')->all(), true);
        $direct = array_fill_keys($user->getDirectPermissions()->pluck('name')->all(), true);
        $owned = $fromRole + $direct;
        $placed = [];
        $groups = [];

        foreach (PermissionMatrixCatalog::groups() as $group) {
            $items = [];
            $sees = false;
            $edits = false;

            foreach ($this->levels($group['actions']) as $name => $level) {
                if (! isset($owned[$name])) {
                    continue;
                }

                $placed[$name] = true;
                $sees = $sees || $level === 'يشوف';
                $edits = $edits || $level === 'يعدل';
                $items[] = $this->item($name, $level, isset($fromRole[$name]), isset($direct[$name]));
            }

            if ($items === []) {
                continue;
            }

            $groups[] = [
                'key' => $group['key'],
                'label' => $group['label'],
                'sees' => $sees,
                'edits' => $edits,
                'items' => $items,
            ];
        }

        $other = [];
        foreach (array_keys($owned) as $name) {
            if (isset($placed[$name])) {
                continue;
            }
            $other[] = $this->item($name, null, isset($fromRole[$name]), isset($direct[$name]));
        }

        if ($other !== []) {
            $groups[] = [
                'key' => 'other',
                'label' => 'صلاحيات أخرى',
                'sees' => false,
                'edits' => false,
                'items' => $other,
            ];
        }

        return $groups;
    }

    /**
     * @param  array<string, list<string>|null>  $actions
     * @return array<string, string>
     */
    private function levels(array $actions): array
    {
        $levels = [];

        foreach (['view' => 'يشوف', 'create' => 'يعدل', 'update' => 'يعدل', 'delete' => 'يعدل'] as $action => $level) {
            foreach ($actions[$action] ?? [] as $name) {
                if (($levels[$name] ?? null) === 'يعدل') {
                    continue;
                }
                $levels[$name] = $level;
            }
        }

        return $levels;
    }

    /**
     * @return array{name: string, label: string, level: ?string, from_role: bool, direct: bool}
     */
    private function item(string $name, ?string $level, bool $fromRole, bool $direct): array
    {
        return [
            'name' => $name,
            'label' => RbacCatalog::permissionArabicLabel($name),
            'level' => $level,
            'from_role' => $fromRole,
            'direct' => $direct,
        ];
    }
}
