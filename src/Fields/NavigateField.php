<?php

namespace studioespresso\navigate\Fields;

use CraftCms\Cms\Field\Dropdown;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use studioespresso\navigate\Models\NavigationRecord;
use studioespresso\navigate\services\NavigateService;

use function CraftCms\Cms\t;

/**
 * A dropdown of the navigations; the value is the navigation's handle.
 */
class NavigateField extends Dropdown
{
    public function __construct($config = [])
    {
        // The options always come from the navigations, never from stored settings
        unset($config['options']);
        parent::__construct($config);
        $this->options = $this->options();
    }

    public static function displayName(): string
    {
        return t('Navigation', category: 'navigate');
    }

    public function settingsAttributes(): array
    {
        return array_values(array_diff(parent::settingsAttributes(), ['options', 'customOptions']));
    }

    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        return Ui::make([]);
    }

    protected function options(): array
    {
        return [
            ['label' => t('Select a navigation', category: 'navigate'), 'value' => '-'],
            ...app(NavigateService::class)->getAllNavigations()
                ->map(fn (NavigationRecord $nav) => ['label' => $nav->title, 'value' => $nav->handle])
                ->all(),
        ];
    }
}
