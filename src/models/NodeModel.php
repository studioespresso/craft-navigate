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

    /** Whether the current request is this node's URL or one below it */
    public function active(): bool
    {
        return match ($this->type) {
            'heading' => false,
            'url' => $this->matchesUrlPrefix(),
            default => $this->current() || ($this->slug !== null && str_starts_with(self::path().'/', $this->slug.'/')),
        };
    }

    /** Whether the current request is exactly this node's URL */
    public function current(): bool
    {
        return match ($this->type) {
            'heading' => false,
            'url' => $this->matchesUrlPrefix(),
            default => $this->url !== null && in_array($this->url, [request()->fullUrl(), request()->url()], true),
        };
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

    private function matchesUrlPrefix(): bool
    {
        if (! $this->url) {
            return false;
        }

        return str_starts_with(self::path(), $this->url) || str_starts_with('/'.self::path(), $this->url);
    }

    /** The request path without leading or trailing slashes ('' for the homepage) */
    private static function path(): string
    {
        return trim(request()->path(), '/');
    }
}
