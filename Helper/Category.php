<?php

/**
 * WISEROBOT INDUSTRIES SDN. BHD. **NOTICE OF LICENSE**
 * This source file is subject to the EULA that is bundled with this package in the file LICENSE.pdf
 * It is also available through the world-wide-web at this URL: http://wiserobot.com/mage_extension_license.pdf
 * =================================================================
 * This package is designed for all versions of Magento
 * =================================================================
 * Copyright (c) 2019 WISEROBOT INDUSTRIES SDN. BHD. (http://www.wiserobot.com)
 * License http://wiserobot.com/mage_extension_license.pdf
 */

declare(strict_types=1);

namespace WiseRobot\Io\Helper;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use WiseRobot\Io\Model\ProductManagement;

class Category extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;
    /**
     * @var CategoryInterfaceFactory
     */
    public $categoryFactory;
    /**
     * @var CategoryRepositoryInterface
     */
    public $categoryRepository;
    /**
     * @var CollectionFactory
     */
    public $categoryCollectionFactory;
    /**
     * @var ProductManagement|null
     */
    public ?ProductManagement $productManagement = null;
    /**
     * @var array
     */
    public array $categoryCache = [];

    /**
     * @param StoreManagerInterface $storeManager
     * @param CategoryInterfaceFactory $categoryFactory
     * @param CategoryRepositoryInterface $categoryRepository
     * @param CollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CategoryInterfaceFactory $categoryFactory,
        CategoryRepositoryInterface $categoryRepository,
        CollectionFactory $categoryCollectionFactory
    ) {
        $this->storeManager = $storeManager;
        $this->categoryFactory = $categoryFactory;
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Split category tree and to path and process path to create categories
     *
     * @param string $tree
     * @param int $storeId
     * @param bool $allowCreate
     * @return array
     */
    public function processCategoryTree(
        string $tree,
        int $storeId,
        bool $allowCreate
    ): array {
        // paths are split by :: or : or ,
        $paths = preg_split("/::|:|,\s*/", $tree, -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        foreach ($paths as $path) {
            $path = trim($path);
            if (!$path) {
                continue;
            }
            $result = array_merge($result, $this->processCategoryPath($path, $storeId, $allowCreate));
        }
        return array_unique($result);
    }

    /**
     * Create and return category id from one path string
     *
     * @param string $path
     * @param int $storeId
     * @param bool $allowCreate
     * @return array
     */
    public function processCategoryPath(
        string $path,
        int $storeId,
        bool $allowCreate = false
    ): array {
        // name is split by / or > or \
        $categoryLevels = preg_split("/\/|>|\\\\/", $path, -1, PREG_SPLIT_NO_EMPTY);
        try {
            $rootCatId = $this->storeManager->getStore($storeId)->getRootCategoryId();
            $rootCat = $this->categoryRepository->get($rootCatId, $storeId);
            $rootCatName = $rootCat->getName();
        } catch (NoSuchEntityException $e) {
            $message = "ERROR category: Cannot load store/root category for store ID " .
                $storeId . ": " . $e->getMessage();
            $this->handleResult($message, 'error');
            return [];
        } catch (\Exception $e) {
            $message = "ERROR category: Unhandled exception when loading root category for store ID " .
                $storeId . ": " . $e->getMessage();
            $this->handleResult($message, 'error');
            return [];
        }
        $parentId = (int) $rootCatId;
        $resultCatIds = [];
        foreach ($categoryLevels as $levelName) {
            $levelName = trim($levelName);
            if (!$levelName || $levelName === $rootCatName) {
                continue;
            }
            $newLevelId = $this->createOrSearchCategory($levelName, (int) $parentId, $allowCreate);
            if (!$newLevelId) {
                break;
            }
            $resultCatIds[] = $newLevelId;
            $parentId = $newLevelId;
        }
        return $resultCatIds;
    }

    /**
     * Create a category
     *
     * @param string $nameToCreate
     * @param int $parentId
     * @param bool $allowCreate
     * @return int|false
     */
    public function createOrSearchCategory(
        string $nameToCreate,
        int $parentId,
        bool $allowCreate = false
    ): int|false {
        $cacheKey = $parentId . '_' . strtolower($nameToCreate);
        if (isset($this->categoryCache[$cacheKey])) {
            return $this->categoryCache[$cacheKey];
        }
        $existingId = $this->searchCategoryId($nameToCreate, $parentId);
        if ($existingId) {
            $this->categoryCache[$cacheKey] = $existingId;
            return $existingId;
        }
        if ($allowCreate) {
            try {
                $parentCategory = $this->categoryRepository->get($parentId);
                $newCategory = $this->categoryFactory->create();
                $newCategory->setName($nameToCreate);
                $newCategory->setParentId($parentId);
                $newCategory->setIsActive(true);
                $newCategory->setStoreId(0);
                $newCategory->setIsAnchor(true);
                $savedCategory = $this->categoryRepository->save($newCategory);
                $newId = (int) $savedCategory->getId();
                $message = "Created new category " . $parentCategory->getName() . " > " . $nameToCreate;
                $this->handleResult($message, 'success');
                $this->categoryCache[$cacheKey] = $newId;
                return $newId;
            } catch (\Exception $e) {
                $message = "Failed to create category " . $parentCategory->getName() . " > " .
                    $nameToCreate . ": " . $e->getMessage();
                $this->handleResult($message, 'error');
                return false;
            }
        }
        return false;
    }

    /**
     * Search a category id
     *
     * @param string $categoryName
     * @param int $parentId
     * @return int|false
     */
    public function searchCategoryId(
        string $categoryName,
        int $parentId
    ): int|false {
        $collection = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect('entity_id')
            ->addFieldToFilter("name", $categoryName);
        if ($parentId) {
            $collection->addFieldToFilter("parent_id", $parentId);
        }
        $collection->setPageSize(1)->setCurPage(1);
        $item = $collection->getFirstItem();
        $foundId = (int) $item->getId();
        return $foundId > 0 ? $foundId : false;
    }

    /**
     * Handles logging and saving the result to ProductManagement
     *
     * @param string $message
     * @param string $type
     * @return void
     */
    public function handleResult(string $message, string $type): void
    {
        if ($this->productManagement !== null) {
            $this->productManagement->results["response"]["category"][$type][] = $message;
            $this->productManagement->cleanResponseMessages();
        }
        $this->log($message);
    }

    /**
     * Logs a message
     *
     * @param string $message
     * @return void
     */
    public function log(string $message): void
    {
        if ($this->productManagement !== null) {
            $this->productManagement->log($message);
        }
    }
}
