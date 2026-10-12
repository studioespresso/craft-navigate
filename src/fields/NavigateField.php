<?php

namespace studioespresso\navigate\fields;

use CraftCms\Cms\Field\Dropdown;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NavigationRecord;

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

    public function settingsForm(FormContext $context = new FormContext): Form
    {
        return Form::make([]);
    }

    protected function options(): array
    {
        return [
            ['label' => t('Select a navigation', category: 'navigate'), 'value' => '-'],
            ...Navigate::getInstance()->navigate->getAllNavigations()
                ->map(fn (NavigationRecord $nav) => ['label' => $nav->title, 'value' => $nav->handle])
                ->all(),
        ];
    }
}
