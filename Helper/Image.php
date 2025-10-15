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

use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Catalog\Model\ProductFactory;
use WiseRobot\Io\Model\ProductManagement;

class Image extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var Filesystem
     */
    public $filesystem;
    /**
     * @var DriverFile
     */
    public $driverFile;
    /**
     * @var ProductFactory
     */
    public $productFactory;
    /**
     * @var ProductManagement|null
     */
    public ?ProductManagement $productManagement = null;

    /**
     * @param Filesystem $filesystem
     * @param DriverFile $driverFile
     * @param ProductFactory $productFactory
     */
    public function __construct(
        Filesystem $filesystem,
        DriverFile $driverFile,
        ProductFactory $productFactory
    ) {
        $this->filesystem = $filesystem;
        $this->driverFile = $driverFile;
        $this->productFactory = $productFactory;
    }

    /**
     * Populates images to product gallery
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param array $imagePlacementsToSet
     * @param string $ioImagesField
     * @return int
     */
    public function populateProductImage(
        \Magento\Catalog\Model\Product $product,
        array $imagePlacementsToSet,
        string $ioImagesField = 'io_images'
    ): int {
        $totalImagesChanges = 0;
        if (!$product || !$product->getId()) {
            return $totalImagesChanges;
        }
        $oldImagesString = (string) $product->getData($ioImagesField);
        $oldImagePlacements = $this->parseOldImageUrls($oldImagesString);
        $placementKeys = array_keys($imagePlacementsToSet);
        usort($placementKeys, function($a, $b) {
            return (int) filter_var($a, FILTER_SANITIZE_NUMBER_INT) <=> (int) filter_var($b, FILTER_SANITIZE_NUMBER_INT);
        });
        $isMainImage = true;
        foreach ($placementKeys as $placementName) {
            $imageUrl = $imagePlacementsToSet[$placementName];
            $imgPos = (int) preg_replace('/[^0-9]/', '', $placementName);
            $imgPos = $imgPos > 0 ? $imgPos : 1;
            $oldImageUrl = $oldImagePlacements[$imgPos] ?? null;
            $shouldRemoveOldImage = true;
            if ($oldImageUrl && $oldImageUrl === $imageUrl) {
                $shouldRemoveOldImage = false;
            }
            if ($shouldRemoveOldImage) {
                $this->removeImage($product, $imgPos);
                $totalImagesChanges++;
            }
            if (!empty($imageUrl) && $shouldRemoveOldImage) {
                $addedImageCount = $this->addImageToProductGallery(
                    $product,
                    $imageUrl,
                    $isMainImage,
                    $imgPos
                );
                if ($addedImageCount) {
                    $totalImagesChanges += $addedImageCount;
                }
            }
            if ($isMainImage === true) {
                $isMainImage = false;
            }
        }
        $newImgPositions = array_map(function($key) {
            return (int) preg_replace('/[^0-9]/', '', $key);
        }, array_keys($imagePlacementsToSet));
        foreach ($oldImagePlacements as $pos => $url) {
            if (!in_array($pos, $newImgPositions)) {
                $this->removeImage($product, $pos);
                $totalImagesChanges++;
            }
        }
        return $totalImagesChanges;
    }

    /**
     * Parses the old image URL string
     *
     * @param string|null $oldImagesString
     * @return array
     */
    public function parseOldImageUrls(?string $oldImagesString): array
    {
        $parsedImages = [];
        if (empty($oldImagesString)) {
            return $parsedImages;
        }
        $pairs = explode(',', $oldImagesString);
        foreach ($pairs as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $url = trim($parts[1]);
                $position = (int) preg_replace('/[^0-9]/', '', $key);
                if ($position > 0 && !empty($url)) {
                    $parsedImages[$position] = $url;
                }
            }
        }
        return $parsedImages;
    }

    /**
     * Add image to product gallery
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $imageUrl
     * @param bool $isMainImage
     * @param int $position
     * @return int
     */
    public function addImageToProductGallery(
        \Magento\Catalog\Model\Product &$product,
        string $imageUrl,
        bool $isMainImage,
        int $position
    ): int {
        $dir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA)
            ->getAbsolutePath('io');
        if (!$this->driverFile->isExists($dir)) {
            $this->driverFile->createDirectory($dir);
        }
        $imageName = basename((string) $imageUrl);
        $imageName = preg_replace('/[^a-z0-9_\\-\\.]+/i', '_', $imageName);
        $uniqueId = uniqid((string) mt_rand(), true);
        $uniqueFilename = $product->getSku() . "_" . $uniqueId . "_" . $imageName;
        $path = $dir . "/" . $uniqueFilename;
        try {
            $isCopy = $this->driverFile->copy($imageUrl, $path);
            $fileSize = $this->driverFile->isFile($path) ? $this->driverFile->stat($path)['size'] : 0;
            if ($isCopy && $this->driverFile->isExists($path) && $fileSize > 0) {
                $mediaArray = $isMainImage ? ["thumbnail", "small_image", "image"] : [];
                $product->addImageToMediaGallery($path, $mediaArray, false, false);
                if ($this->driverFile->isExists($path)) {
                    $this->driverFile->deleteFile($path);
                }
                $gallery = $product->getData("media_gallery");
                if (!empty($gallery["images"])) {
                    end($gallery["images"]);
                    $lastImageIndex = key($gallery["images"]);
                    if (isset($gallery["images"][$lastImageIndex])) {
                        $gallery["images"][$lastImageIndex]["position"] = $position;
                        $product->setData("media_gallery", $gallery);
                    }
                }
                $message = "Added image '" . $imageUrl . "' at position " . $position . " to '" .
                    $product->getSku() . "' - product id <" . $product->getId() . ">";
                $this->handleResult($message, 'success');
                return 1;
            } else {
                $message = "WARN get image '" . $imageUrl . "' failed" . " for '" .
                    $product->getSku() . "' - product id <" . $product->getId() . ">";
                $this->handleResult($message, 'error');
                if ($this->driverFile->isExists($path)) {
                    $this->driverFile->deleteFile($path);
                }
            }
        } catch (\Exception $e) {
            $message = "WARN get image '" . $imageUrl . "' failed for '" .
                $product->getSku() . "' - product id <" . $product->getId() . "> : " . $e->getMessage();
            $this->handleResult($message, 'error');
            if ($this->driverFile->isExists($path)) {
                $this->driverFile->deleteFile($path);
            }
        }
        return 0;
    }

    /**
     * Remove product image by position from the media gallery data
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param int $position
     * @return void
     */
    public function removeImage(
        \Magento\Catalog\Model\Product &$product,
        int $position
    ): void {
        try {
            $gallery = $product->getData("media_gallery");
            if (!empty($gallery['images']) && is_array($gallery['images'])) {
                foreach ($gallery["images"] as &$image) {
                    if (isset($image["position"]) && (int) $image["position"] === $position && empty($image["removed"])) {
                        $image["removed"] = 1;
                        if (!empty($image["file"])) {
                            $this->deleteProductImage($image["file"]);
                        }
                        $nameImage = basename((string) $image["file"]);
                        $message = "Deleted image '" . $nameImage . "' at position " . $position . " for '" .
                            $product->getSku() . "' - product id <" . $product->getId() . ">";
                        $this->handleResult($message, 'success');
                        $product->setData("media_gallery", $gallery);
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            $message = "ERROR: sku '" . $product->getSku() . "' - product id <" .
                $product->getId() . "> remove image: " .  $e->getMessage();
            $this->handleResult($message, 'error');
        }
    }

    /**
     * Delete product image file from the file system
     *
     * @param string $path
     * @return void
     */
    public function deleteProductImage(
        string $path
    ): void {
        $imagePath = 'catalog/product/' . trim((string) $path, ' /');
        $filePath = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA)
            ->getAbsolutePath('') . $imagePath;
        if ($this->driverFile->isExists($filePath)) {
            try {
                $this->driverFile->deleteFile($filePath);
            } catch (\Exception $e) {
                $message = 'Error while deleting product image file ' . $filePath . ': ' .
                    $e->getMessage();
                $this->log($message);
            }
        }
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
            $this->productManagement->results["response"]["image"][$type][] = $message;
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
