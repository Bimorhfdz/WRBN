<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wrbn:backup-db {--directory= : Folder tujuan backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump the MySQL database into storage/backups';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->warn('Backup mysqldump hanya untuk koneksi mysql. DB_CONNECTION saat ini: '.config('database.default'));

            return self::FAILURE;
        }

        $directory = $this->option('directory') ?: storage_path('backups');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $target = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'wrbn-'.now()->format('Y-m-d_H-i-s').'.sql';

        $command = [
            'mysqldump',
            '-h', (string) config('database.connections.mysql.host'),
            '-P', (string) config('database.connections.mysql.port'),
            '-u', (string) config('database.connections.mysql.username'),
            '--single-transaction',
            '--routines',
            '--triggers',
            (string) config('database.connections.mysql.database'),
        ];

        $password = (string) config('database.connections.mysql.password');
        if ($password !== '') {
            array_splice($command, 1, 0, ['-p'.$password]);
        }

        try {
            $process = new Process($command, null, null, null, 600);
            $process->run(function (string $type, string $output): void {
                $this->line($output);
            });
        } catch (RuntimeException $exception) {
            $this->error('mysqldump tidak ditemukan di PATH.');
            $this->line('Jalankan manual, contoh:');
            $this->line(sprintf(
                'mysqldump -h %s -P %s -u %s -p %s > %s',
                config('database.connections.mysql.host'),
                config('database.connections.mysql.port'),
                config('database.connections.mysql.username'),
                config('database.connections.mysql.database'),
                $target
            ));
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        if (! $process->isSuccessful()) {
            $this->error('Backup gagal: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        file_put_contents($target, $process->getOutput());
        $this->info('Backup berhasil: '.$target);

        return self::SUCCESS;
    }
}
