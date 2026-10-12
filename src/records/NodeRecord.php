<?php

namespace studioespresso\navigate\records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property int $id
 * @property int $siteId
 * @property int $navId
 * @property string $name
 * @property string|null $url
 * @property int|null $elementId
 * @property string|null $elementType entry|asset
 * @property string|null $type element|url|heading
 * @property bool $enabled
 * @property bool $blank
 * @property string|null $classes
 * @property int|null $parent
 * @property int|null $order
 */
class NodeRecord extends BaseModel
{
    use HasUid;

    protected $table = 'navigate_nodes';

    protected $casts = [
        'siteId' => 'int',
        'navId' => 'int',
        'elementId' => 'int',
        'enabled' => 'bool',
        'blank' => 'bool',
        'parent' => 'int',
        'order' => 'int',
    ];

    /** Older versions stored `Url`/`Heading`; always work with lowercase types */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : strtolower($value),
            set: fn (?string $value) => $value === null ? null : strtolower($value),
        );
    }
}
