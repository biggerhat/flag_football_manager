<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Turns one of the app's print pages into a PDF using headless Chrome, so a
 * download matches exactly what the browser would print.
 *
 * Chrome loads the page from a short-lived second copy of the app, because the
 * normal dev server handles one request at a time and would be busy waiting
 * for this very render.
 */
class PdfRenderer
{
    /**
     * @param  string  $path  A relative, signed path (with query) on this app.
     *
     * @throws RuntimeException
     */
    public function render(string $path): string
    {
        $binary = $this->binary();
        $server = $this->startServer();
        $work = sys_get_temp_dir().'/coach-pdf-'.bin2hex(random_bytes(6));
        mkdir($work);
        $file = "{$work}/out.pdf";

        try {
            $process = new Process([
                $binary,
                '--headless',
                '--disable-gpu',
                '--no-sandbox',
                '--no-pdf-header-footer',
                '--virtual-time-budget=10000',
                "--user-data-dir={$work}/profile",
                "--print-to-pdf={$file}",
                "http://127.0.0.1:{$server['port']}{$path}",
            ], timeout: 60);
            $process->run();

            if (! is_file($file) || filesize($file) === 0) {
                throw new RuntimeException('The browser could not produce a PDF: '.trim($process->getErrorOutput()));
            }

            return (string) file_get_contents($file);
        } finally {
            $server['process']->stop(1);
            (new Process(['rm', '-rf', $work]))->run();
        }
    }

    /**
     * @return array{process: Process, port: int}
     *
     * @throws RuntimeException
     */
    private function startServer(): array
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = $socket ? (int) parse_url((string) stream_socket_get_name($socket, false), PHP_URL_PORT) : 0;

        if ($socket) {
            fclose($socket);
        }

        $process = new Process(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
            public_path(),
            ['PHP_CLI_SERVER_WORKERS' => '2'],
        );
        $process->start();

        for ($try = 0; $try < 50; $try++) {
            if ($connection = @fsockopen('127.0.0.1', $port, timeout: 0.2)) {
                fclose($connection);

                return ['process' => $process, 'port' => $port];
            }

            usleep(100_000);
        }

        $process->stop(1);

        throw new RuntimeException('Could not start the PDF renderer.');
    }

    private function binary(): string
    {
        $configured = config('coach.chrome_path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $home = getenv('HOME') ?: '';
        $candidates = [
            ...glob("{$home}/.cache/ms-playwright/chromium_headless_shell-*/chrome-headless-shell-linux64/chrome-headless-shell") ?: [],
            ...glob("{$home}/.cache/ms-playwright/chromium-*/chrome-linux*/chrome") ?: [],
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        ];

        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('No Chrome or Chromium found. Install one, or set COACH_CHROME_PATH in .env.');
    }
}
