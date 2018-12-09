<?php
/**
 * Navigate plugin for Craft CMS 3.x
 *
 * Navigation plugin for Craft 3
 *
 * @link      https://studioespresso.co
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate;

use studioespresso\navigate\services\NavigateService as NavigateServiceService;
use studioespresso\navigate\services\NavigateService;
use studioespresso\navigate\services\NodesService;
use studioespresso\navigate\variables\NavigateVariable;
use studioespresso\navigate\models\Settings;
use studioespresso\navigate\fields\NavigateField as NavigateFieldField;
use studioespresso\navigate\utilities\NavigateUtility as NavigateUtilityUtility;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\console\Application as ConsoleApplication;
use craft\log\FileTarget;
use craft\web\UrlManager;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;

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
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Navigate::$plugin
     *
     * @var Navigate
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public $schemaVersion = '0.0.1';

    // Public Methods
    // =========================================================================

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * Navigate::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->name = Craft::t('navigate', 'Navigate');

        $fileTarget = new FileTarget([
            'logFile' => Craft::$app->path->getLogPath() . '/navigate.log', // <--- path of the log file
            'categories' => ['navigate'] // <--- categories in the file
        ]);
        // include the new target file target to the dispatcher
        Craft::getLogger()->dispatcher->targets[] = $fileTarget;

        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {

                $event->rules['navigate'] = 'navigate/default';
                $event->rules['navigate/add'] = 'navigate/default/settings';
                $event->rules['navigate/save'] = 'navigate/default/save';
                $event->rules['navigate/delete'] = 'navigate/default/delete';
                $event->rules['navigate/edit/<navId:\d+>'] = 'navigate/default/edit';
                $event->rules['navigate/edit/<navId:\d+>/<siteHandle:{handle}>'] = 'navigate/default/edit';
                $event->rules['navigate/settings/<navId:\d+>'] = 'navigate/default/settings';
                $event->rules['navigate/nodes/add'] = 'navigate/nodes/add';
                $event->rules['navigate/nodes/move'] = 'navigate/nodes/move';
                $event->rules['navigate/nodes/editor'] = 'navigate/nodes/editor';
                $event->rules['navigate/nodes/update'] = 'navigate/nodes/update';
                $event->rules['navigate/nodes/delete'] = 'navigate/nodes/delete';
            }
        );

        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('navigate', NavigateVariable::class);
            }
        );
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

    /**
     * @inheritdoc
     */
    public function getCpNavItem()
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

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @return string The rendered settings HTML
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'navigate/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
