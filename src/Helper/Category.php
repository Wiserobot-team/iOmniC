<?php
/**
* WISEROBOT INDUSTRIES SDN. BHD. **NOTICE OF LICENSE**
* This source file is subject to the EULA that is bundled with this package in the file LICENSE.pdf. It is also available through the world-wide-web at this URL:
* http://wiserobot.com/mage_extension_license.pdf
* =================================================================
* MAGENTO COMMUNITY EDITION USAGE NOTICE
* =================================================================
* This package is designed for the Magento COMMUNITY edition
* This extension may not work on any other Magento edition except Magento COMMUNITY edition. WiseRobot does not provide extension support in case of incorrect edition usage.
* =================================================================
* Copyright (c) 2019 WISEROBOT INDUSTRIES SDN. BHD. (http://www.wiserobot.com)
* License http://wiserobot.com/mage_extension_license.pdf
*
*/
namespace Wiserobot\Io\Helper;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\CategoryFactory;

class Category extends \Magento\Framework\App\Helper\AbstractHelper
{
    public $pathIndex    = [];
    public $categoryData = [];
    public $logModel     = null;

    public function __construct(
        StoreManagerInterface  $storeManager,
        CategoryFactory        $categoryFactory
    ) {
        $this->storeManager    = $storeManager;
        $this->categoryFactory = $categoryFactory;
    }

    /**
     * split category tree and to path and process path to create categories
     * @param  string $tree                 string as category tree "a\a1:a\a1\a2:a\ab"
     * @param  int $storeId                 id of magento store
     * @param  bool $allowCreateCat         allow create new category or not
     * @return array                        array of category id found/created
     */
    public function processCategoryTree($tree, $storeId, $allowCreateCat)
    {
        // paths is splited by :: or :
        $paths  = preg_split("/(::|:|,)/", $tree);
        $result = [];
        foreach ($paths as $path) {
            $path = trim($path);
            if (!$path) {
                continue;
            }
            $catIdsForPath = $this->processCategoryPath($path, $storeId, $allowCreateCat);
            $result = array_merge($result, $catIdsForPath);
        }

        return $result;
    }

    /**
     * create and return category id from one path string
     * @param  string   $categoryPath   a category path like catA > catA1 > catA12
     * @param  int      $storeId        id of magento store object
     * @param  boolean  $allowCreate    allow create new category or not
     * @return array    ids of categories found/created
     */
    public function processCategoryPath($path, $storeId, $allowCreate = false)
    {
        // name is splited by / or > or \
        $categoryLevels = preg_split("/(\/|>|\\\)/", $path);
        $store          = $this->storeManager->getStore()->load($storeId);
        $rootCatId      = $store->getRootCategoryId();
        $rootCat        = $this->categoryFactory->create()->load($rootCatId);
        $parentId       = $rootCatId;
        $resultCatIds   = [];
        foreach ($categoryLevels as $levelName) {
            $levelName  = trim($levelName);
            if (!$levelName || in_array($levelName, [$rootCat->getName()])) {
                continue;
            }
            $newLevelId = $this->createOrSearchCategory($levelName, $parentId, $allowCreate);
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
     * create a category
     * @param  string       $nameToCreate       name of category to create
     * @param  int          $parentId           id of parent category
     * @param  bolean       $allowCreate        allow to create new category
     * @return int                              id of created category
     */
    public function createOrSearchCategory($nameToCreate, $parentId, $allowCreate = false)
    {
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
                $this->logModel->results["response"]["category"]["success"][] = "created new category " . $parentCategory->getName() . " > " . $nameToCreate;
                $message = "Created new category " . $parentCategory->getName() . " > " . $nameToCreate;
                $this->log($message);
                $this->logModel->cleanResponseMessages();
                return $newCategory->getId();
            } catch (\Exception $e) {
                $this->logModel->results["response"]["category"]["error"][] = "create category " . $parentCategory->getName() . " > " . $nameToCreate . ": " . $e->getMessage();
                $message = "ERROR create category " . $parentCategory->getName() . " > " . $nameToCreate . ": " . $e->getMessage();
                $this->log($message);
                $this->logModel->cleanResponseMessages();
                return false;
            }
        } else {
            return $existingId;
        }
    }

    /**
     * search a category id
     * @param  string   $categoryName         category name to search
     * @param  mix      $parentId             id of parent if needed
     * @return mix                            id of found category or false if not found
     */
    public function searchCategoryId($categoryName, $parentId = null)
    {
        $foundId    = false;
        $collection = $this->categoryFactory->create()
                        ->getCollection()
                        ->addFieldToFilter("name", $categoryName);
        if ($parentId) {
            $collection->addFieldToFilter("parent_id", $parentId);
        }

        $foundIds    = $collection->getAllIds();
        if (count($foundIds)) {
            $foundId =  array_shift($foundIds);
        }

        return $foundId;
    }

    /**
     * get category names (coma sep if multiple) at a level (1 is under root and so on)
     * @param  \Magento\Catalog\Model\Product    $product Magento product oject
     * @param  \Magento\Store\Model\Store       $store Magento store oject
     * @param  int                              $level the level number
     * @return string                           category name at level
     */
    public function getCategoryAtLevel($product, $store, $level)
    {
        $categoryIds = $product->getCategoryIds();
        $levels      = [];
        $paths       = [];
        foreach ($categoryIds as $categoryId) {
            $categoryData = $this->getCategoryData($categoryId, $store->getId());
            $path         = $categoryData["path"];
            if ($path) {
                $paths[] = $path;
            }
        }
        sort($paths);
        foreach ($paths as $path) {
            $count = 1;
            $items = explode("/", $path);
            // remove the first, root category id
            array_shift($items);
            foreach ($items as $catId) {
                if (!isset($levels[$count])) {
                    $levels[$count] = [];
                }
                $categoryData = $this->getCategoryData($catId, $store->getId());
                $categoryName = $categoryData["name"];
                if ($categoryName && !in_array($categoryName, $levels[$count])) {
                    $levels[$count][] = $categoryName;
                }
                $count++;
                if ($count > 5) {
                    break;
                }
            }
        }
        if (isset($levels[$level])) {
            return implode(",", $levels[$level]);
        } else {
            return false;
        }
    }

    /**
     * store category data to avoild load multiple time
     * @param  int $categoryId categoryId to load
     * @param  int $storeId    storeId to get category from
     * @return array           category data
     */
    public function getCategoryData($categoryId, $storeId)
    {
        $keyString = $categoryId . "-" . $storeId;
        if (!isset($this->categoryData[$keyString])) {
            $category = $this->categoryFactory->create()
                            ->setStoreId($storeId)
                            ->load($categoryId);
            $this->categoryData[$keyString] = [
                "name" => $category->getName(),
                "path" => $category->getPath()
            ];
        }

        return $this->categoryData[$keyString];
    }

    public function log($message)
    {
        if (!is_null($this->logModel)) {
            $this->logModel->log($message);
        }
    }
}
