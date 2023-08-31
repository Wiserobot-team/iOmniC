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

use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\Catalog\Model\Product\Gallery\Processor as GalleryProcessor;
use Magento\Catalog\Model\ResourceModel\Product\Gallery as ProductGallery;
use Wiserobot\Io\Model\ProductimageFactory;

class Image extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $currentPlacements;
    public $filesystem;
    public $scopeConfig;
    public $productFactory;
    public $galleryReadHandler;
    public $galleryProcessor;
    public $productGallery;
    public $productImageFactory;

    public function __construct(
        Filesystem                 $filesystem,
        ScopeConfigInterface       $scopeConfig,
        ProductFactory             $productFactory,
        GalleryReadHandler         $galleryReadHandler,
        GalleryProcessor           $galleryProcessor,
        ProductGallery             $productGallery,
        ProductimageFactory        $productimageFactory
    ) {
        $this->filesystem          = $filesystem;
        $this->scopeConfig         = $scopeConfig;
        $this->productFactory      = $productFactory;
        $this->galleryReadHandler  = $galleryReadHandler;
        $this->galleryProcessor    = $galleryProcessor;
        $this->productGallery      = $productGallery;
        $this->productImageFactory = $productimageFactory;
    }

    public function populateProductImage($product, $imagePlacementsToSet, $importModel)
    {
        $totalImagesAdded = 0;
        if (!$product || !$product->getId()) {
            return $totalImagesAdded;
        }
        // $product = $this->productFactory->create()->setStoreId(0)->load($product->getId())
        // $this->galleryReadHandler->execute($product);

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
        $isFirstImage = true;
        foreach ($currentImagePlacements as $currentImagePlacement) {
            $currentPlacementName = $currentImagePlacement["name"];
            $currentImageOrder    = $currentImagePlacement["image_placement_order"];
            // if has image to set
            if (isset($imagePlacementsToSet[$currentPlacementName])) {
                if (isset($oldImages[$currentPlacementName])
                    && $oldImages[$currentPlacementName]['image'] == $imagePlacementsToSet[$currentPlacementName]) {
                    $isFirstImage = false;

                    // if stored image url is same as current url then we do nothing
                    continue;
                }
                // if stored image url is NOT same as current url
                $imageUrl = $imagePlacementsToSet[$currentPlacementName];
                // remove old image from product
                $this->removeImage($product, $currentImageOrder, $importModel);

                // add current url image to product
                $addedImageCount = $this->addImageToProductGallery($product, $imageUrl, $isFirstImage, $currentImageOrder, $importModel);
                if ($addedImageCount) {
                    $totalImagesAdded = $totalImagesAdded + $addedImageCount;
                } else {
                    unset($imagePlacementsToSet[$currentPlacementName]);
                }
            } elseif (isset($oldImages[$currentPlacementName])) {
                // if has NO image to set but old data exist then remove that image
                $totalImagesAdded ++;
                $this->removeImage($product, $currentImageOrder, $importModel);
            }
            $isFirstImage = false;
        }

        $this->saveProductImageImportUrl($product, $imagePlacementsToSet);

        if ($totalImagesAdded) {
            try {
                $product->save();
                $importModel->results["response"]["image"]["success"][] = "sku '" . $product->getSku() . "' - product id <" . $product->getId() . "> saved successful";
                $importModel->log("SAVED: sku '" . $product->getSku() . "' - product id <" . $product->getId() . "> saved successful");
            } catch (\Exception $e) {
                $importModel->results["response"]["image"]["error"][] = "sku '" . $product->getSku() . "' - product id <" . $product->getId() . "> set image: " .  $e->getMessage();
                $importModel->log("ERROR: sku '" . $product->getSku() . "' - product id <" . $product->getId() . "> set image: " .  $e->getMessage());
            }
        }

        return $totalImagesAdded;
    }

    // add image to product gallery
    public function addImageToProductGallery(&$product, $imageUrl, $isMainImage, $position, $importModel)
    {
        $fileHeaders = @get_headers($imageUrl);
        if(!$fileHeaders || $fileHeaders[0] == 'HTTP/1.1 404 Not Found') {
            $importModel->results["response"]["image"]["error"][] = "warn get image '" . $imageUrl . "' falied for '" . $product->getSku() . "' - product id <" . $product->getId() . "> : file not found or corrupt";
            $importModel->log("WARN get image '" . $imageUrl . "' falied for '" . $product->getSku() . "' - product id <" . $product->getId() . "> : file not found or corrupt");
            return 0;
        }

        $dir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA)->getAbsolutePath('wiserobotio');
        if (!file_exists($dir)) {
            mkdir($dir);
        }
        $imageName  = explode("/", (string) $imageUrl);
        $imageName  = end($imageName);
        // replace all none standard charracter with _
        $imageName  = preg_replace('/[^a-z0-9_\\-\\.]+/i', '_', $imageName);
        $imageName  = $product->getSku() . "_" . $imageName;
        $path       = $dir . "/" . $imageName;

        try {
            $imageUrl = str_replace(" ", "%20", (string) $imageUrl);
            $isCopy   = copy($imageUrl, $path);
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

                $importModel->results["response"]["image"]["success"][] = "added image '" . $imageUrl . "' at position " . $position . " to '" . $product->getSku() . "' - product id <" . $product->getId() . ">";
                $message = "Added image '" . $imageUrl . "' at position " . $position . " to '" . $product->getSku() . "' - product id <" . $product->getId() . "> ";
                $importModel->log($message);

                if (file_exists($path)) {
                    try {
                        unlink($path);
                    } catch (\Exception $e) {
                    }
                }

                return 1;
            } else {
                $importModel->results["response"]["image"]["error"][] = "warn get image '" . $imageUrl . "' falied for '" . $product->getSku() . "' - product id <" . $product->getId() . ">";
                $importModel->log("WARN get image '" . $imageUrl . "' falied for '" . $product->getSku() . "' - product id <" . $product->getId() . "> ");
            }
        } catch (\Exception $e) {
            $importModel->results["response"]["image"]["error"][] = "warn add image '" . $imageUrl . "' failed for '" . $product->getSku() . "' - product id <" . $product->getId() . "> : " . $e->getMessage();
            $importModel->log("WARN add image '" . $imageUrl . "' failed for '" . $product->getSku() . "' - product id <" . $product->getId() . "> : " . $e->getMessage());
        }

        return 0;
    }

    public function removeImage(&$product, $position, $importModel)
    {
        try {
            $gallery = $product->getData("media_gallery");
            if ($gallery && is_array($gallery) && count($gallery) && isset($gallery["images"])) {
                foreach ($gallery["images"] as &$image) {
                    if ($image["position"] == $position) {
                        $image["removed"] = 1;
                        // $this->productGallery->deleteGallery($image["value_id"]);
                        // $this->galleryProcessor->removeImage($product, $image["file"]);
                        $this->deleteProductImage($image["file"]);
                        $nameImage = explode("/", (string) $image["file"]);
                        $tamp      = count($nameImage);
                        $importModel->results["response"]["image"]["success"][] = "sku '" . $product->getSku() . "' - product id <" . $product->getId() . "> delete image '". $nameImage[$tamp-1] . "' at position " . $position;
                        $message = "SKU '" . $product->getSku() . "' - product id <" . $product->getId() . "> delete image '". $nameImage[$tamp-1] . "' at position " . $position;
                        $importModel->log($message);
                    }
                }
                $product->setData("media_gallery", $gallery);
                $product->save();
                $product = $this->productFactory->create()->setStoreId(0)->load($product->getId());
            }
        } catch (\Exception $e) {
            $importModel->results["response"]["image"]["error"][] = "sku '" . $product->getSku() . "' - product id <" . $product->getId() . "> remove image: " .  $e->getMessage();
            $message = "ERROR: sku '" . $product->getSku() . "' - product id <" . $product->getId() . "> remove image: " .  $e->getMessage();
            $importModel->log($message);
            $this->deleteStoredProductImages($product->getSku());
        }
    }

    /**
    *   get product original image urls in io that were used to update product image
    *
    *   @param Magento\Catalog\Model\Product $product
    *   @return array
    */
    public function getProductImageImportUrl($product)
    {
        $imageList = [];
        $imageCollection = $this->productImageFactory->create()->getCollection()->addFieldToFilter('sku', $product->getSku());
        foreach ($imageCollection as $image) {
            $imageList[$image->getImagePlacement()] = [
                'id'    => $image->getId(),
                'image' => $image->getImage()
            ];
        }

        return $imageList;
    }

    /**
    *   save product original image urls in io that were used to update product image
    *
    *   @param Magento\Catalog\Model\Product $product
    *   @param array $imageList
    *   @return bool
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
    *   Delete product image file
    *   @param string $path (relative path)
    *
    */
    public function deleteProductImage($path)
    {
        $filePath = $this->filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)->getAbsolutePath('') . 'catalog/product/' . trim((string) $path, ' /');
        if (file_exists($filePath)) {
            try {
                @unlink($filePath);
            } catch (\Exception $e) {
            }
        }
    }

    public function deleteStoredProductImages($sku)
    {
        $ioProductImages = $this->productImageFactory->create()->getCollection()
            ->addFieldToFilter("sku", $sku);
        foreach ($ioProductImages as $ioProductImage) {
            $ioProductImage->delete();
        }
    }
}
