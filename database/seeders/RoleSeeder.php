<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionName;
use App\Enums\Role as RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        foreach (RoleName::cases() as $roleName) {
            Role::findOrCreate($roleName->value)->syncPermissions(
                array_map(fn (PermissionName $permission) => $permission->value, $roleName->permissions()),
            );
        }
    }
}
