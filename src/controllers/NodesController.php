<?php

namespace studioespresso\navigate\controllers;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Cp\Html\MenuHtml;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\FormResolver;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use studioespresso\navigate\controllers\concerns\NavigateScreens;
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NavigationRecord;
use studioespresso\navigate\records\NodeRecord;
use studioespresso\navigate\services\NodesService;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

class NodesController
{
    use NavigateScreens;
    use RespondsWithFlash;

    /** The structure editor of a navigation */
    public function structure(Request $request, int $navId): CpScreenResponse
    {
        $navigation = $this->navigation($navId);
        $site = $this->site($request, $navigation);
        $this->registerAssets();

        $crumbs = $this->crumbs();
        if (Sites::isMultiSite()) {
            $crumbs[] = new ActionItem()
                ->label(t($site->getName(), category: 'site'))
                ->items(app(MenuHtml::class)->siteMenuItems(Navigate::getInstance()->navigate->getEditableSites($navigation)->all(), $site));
        }

        return new CpScreenResponse()
            ->title(t($navigation->title, category: 'site'))
            ->crumbs($crumbs)
            ->contentTemplate('navigate/_edit/_content', [
                'navigation' => $navigation,
                'site' => $site,
                'nodeTypes' => Navigate::getInstance()->nodes->getNodeTypes($navigation),
                'elementTypes' => NodesService::ELEMENT_TYPES,
                'tree' => $this->renderTree($navigation, $site),
            ])
            ->metaSidebarTemplate('navigate/_edit/_sidebar', [
                'navigation' => $navigation,
                'canEditSettings' => $this->canEditSettings($navigation),
            ])
            ->inertiaPage('cp/Screen');
    }

    /** The tree HTML, re-fetched by the structure editor after each change */
    public function tree(Request $request): Response
    {
        $navigation = $this->navigation($request->query('navId'));

        return response($this->renderTree($navigation, $this->site($request, $navigation, 'siteId')));
    }

    /** The form to add a URL or heading node, or to edit any node; opened in a slideout by the structure editor */
    public function edit(Request $request): CpScreenResponse
    {
        $node = $request->filled('nodeId')
            ? NodeRecord::query()->findOrFail($request->integer('nodeId'))
            : new NodeRecord([
                'navId' => $request->integer('navId'),
                'siteId' => $request->integer('siteId'),
                'type' => $request->query('type'),
                'enabled' => true,
            ]);

        $navigation = $this->navigation($node->navId);
        $this->authorizeSite($navigation, $node->siteId);
        abort_unless($node->exists || in_array($node->type, ['url', 'heading'], true), 400);

        $classes = Navigate::getInstance()->getSettings()->nodeClasses;
        $classesControl = $classes
            ? Choice::make('classes')->options(collect($classes)->map(fn ($label, $value) => ['label' => $label, 'value' => (string) $value])->values()->all())
            : Text::make('classes');

        $form = Form::make(array_values(array_filter([
            HiddenField::make('nodeId'),
            HiddenField::make('navId'),
            HiddenField::make('siteId'),
            HiddenField::make('type'),
            Field::make(t('Name', category: 'navigate'), Text::make('name'))->required(),
            $node->type === 'url'
                ? Field::make(t('URL', category: 'navigate'), Text::make('url'))->required()
                    ->instructions(t('Can contain environment variables and `{config}` tokens.', category: 'navigate'))
                : null,
            Field::make(t('Enabled', category: 'navigate'), Lightswitch::make('enabled'))
                ->instructions(t('Show or hide in navigation.', category: 'navigate')),
            $node->type !== 'heading'
                ? Field::make(t('Open in a new window', category: 'navigate'), Lightswitch::make('blank'))
                : null,
            Field::make(t($classes ? 'Style' : 'Classes', category: 'navigate'), $classesControl)
                ->instructions(t("These css classes will be applied to this node's list item", category: 'navigate')),
        ])));

        $payload = app(FormResolver::class)->resolve($form, new FormContext(values: [
            'nodeId' => $node->id,
            'navId' => $node->navId,
            'siteId' => $node->siteId,
            'type' => $node->type,
            'name' => $node->name,
            'url' => $node->url,
            'enabled' => $node->enabled,
            'blank' => $node->blank,
            'classes' => $node->classes,
        ]));

        return new CpScreenResponse()
            ->title($node->exists ? t('Edit “{name}”', ['name' => $node->name], 'navigate') : t('Add new navigation item', category: 'navigate'))
            ->crumbs($this->crumbs())
            ->inertiaPage('Form', [
                'form' => $payload,
                'submit' => [
                    'method' => 'post',
                    'url' => $request->fullUrl(),
                ],
            ]);
    }

