<?php

namespace craft\commerce\controllers;

use craft\commerce\models\TaxCategory;
use Craft;
use craft\commerce\Plugin;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Tax Categories Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class TaxCategoriesController extends BaseAdminController
{
    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $taxCategories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategories();
        return $this->renderTemplate('commerce/settings/taxcategories/index', compact('taxCategories'));
    }

    /**
     * @param int|null         $id
     * @param TaxCategory|null $taxCategory
     *
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, TaxCategory $taxCategory = null): Response
    {
        $variables = [
            'id' => $id,
            'taxCategory' => $taxCategory
        ];

        if (!$variables['taxCategory']) {
            if ($variables['id']) {
                $variables['taxCategory'] = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($variables['id']);

                if (!$variables['taxCategory']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxCategory'] = new TaxCategory();
            };
        }

        if ($variables['taxCategory']->id) {
            $variables['title'] = $variables['taxCategory']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new tax category');
        }

        return $this->renderTemplate('commerce/settings/taxcategories/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $taxCategory = new TaxCategory();

        // Shared attributes
        $taxCategory->id = Craft::$app->getRequest()->getParam('taxCategoryId');
        $taxCategory->name = Craft::$app->getRequest()->getParam('name');
        $taxCategory->handle = Craft::$app->getRequest()->getParam('handle');
        $taxCategory->description = Craft::$app->getRequest()->getParam('description');
        $taxCategory->default = Craft::$app->getRequest()->getParam('default');

        // Save it
        if (Plugin::getInstance()->getTaxCategories()->saveTaxCategory($taxCategory)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'id' => $taxCategory->id,
                    'name' => $taxCategory->name,
                ]);
            } else {
                Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Tax category saved.'));
                $this->redirectToPostedUrl($taxCategory);
            }
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'errors' => $taxCategory->getErrors()
                ]);
            } else {
                Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save tax category.'));
            }
        }

        // Send the tax category back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'taxCategory' => $taxCategory
        ]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredParam('id');

        if (Plugin::getInstance()->getTaxCategories()->deleteTaxCategoryById($id)) {
            return $this->asJson(['success' => true]);
        } else {
            return $this->asErrorJson(Craft::t('commerce', 'Could not delete tax category'));
        }
    }

}
