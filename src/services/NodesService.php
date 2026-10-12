<?php

namespace studioespresso\navigate\services;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Env;
use CraftCms\DependencyAwareCache\Dependency\TagDependency;
use CraftCms\DependencyAwareCache\Facades\DependencyCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use studioespresso\navigate\models\NodeModel;
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NavigationRecord;
use studioespresso\navigate\records\NodeRecord;

use function CraftCms\Cms\renderObjectTemplate;

class NodesService
{
    public const NAVIGATE_CACHE_NODES = 'navigate_cache_nodes';

    /** Node types: handle => label */
    public const TYPES = [
        'entry' => 'Entry',
        'url' => 'Url',
        'asset' => 'Asset',
        'heading' => 'Heading',
    ];

    /** Element types per element node type */
    public const ELEMENT_TYPES = [
        'entry' => Entry::class,
        'asset' => Asset::class,
    ];

    /**
     * All nodes of a navigation in a site, ordered, keyed by parent ID (0 for the top level).
     *
     * @return Collection<int, Collection<int, NodeRecord>>
     */
    public function getTree(int $navId, int $siteId, bool $enabledOnly = false): Collection
    {
        return NodeRecord::query()
            ->where('navId', $navId)
            ->where('siteId', $siteId)
            ->when($enabledOnly, fn ($query) => $query->where('enabled', true))
            ->orderBy('order')
            ->get()
            ->groupBy(fn (NodeRecord $node) => $node->parent ?? 0);
    }

    /**
     * The nodes `craft.navigate.raw()` returns, cached until the navigation changes.
     *
     * @return array<int, NodeModel>|false
     */
    public function getNodesForRender(string $navHandle, int $siteId): array|false
    {
        $navigation = Navigate::getInstance()->navigate->getNavigationByHandle($navHandle);
        if (! $navigation) {
            return false;
        }

        $build = fn () => $this->buildRenderTree($navigation, $siteId);

        if (Cms::config()->devMode || Navigate::getInstance()->getSettings()->disableCaching || request()->isPreview() || request()->getToken()) {
            return $build();
        }

        $key = $this->cacheKey($navigation->handle, $siteId);

        // The cache only unserializes plain data (cache.serializable_classes), so it holds the nodes as arrays
        $nodes = DependencyCache::rememberForever(
            $key,
            fn () => array_map(fn (NodeModel $node) => $node->toArray(), $build()),
            new TagDependency([NavigateService::NAVIGATE_CACHE, self::NAVIGATE_CACHE_NODES, $key]),
        );

        return array_map(NodeModel::fromArray(...), $nodes);
    }

    /**
     * @return list<array{handle: string, title: string}> The node types a navigation allows
     */
    public function getNodeTypes(NavigationRecord $navigation): array
    {
        $allowed = $navigation->allowedSourceList() ?? array_keys(self::TYPES);

        return collect(self::TYPES)
            ->only($allowed)
            ->map(fn (string $title, string $handle) => ['handle' => $handle, 'title' => $title])
            ->values()
            ->all();
    }

    public function save(NodeRecord $node): void
    {
        if (! $node->exists) {
            $node->order = (int) NodeRecord::query()
                ->where('navId', $node->navId)
                ->where('siteId', $node->siteId)
                ->where('parent', $node->parent)
                ->max('order') + 1;
        }

        $node->save();
        $this->clearCacheForNode($node);
    }

    public function delete(NodeRecord $node): void
    {
        // Descendants are removed through the parent foreign key
        $node->delete();
        $this->clearCacheForNode($node);
    }

