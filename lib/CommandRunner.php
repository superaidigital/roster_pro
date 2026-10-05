<?php
declare(strict_types=1);

final class CommandRunner {
    public static function run(string $label, array $command, ?string $cwd = null): void {
        $cwd = $cwd ?: dirname(__DIR__);
        echo "\n=== {$label} ===\n";

        $descriptors = [
            0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, $cwd);
        if (!is_resource($process)) {
            throw new RuntimeException("Unable to start step: {$label}");
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $observedExitCode = null;

        while (true) {
            $status = proc_get_status($process);
            foreach ([1 => STDOUT, 2 => STDERR] as $index => $target) {
                $chunk = stream_get_contents($pipes[$index]);
                if ($chunk !== false && $chunk !== '') fwrite($target, $chunk);
            }
            if (!$status['running']) {
                $observedExitCode = isset($status['exitcode']) ? (int)$status['exitcode'] : null;
                break;
            }
            usleep(50_000);
        }

        foreach ([1 => STDOUT, 2 => STDERR] as $index => $target) {
            $chunk = stream_get_contents($pipes[$index]);
            if ($chunk !== false && $chunk !== '') fwrite($target, $chunk);
            fclose($pipes[$index]);
        }

        $closeExitCode = proc_close($process);
        $exitCode = $closeExitCode !== -1 ? $closeExitCode : ($observedExitCode ?? -1);
        if ($exitCode !== 0) {
            throw new RuntimeException("Step failed: {$label} (exit {$exitCode})");
        }
    }
}
