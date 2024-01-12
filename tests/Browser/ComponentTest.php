<?php

namespace Leuverink\Bundle\Tests\Browser;

use Leuverink\Bundle\Tests\DuskTestCase;

// Pest & Workbench Dusk don't play nicely together
// We need to fall back to PHPUnit syntax.

// Old syntax as a reference:

// it('renders the same import only once')
//     ->blade(<<< HTML
//         <x-bundle import="~/alert" as="alert" />
//         <x-bundle import="~/alert" as="alert" />
//     HTML)
//     ->assertScript(<<< JS
//         document.querySelectorAll('script[data-bundle="alert"').length
//     JS, 1);

class ComponentTest extends DuskTestCase
{
    /** @test */
    public function it_renders_the_same_import_only_once()
    {
        $this->blade(<<< 'HTML'
                <x-bundle import="~/alert" as="alert" />
                <x-bundle import="~/alert" as="alert" />
            HTML)
            ->assertScript(<<< 'JS'
                document.querySelectorAll('script[data-bundle="alert"').length
            JS, 1);
    }

    /** @test */
    public function it_renders_the_same_import_only_once_when_one_was_inlined()
    {
        $this->blade(<<< 'HTML'
                <x-bundle import="~/alert" as="alert" />
                <x-bundle import="~/alert" as="alert" inline />
            HTML)
            ->assertScript(<<< 'JS'
                document.querySelectorAll('script[data-bundle="alert"').length
            JS, 1);
    }

    /** @test */
    public function it_renders_the_same_import_under_different_aliases()
    {
        $this->blade(<<< 'HTML'
                <x-bundle import="~/alert" as="foo" />
                <x-bundle import="~/alert" as="bar" />
            HTML)
            ->assertScript(<<< 'JS'
                document.querySelectorAll('script[data-bundle="foo"').length
            JS, 1)
            ->assertScript(<<< 'JS'
                document.querySelectorAll('script[data-bundle="bar"').length
            JS, 1);
    }

    public function it_renders_script_inline_when_inline_prop_was_passed()
    {
    }

    public function it_doesnt_render_script_inline_by_default()
    {
    }
}
