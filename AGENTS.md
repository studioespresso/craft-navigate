# AGENTS.md — Navigate for Craft CMS

Guidance for AI agents (Claude Code, Cursor, etc.) working with the **Navigate** plugin
(`studioespresso/craft-navigate`). Read this before creating menus, navigations, or menu
items in a Craft project that has Navigate installed.

## Mental model

- A **navigation** is a container: `title`, `handle`, `levels` (depth), `allowedSources`,
  `enabledSiteGroups`. Managed by `NavigateService`.
- A **node** is an item inside a navigation. Managed by `NodesService`. Three kinds:
  - `url` — a literal/relative URL (supports env vars and `{{ }}` object templates).
  - `element` — a link to a Craft **entry**, **asset**, or **category**. The URL is
    resolved from the element at render time.
  - `heading` — a non-link label used to group items (only meaningful when `levels > 1`).
- Nodes are stored **per site** (`siteId`) and can be **nested** (`parent` → node id).

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
Each node exposes: `name`, `url`, `classes`, `blank`, `type`, `children`, `active()`,
`current()`. Check `node.type == 'heading'` to render a label instead of a link.

PHP equivalent: `Navigate::$plugin->nodes->getNodesForRender('mainMenu', $siteId)`.

## Writing — console commands (preferred for agents)

The easiest and most reliable way to build navigations is the console commands. They need no
auth/CSRF, are discoverable (`./craft help navigate/nodes/add`), and take `--json` for
machine-readable output. Run them with whatever wraps `./craft` in the project
(`ddev craft …`, `lando craft …`, `docker compose exec … craft …`, or plain `./craft`).

```bash
# List navigations (add --json for parseable output → [{id,handle,title,levels}])
./craft navigate/navigations/list --json

# Create a navigation (levels>1 allows nesting). Prints the new id (use --json).
./craft navigate/navigations/create "Main menu" mainMenu --levels=2 --json

# Add items. --type is one of: url, entry, asset, category, heading.
./craft navigate/nodes/add mainMenu "Home"     --type=url --url=/
./craft navigate/nodes/add mainMenu "About"    --type=entry --element-id=42
./craft navigate/nodes/add mainMenu "Products" --type=heading
./craft navigate/nodes/add mainMenu "Sub page" --type=url --url=/sub --parent=12   # nest under node 12

# List a navigation's items (ids you need for --parent). --json for the full tree.
./craft navigate/nodes/list mainMenu --json
```

Notes for agents:
- `create` and `add` print the created record's `id` — pass `--json` and read it back;
  use a node's `id` as the `--parent` of a child to nest.
- `--type=entry|asset|category` requires `--element-id`; `--type=url` requires `--url`.
- Extra `add` options: `--site=<handle|id>` (defaults to primary site), `--blank` (new tab),
  `--classes="..."`, `--parent=<nodeId>`.
- Items are stored per site — pass `--site` (or repeat the command per site) for multi-site.

The PHP service API below does the same thing at a lower level (useful inside migrations,
modules, or a tinker/eval context). There are **no GraphQL mutations**.

## Writing — PHP service API

### 1. Create a navigation

```php
use studioespresso\navigate\Navigate;
use studioespresso\navigate\models\NavigationModel;

$nav = new NavigationModel();
$nav->title            = 'Main menu';
$nav->handle           = 'mainMenu';   // unique; validated as a handle
$nav->levels           = 2;            // 1 = flat, >1 allows nested children
$nav->allowedSources   = '*';          // '*' or JSON: '["entry","url"]'
$nav->enabledSiteGroups = '*';         // '*' or JSON array of site-group ids
$nav->adminOnly        = false;
Navigate::$plugin->navigate->saveNavigation($nav);   // returns bool, NOT the model
```

`saveNavigation()` returns a boolean. **Re-fetch to get the id** (it writes through
project config):

```php
$navId = Navigate::$plugin->navigate->getNavigationByHandle('mainMenu', false)->id;
```
(Pass `false` to skip the cache and get a fresh `NavigationRecord`.)

### 2. Add a URL item

