<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wrbn:list-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List every registered client account';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'whatsapp', 'created_at']);

        if ($users->isEmpty()) {
            $this->info('Belum ada akun yang terdaftar.');

            return self::SUCCESS;
        }

        $this->table(
            ['No', 'ID', 'Nama', 'Email', 'WhatsApp', 'Terdaftar'],
            $users->values()->map(fn (User $user, int $index) => [
                $index + 1,
                $user->id,
                $user->name,
                $user->email,
                $user->whatsapp ?? '-',
                $user->created_at?->toDateTimeString(),
            ])->all()
        );

        return self::SUCCESS;
    }
}
