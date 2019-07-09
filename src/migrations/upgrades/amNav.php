<?php

namespace studioespresso\navigate\migrations\upgrades;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;
use studioespresso\navigate\models\NavigationModel;
use studioespresso\navigate\models\NodeModel;
use studioespresso\navigate\Navigate;

class amNav extends Migration
{

    private $newNodes = [];
    public function safeUp()
    {
        if (!Craft::$app->getDb()->tableExists('{{%amnav_navs}}')) {
            return true;
        }
        $amNavs = (new Query())
            ->select(['*'])
            ->from(['{{%amnav_navs}}'])
            ->all();
        foreach ($amNavs as $key => $amNav) {
            echo "\n    > Migrating nav `{$amNav['handle']}` ...\n";
            $nav = Navigate::$plugin->navigate->getNavigationByHandle($amNav['handle']);
            if (!$nav) {
                $nav = new NavigationModel();
            }
            $nav->id = $amNav['id'];
            $nav->title = $amNav['name'];
            $nav->handle = $amNav['handle'];
            $settings = Json::decode($amNav['settings']);
            $nav->levels = !empty($settings['maxLevels']) ? $settings['maxLevels'] : 9;
            $nav->adminOnly = true;
            Navigate::getInstance()->navigate->saveNavigation($nav);
        }

        $navs = Navigate::getInstance()->navigate->getAllNavigations();
        foreach ($navs as $nav) {
            echo "\n    > Migrating nodes for `{$nav['handle']}` ...\n";
            $amNav = (new Query())
                ->select(['*'])
                ->from(['{{%amnav_navs}}'])
                ->where(['handle' => $nav['handle']])
                ->one();
            $amNavNodes = (new Query())
                ->select(['*'])
                ->from(['{{%amnav_nodes}}'])
                ->where(['navId' => $amNav['id']])
                ->orderBy('parentId ASC, order ASC')
                ->all();
            foreach ($amNavNodes as $amNavNode) {
                try {
                    echo "\n    > [{$nav['handle']}] Migrating node '{$amNavNode['name']}' ...\n";
                    $node = new NodeModel();
                    $node->name = $amNavNode['name'];
                    $node->enabled = $amNavNode['enabled'];
                    $node->navId = $nav->id;
                    $node->parent = $amNavNode['parentId'] == 0 ? null : $amNavNode['parentId'];
                    $node->url = $amNavNode['url'];
                    $node->classes = $amNavNode['listClass'];
                    $node->blank = $amNavNode['blank'];
                    $node->order = $amNavNode['order'];
                    $locale = $amNavNode['locale'];
                    $site = Craft::$app->getSites()->getSiteByHandle($locale);
                    if ($site) {
                        $node->siteId = $site->id;
                    } else {
                        $primarySite = Craft::$app->getSites()->getPrimarySite();
                        $node->siteId = $primarySite->id;
                    }

                    if ($amNavNode['elementType'] === 'Entry') {
                        $node->type = 'element';
                        $node->elementType = 'entry';
                        $node->elementId = $amNavNode['elementId'];
                    } else if ($amNavNode['elementType'] === 'Category') {
                        $node->type = 'element';
                        $node->elementType = 'category';
                        $node->elementId = $amNavNode['elementId'];
                    } else if ($amNavNode['elementType'] === 'Asset') {
                        $node->type = 'element';
                        $node->elementType = 'asset';
                        $node->elementId = $amNavNode['elementId'];
                    } else if ($amNavNode['url']) {
                        $node->type = 'url';
                        $node->url = $amNavNode['url'];
                    }
                    $node = Navigate::getInstance()->nodes->save($node);
                    if ($node) {
                        $this->newNodes[$amNavNode['id']] = [
                            'oldParent' => $amNavNode['parentId'],
                            'newNode' => $node->id,
                            'siteId' => $node->siteId,
                        ];
                    } else {
                        echo "\n    > [{$nav['handle']}] ERROR: Unable to save node `{$amNavNode['name']}' ...\n";
                    }

                } catch (\Throwable $e) {
                    Craft::error("Error migratining $node->name");
                }
            }

            foreach ($this->newNodes as $nodeInfo) {
                $newParent = $this->newNodes[$nodeInfo['oldParent']] ?? null;
                if ($newParent) {
                    $node = Navigate::$plugin->nodes->getNodeById($nodeInfo['newNode'], $nodeInfo['siteId']);
                    if ($node) {
                        $node->parent = $newParent['newNode'];
                        if (Navigate::getInstance()->nodes->save($node)) {
                            echo "    > Migrated node `{$node['name']}` ...\n";
                        } else {
                            echo "    > ERROR: Unable to re-save node `{$node['name']}` ...\n";
                        }
                    }
                }
            }
        }
    }

    public function safeDown()
    {
        return false;
    }
}