    /**
     * Saves the order and nesting of all nodes of a navigation in a site.
     *
     * @param  list<array{id: int, children?: array}>  $tree  Nested node IDs, in order
     */
    public function saveTree(NavigationRecord $navigation, int $siteId, array $tree): void
    {
        $nodes = NodeRecord::query()->where('navId', $navigation->id)->where('siteId', $siteId)->get()->keyBy('id');
        $positions = [];
        $this->flattenTree($tree, null, 1, $navigation->levels ?: 1, $positions);

        if (array_diff(array_keys($positions), $nodes->keys()->all())) {
            throw new InvalidArgumentException('The tree contains nodes of another navigation or site.');
        }

        DB::transaction(function () use ($positions, $nodes) {
            foreach ($positions as $id => [$parent, $order]) {
                $node = $nodes[$id];
                $node->parent = $parent;
                $node->order = $order;
                $node->save();
            }
        });

        TagDependency::invalidate(self::NAVIGATE_CACHE_NODES);
    }

    /** Hides or shows the nodes that point to an element */
    public function setEnabledForElement(ElementInterface $element, bool $enabled): void
    {
        if (NodeRecord::query()->where('elementId', $element->id)->update(['enabled' => $enabled])) {
            Navigate::getInstance()->navigate->clearAllCaches();
        }
    }

    /** Clears the render caches when an element used in a navigation changes */
    public function clearCachesForElement(ElementInterface $element): void
    {
        if ($element->id && NodeRecord::query()->where('elementId', $element->id)->exists()) {
            Navigate::getInstance()->navigate->clearAllCaches();
        }
    }

    /** The element a node points to, in the node's site */
    public function getElement(NodeRecord|NodeModel $node): ?ElementInterface
    {
        $class = self::ELEMENT_TYPES[$node->elementType] ?? null;
        if ($node->type !== 'element' || ! $class || ! $node->elementId) {
            return null;
        }

        return $class::find()->id($node->elementId)->siteId($node->siteId)->status(null)->first();
    }

    /** @param array<int, array{0: int|null, 1: int}> $positions node ID => [parent ID, order] */
    private function flattenTree(array $branch, ?int $parent, int $level, int $maxLevels, array &$positions): void
    {
        // Only a level that has nodes counts towards the maximum
        if ($branch && $level > $maxLevels) {
            throw new InvalidArgumentException("The tree is deeper than the navigation’s {$maxLevels} levels.");
        }

        foreach (array_values($branch) as $order => $item) {
            $id = (int) $item['id'];
            $positions[$id] = [$parent, $order];
            $this->flattenTree($item['children'] ?? [], $id, $level + 1, $maxLevels, $positions);
        }
    }

    /** @return array<int, NodeModel> */
    private function buildRenderTree(NavigationRecord $navigation, int $siteId): array
    {
        $tree = $this->getTree($navigation->id, $siteId, enabledOnly: true);

        return $this->renderBranch($tree, 0, $navigation->levels > 1);
    }

    /**
     * @param  Collection<int, Collection<int, NodeRecord>>  $tree
     * @return array<int, NodeModel> keyed by order
     */
    private function renderBranch(Collection $tree, int $parent, bool $withChildren): array
    {
        $nodes = [];
        foreach ($tree->get($parent, collect()) as $record) {
            $node = NodeModel::fromRecord($record);

            if ($node->type === 'element') {
                $element = $this->getElement($record);
                if (! $element || ! $element->enabled) {
                    continue;
                }
                $node->url = $element->getUrl();
                $node->slug = $element->uri;
            } elseif ($node->type === 'url' && $node->url) {
                $node->url = renderObjectTemplate((string) Env::parse($node->url), Cms::config());
            }

            if ($withChildren) {
                $node->children = $this->renderBranch($tree, $record->id, true);
            }

            $nodes[$node->order] = $node;
        }

        return $nodes;
    }

    private function cacheKey(string $handle, int $siteId): string
    {
        return self::NAVIGATE_CACHE_NODES.'_'.$handle.'_'.$siteId;
    }

    private function clearCacheForNode(NodeRecord $node): void
    {
        $navigation = Navigate::getInstance()->navigate->getNavigationById($node->navId);
        if ($navigation) {
            TagDependency::invalidate($this->cacheKey($navigation->handle, $node->siteId));
        }
    }
}
