<?php
/**
 * Navigate plugin for Craft CMS 3.x
 *
 * Navigation plugin for Craft 3
 *
 * @link      https://studioespresso.dev
 * @copyright Copyright (c) 2018 Studio Espresso
 */

namespace studioespresso\navigate\controllers;

use Craft;
use craft\web\Controller;
use studioespresso\navigate\models\NodeModel;
use studioespresso\navigate\Navigate;
use yii\web\NotFoundHttpException;

/**
 * Default Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Studio Espresso
 * @package   Navigate
 * @since     0.0.1
 */
class NodesController extends Controller
{
    public function actionAdd()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();


        if (!Craft::$app->request->getRequiredBodyParam('navId')) {
            throw new NotFoundHttpException('Navigation not found', 404);
        }

        $attributes = Craft::$app->request->getBodyParams();

        $model = new NodeModel();

        if ($attributes['type'] === 'element') {
            $model->setAttributes([
                'type' => $attributes['type'],
                'elementType' => $attributes['elementType'],
                'elementId' => $attributes['elementId'],
            ]);
        }

        if ($attributes['type'] === 'url') {
            $model->setAttributes([
                'type' => $attributes['type'],
                'url' => $attributes['url'],
                'classes' => $attributes['classes'],
            ]);
        }
        if ($attributes['type'] === 'heading') {
            $model->setAttributes([
                'type' => $attributes['type'],
                'classes' => $attributes['classes'],
            ]);
        }

        $model->setAttributes([
            'siteId' => $attributes['siteId'],
            'navId' => $attributes['navId'],
            'parent' => isset($attributes['parent']) ? $attributes['parent'] : null,
            'name' => $attributes['name'],
            'blank' => isset($attributes['blank']) ? $attributes['blank'] == 'true' : false,
            'enabled' => true,
        ]);

        if (!$model->validate()) {
            $returnData['success'] = false;
            $returnData['message'] = Craft::t('navigate', 'Oops, something went wrong here');
            return $this->asJson($returnData);
        }

