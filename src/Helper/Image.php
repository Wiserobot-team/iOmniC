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

use Magento\Catalog\Model\Category as ModelCategory;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\Catalog\Model\Product\Gallery\Processor as GalleryProcessor;
use Magento\Catalog\Model\ResourceModel\Product\Gallery as ProductGallery;
use Magento\Framework\Filesystem\Driver\File;
use WiseRobot\Io\Model\ProductImageFactory;

class Image extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var array
     */
    public $currentPlacements = [];
    /**
     * @var Filesystem
     */
    public $filesystem;
    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfig;
    /**
     * @var ProductFactory
     */
    public $productFactory;
    /**
     * @var GalleryReadHandler
     */
    public $galleryReadHandler;
    /**
     * @var GalleryProcessor
     */
    public $galleryProcessor;
    /**
     * @var ProductGallery
     */
    public $productGallery;
    /**
     * @var File
     */
    public $driverFile;
    /**
     * @var ProductImageFactory
     */
    public $productImageFactory;

    /**
     * @param Filesystem $filesystem
     * @param ScopeConfigInterface $scopeConfig
     * @param ProductFactory $productFactory
     * @param GalleryReadHandler $galleryReadHandler
     * @param GalleryProcessor $galleryProcessor
     * @param ProductGallery $productGallery
     * @param File $driverFile
     * @param ProductImageFactory $productImageFactory
     */
    public function __construct(
        Filesystem                 $filesystem,
        ScopeConfigInterface       $scopeConfig,
        ProductFactory             $productFactory,
        GalleryReadHandler         $galleryReadHandler,
        GalleryProcessor           $galleryProcessor,
        ProductGallery             $productGallery,
        File                       $driverFile,
        ProductImageFactory        $productImageFactory
    ) {
        $this->filesystem          = $filesystem;
        $this->scopeConfig         = $scopeConfig;
        $this->productFactory      = $productFactory;
        $this->galleryReadHandler  = $galleryReadHandler;
        $this->galleryProcessor    = $galleryProcessor;
        $this->productGallery      = $productGallery;
        $this->driverFile          = $driverFile;
        $this->productImageFactory = $productImageFactory;
    }

    /**
     * Populate image to product gallery
     *
     * @param Magento\Catalog\Model\ProductFactory $product
     * @param array $imagePlacementsToSet
     * @param WiseRobot\Io\Model\ProductImport $importModel
     * @return int
     */
    public function populateProductImage($product, $imagePlacementsToSet, $importModel)
    {
        $totalImagesAdded = 0;
        if (!$product || !$product->getId()) {
            return $totalImagesAdded;
        }
        // $product = $this->productFactory->create()->setStoreId(0)->load($product->getId())
        // $this->galleryReadHandler->execute($product);
        $sku       = $product->getSku();
        $productId = $product->getId();

        $this->currentPlacements = [
            [
                'name' => "ITEMIMAGEURL1",
                'image_placement_order' => 1
            ],
            [
                'name' => "ITEMIMAGEURL2",
                'image_placement_order' => 2
            ],
            [
                'name' => "ITEMIMAGEURL3",
                'image_placement_order' => 3
            ],
            [
                'name' => "ITEMIMAGEURL4",
                'image_placement_order' => 4
            ],
            [
                'name' => "ITEMIMAGEURL5",
                'image_placement_order' => 5
            ]
        ];

        $currentImagePlacements = $this->currentPlacements;

        $oldImages = $this->getProductImageImportUrl($product);

        // flag for product base image
        $flag = true;
        foreach ($currentImagePlacements as $currentImagePlacement) {
            $currentPlacementName = $currentImagePlacement["name"];
            $imgPos               = $currentImagePlacement["image_placement_order"];
            // if it has image to set
            if (isset($imagePlacementsToSet[$currentPlacementName])) {
                if (isset($oldImages[$currentPlacementName])
                    && $oldImages[$currentPlacementName]['image'] == $imagePlacementsToSet[$currentPlacementName]) {
                    $flag = false;

                    // if stored image url is same as current url then we do nothing
                    continue;
                }
                // if stored image url is NOT same as current url
                $imageUrl = $imagePlacementsToSet[$currentPlacementName];
                // remove old image from product
                $this->removeImage($product, $imgPos, $importModel);

                // add current url image to product
                $addedImageCount = $this->addImageToProductGallery($product, $imageUrl, $flag, $imgPos, $importModel);
                if ($addedImageCount) {
                    $totalImagesAdded = $totalImagesAdded + $addedImageCount;
                } else {
                    unset($imagePlacementsToSet[$currentPlacementName]);
                }
            } elseif (isset($oldImages[$currentPlacementName])) {
                // if has NO image to set but old data exist then remove that image
                $totalImagesAdded ++;
                $this->removeImage($product, $imgPos, $importModel);
            }
            $flag = false;
        }

        $this->saveProductImageImportUrl($product, $imagePlacementsToSet);

        if ($totalImagesAdded) {
            try {
                $product->save();
                $message = "Sku '" . $sku . "' - product id <" . $productId . "> saved successful";
                $importModel->results["response"]["image"]["success"][] = $message;
                $importModel->log("SAVED: " . $message);
            } catch (\Exception $e) {
                $message = "Sku '" . $sku . "' - product id <" . $productId . "> set image: " .  $e->getMessage();
                $importModel->results["response"]["image"]["error"][] = $message;
                $importModel->log("ERROR: " . $message);
            }
        }

        return $totalImagesAdded;
    }

    /**
     * Add image to product gallery
     *
     * @param Magento\Catalog\Model\ProductFactory $product
     * @param string $imageUrl
     * @param int $isMainImage
     * @param int $position
     * @param WiseRobot\Io\Model\ProductImport $importModel
     * @return bool
     */
    public function addImageToProductGallery(&$product, $imageUrl, $isMainImage, $position, $importModel)
    {
        $sku       = $product->getSku();
        $productId = $product->getId();
        $dir       = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA)->getAbsolutePath('io');
        if (!$this->driverFile->isExists($dir)) {
            $this->driverFile->createDirectory($dir);
        }
        $imageName  = explode("/", (string) $imageUrl);
        $imageName  = end($imageName);
        // replace all none standard character with _
        $imageName  = preg_replace('/[^a-z0-9_\\-\\.]+/i', '_', $imageName);
        $imageName  = $product->getSku() . "_" . $imageName;
        $path       = $dir . "/" . $imageName;

        try {
            $imageUrl = str_replace(" ", "%20", (string) $imageUrl);
            $isCopy   = $this->driverFile->copy($imageUrl, $path);
            if ($isCopy) {
                if ($isMainImage) {
                    $mediaArray = [
                        "thumbnail",
                        "small_image",
                        "image"
                    ];
                } else {
                    $mediaArray = null;
                }

                $product->addImageToMediaGallery($path, $mediaArray, false, false);

                $gallery               = $product->getData("media_gallery");
                // get image just added
                $lastImage             = array_pop($gallery["images"]);
                $lastImage["position"] = $position;
                // re-add that image
                array_push($gallery["images"], $lastImage);
                $product->setData("media_gallery", $gallery);

                $message = "Added image '" . $imageUrl . "' at position " .
                    $position . " to '" . $sku . "' - product id <" . $productId . ">";
                $importModel->results["response"]["image"]["success"][] = $message;
                $importModel->log($message);

                if ($this->driverFile->isExists($path)) {
                    try {
                        $this->driverFile->deleteFile($path);
                    } catch (\Exception $error) {
                        $errorMessage = 'Error while delete file: ' . $error->getMessage();
                        $importModel->log($errorMessage);
                    }
                }

                return 1;
            } else {
                $message = "WARN get image '" . $imageUrl . "' failed" . " for '" .
                    $product->getSku() . "' - product id <" . $product->getId() . ">" ;
                $importModel->results["response"]["image"]["error"][] = $message;
                $importModel->log($message);
            }
        } catch (\Exception $e) {
            $message = "WARN get image '" . $imageUrl . "' failed for '" .
                $sku . "' - product id <" . $productId . "> : " . $e->getMessage();
            $importModel->results["response"]["image"]["error"][] = $message;
            $importModel->log($message);
        }

        return 0;
    }

    /**
     * Remove product images
     *
     * @param Magento\Catalog\Model\ProductFactory $product
     * @param int $position
     * @param WiseRobot\Io\Model\ProductImport $importModel
     * @return void
     */
    public function removeImage(&$product, $position, $importModel)
    {
        $sku       = $product->getSku();
        $productId = $product->getId();
        try {
            $gallery = $product->getData("media_gallery");
            if ($gallery && is_array($gallery) && count($gallery) && isset($gallery["images"])) {
                foreach ($gallery["images"] as &$image) {
                    if ($image["position"] == $position) {
                        $image["removed"] = 1;
                        // $this->productGallery->deleteGallery($image["value_id"]);
                        // $this->galleryProcessor->removeImage($product, $image["file"]);
                        $this->deleteProductImage($image["file"], $importModel);
                        $nameImage = explode("/", (string) $image["file"]);
                        $tamp      = count($nameImage);
                        $message   = "Sku '" . $sku . "' - product id <" .
                            $productId . "> " . "deleted image '". $nameImage[$tamp-1] . "' at position " . $position;
                        $importModel->results["response"]["image"]["success"][] = $message;
                        $importModel->log($message);
                    }
                }
                $product->setData("media_gallery", $gallery);
                $product->save();
                $product = $this->productFactory->create()->setStoreId(0)->load($product->getId());
            }
        } catch (\Exception $e) {
            $message = "Sku '" . $sku . "' - product id <" . $productId . "> remove image: " .  $e->getMessage();
            $importModel->results["response"]["image"]["error"][] = $message;
            $importModel->log("ERROR: " . $message);
            $this->deleteStoredProductImages($product->getSku());
        }
    }

    /**
     * Get product images
     *
     * @param Magento\Catalog\Model\ProductFactory $product
     * @return array
     */
    public function getProductImageImportUrl($product)
    {
        $imageList       = [];
        $sku             = $product->getSku();
        $imageCollection = $this->productImageFactory->create()->getCollection()->addFieldToFilter('sku', $sku);
        foreach ($imageCollection as $image) {
            $imageList[$image->getImagePlacement()] = [
                'id'    => $image->getId(),
                'image' => $image->getImage()
            ];
        }

        return $imageList;
    }

    /**
     * Save product image from image list
     *
     * @param Magento\Catalog\Model\ProductFactory $product
     * @param array $imageList
     * @return void
     */
    public function saveProductImageImportUrl($product, $imageList)
    {
        $oldList = $this->getProductImageImportUrl($product);

        $currentImagePlacements = $this->currentPlacements;

        foreach ($currentImagePlacements as $imagePlacement) {
            $currentPlacementName = $imagePlacement["name"];
            $currentImageOrder    = $imagePlacement["image_placement_order"];
            if (isset($imageList[$currentPlacementName])) {
                $ioImage = $this->productImageFactory->create();
                if (isset($oldList[$currentPlacementName])) {
                    if ($oldList[$currentPlacementName]['image'] == $imageList[$currentPlacementName]) {
                        continue;
                    }
                    $ioImage->load($oldList[$currentPlacementName]['id']);
                } else {
                    $ioImage->setSku($product->getSku())
                        ->setImagePlacement($currentPlacementName);
                }
                $ioImage->setImage($imageList[$currentPlacementName]);
                $ioImage->save();
            } else {
                if (isset($oldList[$currentPlacementName])) {
                    $ioImage = $this->productImageFactory->create()->load($oldList[$currentPlacementName]['id']);
                    $ioImage->delete();
                }
            }
        }
    }

    /**
     * Delete product image file
     *
     * @param string $path
     * @param WiseRobot\Io\Model\ProductImport $importModel
     * @return void
     */
    public function deleteProductImage($path, $importModel)
    {
        $imagePath = 'catalog/product/' . trim((string) $path, ' /');
        $filePath  = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA)->getAbsolutePath('') . $imagePath;
        if ($this->driverFile->isExists($filePath)) {
            try {
                $this->driverFile->deleteFile($filePath);
            } catch (\Exception $error) {
                $errorMessage = 'Error while delete product image: ' . $error->getMessage();
                $importModel->log($errorMessage);
            }
        }
    }

    /**
     * Delete stored product images
     *
     * @param string $sku
     * @return void
     */
    public function deleteStoredProductImages($sku)
    {
        $ioProductImages = $this->productImageFactory->create()->getCollection()
            ->addFieldToFilter("sku", $sku);
        foreach ($ioProductImages as $ioProductImage) {
            $ioProductImage->delete();
        }
    }
}
