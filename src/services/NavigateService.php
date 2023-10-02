<?php
/**
 * Navigate plugin for Craft CMS 3.x
 *
 * Navigation plugin for Craft 3
 *
 * @link      https://studioespresso.dev
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\services;

use Craft;
use craft\base\Component;
use craft\events\ConfigEvent;
use craft\helpers\StringHelper;
use studioespresso\navigate\models\NavigationModel;
use studioespresso\navigate\records\NavigationRecord;
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
    public const NAVIGATE_CACHE = "navigate_cache";
    public const NAVIGATE_CACHE_NAV = "navigate_cache_nav";

    public function getAllNavigations()
    {
        return NavigationRecord::find()->all();
    }

    public function getAllNavigationForUser()
    {
        $allNavigations = NavigationRecord::find()->all();
        $currentUser = Craft::$app->getUser()->getIdentity();
        $navs = array_filter($allNavigations, function($nav) use ($currentUser) {
            /**
             * @var NavigationRecord $nav
             */
            if ($nav->enabledSiteGroups === '*' || $nav->enabledSiteGroups === null) {
                return true;
            } else {
                $groups = json_decode($nav->enabledSiteGroups);
                $permissionsForGroup = false;
                foreach ($groups as $group) {
                    $sites = Craft::$app->getSites()->getSitesByGroupId($group);
                    foreach ($sites as $site) {
                        if ($currentUser->can("editSite:{$site->uid}")) {
                            $permissionsForGroup = true;
                        }
                    }
                }
                if ($permissionsForGroup) {
                    return true;
                }
                return false;
            }
        });
        return $navs;
    }


    /**
     * @param $id
     * @return NavigationModel
     */
    public function getNavigationById($id)
    {
        $record = NavigationRecord::findOne([
            'id' => $id,
        ]);
        return new NavigationModel($record->getAttributes());
    }

    public function getNavigationByHandle($handle, $fromCache = true)
    {
        if (!$fromCache) {
            $nav = NavigationRecord::findOne([
                'handle' => $handle,
            ]);
        } else {
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                return NavigationRecord::findOne([
                    'handle' => $handle,
                ]);
            } else {
                $cacheTags = new TagDependency([
                    'tags' => [
                        self::NAVIGATE_CACHE,
                        self::NAVIGATE_CACHE_NAV,
                        self::NAVIGATE_CACHE_NAV . '_' . $handle,
                    ], ]);

                $nav = Craft::$app->getCache()->getOrSet(
                    self::NAVIGATE_CACHE_NAV . '_' . $handle,
                    function() use ($handle) {
                        return NavigationRecord::findOne([
                            'handle' => $handle,
                        ]);
                    },
                    null,
                    $cacheTags
                );
            }
        }
        return $nav;
    }

    public function deleteNavigationById($id)
    {
        $record = NavigationRecord::findOne([
            'id' => $id,
        ]);
        if ($record) {
            Craft::$app->projectConfig->remove("navigate.nav.{$record->uid}");
        }
        return true;
    }

    public function handleRemoveNavigation(ConfigEvent $event)
    {
        $record = NavigationRecord::findOne([
            'uid' => $event->tokenMatches[0],
        ]);
        if (!$record) {
            return false;
        }


        if ($record->delete()) {
            TagDependency::invalidate(Craft::$app->getCache(), [
                self::NAVIGATE_CACHE_NAV . '_' . $record->handle,
            ]);
            return 1;
        };
    }

    public function handleAddNavigation(ConfigEvent $event)
    {
        $record = NavigationRecord::findOne([
            'uid' => $event->tokenMatches[0],
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
        $record->enabledSiteGroups = $event->newValue['enabledSiteGroups'];

        if (!$record->save()) {
            Craft::getLogger()->log($record->getErrors(), LOG_ERR, 'navigate');
        }
    }

    public function saveNavigation(NavigationModel $model)
    {
        $record = false;
        if (isset($model->id)) {
            $record = NavigationRecord::findOne([
                'id' => $model->id,
            ]);
        }

        if (!$record) {
            $record = new NavigationRecord();
            $record->uid = StringHelper::UUID();
        }

        $record->title = $model->title;
        $record->handle = $model->handle;
        $record->levels = $model->levels;
        $record->enabledSiteGroups = $model->enabledSiteGroups;
        $record->adminOnly = $model->adminOnly ? 1 : 0;
        $record->allowedSources = $model->allowedSources;
        if (!$record->validate()) {
            return false;
        }

        if (Craft::$app->getConfig()->general->allowAdminChanges) {
            $path = "navigate.nav.{$record->uid}";
            Craft::$app->projectConfig->set($path, [
                'title' => $record->title,
                'handle' => $record->handle,
                'levels' => $record->levels,
                'adminOnly' => $record->adminOnly,
                'allowSources' => $record->allowedSources,
                'enabledSiteGroups' => $record->enabledSiteGroups,
            ]);
        } else {
            $record->save();
        }

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
        foreach ($navs->all() as $nav) {
            $data[$nav->uid] = [
                'title' => $nav->title,
                'handle' => $nav->handle,
                'levels' => $nav->levels,
                'adminOnly' => $nav->adminOnly,
                'allowSources' => $nav->allowedSources,
                'enabledSiteGroups' => $nav->enabledSiteGroups,
            ];
        }
        return ['nav' => $data];
    }
}
