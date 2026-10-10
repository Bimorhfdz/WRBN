<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Uploads;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wrbn:delete-user {email : Email akun yang dihapus} {--force : Lewati konfirmasi interaktif}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete one client account together with its projects and uploaded files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if ($email === '') {
            $this->error('Cara pakai: php artisan wrbn:delete-user email@example.com');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->info("Akun dengan email {$email} tidak ditemukan.");

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $answer = $this->ask("Hapus akun {$user->name} <{$user->email}> beserta data konsultasinya? Ketik DELETE untuk lanjut");
            if (trim((string) $answer) !== 'DELETE') {
                $this->info('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        $paths = DB::table('consultation_files')
            ->join('consultations', 'consultations.id', '=', 'consultation_files.consultation_id')
            ->where('consultations.user_id', $user->id)
            ->pluck('storage_path');

        DB::transaction(function () use ($user): void {
            $user->delete();
        });

        foreach ($paths as $path) {
            try {
                Uploads::delete($path);
            } catch (\Throwable $exception) {
                $this->warn("Gagal menghapus file {$path}: {$exception->getMessage()}");
            }
        }

        $this->info("Akun {$user->email} berhasil dihapus.");

        return self::SUCCESS;
    }
}
