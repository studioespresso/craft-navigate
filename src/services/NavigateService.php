<?php
/**
 * Navigate plugin for Craft CMS 3.x
 *
 * Navigation plugin for Craft 3
 *
 * @link      https://studioespresso.co
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\services;

use Craft;
use craft\base\Component;
use craft\events\ConfigEvent;
use craft\helpers\StringHelper;
use studioespresso\navigate\models\NavigationModel;
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NavigationRecord;
use yii\bootstrap\Nav;
use yii\caching\TagDependency;

/**
 * NavigateService Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Studio Espresso
 * @package   Navigate
 * @since     0.0.1
 */
class NavigateService extends Component
{

    const NAVIGATE_CACHE = "navigate_cache";
    const NAVIGATE_CACHE_NAV = "navigate_cache_nav";

    public function getAllNavigations()
    {
        return NavigationRecord::find()->all();
    }

    public function getNavigationById($id)
    {
        $record = NavigationRecord::findOne([
            'id' => $id
        ]);
        return new NavigationModel($record->getAttributes());
    }

    public function getNavigationByHandle($handle, $fromCache = true)
    {
        if (!$fromCache) {
            $nav = NavigationRecord::findOne([
                'handle' => $handle
            ]);
        } else {
            $cacheTags = new TagDependency([
                'tags' => [
                    self::NAVIGATE_CACHE,
                    self::NAVIGATE_CACHE_NAV,
                    self::NAVIGATE_CACHE_NAV . '_' . $handle
                ]
            ]);

            $nav = Craft::$app->getCache()->getOrSet(
                self::NAVIGATE_CACHE_NAV . '_' . $handle,
                function () use ($handle) {
                    return NavigationRecord::findOne([
                        'handle' => $handle
                    ]);
                },
                null,
                $cacheTags
            );

        }
        return $nav;
    }

    public function deleteNavigationById($id)
    {
        $record = NavigationRecord::findOne([
            'id' => $id
        ]);
        if ($record) {
            Craft::$app->projectConfig->remove("navigate.nav.{$record->uid}");
        }
        return true;
    }

    public function handleRemoveNavigation(ConfigEvent $event)
    {
        $record = NavigationRecord::findOne([
            'uid' => $event->tokenMatches[0]
        ]);
        if (!$record) {
            return false;
        }


        if ($record->delete()) {
            TagDependency::invalidate(Craft::$app->getCache(), [
                self::NAVIGATE_CACHE_NAV . '_' . $record->handle
            ]);
            return 1;
        };
    }

    public function handleAddNavigation(ConfigEvent $event)
    {
        $record = NavigationRecord::findOne([
            'uid' => $event->tokenMatches[0]
        ]);
        if (!$record) {
            $record = new NavigationRecord();
        }
        $record->uid = $event->tokenMatches[0];
        $record->title = $event->newValue['title'];
        $record->handle = $event->newValue['handle'];
        $record->levels = $event->newValue['levels'];
        $record->adminOnly = $event->newValue['adminOnly'];
        $record->allowedSources = $event->newValue['allowSources'];

        if (!$record->save()) {
            Craft::getLogger()->log($record->getErrors(), LOG_ERR, 'navigate');
        }
    }

    public function saveNavigation(NavigationModel $model)
    {
        $record = false;
        if (isset($model->id)) {
            $record = NavigationRecord::findOne([
                'id' => $model->id
            ]);
        }

        if (!$record) {
            $record = new NavigationRecord();
            $record->uid = StringHelper::UUID();
        }

        $record->title = $model->title;
        $record->handle = $model->handle;
        $record->levels = $model->levels;
        $record->adminOnly = $model->adminOnly ? 1 : 0;
        $record->allowedSources = $model->allowedSources;

        if (!$record->validate()) {
            return false;
        }

        $path = "navigate.nav.{$record->uid}";
        Craft::$app->projectConfig->set($path, [
            'title' => $record->title,
            'handle' => $record->handle,
            'levels' => $record->levels,
            'adminOnly' => $record->adminOnly,
            'allowSources' => $record->allowedSources
        ]);

        return true;
    }

    public function clearAllCaches($tags = [self::NAVIGATE_CACHE])
    {
        TagDependency::invalidate(
            Craft::$app->getCache(),
            $tags
        );
    }

    public function rebuildProjectConfig()
    {
        $navs = NavigationRecord::find();
        $data = [];
        /** @var NavigationRecord $nav */
        foreach($navs->all() as $nav) {
            $data[$nav->uid] = [
                'title' => $nav->title,
                'handle' => $nav->handle,
                'levels' => $nav->levels,
                'adminOnly' => $nav->adminOnly,
                'allowSources' => $nav->allowedSources
            ];
        }
        return ['nav' => $data];
    }
}