        $node = Navigate::$plugin->nodes->save($model);
        if ($node !== false) {
            // Return data
            $returnData['success'] = true;
            $returnData['message'] = Craft::t('navigate', 'Node added.');
            $returnData['nodeData'] = $node;

            return $this->asJson($returnData);
        }
    }

    public function actionDelete()
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $nodeId = Craft::$app->request->getRequiredBodyParam('nodeId');
        $node = Navigate::$plugin->nodes->getNodeById($nodeId);

        if (Navigate::$plugin->nodes->deleteNode($node)) {
            // Return data
            $returnData['success'] = true;
            $returnData['message'] = Craft::t('navigate', 'Node removed');
            $returnData['nodeData'] = $node;
        }

        return $this->asJson($returnData);
    }

    public function actionUpdate()
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $nodeId = Craft::$app->request->getRequiredBodyParam('nodeId');
        $node = Navigate::$plugin->nodes->getNodeById($nodeId);

        if (!$node) {
            throw new NotFoundHttpException('Node not found', 404);
        }

        $data = Craft::$app->request->getBodyParams();

        if ($node->type === 'url') {
            $node->setAttributes([
                'url' => $data['url'],
            ]);
        }

        $node->setAttributes([
            'name' => $data['name'],
            'enabled' => $data['enabled'],
            'blank' => $data['blank'] ?? '',
            'classes' => $data['classes'] ?? '',

        ]);

        $payload = array('success' => false);

        $save = Navigate::$plugin->nodes->save($node);
        if ($save) {
            $payload['success'] = true;
            $payload['message'] = Craft::t('navigate', 'Node saved.');
            $payload['nodeData'] = $node;
        }

        return $this->asJson($payload);
    }

    public function actionMove()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $nodeId = Craft::$app->request->getRequiredBodyParam('nodeId');

        // Get node
        $node = Navigate::$plugin->nodes->getNodeById($nodeId);
        if (!$node) {
            throw new NotFoundHttpException('Node not found', 404);
        }

        $prevId = Craft::$app->request->getBodyParam('prevId', false);
        $parentId = Craft::$app->request->getBodyParam('parentId', null);
        // Move it move it!
        $moved = Navigate::$plugin->nodes->move($node, $parentId, $prevId);
        if ($moved) {
            // Return data
            $returnData['success'] = true;
            $returnData['message'] = Craft::t('navigate', 'Order updated');
            $returnData['nodeData'] = $node;

            return $this->asJson($returnData);
        }
    }

    public function actionEditor()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $nodeId = Craft::$app->request->getRequiredBodyParam('nodeId');

        // Get node
        $node = Navigate::$plugin->nodes->getNodeById($nodeId);
        if (!$node) {
            throw new NotFoundHttpException('Node not foud', 404);
        }

        $payload['html'] = Craft::$app->view->renderTemplate('navigate/_includes/_editor', ['node' => $node]);

        return $this->asJson($payload);
    }

    public function actionUrl($id)
    {
        $node = Navigate::$plugin->nodes->getNodeById($id);
        $url = Navigate::$plugin->nodes->getNodeUrl($node);
        header('Location: ' . $url, true, 200);
        exit;
    }

    public function actionAddSlideOut()
    {

        // The slideout submits back to this action
        // so check if it's a post and respond with a success
        if ($this->request->isPost) {
            if (!Craft::$app->request->getRequiredBodyParam('fields[navId]')) {
                throw new NotFoundHttpException('Navigation not found', 404);
            }

            $data = Craft::$app->request->getBodyParams();
            $node = new NodeModel();

            if ($data['fields']['type'] === 'Url') {
                $node->setAttributes([
                    'type' => $data['fields']['type'],
                    'url' => $data['fields']['url'],
                    'classes' => $data['fields']['classes'],
                ]);
            }
            if ($data['fields']['type'] === 'Heading') {
                $node->setAttributes([
                    'type' => $data['fields']['type'],
                    'classes' => $data['fields']['classes'],
                ]);
            }

            $node->setAttributes([
                'siteId' => $data['fields']['siteId'],
                'navId' => $data['fields']['navId'],
                'parent' => isset($data['fields']['parent']) ? $data['fields']['parent'] : null,
                'name' => $data['fields']['name'],
                'blank' => isset($data['fields']['blank']) ? $data['fields']['blank'] == 'true' : false,
                'enabled' => true,
            ]);

            if (!$node->validate()) {
                dd($node->getErrors());
                return $this->asFailure(Craft::t('navigate', 'Oops, something went wrong here'), $node->toArray());
            }

            $node = Navigate::$plugin->nodes->save($node);
            if ($node !== false) {
                return $this->asSuccess(Craft::t('navigate', 'Node added.'), $node->toArray());
            }
        }

        $type = Craft::$app->getRequest()->getRequiredQueryParam('type');
        $siteId = Craft::$app->getRequest()->getRequiredQueryParam('site');
        $site = Craft::$app->getSites()->getSiteById($siteId);

        $navId = Craft::$app->getRequest()->getRequiredQueryParam('nav');
        $navigation = Navigate::getInstance()->navigate->getNavigationById($navId);

        $node = new NodeModel();
        $node->type = $type;
        return $this->asCpScreen()
            ->action('navigate/nodes/add-slide-out')
            ->title('Add new navigation item')
            // Render the content template
            ->contentTemplate(
                'navigate/_slidedout.twig', [
                    'node' => $node,
                    'navigation' => $navigation,
                    'site' => $site,
                ]
            );
    }

    public function actionEditSlideOut()
    {
        if ($this->request->isPost) {
            $nodeId = Craft::$app->request->getRequiredBodyParam('nodeId');
            $node = Navigate::$plugin->nodes->getNodeById($nodeId);

            if (!$node) {
                throw new NotFoundHttpException('Node not found', 404);
            }

            $data = Craft::$app->request->getBodyParams();

            if ($node->type === 'url') {
                $node->setAttributes([
                    'url' => $data['fields']['url'],
                ]);
            }

            $node->setAttributes([
                'name' => $data['fields']['name'],
                'enabled' => $data['fields']['enabled'],
                'blank' => $data['fields']['blank'] ?? '',
                'classes' => $data['fields']['classes'] ?? '',

            ]);
            Navigate::$plugin->nodes->save($node);
            return $this->asSuccess(Craft::t('navigate', 'Node saved.'), $node->toArray());
        }

        $nodeId = Craft::$app->getRequest()->getRequiredQueryParam('node');
        $nodeModel = Navigate::getInstance()->nodes->getNodeById($nodeId);

        return $this->asCpScreen()
            ->action('navigate/nodes/edit-slide-out')
            ->title('Slideout Content')
            // Render the content template
            ->contentTemplate(
                'navigate/_slidedout.twig', ['node' => $nodeModel]
            );
    }
}
