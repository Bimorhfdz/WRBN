<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wrbn:create-admin {--role= : Role akun bootstrap (superadmin, admin, support)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update the WRBN admin account from ADMIN_EMAIL / ADMIN_PASSWORD';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $role = (string) ($this->option('role') ?: config('wrbn.admin_role', 'superadmin'));
        if (! in_array($role, ['superadmin', 'admin', 'support'], true)) {
            $role = 'superadmin';
        }

        $email = (string) config('wrbn.admin_email');
        $password = (string) config('wrbn.admin_password');
        $reset = (bool) config('wrbn.admin_reset_password');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->error('Set ADMIN_EMAIL dan ADMIN_PASSWORD di .env terlebih dahulu.');

            return self::FAILURE;
        }

        if (mb_strlen($password) < 10) {
            $this->error('ADMIN_PASSWORD minimal 10 karakter.');

            return self::FAILURE;
        }

        $email = strtolower(trim($email));
        $existing = Admin::query()->where('email', $email)->first();

        if ($existing && ! $reset) {
            $this->info("Admin sudah ada: {$email}. Tidak ada perubahan.");
            $this->line('Untuk reset password, set ADMIN_RESET_PASSWORD=true lalu jalankan php artisan wrbn:create-admin.');

            return self::SUCCESS;
        }

        $hash = Hash::make($password, ['rounds' => 12]);

        if ($existing) {
            $existing->update(['password_hash' => $hash] + ($this->option('role') ? ['role' => $role] : []));
            $this->info("Password admin diperbarui: {$email}");
        } else {
            Admin::query()->create([
                'email' => $email,
                'password_hash' => $hash,
                'name' => 'WRBN Admin',
                'role' => $role,
            ]);
            $this->info("Admin berhasil dibuat: {$email}");
        }

        return self::SUCCESS;
    }
}