```php
use studioespresso\navigate\models\NodeModel;

$node = new NodeModel();
$node->navId   = $navId;
$node->siteId  = Craft::$app->sites->getPrimarySite()->id;
$node->name    = 'Home';
$node->type    = 'url';   // lowercase!
$node->url     = '/';
$node->blank   = false;   // open in new tab
$node->classes = '';      // optional CSS classes
$node->enabled = true;
$node->parent  = null;    // or a parent node id to nest
Navigate::$plugin->nodes->save($node);   // returns saved NodeModel (with ->id) or false
```

### 3. Add an element item (entry / asset / category)

```php
$node = new NodeModel();
$node->navId       = $navId;
$node->siteId      = $siteId;
$node->name        = 'About us';
$node->type        = 'element';   // lowercase!
$node->elementType = 'entry';     // 'entry' | 'asset' | 'category'
$node->elementId   = 42;          // id of the entry/asset/category
$node->enabled     = true;
Navigate::$plugin->nodes->save($node);
```
Do **not** set `url`/`slug` on element nodes — they resolve from the element per site.

### 4. Add a heading

```php
$node = new NodeModel();
$node->navId  = $navId;
$node->siteId = $siteId;
$node->name   = 'Products';
$node->type   = 'heading';   // lowercase!
Navigate::$plugin->nodes->save($node);
```

### 5. Nest items

Set `$node->parent = <parentNodeId>` (the id returned by the parent's `save()`). The
navigation's `levels` must be `> 1`. `save()` auto-assigns `order` (appends within parent).

## Gotchas (read these)

- **`type` is lowercase**: `url`, `element`, `heading`. Always use lowercase via the console
  or service API (see the known issue below about capitalized values).
- **`saveNavigation()` returns bool, not the model.** Re-fetch by handle for the id.
- **Nodes are per site.** Always set `siteId`. To mirror a menu across sites, create the
  nodes once per target `siteId`.
- **Required node attributes**: `name`, `navId`, `siteId`, `type`. Missing any → validation
  fails and `save()` returns `false` (errors logged to the `navigate` log category).
- **Caching**: node/nav reads are cached when `devMode` is off. Writes via the services
  clear the relevant caches automatically. To clear manually:
  `Navigate::$plugin->navigate->clearAllCaches()`.

## Known issues

- **Capitalized node types in the CP slide-out.** The Control-Panel "add/edit item"
  slide-out (`controllers/NodesController::actionAddSlideOut()` and `actionEditSlideOut()`)
  compares the submitted type against capitalized `'Url'` / `'Heading'`, but node types are
  stored and rendered in **lowercase** (`url`, `element`, `heading`) — see
  `NodesService::parseNode()` and `NodeModel::active()`/`current()`. Those capitalized
  branches therefore don't fire, so a node's `url`/`classes` may not be set through that
  slide-out path. The console commands and the service API in this document always use the
  correct lowercase values, so prefer them. If you set `type` yourself, use lowercase.

## Quick API reference

| Call | Purpose |
| --- | --- |
| `Navigate::$plugin->navigate->getAllNavigations()` | All navigations (records) |
| `Navigate::$plugin->navigate->getNavigationByHandle($h, false)` | One navigation by handle (fresh) |
| `Navigate::$plugin->navigate->getNavigationById($id)` | One navigation (NavigationModel) |
| `Navigate::$plugin->navigate->saveNavigation($model)` | Create/update a navigation → bool |
| `Navigate::$plugin->navigate->deleteNavigationById($id)` | Delete a navigation |
| `Navigate::$plugin->nodes->getNodesForRender($handle, $siteId)` | Parsed node tree for output |
| `Navigate::$plugin->nodes->getNodesByNavIdAndSiteById($navId, $siteId)` | Top-level nodes (models) |
| `Navigate::$plugin->nodes->getNodeById($id)` | One node (NodeModel) |
| `Navigate::$plugin->nodes->save($node)` | Create/update a node → NodeModel\|false |
| `Navigate::$plugin->nodes->deleteNode($node)` | Delete a node (and its children) |
