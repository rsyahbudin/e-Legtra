<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard permissions
            ['PERMISSION_NAME' => 'Lihat Dashboard Ticket Statistics', 'PERMISSION_CODE' => 'dashboard.tickets.view', 'PERMISSION_GROUP' => 'dashboard', 'PERMISSION_DESC' => 'Melihat statistik tiket di dashboard'],
            ['PERMISSION_NAME' => 'Lihat Dashboard Contract Statistics', 'PERMISSION_CODE' => 'dashboard.contracts.view', 'PERMISSION_GROUP' => 'dashboard', 'PERMISSION_DESC' => 'Melihat statistik kontrak di dashboard'],
            ['PERMISSION_NAME' => 'Lihat My Tickets', 'PERMISSION_CODE' => 'dashboard.my-tickets.view', 'PERMISSION_GROUP' => 'dashboard', 'PERMISSION_DESC' => 'Melihat tiket milik pengguna di dashboard'],
            ['PERMISSION_NAME' => 'Lihat Dashboard Aging', 'PERMISSION_CODE' => 'dashboard.aging.view', 'PERMISSION_GROUP' => 'dashboard', 'PERMISSION_DESC' => 'Melihat data aging di dashboard'],

            // Tickets permissions (replacing old contracts.create which is now ticket creation)
            ['PERMISSION_NAME' => 'Lihat Tickets', 'PERMISSION_CODE' => 'tickets.view', 'PERMISSION_GROUP' => 'tickets', 'PERMISSION_DESC' => 'Melihat daftar tickets'],
            ['PERMISSION_NAME' => 'Buat Ticket', 'PERMISSION_CODE' => 'tickets.create', 'PERMISSION_GROUP' => 'tickets', 'PERMISSION_DESC' => 'Membuat ticket baru'],
            ['PERMISSION_NAME' => 'Edit Ticket', 'PERMISSION_CODE' => 'tickets.edit', 'PERMISSION_GROUP' => 'tickets', 'PERMISSION_DESC' => 'Mengedit ticket (legal only)'],
            ['PERMISSION_NAME' => 'Process Ticket', 'PERMISSION_CODE' => 'tickets.process', 'PERMISSION_GROUP' => 'tickets', 'PERMISSION_DESC' => 'Memproses ticket (legal only)'],
            ['PERMISSION_NAME' => 'Reject Ticket', 'PERMISSION_CODE' => 'tickets.reject', 'PERMISSION_GROUP' => 'tickets', 'PERMISSION_DESC' => 'Menolak ticket (legal only)'],
            ['PERMISSION_NAME' => 'Complete Ticket', 'PERMISSION_CODE' => 'tickets.complete', 'PERMISSION_GROUP' => 'tickets', 'PERMISSION_DESC' => 'Menyelesaikan ticket dan membuat contract (legal only)'],

            // Contracts permissions (actual contract management)
            ['PERMISSION_NAME' => 'Lihat Kontrak', 'PERMISSION_CODE' => 'contracts.view', 'PERMISSION_GROUP' => 'contracts', 'PERMISSION_DESC' => 'Melihat daftar kontrak'],
            ['PERMISSION_NAME' => 'Edit Kontrak', 'PERMISSION_CODE' => 'contracts.edit', 'PERMISSION_GROUP' => 'contracts', 'PERMISSION_DESC' => 'Mengedit kontrak'],
            ['PERMISSION_NAME' => 'Terminate Kontrak', 'PERMISSION_CODE' => 'contracts.terminate', 'PERMISSION_GROUP' => 'contracts', 'PERMISSION_DESC' => 'Mengakhiri kontrak'],
            ['PERMISSION_NAME' => 'Hapus Kontrak', 'PERMISSION_CODE' => 'contracts.delete', 'PERMISSION_GROUP' => 'contracts', 'PERMISSION_DESC' => 'Menghapus kontrak'],
            ['PERMISSION_NAME' => 'Kirim Pengingat Kontrak', 'PERMISSION_CODE' => 'contracts.send_reminder', 'PERMISSION_GROUP' => 'contracts', 'PERMISSION_DESC' => 'Mengirim email pengingat kontrak secara manual'],

            // Users permissions
            ['PERMISSION_NAME' => 'Lihat User', 'PERMISSION_CODE' => 'users.view', 'PERMISSION_GROUP' => 'users', 'PERMISSION_DESC' => 'Melihat daftar pengguna'],
            ['PERMISSION_NAME' => 'Manage Users', 'PERMISSION_CODE' => 'users.manage', 'PERMISSION_GROUP' => 'users', 'PERMISSION_DESC' => 'Mengelola pengguna (create, edit, delete)'],
            ['PERMISSION_NAME' => 'Buat User', 'PERMISSION_CODE' => 'users.create', 'PERMISSION_GROUP' => 'users', 'PERMISSION_DESC' => 'Membuat pengguna baru'],
            ['PERMISSION_NAME' => 'Edit User', 'PERMISSION_CODE' => 'users.edit', 'PERMISSION_GROUP' => 'users', 'PERMISSION_DESC' => 'Mengedit pengguna'],
            ['PERMISSION_NAME' => 'Hapus User', 'PERMISSION_CODE' => 'users.delete', 'PERMISSION_GROUP' => 'users', 'PERMISSION_DESC' => 'Menghapus pengguna'],

            // Roles permissions
            ['PERMISSION_NAME' => 'Lihat Role', 'PERMISSION_CODE' => 'roles.view', 'PERMISSION_GROUP' => 'roles', 'PERMISSION_DESC' => 'Melihat daftar role'],
            ['PERMISSION_NAME' => 'Edit Role', 'PERMISSION_CODE' => 'roles.edit', 'PERMISSION_GROUP' => 'roles', 'PERMISSION_DESC' => 'Mengedit role'],
            ['PERMISSION_NAME' => 'Manage Role Permissions', 'PERMISSION_CODE' => 'roles.manage', 'PERMISSION_GROUP' => 'roles', 'PERMISSION_DESC' => 'Mengelola permissions untuk setiap role'],

            // Divisions
            ['PERMISSION_NAME' => 'Lihat Divisi', 'PERMISSION_CODE' => 'divisions.view', 'PERMISSION_GROUP' => 'divisions', 'PERMISSION_DESC' => 'Melihat daftar divisi'],
            ['PERMISSION_NAME' => 'Buat Divisi', 'PERMISSION_CODE' => 'divisions.create', 'PERMISSION_GROUP' => 'divisions', 'PERMISSION_DESC' => 'Membuat divisi baru'],
            ['PERMISSION_NAME' => 'Edit Divisi', 'PERMISSION_CODE' => 'divisions.edit', 'PERMISSION_GROUP' => 'divisions', 'PERMISSION_DESC' => 'Mengedit divisi'],
            ['PERMISSION_NAME' => 'Hapus Divisi', 'PERMISSION_CODE' => 'divisions.delete', 'PERMISSION_GROUP' => 'divisions', 'PERMISSION_DESC' => 'Menghapus divisi'],
            ['PERMISSION_NAME' => 'Manage Divisi', 'PERMISSION_CODE' => 'divisions.manage', 'PERMISSION_GROUP' => 'divisions', 'PERMISSION_DESC' => 'Mengelola divisi (create, edit, delete)'],

            // Departments
            ['PERMISSION_NAME' => 'Lihat Departemen', 'PERMISSION_CODE' => 'departments.view', 'PERMISSION_GROUP' => 'departments', 'PERMISSION_DESC' => 'Melihat daftar departemen'],
            ['PERMISSION_NAME' => 'Buat Departemen', 'PERMISSION_CODE' => 'departments.create', 'PERMISSION_GROUP' => 'departments', 'PERMISSION_DESC' => 'Membuat departemen baru'],
            ['PERMISSION_NAME' => 'Edit Departemen', 'PERMISSION_CODE' => 'departments.edit', 'PERMISSION_GROUP' => 'departments', 'PERMISSION_DESC' => 'Mengedit departemen'],
            ['PERMISSION_NAME' => 'Hapus Departemen', 'PERMISSION_CODE' => 'departments.delete', 'PERMISSION_GROUP' => 'departments', 'PERMISSION_DESC' => 'Menghapus departemen'],
            ['PERMISSION_NAME' => 'Manage Departemen & CC Emails', 'PERMISSION_CODE' => 'departments.manage', 'PERMISSION_GROUP' => 'departments', 'PERMISSION_DESC' => 'Mengelola departemen dan CC email lists'],

            // Settings
            ['PERMISSION_NAME' => 'Lihat Pengaturan', 'PERMISSION_CODE' => 'settings.view', 'PERMISSION_GROUP' => 'settings', 'PERMISSION_DESC' => 'Melihat pengaturan sistem'],
            ['PERMISSION_NAME' => 'Edit Pengaturan', 'PERMISSION_CODE' => 'settings.edit', 'PERMISSION_GROUP' => 'settings', 'PERMISSION_DESC' => 'Mengubah pengaturan sistem'],
            ['PERMISSION_NAME' => 'Edit Template Email', 'PERMISSION_CODE' => 'email_templates.edit', 'PERMISSION_GROUP' => 'settings', 'PERMISSION_DESC' => 'Mengedit template email pengingat'],

            // Reports
            ['PERMISSION_NAME' => 'Lihat Laporan', 'PERMISSION_CODE' => 'reports.view', 'PERMISSION_GROUP' => 'reports', 'PERMISSION_DESC' => 'Melihat laporan dan statistik'],
            ['PERMISSION_NAME' => 'Export Laporan', 'PERMISSION_CODE' => 'reports.export', 'PERMISSION_GROUP' => 'reports', 'PERMISSION_DESC' => 'Export laporan ke Excel/CSV (Tickets, Contracts)'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['PERMISSION_CODE' => $permission['PERMISSION_CODE']],
                $permission
            );
        }
    }
}
