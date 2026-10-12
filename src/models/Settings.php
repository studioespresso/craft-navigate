<?php

namespace studioespresso\navigate\models;

use CraftCms\Cms\Plugin\PluginSettings;

class Settings extends PluginSettings
{
    public string $pluginLabel = 'Navigate';

    /** Whether non-admin users can add and delete navigations */
    public bool $anyoneCanAdd = false;

    public bool $disableCaching = false;

    /** Whether navigations can be created while admin changes are disallowed */
    public bool $allowWhenReadOnly = false;

    /** @var array<string, string> Class to apply => label, turns the classes field into a dropdown */
    public array $nodeClasses = [];

    public function getRules(): array
    {
        return [
            'pluginLabel' => ['nullable', 'string'],
            'anyoneCanAdd' => ['boolean'],
            'allowWhenReadOnly' => ['boolean'],
            'disableCaching' => ['boolean'],
            'nodeClasses' => ['array'],
        ];
    }
}
