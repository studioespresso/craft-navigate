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
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Mcp\CapabilityRegistry;
use CraftCms\Cms\Plugin\Plugin;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\ProjectConfig\Events\ProjectConfigRebuilt;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Twig\Variables\CraftVariable;
use Illuminate\Support\Facades\Event;
use studioespresso\navigate\Console\Commands\AddNodeCommand;
use studioespresso\navigate\Console\Commands\CreateNavigationCommand;
use studioespresso\navigate\Console\Commands\ListNavigationsCommand;
use studioespresso\navigate\Console\Commands\ListNodesCommand;
use studioespresso\navigate\Fields\NavigateField;
use studioespresso\navigate\Mcp\NavigateCapabilities;
use studioespresso\navigate\services\NavigateService;
use studioespresso\navigate\services\NodesService;
use studioespresso\navigate\Twig\NavigateVariable;

use function CraftCms\Cms\t;

/**
 * @method Settings getSettings()
 */
class Navigate extends Plugin
{
    public string $schemaVersion = '2.4.0';

    protected array $fieldTypes = [NavigateField::class];

    protected array $commands = [
        ListNavigationsCommand::class,
        CreateNavigationCommand::class,
        ListNodesCommand::class,
        AddNodeCommand::class,
    ];

    protected array $publishables = [
        __DIR__.'/../resources/dist' => 'dist',
    ];

    public NavigateService $navigate { get => app(NavigateService::class); }

    public NodesService $nodes { get => app(NodesService::class); }

    public function boot(): void
    {
        $this->app->singleton(Settings::class, fn () => $this->getSettings());

        $path = NavigateService::CONFIG_PATH.'.{uid}';
        ProjectConfig::onAdd($path, fn (ConfigEvent $event) => $this->navigate->handleChangedNavigation($event))
            ->onUpdate($path, fn (ConfigEvent $event) => $this->navigate->handleChangedNavigation($event))
            ->onRemove($path, fn (ConfigEvent $event) => $this->navigate->handleDeletedNavigation($event));
        Event::listen(ProjectConfigRebuilt::class, function (ProjectConfigRebuilt $event) {
            $event->config['navigate'] = $this->navigate->rebuildProjectConfig();
        });

        CraftVariable::macro('navigate', fn () => new NavigateVariable);
        app(CapabilityRegistry::class)->register(NavigateCapabilities::class);

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

    public function settingsUi(UiContext $context = new UiContext): ?Ui
    {
        return Ui::make([
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
            ->iconSvg(file_get_contents($this->getResourcesPath().'/icon-mask.svg'));
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
