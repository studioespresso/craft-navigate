# Navigate for Craft CMS 3.x

![Screenshot](resources/banner.png)

## Requirements

This plugin requires Craft CMS 3.0.0 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require studioespresso/craft-navigate

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Navigate.

## Navigate Overview

![Screenshot](resources/screenshots/navigate-nodes.png)

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
	{{ node.target }}
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

### craft.navigate.render

The `render` function gives you the option to let the plugin build the HTML for you. You can add the following options:

```twig
{{ craft.navigate.render('navHandle', {
    wrapperClass : 'navbar',
    ulClass: 'navbar-nav',
    listClass: 'nav-item',
    linkClass: 'nav-link'
}) }}
```

This will return HTML, with the classes you specified, based on how [Bootstrap](http://getbootstrap.com/docs/4.1/components/navbar/) does navigations


Brought to you by [Studio Espresso](https://studioespresso.co)
