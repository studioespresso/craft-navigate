# AGENTS.md — Navigate for Craft CMS 6

Guidance for AI agents (Claude Code, Cursor, etc.) working with the **Navigate** plugin
(`studioespresso/craft-navigate`). Read this before creating menus, navigations, or menu
items in a Craft 6 project that has Navigate installed.

## Mental model

- A **navigation** is a container: `title`, `handle`, `levels` (depth), `allowedSources`,
  `enabledSiteGroups`, `adminOnly`. Eloquent model `NavigationRecord`, managed by `NavigateService`.
  Navigations live in project config (`navigate.nav.{uid}`).
- A **node** is an item inside a navigation. Eloquent model `NodeRecord`, managed by `NodesService`. Three kinds:
  - `url` — a literal/relative URL (supports env vars and `{config}` object templates).
  - `element` — a link to a Craft **entry** or **asset**. The URL is resolved from the element at render time.
  - `heading` — a non-link label used to group items (only meaningful when `levels > 1`).
- Nodes are stored **per site** (`siteId`) and can be **nested** (`parent` → node id).
  Deleting a node deletes its descendants.

## Reading a navigation (output)

Twig:
```twig
{% set nav = craft.navigate.raw('mainMenu') %}      {# current site #}
{% set nav = craft.navigate.raw('mainMenu', 2) %}   {# explicit siteId #}
{% for node in nav %}
  <a href="{{ node.url }}" class="{{ node.classes }}{{ node.active() ? ' is-active' }}"
     {{ node.blank ? 'target="_blank" rel="noopener"' }}>{{ node.name }}</a>
  {% if node.children|length %}{# recurse over node.children #}{% endif %}
{% endfor %}
```
Each node (`NodeModel`) exposes: `name`, `url`, `classes`, `blank`, `type`, `children`, `active()`,
`current()`. Check `node.type == 'heading'` to render a label instead of a link.
`active()` matches the node's page and the pages below it, `current()` only the page itself;
both compare host and path.

PHP equivalent: `Navigate::getInstance()->nodes->getNodesForRender('mainMenu', $siteId)`.

## Writing — console commands (preferred for agents)

They are discoverable (`php artisan list navigate`) and take `--json` for machine-readable output.
Run them with whatever wraps `php artisan` in the project (`ddev exec php artisan …`, …).
The Craft 5 names still work as aliases: `php craft navigate/nodes/add …`.

```bash
# List navigations (--json → [{id,handle,title,levels}])
php artisan navigate:navigations:list --json

# Create a navigation (levels>1 allows nesting). Prints the new id (use --json).
php artisan navigate:navigations:create "Main menu" mainMenu --levels=2 --json

# Add items. --type is one of: url, entry, asset, heading.
php artisan navigate:nodes:add mainMenu "Home"     --type=url --url=/
php artisan navigate:nodes:add mainMenu "About"    --type=entry --element-id=42
php artisan navigate:nodes:add mainMenu "Products" --type=heading
php artisan navigate:nodes:add mainMenu "Sub page" --type=url --url=/sub --parent=12   # nest under node 12

# List a navigation's items (ids you need for --parent)
php artisan navigate:nodes:list mainMenu --json
```

Notes:
- `create` and `add` print the created record's `id` — pass `--json` and read it back;
  use a node's `id` as the `--parent` of a child to nest.
- `--type=entry|asset` requires `--element-id` (the element must exist in that site); `--type=url` requires `--url`.
- Extra `add` options: `--site=<handle|id>` (defaults to the primary site), `--blank` (new tab),
  `--classes="..."`, `--parent=<nodeId>` (must be in the same navigation and site).
- `create` options: `--allowed-sources=entry,url` and `--site-groups=1,2` (both default to all).
- Items are stored per site — pass `--site` (or repeat the command per site) for multi-site.

There are **no GraphQL mutations**.

## Writing — PHP service API

For migrations, modules or `php artisan tinker`:

```php
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NavigationRecord;
use studioespresso\navigate\records\NodeRecord;

// 1. Create a navigation — returns void and writes through project config
Navigate::getInstance()->navigate->saveNavigation(new NavigationRecord([
    'title' => 'Main menu',
    'handle' => 'mainMenu',       // unique handle, not validated by the service
    'levels' => 2,                // 1 = flat, >1 allows nested children
    'allowedSources' => '*',      // '*' or JSON: '["entry","url"]'
    'enabledSiteGroups' => '*',   // '*' or JSON array of site group ids
    'adminOnly' => false,
]));
$navId = Navigate::getInstance()->navigate->getNavigationByHandle('mainMenu', false)->id; // false skips the cache

// 2. A URL item — save() assigns the order and clears caches; $node->id is set afterwards
$node = new NodeRecord(['navId' => $navId, 'siteId' => $siteId, 'name' => 'Home', 'type' => 'url', 'url' => '/']);
Navigate::getInstance()->nodes->save($node);

// 3. An element item — don't set url, it resolves from the element per site
Navigate::getInstance()->nodes->save(new NodeRecord([
    'navId' => $navId, 'siteId' => $siteId, 'name' => 'About us',
    'type' => 'element', 'elementType' => 'entry', 'elementId' => 42, // 'entry' | 'asset'
    'parent' => $node->id, // nest; the navigation's levels must be > 1
]));

// 4. A heading
Navigate::getInstance()->nodes->save(new NodeRecord(['navId' => $navId, 'siteId' => $siteId, 'name' => 'Products', 'type' => 'heading']));
```

## Gotchas

- **The services don't validate.** The console commands and CP do; when calling the services
  yourself, set `name`, `navId`, `siteId` and `type`, and keep handles unique.
- **`saveNavigation()` returns nothing.** Re-fetch by handle for the id.
- **Nodes are per site.** To mirror a menu across sites, create the nodes once per `siteId`.
- **Caching**: rendered navigations are cached when `devMode` is off (unless `disableCaching`).
  Writes through the services clear the relevant caches. To clear manually:
  `Navigate::getInstance()->navigate->clearAllCaches()`. Pages rendering a navigation get the
  `navigate` and `navigate:{handle}:{siteId}` cache tags.

## Quick API reference

| Call | Purpose |
| --- | --- |
| `navigate->getAllNavigations()` | All navigations (Collection of `NavigationRecord`) |
| `navigate->getNavigationByHandle($handle, false)` | One navigation by handle (fresh) |
| `navigate->getNavigationById($id)` | One navigation |
| `navigate->saveNavigation($record)` | Create/update a navigation |
| `navigate->deleteNavigation($record)` | Delete a navigation |
| `nodes->getNodesForRender($handle, $siteId)` | Node tree for output (`NodeModel`s) |
| `nodes->getTree($navId, $siteId)` | All `NodeRecord`s, grouped by parent id (0 = top level) |
| `nodes->save($record)` | Create/update a node |
| `nodes->delete($record)` | Delete a node and its children |
| `nodes->saveTree($navigation, $siteId, $tree)` | Reorder/nest: `[['id' => 1, 'children' => [['id' => 2]]]]` |

(All on `Navigate::getInstance()`.)
