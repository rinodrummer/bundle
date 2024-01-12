<?php

namespace Leuverink\Bundle\Bundlers;

use SplFileInfo;
use Illuminate\Support\Facades\Process;
use Leuverink\Bundle\Contracts\Bundler;
use Leuverink\Bundle\Traits\Constructable;
use Leuverink\Bundle\Exceptions\BundlingFailedException;

class Bun implements Bundler
{
    use Constructable;

    public function build(string $inputPath, string $outputPath, string $fileName, bool $sourcemaps = false): SplFileInfo
    {
        $path = base_path('node_modules/.bin/');
        $options = [
            // '--tsconfig-override' => base_path('jsconfig.json'), // Disable enforcing this. custom config is optional.
            '--chunk-naming' => 'chunks/[name]-[hash].[ext]', // Not in use without --splitting
            '--asset-naming' => 'assets/[name]-[hash].[ext]', // Not in use without --splitting
            '--entrypoints' => $inputPath . $fileName,
            '--public-path' => $outputPath,
            '--outdir' => $outputPath,
            '--target' => 'browser',
            '--root' => $inputPath,
            // '--splitting', // Breaks relative paths to imports from resources/js (TODO: Experiment more after writing tests)
            '--format' => 'esm',
            '--minify', // Only in production?

            $sourcemaps
                ? '--sourcemap=external'
                : '--sourcemap=none',
        ];

        Process::run("{$path}bun build {$this->args($options)}")
            ->throw(function ($res) use ($inputPath, $fileName): void {
                $failed = file_get_contents($inputPath . $fileName);
                throw new BundlingFailedException($res, $failed);
            });

        return new SplFileInfo($outputPath . $fileName);
    }

    //--------------------------------------------------------------------------
    // Helper methods
    //--------------------------------------------------------------------------
    private function args(array $options): string
    {
        return collect($options)->reduce(function ($carry, $option, $key) {
            return str($carry)
                ->append(is_int($key) ? '' : $key)->append(' ')
                ->append($option)->append(' ')
                ->replace('  ', ' ')
                ->toString();
        });
    }
}
