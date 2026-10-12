<?php

namespace studioespresso\navigate\records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string $handle
 * @property string|null $allowedSources `*` or a JSON list of node types
 * @property string|null $enabledSiteGroups `*` or a JSON list of site group IDs
 * @property int $levels
 * @property bool $adminOnly
 * @property string $uid
 */
class NavigationRecord extends BaseModel
{
    use HasUid;

    protected $table = 'navigate_navigations';

    protected $casts = [
        'levels' => 'int',
        'adminOnly' => 'bool',
    ];

    /** @return HasMany<NodeRecord, $this> */
    public function nodes(): HasMany
    {
        return $this->hasMany(NodeRecord::class, 'navId');
    }

    /** @return list<string>|null The allowed node types, or null when all are allowed */
    public function allowedSourceList(): ?array
    {
        return $this->decodeList($this->allowedSources);
    }

    /** @return list<int>|null The enabled site group IDs, or null when all are enabled */
    public function enabledSiteGroupIds(): ?array
    {
        $ids = $this->decodeList($this->enabledSiteGroups);

        return $ids === null ? null : array_map('intval', $ids);
    }

    private function decodeList(?string $value): ?array
    {
        if ($value === null || $value === '' || $value === '*') {
            return null;
        }

        $list = json_decode($value, true);

        return is_array($list) ? array_values($list) : null;
    }
}
