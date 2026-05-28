<?php

namespace DotMike\NmsCustomFields\Tests\Feature;

use App\Models\Device;
use DotMike\NmsCustomFields\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Renders top-level plugin views so a missing @include or x-component
 * reference fails CI
 */
#[Group('smoke')]
final class ViewRenderSmokeTest extends TestCase
{
    public function test_device_page_resolves_all_view_includes(): void
    {
        $device = Device::first() ?? Device::factory()->create();

        $this->assertNoMissingView('nmscustomfields::device.customfields', [
            'device'          => $device,
            'alert_class'     => '',
            'parent_id'       => null,
            'overview_graphs' => [],
        ]);
    }

    public function test_plugin_admin_page_resolves_all_view_includes(): void
    {
        $this->assertNoMissingView('nmscustomfields::customfield.main', [
            'customfields' => collect(),
        ]);
    }

    public function test_device_overview_hook_view_resolves_all_view_includes(): void
    {
        $device = Device::first() ?? Device::factory()->create();

        $this->assertNoMissingView('nmscustomfields::device.overview', [
            'device'       => $device,
            'customFields' => collect(),
        ]);
    }

    private function assertNoMissingView(string $view, array $vars): void
    {
        try {
            view($view, $vars)->render();
        } catch (\Throwable $e) {
            for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
                if ($cur instanceof \InvalidArgumentException
                    && str_starts_with($cur->getMessage(), 'View [')) {
                    $this->fail("Missing view while rendering {$view}: " . $cur->getMessage());
                }
            }
        }

        $this->assertTrue(true);
    }
}
