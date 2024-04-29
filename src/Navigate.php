<?php
/**
 * Navigate plugin for Craft CMS 3.x
 *
 * Navigation plugin for Craft 3
 *
 * @link      https://studioespresso.dev
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\MatrixBlock;
use craft\events\ElementEvent;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\ElementHelper;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\ProjectConfig;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use studioespresso\navigate\base\PluginTrait;
use studioespresso\navigate\extensions\NavigateExtension;
use studioespresso\navigate\fields\NavigateField;
use studioespresso\navigate\models\Settings;
use studioespresso\navigate\records\NodeRecord;
use studioespresso\navigate\services\NavigateService;
use studioespresso\navigate\services\NodesService;
use studioespresso\navigate\variables\NavigateVariable;
use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\SuperTable;
use yii\base\Event;

/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    Studio Espresso
 * @package   Navigate
 * @since     0.0.1
 *
 * @property  Settings $settings
 * @property NavigateService $navigate
 * @property NodesService $nodes
 * @method    Settings getSettings()
 */
class Navigate extends Plugin
{
    // Public Properties
    // =========================================================================
    public string $schemaVersion = '2.4.0';

    // Traits
    // =========================================================================
    use PluginTrait;

    // Public Methods
    // =========================================================================
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->name = Craft::t('navigate', 'Navigate');

        $this->setComponents([
            "navigate" => NavigateService::class,
            "nodes" => NodesService::class,
        ]);

        if (Craft::$app->request->getIsCpRequest()) {
            // Add in our Twig extension
            $navigateExtension = new NavigateExtension();
            Craft::$app->view->registerTwigExtension($navigateExtension);
        }

        $this->_projectConfig();
        $this->_registerRoutes();
        $this->_registerVariables();
        $this->_registerCacheOptions();
        $this->_registerField();
        $this->_elementListeners();
    }

    private function _projectConfig(): void
    {
        Craft::$app->projectConfig
            ->onAdd('navigate.nav.{uid}', [$this->navigate, 'handleAddNavigation'])
            ->onUpdate('navigate.nav.{uid}', [$this->navigate, 'handleAddNavigation'])
            ->onRemove('navigate.nav.{uid}', [$this->navigate, 'handleRemoveNavigation']);

        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function(RebuildConfigEvent $event) {
            $event->config['navigate'] = Navigate::getInstance()->navigate->rebuildProjectConfig();
        });
    }

    private function _registerRoutes(): void
    {
        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['navigate'] = 'navigate/default';
                $event->rules['navigate/add'] = 'navigate/default/settings';
                $event->rules['navigate/save'] = 'navigate/default/save';
                $event->rules['navigate/delete'] = 'navigate/default/delete';
                $event->rules['navigate/<action>/<navId:\d+>'] = 'navigate/default/<action>';
                $event->rules['navigate/settings/<navId:\d+>'] = 'navigate/default/settings';
                $event->rules['navigate/nodes/<action>'] = 'navigate/nodes/<action>';
            }
        );
    }

    public function getCpNavItem(): array
    {
        $ret = [
            'label' => $this->getSettings()->pluginLabel ? $this->getSettings()->pluginLabel : 'Navigate',
            'url' => $this->id,
        ];
        if (($iconPath = $this->cpNavIconPath()) !== null) {
            $ret['icon'] = $iconPath;
        }
        return $ret;
    }

    public static function info($message)
    {
        Craft::getLogger()->log($message, \yii\log\Logger::LEVEL_INFO, 'navigate');
    }

    public static function warning($message)
    {
        Craft::getLogger()->log($message, \yii\log\Logger::LEVEL_WARNING, 'navigate');
    }

    public static function error($message)
    {
        Craft::getLogger()->log($message, \yii\log\Logger::LEVEL_ERROR, 'navigate');
    }

    // Protected Methods
    // =========================================================================
    protected function createSettingsModel(): Model
    {
        return new Settings();
    }

    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'navigate/settings',
            [
                'settings' => $this->getSettings(),
            ]
        );
    }

    // Private Methods
    // =========================================================================
    private function _registerVariables(): void
    {
        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('navigate', NavigateVariable::class);
            }
        );
    }

    private function _registerCacheOptions(): void
    {
        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function(RegisterCacheOptionsEvent $event) {
                // Register our Control Panel routes
                $event->options = array_merge(
                    $event->options, [
                    [
                        "key" => 'navigate_caches_all',
                        "label" => "Navigation caches (Navigate)",
                        "action" => [Navigate::getInstance()->navigate, 'clearAllCaches'],
                    ],
                ]);
            }
        );
    }

    private function _registerField(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = NavigateField::class;
            }
        );
    }

    private function _elementListeners(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(ElementEvent $event) {
                if (version_compare(Craft::$app->getVersion(), '3.2.0', '>=')) {
                    if (
                        /** @phpstan-ignore-next-line */
                        get_class($event->element) != SuperTable::class and
                        get_class($event->element) != MatrixBlock::class
                    ) {
                        if (ElementHelper::isDraftOrRevision($event->element)) {
                            return;
                        };
                    }
                };
                if ($event->element->id) {
                    $query = NodeRecord::find();
                    $query->where(['elementId' => $event->element->id]);
                    if ($query->all()) {
                        Navigate::getInstance()->navigate->clearAllCaches();
                    }
                }
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_DELETE_ELEMENT,
            function(ElementEvent $event) {
                if (version_compare(Craft::$app->getVersion(), '3.2.0', '>=')) {
                    if (
                        /** @phpstan-ignore-next-line */
                        get_class($event->element) != SuperTableBlockElement::class and
                        get_class($event->element) != MatrixBlock::class
                    ) {
                        if (ElementHelper::isDraftOrRevision($event->element)) {
                            return;
                        };
                    }
                };
                if ($event->element->id) {
                    $query = NodeRecord::find();
                    $query->where(['elementId' => $event->element->id]);
                    $record = $query->one();
                    if ($record) {
                        $record->setAttribute('enabled', false);
                        $record->save();
                        Navigate::getInstance()->navigate->clearAllCaches();
                    }
                }
            }
        );


        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_RESTORE_ELEMENT,
            function(ElementEvent $event) {
                if ($event->element->id) {
                    $query = NodeRecord::find();
                    $query->where(['elementId' => $event->element->id]);
                    $record = $query->one();
                    if ($record) {
                        $record->setAttribute('enabled', true);
                        $record->save();
                        Navigate::getInstance()->navigate->clearAllCaches();
                    }
                }
            });
    }
}
