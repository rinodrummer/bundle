<?php

namespace Leuverink\Bundle\Components;

use Illuminate\View\Component;
use Leuverink\Bundle\BundleManager;
use Leuverink\Bundle\Exceptions\BundlingFailedException;
use Leuverink\Bundle\Contracts\BundleManager as BundleManagerContract;

class Import extends Component
{
    public function __construct(
        public string $module,
        public ?string $as = null,
        public bool $inline = false
    ) {
    }

    public function render()
    {
        try {
            return $this->bundle();
        } catch (BundlingFailedException $e) {
            return $this->raiseConsoleErrorOrException($e);
        }
    }

    /** Builds the core JavaScript & packages it up in a bundle */
    protected function bundle()
    {
        $js = $this->core();

        // Render script tag with bundled code
        return view('x-import::script', [
            'bundle' => $this->manager()->bundle($js),
        ]);
    }

    /** Get an instance of the BundleManager */
    protected function manager(): BundleManagerContract
    {
        return BundleManager::new();
    }

    /** Determines wherether to raise a console error or throw a PHP exception when the BundleManager throws an Exception */
    protected function raiseConsoleErrorOrException(BundlingFailedException $e)
    {
        if (app()->hasDebugModeEnabled()) {
            throw $e;
        }

        report($e);

        return <<< HTML
            <!--[BUNDLE: {$this->as} from '{$this->module}']-->
            <script data-module="{$this->module}" data-alias="{$this->as}">throw "BUNDLING ERROR: No module found at path '{$this->module}'"</script>
            <!--[ENDBUNDLE]>-->
        HTML;
    }

    /** Builds Bundle's core JavaScript */
    protected function core(): string
    {
        return <<< JS
            //--------------------------------------------------------------------------
            // Expose x_import_modules map
            //--------------------------------------------------------------------------
            if(!window.x_import_modules) window.x_import_modules = {};

            //--------------------------------------------------------------------------
            // Import the module & push to x_import_modules
            // Invoke IIFE so we can break out of execution when needed
            //--------------------------------------------------------------------------
            (() => {

                // Check if module is already loaded under a different alias
                const previous = document.querySelector(`script[data-module="{$this->module}"`)

                // Was previously loaded & needs to be pushed to import map
                if(previous && '{$this->as}') {
                    // Throw error improve debugging experience
                    if(previous.dataset.alias !== '{$this->as}') {
                        throw `BUNDLING ERROR: '{$this->as}' already imported as '\${previous.dataset.alias}'`
                    }
                }

                // Assign the import to the window.x_import_modules object (or invoke IIFE)
                '{$this->as}'
                    // Assign it under an alias
                    ? window.x_import_modules['{$this->as}'] = import('{$this->module}')
                    // Only import it (for IIFE no alias needed)
                    : import('{$this->module}')
            })();

        JS;
    }
}
