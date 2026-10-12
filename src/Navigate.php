<?php

/**
 * Navigate plugin for Craft CMS
 *
 * Navigation plugin for Craft
 *
 * @link      https://studioespresso.dev
 *
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate;

use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\Element\Events\ElementDeleted;
use CraftCms\Cms\Element\Events\ElementRestored;
use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Plugin\Plugin;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\ProjectConfig\Events\ProjectConfigRebuilt;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Twig\Variables\CraftVariable;
use Illuminate\Support\Facades\Event;
use studioespresso\navigate\fields\NavigateField;
use studioespresso\navigate\models\Settings;
use studioespresso\navigate\services\NavigateService;
use studioespresso\navigate\services\NodesService;
use studioespresso\navigate\variables\NavigateVariable;

use function CraftCms\Cms\t;

/**
 * @method Settings getSettings()
 */
class Navigate extends Plugin
{
    public string $schemaVersion = '2.4.0';

    protected array $fieldTypes = [NavigateField::class];

    protected array $publishables = [
        __DIR__.'/../resources/dist' => 'dist',
    ];

    public NavigateService $navigate { get => $this->navigate ??= new NavigateService; }

    public NodesService $nodes { get => $this->nodes ??= new NodesService; }

    public function boot(): void
    {
        $path = NavigateService::CONFIG_PATH.'.{uid}';
        ProjectConfig::onAdd($path, fn (ConfigEvent $event) => $this->navigate->handleChangedNavigation($event))
            ->onUpdate($path, fn (ConfigEvent $event) => $this->navigate->handleChangedNavigation($event))
            ->onRemove($path, fn (ConfigEvent $event) => $this->navigate->handleDeletedNavigation($event));
        Event::listen(ProjectConfigRebuilt::class, function (ProjectConfigRebuilt $event) {
            $event->config['navigate'] = $this->navigate->rebuildProjectConfig();
        });

        CraftVariable::macro('navigate', fn () => new NavigateVariable);

        Event::listen(ElementSaved::class, function (ElementSaved $event) {
            if (! ElementHelper::isDraftOrRevision($event->element)) {
                $this->nodes->clearCachesForElement($event->element);
            }
        });
        Event::listen(ElementDeleted::class, function (ElementDeleted $event) {
            if (! ElementHelper::isDraftOrRevision($event->element)) {
                $this->nodes->setEnabledForElement($event->element, false);
            }
        });
        Event::listen(ElementRestored::class, fn (ElementRestored $event) => $this->nodes->setEnabledForElement($event->element, true));
    }

    protected static function createSettings(): ?PluginSettings
    {
        return new Settings;
    }

    public function settingsForm(FormContext $context = new FormContext): ?Form
    {
        return Form::make([
            Field::make(t('Sidebar label', category: 'navigate'), Text::make('pluginLabel'))
                ->instructions(t('You can change the plugin label in the CP sidebar here.', category: 'navigate')),
            Field::make(t('Allow non-admin users to add/remove navigations', category: 'navigate'), Lightswitch::make('anyoneCanAdd')),
            Field::make(t('Navigations can be created on read-only environments', category: 'navigate'), Lightswitch::make('allowWhenReadOnly')),
        ]);
    }

    public function getCpNavItem(): NavItem|array|null
    {
        return parent::getCpNavItem()
            ->label($this->getSettings()->pluginLabel ?: 'Navigate')
            ->icon(null)
            ->iconSvg(file_get_contents($this->getBasePath().'/icon-mask.svg'));
    }

    protected function getCacheOptions(): array
    {
        return [
            'navigate_caches_all' => [
                'label' => t('Navigation caches (Navigate)', category: 'navigate'),
                'action' => fn () => $this->navigate->clearAllCaches(),
            ],
        ];
    }
}
