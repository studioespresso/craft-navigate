<?php

namespace studioespresso\navigate\console;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use studioespresso\navigate\console\concerns\ResolvesNavigation;
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NodeRecord;
use studioespresso\navigate\services\NodesService;

class AddNodeCommand extends Command
{
    use CraftCommand;
    use ResolvesNavigation;

    protected $signature = 'navigate:nodes:add
        {nav : The navigation handle}
        {name : The item’s label}
        {--type=url : url, entry, asset or heading}
        {--url= : URL for a url item}
        {--element-id= : Element ID for an entry or asset item}
        {--parent= : Parent node ID, to nest this item beneath it}
        {--site= : Site handle or ID, defaults to the primary site}
        {--blank : Open the link in a new tab}
        {--classes= : CSS classes to apply to the item}
        {--json : Output JSON}';

    protected $description = 'Adds an item to a navigation.';

    protected $aliases = ['navigate/nodes/add'];

    public function handle(): int
    {
        $navigation = $this->resolveNavigation();
        $site = $this->resolveSite();
        if (! $navigation || ! $site) {
            return self::INVALID;
        }

        $node = new NodeRecord([
            'navId' => $navigation->id,
            'siteId' => $site->id,
            'name' => $this->argument('name'),
            'blank' => $this->option('blank'),
            'classes' => $this->option('classes'),
            'enabled' => true,
        ]);

        // entry/asset become an "element" node with an elementType
        $type = strtolower((string) $this->option('type'));
        if ($type === 'url') {
            if (! $this->option('url')) {
                return $this->invalid('--url is required for a url item.');
            }
            $node->type = 'url';
            $node->url = $this->option('url');
        } elseif (isset(NodesService::ELEMENT_TYPES[$type])) {
            $elementId = (int) $this->option('element-id');
            if (! $elementId) {
                return $this->invalid("--element-id is required for an $type item.");
            }
            $node->type = 'element';
            $node->elementType = $type;
            $node->elementId = $elementId;
            if (! Navigate::getInstance()->nodes->getElement($node)) {
                return $this->invalid("No $type found with ID $elementId in site “{$site->handle}”.");
            }
        } elseif ($type === 'heading') {
            $node->type = 'heading';
            $node->blank = false;
        } else {
            return $this->invalid("Unknown --type “{$type}”. Use: url, entry, asset or heading.");
        }

        if ($parentId = (int) $this->option('parent')) {
            $parentExists = NodeRecord::query()->whereKey($parentId)->where('navId', $navigation->id)->where('siteId', $site->id)->exists();
            if (! $parentExists) {
                return $this->invalid("Node $parentId isn’t in “{$navigation->handle}” for site “{$site->handle}”.");
            }
            $node->parent = $parentId;
            if ($navigation->levels <= 1) {
                $this->components->warn("“{$navigation->handle}” has 1 level, so nested items won’t render. Increase its levels to nest.");
            }
        }

        Navigate::getInstance()->nodes->save($node);

        if ($this->option('json')) {
            $this->line(json_encode([
                'id' => $node->id,
                'name' => $node->name,
                'type' => $node->type,
                'navId' => $node->navId,
                'siteId' => $node->siteId,
                'parent' => $node->parent,
            ]));
        } else {
            $this->components->info("Added “{$node->name}” to “{$navigation->handle}” (node id: {$node->id}, site: {$site->handle}).");
        }

        return self::SUCCESS;
    }

    private function invalid(string $message): int
    {
        $this->components->error($message);

        return self::INVALID;
    }
}
