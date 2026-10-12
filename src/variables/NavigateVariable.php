<?php

namespace studioespresso\navigate\variables;

use CraftCms\Cms\Support\Facades\Sites;
use studioespresso\navigate\models\NodeModel;
use studioespresso\navigate\models\Settings;
use studioespresso\navigate\Navigate;

/**
 * `craft.navigate`
 */
class NavigateVariable
{
    /**
     * The enabled nodes of a navigation, with their children.
     *
     * @return array<int, NodeModel>|false
     */
    public function raw(string $navHandle, ?int $siteId = null): array|false
    {
        $site = ($siteId ? Sites::getSiteById($siteId) : null) ?? Sites::getCurrentSite();

        return Navigate::getInstance()->nodes->getNodesForRender($navHandle, $site->id);
    }

    public function getSettings(): Settings
    {
        return Navigate::getInstance()->getSettings();
    }
}
