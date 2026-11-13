<?php

namespace studioespresso\navigate\Mcp;

use CraftCms\Cms\Mcp\Attributes\RequiresAdmin;
use CraftCms\Cms\Mcp\Attributes\RequiresAdminChanges;
use CraftCms\Cms\Mcp\Attributes\RequiresPermission;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Validation\Rules\HandleRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use studioespresso\navigate\Models\NavigationRecord;
use studioespresso\navigate\Models\NodeRecord;
use studioespresso\navigate\services\NavigateService;
use studioespresso\navigate\services\NodesService;

/**
 * MCP tools for Navigate. Mirrors the `navigate:*` console commands, with the control panel's site rules.
 */
readonly class NavigateCapabilities
{
    public function __construct(
        private NavigateService $navigate,
        private NodesService $nodes,
    ) {}

    /** @return array{navigations: list<array<string, mixed>>} */
    #[McpTool(
        name: 'navigate_navigations_list',
        description: 'Lists the Navigate navigations (menus) the current user can edit, with their id, handle, title and max levels.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    #[RequiresPermission('accessPlugin-navigate')]
    public function listNavigations(): array
    {
        return ['navigations' => $this->navigate->getAllNavigationForUser()
            ->map($this->serializeNavigation(...))
            ->all()];
    }

    /**
     * Navigations live in project config, so this is only offered where admin changes are allowed (typically local).
     *
     * @param  list<string>|null  $allowedSources
     * @param  list<int>|null  $siteGroups
     * @return array{navigation: array<string, mixed>}
     */
    #[McpTool(
        name: 'navigate_navigations_create',
        description: 'Creates a Navigate navigation (menu). Writes to project config, so it is only available to admins where allowAdminChanges is enabled.',
    )]
    #[RequiresAdmin]
    #[RequiresAdminChanges]
    public function createNavigation(
        string $title,
        #[Schema(description: 'Unique handle, e.g. mainMenu.')]
        string $handle,
        #[Schema(description: 'Menu depth. 1 = flat.', minimum: 1, maximum: 5)]
        int $levels = 1,
        #[Schema(description: 'Allowed item types. Omit to allow all.', items: ['type' => 'string', 'enum' => ['url', 'entry', 'asset', 'heading']])]
        ?array $allowedSources = null,
        #[Schema(description: 'Site group ids the navigation is enabled for. Omit for all.', items: ['type' => 'integer'])]
        ?array $siteGroups = null,
    ): array {
        $this->validate(compact('title', 'handle', 'levels', 'allowedSources', 'siteGroups'), [
            'title' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:64', new HandleRule, Rule::unique(NavigationRecord::class, 'handle')],
            'levels' => ['required', 'integer', 'min:1', 'max:5'],
            'allowedSources' => ['nullable', 'array'],
            'allowedSources.*' => [Rule::in(array_keys(NodesService::TYPES))],
            'siteGroups' => ['nullable', 'array'],
            'siteGroups.*' => ['integer'],
        ]);

        $this->navigate->saveNavigation(new NavigationRecord([
            'title' => $title,
            'handle' => $handle,
            'levels' => $levels,
            'adminOnly' => false,
            'allowedSources' => $allowedSources ? json_encode(array_values($allowedSources)) : '*',
            'enabledSiteGroups' => $siteGroups ? json_encode(array_map('intval', array_values($siteGroups))) : '*',
        ]));

        // Saved through project config, so fetch the record to get its ID
        return ['navigation' => $this->serializeNavigation($this->navigation($handle))];
    }

    /** @return array{nodes: list<array<string, mixed>>} */
    #[McpTool(
        name: 'navigate_nodes_list',
        description: 'Lists the items (nodes) of a Navigate navigation for one site, in structure order (parents before their children). Defaults to the primary site.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    #[RequiresPermission('accessPlugin-navigate')]
    public function listNodes(
        #[Schema(description: 'Navigation handle.')]
        string $nav,
        #[Schema(description: 'Site handle. Defaults to the primary site.')]
        ?string $site = null,
    ): array {
        $navigation = $this->navigation($nav);
        $siteId = $this->siteId($site);
        $this->authorize($navigation, $siteId);

        $tree = $this->nodes->getTree($navigation->id, $siteId);
        $flatten = function (int $parent) use (&$flatten, $tree): array {
            return ($tree[$parent] ?? collect())
                ->flatMap(fn (NodeRecord $node) => [$this->serializeNode($node), ...$flatten($node->id)])
                ->all();
        };

        return ['nodes' => $flatten(0)];
    }

    /** @return array{node: array<string, mixed>} */
    #[McpTool(
        name: 'navigate_nodes_add',
        description: 'Adds an item to a Navigate navigation. type "url" needs url; "entry" and "asset" need elementId; "heading" needs neither. Pass parent (a node id) to nest it.',
    )]
    #[RequiresPermission('accessPlugin-navigate')]
    public function addNode(
        #[Schema(description: 'Navigation handle.')]
        string $nav,
        #[Schema(description: 'Item label.')]
        string $name,
        #[Schema(enum: ['url', 'entry', 'asset', 'heading'])]
        string $type = 'url',
        ?string $url = null,
        ?int $elementId = null,
        #[Schema(description: 'Parent node id.')]
        ?int $parent = null,
        #[Schema(description: 'Site handle. Defaults to the primary site.')]
        ?string $site = null,
        bool $blank = false,
        ?string $classes = null,
    ): array {
        $navigation = $this->navigation($nav);
        $siteId = $this->siteId($site);
        $this->authorize($navigation, $siteId);

        $this->validate(compact('name', 'type', 'url', 'classes'), [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(NodesService::TYPES))],
            'url' => [$type === 'url' ? 'required' : 'nullable', 'string', 'max:255'],
            'classes' => ['nullable', 'string', 'max:255'],
        ]);

        $node = new NodeRecord([
            'navId' => $navigation->id,
            'siteId' => $siteId,
            'name' => $name,
            'blank' => $type !== 'heading' && $blank,
            'classes' => $classes,
            'enabled' => true,
        ]);

        if (isset(NodesService::ELEMENT_TYPES[$type])) {
            $node->type = 'element';
            $node->elementType = $type;
            $node->elementId = $elementId ?? throw new ToolCallException("elementId is required for an {$type} item.");
            $this->requireElement($node);
        } else {
            $node->type = $type;
            $node->url = $type === 'url' ? $url : null;
        }

        if ($parent !== null) {
            $parentNode = $this->node($parent);
            if ($parentNode->navId !== $node->navId || $parentNode->siteId !== $node->siteId) {
                throw new ToolCallException('parent must be an item in the same navigation and site.');
            }
            if ($this->depth($parentNode) >= ($navigation->levels ?: 1)) {
                throw new ToolCallException("“{$navigation->handle}” allows {$navigation->levels} level(s), so the item can’t be nested that deep.");
            }
            $node->parent = $parent;
        }

        $this->nodes->save($node);

        return ['node' => $this->serializeNode($node)];
    }

    /** @return array{node: array<string, mixed>} */
    #[McpTool(
        name: 'navigate_nodes_update',
        description: 'Updates a Navigate item. Only the arguments you pass are changed. url applies to url items, elementId to entry/asset items.',
    )]
    #[RequiresPermission('accessPlugin-navigate')]
    public function updateNode(
        #[Schema(description: 'Node id.')]
        int $id,
        ?string $name = null,
        ?string $url = null,
        ?int $elementId = null,
        ?bool $enabled = null,
        ?bool $blank = null,
        ?string $classes = null,
    ): array {
        $node = $this->node($id);

        if ($url !== null && $node->type !== 'url') {
            throw new ToolCallException('url can only be set on url items.');
        }
        if ($elementId !== null && $node->type !== 'element') {
            throw new ToolCallException('elementId can only be set on entry or asset items.');
        }
        if ($blank !== null && $node->type === 'heading') {
            throw new ToolCallException('blank can’t be set on heading items.');
        }

        $changes = array_filter(compact('name', 'url', 'elementId', 'enabled', 'blank', 'classes'), fn ($value) => $value !== null);
        $this->validate($changes, [
            'name' => ['string', 'min:1', 'max:255'],
            'url' => ['string', 'min:1', 'max:255'],
            'classes' => ['string', 'max:255'],
        ]);

        $node->fill($changes);
        if ($elementId !== null) {
            $this->requireElement($node);
        }

        $this->nodes->save($node);

        return ['node' => $this->serializeNode($node)];
    }

    /** @return array{node: array<string, mixed>} */
    #[McpTool(
        name: 'navigate_nodes_move',
        description: 'Moves a Navigate item within its navigation and site, together with its children. parent is the new parent node id (omit for top level); after is the sibling node id to place it after (omit to make it the first child).',
    )]
    #[RequiresPermission('accessPlugin-navigate')]
    public function moveNode(
        #[Schema(description: 'Node id.')]
        int $id,
        #[Schema(description: 'New parent node id. Omit for top level.')]
        ?int $parent = null,
        #[Schema(description: 'Sibling node id to place it after. Omit to place it first.')]
        ?int $after = null,
    ): array {
        $node = $this->node($id);

        foreach (['parent' => $parent, 'after' => $after] as $label => $otherId) {
            if ($otherId === null) {
                continue;
            }
            $other = $this->node($otherId);
            if ($other->navId !== $node->navId || $other->siteId !== $node->siteId || $other->id === $node->id) {
                throw new ToolCallException("{$label} must be another item in the same navigation and site.");
            }
            if ($label === 'after' && $other->parent !== $parent) {
                throw new ToolCallException('after must be a child of the given parent.');
            }
        }

        for ($ancestor = $parent; $ancestor; $ancestor = NodeRecord::query()->find($ancestor)?->parent) {
            if ($ancestor === $node->id) {
                throw new ToolCallException('An item can’t be moved under one of its own children.');
            }
        }

        // Rebuild the whole tree with the node in its new place, so saveTree() checks the depth and clears the caches
        $children = $this->nodes->getTree($node->navId, $node->siteId)
            ->map(fn ($nodes) => $nodes->pluck('id')->reject(fn (int $nodeId) => $nodeId === $node->id)->values()->all())
            ->all();
        $siblings = $children[$parent ?? 0] ?? [];
        array_splice($siblings, $after === null ? 0 : array_search($after, $siblings, true) + 1, 0, [$node->id]);
        $children[$parent ?? 0] = $siblings;

        $build = function (int $parentId) use (&$build, $children): array {
            return array_map(fn (int $nodeId) => ['id' => $nodeId, 'children' => $build($nodeId)], $children[$parentId] ?? []);
        };

        try {
            $this->nodes->saveTree($this->navigate->getNavigationById($node->navId), $node->siteId, $build(0));
        } catch (InvalidArgumentException $e) {
            throw new ToolCallException($e->getMessage());
        }

        return ['node' => $this->serializeNode($node->refresh())];
    }

    /** @return array{deleted: int} */
    #[McpTool(
        name: 'navigate_nodes_delete',
        description: 'Deletes a Navigate item and all of its children.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('accessPlugin-navigate')]
    public function deleteNode(
        #[Schema(description: 'Node id.')]
        int $id,
    ): array {
        $this->nodes->delete($this->node($id));

        return ['deleted' => $id];
    }

    /** @return array<string, mixed> */
    private function serializeNavigation(NavigationRecord $navigation): array
    {
        return [
            'id' => $navigation->id,
            'handle' => $navigation->handle,
            'title' => $navigation->title,
            'levels' => (int) $navigation->levels,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeNode(NodeRecord $node): array
    {
        return [
            'id' => $node->id,
            'name' => $node->name,
            'type' => $node->type,
            'elementType' => $node->elementType,
            'url' => $node->url,
            'elementId' => $node->elementId,
            'navId' => $node->navId,
            'siteId' => $node->siteId,
            'parent' => $node->parent,
            'order' => $node->order,
            'enabled' => (bool) $node->enabled,
            'blank' => (bool) $node->blank,
            'classes' => $node->classes,
        ];
    }

    private function node(int $id): NodeRecord
    {
        $node = NodeRecord::query()->find($id)
            ?? throw new ToolCallException("No navigation item found with id {$id}.");
        $this->authorize($this->navigate->getNavigationById($node->navId), $node->siteId);

        return $node;
    }

    /** Same rule as the control panel: the site must be one the user can edit the navigation in */
    private function authorize(?NavigationRecord $navigation, int $siteId): void
    {
        if (! $navigation || ! $this->navigate->getEditableSites($navigation)->contains('id', $siteId)) {
            throw new ToolCallException("You can’t edit this navigation in site {$siteId}: it isn’t enabled there, or you lack permission to edit that site.");
        }
    }

    private function navigation(string $handle): NavigationRecord
    {
        return $this->navigate->getNavigationByHandle($handle, false)
            ?? throw new ToolCallException("No navigation found with handle “{$handle}”.");
    }

    private function siteId(?string $handle): int
    {
        $site = $handle ? Sites::getSiteByHandle($handle) : Sites::getPrimarySite();

        return $site->id ?? throw new ToolCallException("No site found with handle “{$handle}”.");
    }

    /** The level of a node, 1 for the top level */
    private function depth(NodeRecord $node): int
    {
        $depth = 1;
        for ($parent = $node->parent; $parent; $parent = NodeRecord::query()->find($parent)?->parent) {
            $depth++;
        }

        return $depth;
    }

    private function requireElement(NodeRecord $node): void
    {
        if (! $this->nodes->getElement($node)) {
            throw new ToolCallException("No {$node->elementType} found with id {$node->elementId} in site {$node->siteId}.");
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $rules
     */
    private function validate(array $values, array $rules): void
    {
        $validator = Validator::make($values, $rules);
        if ($validator->fails()) {
            throw new ToolCallException(implode(' ', $validator->errors()->all()));
        }
    }
}