    /** Saves the node form */
    public function save(Request $request): Response
    {
        $node = $request->filled('nodeId')
            ? NodeRecord::query()->findOrFail($request->integer('nodeId'))
            : new NodeRecord(['navId' => $request->integer('navId'), 'siteId' => $request->integer('siteId')]);

        $navigation = $this->navigation($node->navId);
        $this->authorizeSite($navigation, $node->siteId);

        $type = $node->exists ? $node->type : $request->input('type');
        $values = $request->validate([
            // Only new nodes get a type from the request; element nodes are added through addElements()
            'type' => $node->exists ? [] : ['required', Rule::in(['url', 'heading'])],
            'name' => ['required', 'string', 'max:255'],
            'url' => [$type === 'url' ? 'required' : 'nullable', 'string', 'max:255'],
            'classes' => ['nullable', 'string', 'max:255'],
        ]);

        $node->fill([
            'type' => $type,
            'name' => $values['name'],
            'url' => $type === 'url' ? $values['url'] : $node->url,
            'classes' => $values['classes'] ?? null,
            'enabled' => $request->boolean('enabled'),
            'blank' => $type !== 'heading' && $request->boolean('blank'),
        ]);
        Navigate::getInstance()->nodes->save($node);

        return $this->asModelSuccess($node, t('Node saved.', category: 'navigate'), 'node');
    }

    /** Adds the elements picked in the element selector modal */
    public function addElements(Request $request): Response
    {
        $values = $request->validate([
            'navId' => ['required', 'integer'],
            'siteId' => ['required', 'integer'],
            'elementType' => ['required', Rule::in(array_keys(NodesService::ELEMENT_TYPES))],
            'elements' => ['required', 'array'],
            'elements.*.id' => ['required', 'integer'],
            'elements.*.label' => ['nullable', 'string'],
            'elements.*.status' => ['nullable', 'string'],
        ]);

        $navigation = $this->navigation($values['navId']);
        $this->authorizeSite($navigation, $values['siteId']);
        abort_unless(in_array($values['elementType'], array_column(Navigate::getInstance()->nodes->getNodeTypes($navigation), 'handle'), true), 403);

        foreach ($values['elements'] as $element) {
            Navigate::getInstance()->nodes->save(new NodeRecord([
                'navId' => $navigation->id,
                'siteId' => $values['siteId'],
                'type' => 'element',
                'elementType' => $values['elementType'],
                'elementId' => $element['id'],
                'name' => $element['label'] ?? '',
                'enabled' => ($element['status'] ?? 'live') !== 'disabled',
                'blank' => false,
            ]));
        }

        return $this->asSuccess(t('Node added.', category: 'navigate'));
    }

    public function delete(Request $request): Response
    {
        $node = NodeRecord::query()->findOrFail($request->integer('nodeId'));
        $this->authorizeSite($this->navigation($node->navId), $node->siteId);
        Navigate::getInstance()->nodes->delete($node);

        return $this->asSuccess(t('Node removed', category: 'navigate'));
    }

    /** Saves the order and nesting after a node was moved */
    public function saveTree(Request $request): Response
    {
        $values = $request->validate([
            'navId' => ['required', 'integer'],
            'siteId' => ['required', 'integer'],
            'tree' => ['present', 'array'],
        ]);

        $navigation = $this->navigation($values['navId']);
        $this->authorizeSite($navigation, $values['siteId']);

        try {
            Navigate::getInstance()->nodes->saveTree($navigation, $values['siteId'], $values['tree']);
        } catch (InvalidArgumentException $e) {
            return $this->asFailure($e->getMessage());
        }

        return $this->asSuccess(t('Order updated', category: 'navigate'));
    }

    private function authorizeSite(NavigationRecord $navigation, int $siteId): void
    {
        abort_unless(Navigate::getInstance()->navigate->getEditableSites($navigation)->contains('id', $siteId), 403);
    }

    private function renderTree(NavigationRecord $navigation, Site $site): string
    {
        $tree = Navigate::getInstance()->nodes->getTree($navigation->id, $site->id);

        return template('navigate/_edit/_tree', [
            'tree' => $tree,
            'elements' => $this->elementsForTree($tree, $site),
            'navigation' => $navigation,
        ]);
    }

    /**
     * The elements the element nodes point to, keyed by "{type}:{id}", fetched in one query per type.
     *
     * @param  Collection<int, Collection<int, NodeRecord>>  $tree
     */
    private function elementsForTree(Collection $tree, Site $site): array
    {
        $elements = [];
        $byType = $tree->flatten()->where('type', 'element')->groupBy('elementType');

        foreach ($byType as $type => $nodes) {
            $class = NodesService::ELEMENT_TYPES[$type] ?? null;
            if (! $class) {
                continue;
            }
            foreach ($class::find()->id($nodes->pluck('elementId')->all())->siteId($site->id)->status(null)->get() as $element) {
                $elements["$type:$element->id"] = [
                    'cpEditUrl' => $element->getCpEditUrl(),
                    'path' => ($url = $element->getUrl()) ? parse_url($url, PHP_URL_PATH) : null,
                ];
            }
        }

        return $elements;
    }
}
