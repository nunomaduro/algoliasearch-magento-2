<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Indexer\Category as CategoryIndexer;
use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Indexer\IndexerRegistry;

class CategoryObserver
{
    /** @var CategoryIndexer */
    private $indexer;

    /** @var ConfigHelper */
    private $configHelper;

    /**
     * @param IndexerRegistry $indexerRegistry
     * @param ConfigHelper $configHelper
     */
    public function __construct(IndexerRegistry $indexerRegistry, ConfigHelper $configHelper)
    {
        $this->indexer = $indexerRegistry->get('algolia_categories');
        $this->configHelper = $configHelper;
    }

    /**
     * @param CategoryResourceModel $categoryResource
     * @param CategoryResourceModel $result
     * @param CategoryModel $category
     *
     * @return CategoryResourceModel
     */
    public function afterSave(
        CategoryResourceModel $categoryResource,
        CategoryResourceModel $result,
        CategoryModel $category
    ) {
        $categoryResource->addCommitCallback(function () use ($category) {
            if (!$this->indexer->isScheduled() || $this->configHelper->isQueueActive()) {
                /** @var ProductCollection $productCollection */
                $productCollection = $category->getProductCollection();
                CategoryIndexer::$affectedProductIds = (array) $productCollection->getColumnValues('entity_id');

                $this->indexer->reindexRow($category->getId());
            }
        });

        return $result;
    }

    /**
     * @param CategoryResourceModel $categoryResource
     * @param CategoryResourceModel $result
     * @param CategoryModel $category
     *
     * @return CategoryResourceModel
     */
    public function afterDelete(
        CategoryResourceModel $categoryResource,
        CategoryResourceModel $result,
        CategoryModel $category
    ) {
        $categoryResource->addCommitCallback(function () use ($category) {
            if (!$this->indexer->isScheduled() || $this->configHelper->isQueueActive()) {
                /* we are using products position because getProductCollection() doesn't use correct store */
                $productCollection = $category->getProductsPosition();
                CategoryIndexer::$affectedProductIds = array_keys($productCollection);

                $this->indexer->reindexRow($category->getId());
            }
        });

        return $result;
    }
}
