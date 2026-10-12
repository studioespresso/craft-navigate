<?php

namespace studioespresso\navigate\controllers;

use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Handle;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Number;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\FormResolver;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\SiteGroups;
use CraftCms\Cms\Support\Flash;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Validation\Rules\HandleRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use studioespresso\navigate\controllers\concerns\NavigateScreens;
use studioespresso\navigate\Navigate;
use studioespresso\navigate\records\NavigationRecord;
use studioespresso\navigate\services\NodesService;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

class NavigationsController
{
    use NavigateScreens;
    use RespondsWithFlash;

    public function index(): CpScreenResponse
    {
        $this->registerAssets();
        $canManage = $this->canManageNavigations();

        $navigations = Navigate::getInstance()->navigate->getAllNavigationForUser()->map(fn (NavigationRecord $nav) => [
            'id' => $nav->id,
            'title' => t($nav->title, category: 'site'),
            'handle' => $nav->handle,
            'canEditSettings' => $this->canEditSettings($nav),
        ]);

        return new CpScreenResponse()
            ->title(Navigate::getInstance()->getSettings()->pluginLabel ?: 'Navigate')
            ->toolbarTemplate('navigate/_index/_toolbar', ['canManage' => $canManage])
            ->contentTemplate('navigate/_index/_content', ['navigations' => $navigations, 'canManage' => $canManage])
            ->inertiaPage('cp/Screen');
    }

    public function edit(?int $navId = null): CpScreenResponse
    {
        $navigation = $navId ? $this->navigation($navId) : null;
        abort_unless($navigation ? $this->canEditSettings($navigation) : $this->canManageNavigations(), 403);

        $title = $navigation ? t($navigation->title, category: 'site') : t('New navigation', category: 'navigate');
        $handle = Handle::make('handle')->maxLength(64);
        if (! $navigation) {
            $handle->source('title');
        }

        $form = Form::make([
            Field::make(t('Title', category: 'navigate'), Text::make('title'))
                ->instructions(t('What this navigation will be called in the CP.', category: 'navigate'))
                ->required(),
            Field::make(t('Handle', category: 'navigate'), $handle)
                ->instructions(t('How you’ll refer to this navigation in the templates.', category: 'navigate'))
                ->required(),
            Field::make(t('Enable this navigation for these site groups', category: 'navigate'), Choice::make('enabledSiteGroups')
                ->multiple()
                ->allOption()
                ->options(SiteGroups::getAllGroups()->map(fn ($group) => ['label' => t($group->name, category: 'site'), 'value' => (string) $group->id])->values()->all())),
            Field::make(t('Available Sources', category: 'navigate'), Choice::make('allowedSources')
                ->multiple()
                ->allOption()
                ->options(collect(NodesService::TYPES)->map(fn (string $label, string $handle) => ['label' => t($label, category: 'navigate'), 'value' => $handle])->values()->all()))
                ->instructions(t('Select the types of links would you like to be available in this navigation', category: 'navigate')),
            Field::make(t('Maximum levels', category: 'navigate'), Number::make('levels')->min(1)->max(5)->size(3))
                ->instructions(t('Maximum levels of sub navigations, defaults to 1 if empty', category: 'navigate'))
                ->required(),
            Field::make(t('Restrict access to settings to admin users only', category: 'navigate'), Lightswitch::make('adminOnly')),
        ]);

        return new CpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbs())
            ->inertiaPage('Form', [
                'form' => app(FormResolver::class)->resolve($form, new FormContext(values: [
                    'title' => $navigation?->title,
                    'handle' => $navigation?->handle,
                    'enabledSiteGroups' => $this->listValue($navigation?->enabledSiteGroups),
                    'allowedSources' => $this->listValue($navigation?->allowedSources),
                    'levels' => $navigation->levels ?? 1,
                    'adminOnly' => $navigation->adminOnly ?? true,
                ])),
                'submit' => [
                    'method' => 'post',
                    'url' => Url::cpUrl($navigation ? "navigate/settings/{$navigation->id}" : 'navigate/new'),
                ],
            ]);
    }

    public function store(Request $request, ?int $navId = null): Response
    {
        $navigation = $navId ? $this->navigation($navId) : new NavigationRecord;
        abort_unless($navId ? $this->canEditSettings($navigation) : $this->canManageNavigations(), 403);

        $values = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'handle' => [
                'required', 'string', 'max:64', new HandleRule,
                Rule::unique(NavigationRecord::class, 'handle')->ignore($navigation->id),
            ],
            'enabledSiteGroups' => ['nullable'],
            'allowedSources' => ['nullable'],
            'levels' => ['required', 'integer', 'min:1', 'max:5'],
            'adminOnly' => ['boolean'],
        ]);

        $navigation->fill([
            'title' => $values['title'],
            'handle' => $values['handle'],
            'levels' => (int) $values['levels'],
            'adminOnly' => $request->boolean('adminOnly'),
            'enabledSiteGroups' => $this->storedList($values['enabledSiteGroups'] ?? null),
            'allowedSources' => $this->storedList($values['allowedSources'] ?? null),
        ]);
        Navigate::getInstance()->navigate->saveNavigation($navigation);

        // ponytail: full page load instead of an Inertia visit; Craft's screen store (alpha.19) keeps the Form
        // page's Save footer on the cp/Screen index otherwise. Back to asSuccess(redirect:) once core resets it.
        Flash::success(t('“{title}” saved', ['title' => $navigation->title], 'navigate'));

        return Inertia::location(Url::cpUrl('navigate'));
    }

    public function delete(int $navId): Response
    {
        abort_unless($this->canManageNavigations(), 403);
        $navigation = $this->navigation($navId);
        Navigate::getInstance()->navigate->deleteNavigation($navigation);

        return $this->asSuccess(t('“{title}” deleted', ['title' => $navigation->title], 'navigate'), redirect: Url::cpUrl('navigate'));
    }

    /** Stored `*` or JSON list → the Choice control's value */
    private function listValue(?string $stored): string|array
    {
        if ($stored === null || $stored === '' || $stored === '*') {
            return Choice::ALL_VALUE;
        }

        return array_map('strval', json_decode($stored, true) ?: []);
    }

    /** The Choice control's value → stored `*` or JSON list */
    private function storedList(mixed $value): string
    {
        if ($value === null || $value === Choice::ALL_VALUE || $value === [Choice::ALL_VALUE]) {
            return '*';
        }

        return json_encode(array_values((array) $value));
    }
}
