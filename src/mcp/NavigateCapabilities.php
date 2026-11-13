<?php
/**
 * Navigate plugin for Craft CMS
 *
 * @link      https://studioespresso.co
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\mcp;

use Craft;
use craft\helpers\Json;
use craft\mcp\attributes\RequiresAdminChanges;
use craft\mcp\attributes\RequiresPermission;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use studioespresso\navigate\models\NavigationModel;
use studioespresso\navigate\models\NodeModel;
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NavigationRecord;

/**
 * MCP tools for Navigate (Craft 5.12+). Mirrors the navigate/* console commands.
 *
 * @since 5.2.0
 */
class NavigateCapabilities
{
    /** @return array{navigations: list<array<string, mixed>>} */
    #[McpTool(
        name: 'navigate_navigations_list',
        description: 'Lists the Navigate navigations (menus) the current user can edit, with their id, handle, title and max levels.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    #[RequiresPermission('accessPlugin-navigate')]
    public function listNavigations(): array
    {
        return ['navigations' => array_map(fn(NavigationRecord $nav) => [
            'id' => (int)$nav->id,
            'handle' => $nav->handle,
            'title' => $nav->title,
            'levels' => (int)$nav->levels,
        ], array_values(Navigate::getInstance()->navigate->getAllNavigationForUser()))];
    }

