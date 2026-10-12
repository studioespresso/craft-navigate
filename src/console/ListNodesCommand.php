<?php

namespace studioespresso\navigate\console;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use studioespresso\navigate\console\concerns\ResolvesNavigation;
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NodeRecord;

class ListNodesCommand extends Command
{
    use CraftCommand;
    use ResolvesNavigation;

    protected $signature = 'navigate:nodes:list
        {nav : The navigation handle}
        {--site= : Site handle or ID, defaults to the primary site}
        {--json : Output JSON instead of a table}';

    protected $description = 'Lists the items of a navigation in a site.';

    protected $aliases = ['navigate/nodes/list'];

    public function handle(): int
    {
        $navigation = $this->resolveNavigation();
        $site = $this->resolveSite();
        if (! $navigation || ! $site) {
            return self::INVALID;
        }

        $rows = Navigate::getInstance()->nodes->getTree($navigation->id, $site->id)
            ->flatten()
            ->map(fn (NodeRecord $node) => [
                'id' => $node->id,
                'name' => $node->name,
                'type' => $node->type,
                'url' => $node->url,
                'elementId' => $node->elementId,
                'parent' => $node->parent,
                'order' => $node->order,
                'enabled' => $node->enabled,
            ])
            ->all();

        if ($this->option('json')) {
            $this->line(json_encode($rows));
        } elseif (! $rows) {
            $this->components->info("No items in “{$navigation->handle}” for site “{$site->handle}”.");
        } else {
            $this->table(['ID', 'Name', 'Type', 'URL', 'Element', 'Parent', 'Order', 'Enabled'], $rows);
        }

        return self::SUCCESS;
    }
}
