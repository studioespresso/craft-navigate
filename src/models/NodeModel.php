<?php

namespace studioespresso\navigate\models;

use CraftCms\Cms\Support\Facades\Deprecator;
use studioespresso\navigate\records\NodeRecord;

/**
 * A navigation node as returned by `craft.navigate.raw()`.
 */
class NodeModel
{
    public ?int $id = null;

    public ?string $name = null;

    public ?int $navId = null;

    public ?int $siteId = null;

    /** element|url|heading */
    public ?string $type = null;

    public ?string $url = null;

    /** The element's URI, for element nodes */
    public ?string $slug = null;

    public ?int $order = null;

    public ?int $parent = null;

    public ?string $classes = null;

    public bool $enabled = true;

    public ?int $elementId = null;

    /** entry|asset */
    public ?string $elementType = null;

    public bool $blank = false;

    /** @var array<int, NodeModel> */
    public array $children = [];

    public static function fromRecord(NodeRecord $record): self
    {
        $node = new self;
        foreach (['id', 'name', 'navId', 'siteId', 'type', 'url', 'order', 'parent', 'classes', 'enabled', 'elementId', 'elementType', 'blank'] as $attribute) {
            $node->$attribute = $record->$attribute;
        }

        return $node;
    }

    /** @return array<string, mixed> The node and its children as plain data, for caching */
    public function toArray(): array
    {
        return [...get_object_vars($this), 'children' => array_map(fn (self $child) => $child->toArray(), $this->children)];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $node = new self;
        foreach ($data as $attribute => $value) {
            $node->$attribute = $attribute === 'children' ? array_map(self::fromArray(...), $value) : $value;
        }

        return $node;
    }

    /** Whether the current request is this node's URL or a page below it */
    public function active(): bool
    {
        return $this->matchesRequest(true);
    }

    /** Whether the current request is exactly this node's URL */
    public function current(): bool
    {
        return $this->matchesRequest(false);
    }

    /** @deprecated Use `classes` */
    public function listClass(): ?string
    {
        Deprecator::log(self::class.'::listClass', "The 'listClass' method has been renamed to 'classes'.");

        return $this->classes;
    }

    /** @return array<int, NodeModel> */
    public function getChildren(): array
    {
        return $this->children;
    }

    /** @deprecated Use `children` */
    public function hasChildren(): array
    {
        Deprecator::log(self::class.'::hasChildren', "The 'hasChildren' method has been renamed to 'children'.");

        return $this->children;
    }

    /**
     * Compares the node's URL with the current request: same host, and the same path
     * (or, with $includeDescendants, a path below it). The homepage only matches itself.
     */
    private function matchesRequest(bool $includeDescendants): bool
    {
        if ($this->type === 'heading' || ! $this->url) {
            return false;
        }

        $node = parse_url($this->url);
        if ($node === false || (isset($node['scheme']) && ! in_array(strtolower($node['scheme']), ['http', 'https'], true))) {
            return false;
        }

        if (isset($node['host']) && strcasecmp($node['host'], request()->getHost()) !== 0) {
            return false;
        }

        // Fragment- or query-only links ("#contact", "?page=2") don't point to a page
        if (! isset($node['host']) && ($node['path'] ?? '') === '') {
            return false;
        }

        $nodePath = trim($node['path'] ?? '', '/');
        $currentPath = trim((string) parse_url(request()->url(), PHP_URL_PATH), '/');

        if ($nodePath === $currentPath) {
            return true;
        }

        return $includeDescendants && $nodePath !== '' && str_starts_with($currentPath.'/', $nodePath.'/');
    }
}
