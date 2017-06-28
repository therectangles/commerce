<?php

namespace craft\commerce\services;

use craft\commerce\models\TaxCategory;
use craft\commerce\records\TaxCategory as TaxCategoryRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

/**
 * Tax category service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 *
 * @property \craft\commerce\models\TaxCategory|null $defaultTaxCategory
 */
class TaxCategories extends Component
{
    /**
     * @var bool
     */
    private $_fetchedAllTaxCategories = false;

    /**
     * @var TaxCategory[]
     */
    private $_taxCategoriesById = [];

    /**
     * @var TaxCategory[]
     */
    private $_taxCategoriesByHandle = [];

    /**
     * @var TaxCategory
     */
    private $_defaultTaxCategory;

    /**
     * Returns all Tax Categories
     *
     * @return TaxCategory[]
     */
    public function getAllTaxCategories(): array
    {
        if (!$this->_fetchedAllTaxCategories) {
            $results = $this->_createTaxCategoryQuery()->all();

            foreach ($results as $result) {
                $taxCategory = new TaxCategory($result);
                $this->_memoizeTaxCategory($taxCategory);
            }

            $this->_fetchedAllTaxCategories = true;
        }

        return $this->_taxCategoriesById;
    }

    /**
     * @param int $taxCategoryId
     *
     * @return TaxCategory|null
     */
    public function getTaxCategoryById($taxCategoryId)
    {
        if ($this->_fetchedAllTaxCategories && isset($this->_taxCategoriesById[$taxCategoryId])) {
            return $this->_taxCategoriesById[$taxCategoryId];
        }

        $result = $this->_createTaxCategoryQuery()
            ->where(['id' => $taxCategoryId])
            ->one();

        if ($result) {
            $taxCategory = new TaxCategory($result);
            $this->_memoizeTaxCategory($taxCategory);

            return $this->_taxCategoriesById[$taxCategoryId];
        }

        return null;
    }

    /**
     * Memoize a tax category model by its ID and handle.
     *
     * @param TaxCategory $taxCategory
     *
     * @return void
     */
    private function _memoizeTaxCategory(TaxCategory $taxCategory)
    {
        $this->_taxCategoriesById[$taxCategory->id] = $taxCategory;
        $this->_taxCategoriesByHandle[$taxCategory->handle] = $taxCategory;
    }

    /**
     * @param int $taxCategoryHandle
     *
     * @return TaxCategory|null
     */
    public function getTaxCategoryByHandle($taxCategoryHandle)
    {
        if ($this->_fetchedAllTaxCategories && isset($this->_taxCategoriesByHandle[$taxCategoryHandle])) {
            return $this->_taxCategoriesByHandle[$taxCategoryHandle];
        }

        $result = $this->_createTaxCategoryQuery()
            ->where(['handle' => $taxCategoryHandle])
            ->one();

        if ($result) {
            $taxCategory = new TaxCategory($result);
            $this->_memoizeTaxCategory($taxCategory);

            return $this->_taxCategoriesByHandle[$taxCategoryHandle];
        }

        return null;
    }

    /**
     * Get the default tax category
     *
     * @return TaxCategory|null
     */
    public function getDefaultTaxCategory()
    {
        if (null === $this->_defaultTaxCategory) {
            $row = $this->_createTaxCategoryQuery()
                ->where(['default' => 1])
                ->one();
            
            if (!$row) {
                return null;
            }

            $this->_defaultTaxCategory = new TaxCategory($row);
        }

        return $this->_defaultTaxCategory;
    }

    /**
     * @param TaxCategory $model
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveTaxCategory(TaxCategory $model): bool
    {
        $oldHandle = null;

        if ($model->id) {
            $record = TaxCategoryRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No tax category exists with the ID “{id}”',
                    ['id' => $model->id]));
            }

            $oldHandle = $record->handle;
        } else {
            $record = new TaxCategoryRecord();
        }

        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->description = $model->description;
        $record->default = $model->default;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            // If this was the default make all others not the default.
            if ($model->default) {
                TaxCategoryRecord::updateAll(['default' => 0], ['id' => $record->id]);
            }

            // Update Service cache
            $this->_memoizeTaxCategory($model);

            if (null !== $oldHandle && $model->handle != $oldHandle) {
                unset($this->_taxCategoriesByHandle[$oldHandle]);
            }

            return true;
        }

        return false;
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public function deleteTaxCategoryById($id): bool
    {
        $all = $this->getAllTaxCategories();

        if (count($all) === 1) {
            return false;
        }

        $record = TaxCategoryRecord::findOne($id);

        if ($record) {
            return (bool)$record->delete();
        }

        return false;
    }

    /**
     * Returns a Query object prepped for retrieving tax categories.
     *
     * @return Query
     */
    private function _createTaxCategoryQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'description',
                'default'
            ])
            ->from(['{{%commerce_taxcategories}}']);
    }
}
