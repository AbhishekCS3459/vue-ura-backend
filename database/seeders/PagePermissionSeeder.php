<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class PagePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            ['page_key' => 'schedule', 'page_name' => 'Schedule', 'description' => 'View and manage appointments'],
            ['page_key' => 'inactive_patients', 'page_name' => 'Inactive Patients', 'description' => 'Manage inactive patient follow-ups'],
            ['page_key' => 'staff_management', 'page_name' => 'Staff Management', 'description' => 'Manage staff members'],
            ['page_key' => 'branch_analytics', 'page_name' => 'Branch Analytics', 'description' => 'View branch analytics and reports'],
            ['page_key' => 'settings', 'page_name' => 'Branch Settings', 'description' => 'Configure branch settings'],
            ['page_key' => 'report_builder', 'page_name' => 'Report Builder', 'description' => 'Create and manage report templates'],
            ['page_key' => 'report_history', 'page_name' => 'Report History', 'description' => 'View report history'],
            ['page_key' => 'iam', 'page_name' => 'IAM', 'description' => 'Identity and Access Management'],
        ];

        foreach ($pages as $page) {
            // Check if page permission already exists
            $existing = DB::table('page_permissions')
                ->where('page_key', $page['page_key'])
                ->first();

            if ($existing === null) {
                $pageId = DB::table('page_permissions')->insertGetId([
                    'page_key' => $page['page_key'],
                    'page_name' => $page['page_name'],
                    'description' => $page['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->setDefaultPermissions($pageId, $page['page_key']);
            } else {
                // Update existing permissions if needed
                $this->setDefaultPermissions($existing->id, $page['page_key']);
            }
        }
    }

    private function setDefaultPermissions(int $pageId, string $pageKey): void
    {
        $defaultPermissions = [
            'super_admin' => [
                'schedule', 'inactive_patients', 'staff_management', 'branch_analytics',
                'settings', 'report_builder', 'report_history', 'iam',
            ],
            'branch_manager' => [
                'schedule', 'inactive_patients', 'staff_management', 'branch_analytics', 'settings',
                'report_builder', 'report_history',
            ],
            'staff' => [
                'schedule', 'inactive_patients',
            ],
        ];

        foreach (['super_admin', 'branch_manager', 'staff'] as $role) {
            $isAllowed = in_array($pageKey, $defaultPermissions[$role], true);

            // Check if permission already exists
            $existing = DB::table('role_page_permissions')
                ->where('role', $role)
                ->where('page_permission_id', $pageId)
                ->first();

            if ($existing === null) {
                DB::table('role_page_permissions')->insert([
                    'role' => $role,
                    'page_permission_id' => $pageId,
                    'is_allowed' => $isAllowed,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
