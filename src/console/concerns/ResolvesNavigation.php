<?php

namespace studioespresso\navigate\console\concerns;

use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Console\Command;
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NavigationRecord;

/**
 * The `nav` argument and `--site` option shared by the node commands.
 *
 * @mixin Command
 */
trait ResolvesNavigation
{
    protected function resolveNavigation(): ?NavigationRecord
    {
        $handle = $this->argument('nav');
        $navigation = Navigate::getInstance()->navigate->getNavigationByHandle($handle, false);
        if (! $navigation) {
            $this->components->error("No navigation found with handle “{$handle}”.");
        }

        return $navigation;
    }

    /** The `--site` option (handle or ID), or the primary site */
    protected function resolveSite(): ?Site
    {
        $value = $this->option('site');
        if (! $value) {
            return Sites::getPrimarySite();
        }

        $site = is_numeric($value) ? Sites::getSiteById((int) $value) : Sites::getSiteByHandle($value);
        if (! $site) {
            $this->components->error("No site found matching “{$value}”.");
        }

        return $site;
    }
}
