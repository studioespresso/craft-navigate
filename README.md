# Navigate for Craft CMS

Menu's & navigations made easy.

![Easy Address Field](https://www.studioespresso.co/assets/Navigate-Github-Banner.png)


## Requirements

This plugin works with Craft CMS 3.x, 4.x and 5.x

## Installation

To install the plugin, follow these instructions.

        cd /path/to/project
        composer require studioespresso/craft-navigate
        ./craft install/plugin navigate


## Templating

### craft.navigate.raw

`craft.navigate.raw` will give you an array of the items you added to the navigation, with children for each if you have those. 
For each node you will have access to:

```twig
{% set nav = craft.navigate.raw('navHandle') %}
{% for node in nav %}
	{{ node.name }}
	{{ node.url }}
	{{ node.classes }}
	{{ node.blank }}
	{{ node.children }}
{% endfor %} 
```

This gives you complete control over the HTML & CSS used to display your navigation.

Here's an example of how to show a navigation and it's possible children using a twig macro:

```twig
{% import _self as macros %}
{% macro renderNode(node) %}
    {% import _self as macros %}
    <li class="{% if node.classes|length %}{{ node.classes }}{% endif %}">
        <a  href="{{ node.url }}" class="{% if node.active %}active{% endif %}" 
        {% if node.blank %}target="_blank" rel="noopener"{% endif %}>{{ node.name }}
        </a>
        {% if node.children|length %}
            <ul>
            {% for child in node.children %}
                {{ macros.renderNode(child) }}
            {% endfor %}
            </ul>
        {% endif %}
    </li>
{% endmacro %}

<div>
    <ul>
    {% for node in nodes %}
        {{ macros.renderNode(node) }}
    {% endfor %}
    </ul>
</div>
```

## Headings

Since 2.3.0, headings can be added to make it easier to build larger navigations with mulitple sections or columns. To make full use of this you check check ``node.type == "heading"`` and add the html you need in the condition. 

These only work on multiple levels (eg: 3 headings on the top level, each with navigation items on deeper levels). 

## Console commands

Navigate ships console commands for building navigations from the command line — handy for
seeding, scripting, and AI-assisted prototyping. All support `--json` for machine-readable
output. (Prefix with your environment's runner, e.g. `ddev craft …` or `./craft …`.)

```bash
./craft navigate/navigations/list [--json]
./craft navigate/navigations/create "<title>" <handle> [--levels=2] [--allowed-sources=*] [--site-groups=*]

./craft navigate/nodes/list <navHandle> [--site=<handle|id>] [--json]
./craft navigate/nodes/add <navHandle> "<name>" --type=url --url=/path
./craft navigate/nodes/add <navHandle> "<name>" --type=entry --element-id=42
./craft navigate/nodes/add <navHandle> "<name>" --type=heading
./craft navigate/nodes/add <navHandle> "<name>" --type=url --url=/x --parent=<nodeId>   # nest
```

`--type` accepts `url`, `entry`, `asset`, `category` or `heading`. `entry`/`asset`/`category`
require `--element-id`; `url` requires `--url`. Other `add` options: `--site`, `--parent`,
`--blank`, `--classes`. Run `./craft help navigate/nodes/add` for the full list.

## Programmatic API

Navigate also exposes two services for managing navigations and their items in code — useful
inside migrations, modules, or a tinker/eval context. (There are no GraphQL mutations; the
console commands above are the quickest way to script navigation changes.)

- `Navigate::$plugin->navigate` — `NavigateService`, manages **navigations**.
- `Navigate::$plugin->nodes` — `NodesService`, manages **nodes** (menu items).

Agents working in this repo should also read [`AGENTS.md`](AGENTS.md).

### Creating a navigation

```php
use studioespresso\navigate\Navigate;
use studioespresso\navigate\models\NavigationModel;

$nav = new NavigationModel();
$nav->title             = 'Main menu';
$nav->handle            = 'mainMenu';   // unique, validated as a handle
$nav->levels            = 2;            // 1 = flat; > 1 allows nested children
$nav->allowedSources    = '*';          // '*' or a JSON array e.g. '["entry","url"]'
$nav->enabledSiteGroups = '*';          // '*' or a JSON array of site-group ids
$nav->adminOnly         = false;
Navigate::$plugin->navigate->saveNavigation($nav);   // returns bool (not the model)

// saveNavigation() returns a boolean, so re-fetch to get the new id:
$navId = Navigate::$plugin->navigate->getNavigationByHandle('mainMenu', false)->id;
```

`title`, `handle`, `allowedSources` and `enabledSiteGroups` are required.

### Adding items (nodes)

A node's `type` is always **lowercase**: `url`, `element`, or `heading`. Required on every
node: `name`, `navId`, `siteId`, `type`. `save()` returns the saved `NodeModel` (with `id`)
or `false`.

```php
use studioespresso\navigate\models\NodeModel;

// A URL item
$node = new NodeModel();
$node->navId  = $navId;
$node->siteId = Craft::$app->sites->getPrimarySite()->id;
$node->name   = 'Home';
$node->type   = 'url';
$node->url    = '/';
Navigate::$plugin->nodes->save($node);

// An element item (entry / asset / category) — url resolves from the element
$node = new NodeModel();
$node->navId       = $navId;
$node->siteId      = $siteId;
$node->name        = 'About us';
$node->type        = 'element';
$node->elementType = 'entry';   // 'entry' | 'asset' | 'category'
$node->elementId   = 42;
Navigate::$plugin->nodes->save($node);

// A heading (grouping label; needs levels > 1 to be useful)
$node = new NodeModel();
$node->navId  = $navId;
$node->siteId = $siteId;
$node->name   = 'Products';
$node->type   = 'heading';
Navigate::$plugin->nodes->save($node);
```

Optional node attributes: `blank` (open in new tab), `classes` (CSS classes), `enabled`
(default `true`), and `parent` (a parent node id — nest items by setting it; the parent
navigation's `levels` must be `> 1`). New nodes get their `order` assigned automatically.

### Node types reference

| `type`    | Extra attributes                 | Notes                                             |
| --------- | -------------------------------- | ------------------------------------------------- |
| `url`     | `url`                            | Literal/relative URL; supports env & object tags  |
| `element` | `elementType`, `elementId`       | `elementType` ∈ `entry`, `asset`, `category`      |
| `heading` | —                                | Non-link label; only meaningful when `levels > 1` |

> **Known issue — always use lowercase types.** Node types are stored and rendered in
> lowercase (`url`, `element`, `heading`; see `NodesService::parseNode()` and
> `NodeModel::active()`). The CP "add/edit item" slide-out
> (`NodesController::actionAddSlideOut()` / `actionEditSlideOut()`) compares against
> capitalized `'Url'` / `'Heading'`, so those branches don't fire and a node's `url`/`classes`
> may not be set through that path. The console commands and service API above use the correct
> lowercase values — prefer them, and use lowercase if you ever set `type` yourself.

### Reading in PHP

```php
$tree  = Navigate::$plugin->nodes->getNodesForRender('mainMenu', $siteId); // parsed for output
$nodes = Navigate::$plugin->nodes->getNodesByNavIdAndSiteById($navId, $siteId); // raw models
```

## Configuration

You can create a file called ``navigate.php`` in the ``config`` directory (you can copy [this one](https://github.com/studioespresso/craft3-navigate/blob/master/src/config.php) to start) to manage these settings in your code. The ones listed here are not available through the CP.

### Disabling caching
Out of the box, the plugin will cache the querries it makes when ``devMode`` is not enabled. 

If you want to disable caching within the plugin entirely, you can do so by setting ``disableCaching`` to ``true`` in the plugin's configuration file. (see example [here](https://github.com/studioespresso/craft3-navigate/blob/master/src/config.php))

###  CSS class option list

Instead of the "Classes" field being a plain text field, you can also change it to a predefined dropdown with class that you want to make available for the user.
The ``nodeClasses`` setting takes an array where that contains "Class to be applied" => "Label to be shown in the cp".


````php
"nodeClasses" => [
    '' => '---',
    'nav nav-primary' => "Primary",
    'nav nav-highlight' => 'Highlight'
]
````

Make sure to also include an empty option in case you don't want the the first item to be selected by default

---
Brought to you by [Studio Espresso](https://www.studioespresso.co/)