    /**
     * Navigations live in project config, so this is only offered where admin changes are allowed (typically local).
     *
     * @param list<string>|null $allowedSources
     * @param list<int>|null $siteGroups
     * @return array{navigation: array<string, mixed>}
     */
    #[McpTool(
        name: 'navigate_navigations_create',
        description: 'Creates a Navigate navigation (menu). Writes to project config, so it is only available where allowAdminChanges is enabled.',
    )]
    #[RequiresAdminChanges]
    public function createNavigation(
        string $title,
        #[Schema(description: 'Unique handle, e.g. mainMenu.')]
        string $handle,
        #[Schema(description: 'Menu depth. 1 = flat.', minimum: 1)]
        int $levels = 1,
        #[Schema(description: 'Allowed item types. Omit to allow all.', items: ['type' => 'string', 'enum' => ['url', 'entry', 'asset', 'category', 'heading']])]
        ?array $allowedSources = null,
        #[Schema(description: 'Site group ids the navigation is enabled for. Omit for all.', items: ['type' => 'integer'])]
        ?array $siteGroups = null,
    ): array {
        $model = new NavigationModel();
        $model->title = $title;
        $model->handle = $handle;
        $model->levels = $levels;
        $model->allowedSources = $allowedSources ? Json::encode(array_values($allowedSources)) : '*';
        $model->enabledSiteGroups = $siteGroups ? Json::encode(array_values($siteGroups)) : '*';
        $model->adminOnly = false;

        if (!$model->validate()) {
            throw new ToolCallException(implode(' ', $model->getFirstErrors()));
        }
        if (!Navigate::getInstance()->navigate->saveNavigation($model)) {
            throw new ToolCallException('Failed to save navigation.');
        }

        $saved = $this->navigation($handle);

        return ['navigation' => [
            'id' => (int)$saved->id,
            'handle' => $saved->handle,
            'title' => $saved->title,
            'levels' => (int)$saved->levels,
        ]];
    }

    /** @return array{nodes: list<array<string, mixed>>} */
    #[McpTool(
        name: 'navigate_nodes_list',
        description: 'Lists the items (nodes) of a Navigate navigation for one site, in structure order. Defaults to the primary site.',
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
        $this->authorize($navigation->id, $siteId);

        $nodes = Navigate::getInstance()->nodes->getNodesStructureByNavIdAndSiteById($navigation->id, $siteId);

        return ['nodes' => array_map($this->serialize(...), array_values($nodes))];
    }

    /** @return array{node: array<string, mixed>} */
    #[McpTool(
        name: 'navigate_nodes_add',
        description: 'Adds an item to a Navigate navigation. type "url" needs url; "entry", "asset" and "category" need elementId; "heading" needs neither. Pass parent (a node id) to nest it.',
    )]
    #[RequiresPermission('accessPlugin-navigate')]
    public function addNode(
        #[Schema(description: 'Navigation handle.')]
        string $nav,
        #[Schema(description: 'Item label.')]
        string $name,
        #[Schema(enum: ['url', 'entry', 'asset', 'category', 'heading'])]
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

        $node = new NodeModel();
        $node->navId = $navigation->id;
        $node->siteId = $this->siteId($site);
        $this->authorize($navigation->id, $node->siteId);

        if ($parent !== null) {
            $parentNode = $this->node($parent);
            if ($parentNode->navId != $node->navId || $parentNode->siteId != $node->siteId) {
                throw new ToolCallException('parent must be an item in the same navigation and site.');
            }
        }

        $node->name = $name;
        $node->parent = $parent;
        $node->blank = $blank;
        $node->classes = $classes;
        $node->enabled = true;

        if ($type === 'url') {
            if (!$url) {
                throw new ToolCallException('url is required for a url item.');
            }
            $node->type = 'url';
            $node->url = $url;
        } elseif ($type === 'heading') {
            $node->type = 'heading';
        } else {
            if (!$elementId) {
                throw new ToolCallException("elementId is required for a {$type} item.");
            }
            $node->type = 'element';
            $node->elementType = $type;
            $node->elementId = $elementId;
        }

        if (!$node->validate()) {
            throw new ToolCallException(implode(' ', $node->getFirstErrors()));
        }

        $saved = Navigate::getInstance()->nodes->save($node);
        if ($saved === false) {
            throw new ToolCallException("Failed to save item (see the 'navigate' log).");
        }

        return ['node' => $this->serialize($saved)];
    }

    /** @return array{node: array<string, mixed>} */
    #[McpTool(
        name: 'navigate_nodes_update',
        description: 'Updates a Navigate item. Only the arguments you pass are changed. url applies to url items, elementId to entry/asset/category items.',
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
            throw new ToolCallException('elementId can only be set on entry, asset or category items.');
        }

        foreach (compact('name', 'url', 'elementId', 'enabled', 'blank', 'classes') as $attribute => $value) {
            if ($value !== null) {
                $node->$attribute = $value;
            }
        }

        if (!$node->validate()) {
            throw new ToolCallException(implode(' ', $node->getFirstErrors()));
        }

        $saved = Navigate::getInstance()->nodes->save($node);
        if ($saved === false) {
            throw new ToolCallException("Failed to save item (see the 'navigate' log).");
        }

        return ['node' => $this->serialize($saved)];
    }

    /** @return array{node: array<string, mixed>} */
    #[McpTool(
        name: 'navigate_nodes_move',
        description: 'Moves a Navigate item within its navigation and site. parent is the new parent node id (omit for top level); after is the sibling node id to place it after (omit to make it the first child).',
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
            if ($other->navId != $node->navId || $other->siteId != $node->siteId || $other->id == $node->id) {
                throw new ToolCallException("{$label} must be another item in the same navigation and site.");
            }
            if ($label === 'after' && $other->parent != $parent) {
                throw new ToolCallException('after must be a child of the given parent.');
            }
        }

        for ($ancestor = $parent; $ancestor; $ancestor = $this->node($ancestor)->parent) {
            if ($ancestor == $node->id) {
                throw new ToolCallException('An item can’t be moved under one of its own children.');
            }
        }

        Navigate::getInstance()->nodes->move($node, $parent, $after ?? false);

        return ['node' => $this->serialize($this->node($id))];
    }

    /** @return array{deleted: int} */
    #[McpTool(
        name: 'navigate_nodes_delete',
        description: 'Deletes a Navigate item and its direct children.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('accessPlugin-navigate')]
    public function deleteNode(
        #[Schema(description: 'Node id.')]
        int $id,
    ): array {
        Navigate::getInstance()->nodes->deleteNode($this->node($id));

        return ['deleted' => $id];
    }

    /** @return array<string, mixed> */
    private function serialize(NodeModel $node): array
    {
        return [
            'id' => (int)$node->id,
            'name' => $node->name,
            'type' => $node->type,
            'elementType' => $node->elementType,
            'url' => $node->url,
            'elementId' => $node->elementId ? (int)$node->elementId : null,
            'navId' => (int)$node->navId,
            'siteId' => (int)$node->siteId,
            'parent' => $node->parent ? (int)$node->parent : null,
            'order' => (int)$node->order,
            'enabled' => (bool)$node->enabled,
            'blank' => (bool)$node->blank,
            'classes' => $node->classes,
        ];
    }

    private function node(int $id): NodeModel
    {
        $node = Navigate::getInstance()->nodes->getNodeById($id)
            ?: throw new ToolCallException("No navigation item found with id {$id}.");
        $this->authorize((int)$node->navId, (int)$node->siteId);

        return $node;
    }

    /**
     * Same rules as the CP (DefaultController::getEditAbleSites()): the site must be
     * enabled for the navigation, and with more than one enabled site the user needs
     * editSite permission for it.
     */
    private function authorize(int $navId, int $siteId): void
    {
        $navigation = NavigationRecord::findOne($navId)
            ?? throw new ToolCallException("No navigation found with id {$navId}.");
        $sites = Craft::$app->getSites();
        $groupIds = $navigation->enabledSiteGroups;

        $enabledSites = !$groupIds || $groupIds === '*'
            ? $sites->getAllSites()
            : array_merge(...array_map(fn($groupId) => $sites->getSitesByGroupId((int)$groupId), json_decode($groupIds) ?: []));

        $site = array_values(array_filter($enabledSites, fn($site) => $site->id == $siteId))[0] ?? null;
        if (!$site) {
            throw new ToolCallException("Navigation '{$navigation->handle}' is not enabled for site {$siteId}.");
        }

        $user = Craft::$app->getUser()->getIdentity()
            ?? throw new ToolCallException('Authentication is required.');
        if (count($enabledSites) > 1 && !$user->can("editSite:{$site->uid}")) {
            throw new ToolCallException("You don't have permission to edit site '{$site->handle}'.");
        }
    }

    private function navigation(string $handle): NavigationRecord
    {
        return Navigate::getInstance()->navigate->getNavigationByHandle($handle, false)
            ?: throw new ToolCallException("No navigation found with handle '{$handle}'.");
    }

    private function siteId(?string $handle): int
    {
        $sites = Craft::$app->getSites();
        $site = $handle ? $sites->getSiteByHandle($handle) : $sites->getPrimarySite();

        return $site?->id ?? throw new ToolCallException("No site found with handle '{$handle}'.");
    }
}
