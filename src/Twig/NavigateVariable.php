<?php

namespace studioespresso\navigate\Twig;

use CraftCms\Cms\Support\Facades\Sites;
use studioespresso\navigate\Data\NodeModel;
use studioespresso\navigate\services\NodesService;
use studioespresso\navigate\Settings;

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

        return app(NodesService::class)->getNodesForRender($navHandle, $site->id);
    }

    public function getSettings(): Settings
    {
        return app(Settings::class);
    }
}
