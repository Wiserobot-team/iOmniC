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

namespace WiseRobot\Io\Helper;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\CategoryFactory;

class Category extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var mixed
     */
    public $logModel = null;
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;
    /**
     * @var CategoryFactory
     */
    public $categoryFactory;

    /**
     * @param StoreManagerInterface $storeManager
     * @param CategoryFactory $categoryFactory
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CategoryFactory $categoryFactory
    ) {
        $this->storeManager = $storeManager;
        $this->categoryFactory = $categoryFactory;
    }

    /**
     * Split category tree and to path and process path to create categories
     *
     * @param string $tree
     * @param int $storeId
     * @param bool $allowCreateCat
     * @return array
     */
    public function processCategoryTree(
        $tree,
        $storeId,
        $allowCreateCat
    ) {
        // paths are split by :: or :
        $paths = preg_split("/(::|:|,)/", $tree);
        $result = [];
        foreach ($paths as $path) {
            $path = trim((string) $path);
            if (!$path) {
                continue;
            }
            $result[] = $this->processCategoryPath($path, $storeId, $allowCreateCat);
        }
        $result = array_merge([], ...$result);

        return $result;
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
        $path,
        $storeId,
        $allowCreate = false
    ) {
        // name is split by / or > or \
        $categoryLevels = preg_split("/(\/|>|\\\)/", (string) $path);
        $store = $this->storeManager->getStore()->load($storeId);
        $rootCatId = $store->getRootCategoryId();
        $rootCat = $this->categoryFactory->create()->load($rootCatId);
        $parentId = $rootCatId;
        $resultCatIds = [];
        foreach ($categoryLevels as $levelName) {
            $levelName  = trim((string) $levelName);
            if (!$levelName || in_array($levelName, [$rootCat->getName()])) {
                continue;
            }
            $newLevelId = $this->createOrSearchCategory($levelName, (int) $parentId, $allowCreate);
            if (!$newLevelId) {
                break;
            } else {
                $resultCatIds[] = $newLevelId;
            }
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
     * @return mixed
     */
    public function createOrSearchCategory(
        $nameToCreate,
        $parentId,
        $allowCreate = false
    ) {
        $existingId = $this->searchCategoryId($nameToCreate, $parentId);
        if (!$existingId && $allowCreate) {
            $newCategory = $this->categoryFactory->create();
            $newCategory->setName($nameToCreate);
            $newCategory->setIsActive(1);
            $newCategory->setStoreId(0);
            $newCategory->setIsAnchor(1);
            $newCategory->setParentId($parentId);
            $parentCategory = $this->categoryFactory->create()->load($parentId);
            $newCategory->setPath($parentCategory->getPath());
            try {
                $newCategory->save();
                $message = "Created new category " . $parentCategory->getName() . " > " . $nameToCreate;
                $this->logModel->results["response"]["category"]["success"][] = $message;
                $this->log($message);
                $this->logModel->cleanResponseMessages();
                return $newCategory->getId();
            } catch (\Exception $e) {
                $catName = $parentCategory->getName();
                $message = "Created category " . $catName . " > " . $nameToCreate . ": " . $e->getMessage();
                $this->logModel->results["response"]["category"]["error"][] = $message;
                $this->log("ERROR " . $message);
                $this->logModel->cleanResponseMessages();
                return false;
            }
        } else {
            return $existingId;
        }
    }

    /**
     * Search a category id
     *
     * @param string $categoryName
     * @param int $parentId
     * @return mixed
     */
    public function searchCategoryId(
        $categoryName,
        $parentId
    ) {
        $foundId = false;
        $collection = $this->categoryFactory->create()
            ->getCollection()
            ->addFieldToFilter("name", $categoryName);
        if ($parentId) {
            $collection->addFieldToFilter("parent_id", $parentId);
        }
        $foundIds = $collection->getAllIds();
        if (count($foundIds)) {
            $foundId = array_shift($foundIds);
        }

        return $foundId;
    }

    /**
     * Log message
     *
     * @param string $message
     * @return void
     */
    public function log(string $message)
    {
        if ($this->logModel !== null) {
            $this->logModel->log($message);
        }
    }
}
