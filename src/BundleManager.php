<?php

namespace Leuverink\Bundle;

use Throwable;
use SplFileInfo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Leuverink\Bundle\Traits\Constructable;
use Illuminate\Config\Repository as ConfigRepository;
use Leuverink\Bundle\Contracts\Bundler as BundlerContract;
use Illuminate\Contracts\Config\Repository as RepositoryContract;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Leuverink\Bundle\Contracts\BundleManager as BundleManagerContract;

class BundleManager implements BundleManagerContract
{
    use Constructable;

    public const BUILD_DIR = 'bundle';
    public const TEMP_DIR = 'bundle-tmp';

    protected readonly BundlerContract $bundler;

    public function __construct(BundlerContract $bundler)
    {
        $this->bundler = $bundler;
    }

    public function bundle(string $script): SplFileInfo
    {
        $file = "{$this->hash($script)}.min.js";

        // Return cached file if available
        if (config('bundle.caching_enabled') && $cached = $this->fromDisk($file)) {
            return $cached;
        }

        // Create temporary input file
        $this->tempDisk()->put($file, $script);

        // Attempt bundling & cleanup
        try {
            $processed = $this->bundler->build(
                sourcemaps: $this->config()->get('sourcemaps_enabled'),
                inputPath: $this->tempDisk()->path(''),
                outputPath: $this->buildDisk()->path(''),
                fileName: $file,
            );
        } catch (Throwable $e) {
            $this->cleanup($file);
            throw $e; // TODO: Consider raising a browser console error or whoops in a dialog instead
        } finally {
            $this->cleanup($file);
        }

        return $processed;
    }

    //--------------------------------------------------------------------------
    // Helper methods
    //--------------------------------------------------------------------------
    public function config(): RepositoryContract
    {
        return new ConfigRepository(config('bundle'));
    }

    public function tempDisk(): FilesystemContract
    {
        return Storage::build([
            'driver' => 'local',
            'root' => storage_path('app/' . static::TEMP_DIR),
        ]);
    }

    public function buildDisk(): FilesystemContract
    {
        return Storage::build([
            'driver' => 'local',
            'root' => storage_path('app/' . static::BUILD_DIR),
        ]);
    }

    private function fromDisk(string $fileName): ?SplFileInfo
    {
        if (! $this->buildDisk()->exists($fileName)) {
            return null;
        }

        return new SplFileInfo(
            $this->buildDisk()->path($fileName)
        );
    }

    public function bundleContents($fileName): Response
    {
        $file = $this->fromDisk($fileName);

        abort_unless((bool) $file, 404, 'Bundle not found');

        $contents = file_get_contents($file);

        return response($contents)
            // ->header('Cache-Control', 'max-age=31536000, immutable')
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Last-Modified', gmdate('Y-m-d H:i:s', $file->getMTime()));
    }

    public function hash($input, $length = 12): string
    {
        $hash = hash('sha256', $input);

        return substr($hash, 0, $length);
    }

    private function cleanup($file)
    {
        $this->tempDisk()->delete($file);

        if (! $this->tempDisk()->files()) {
            rmdir($this->tempDisk()->path(''));
        }
    }
}
