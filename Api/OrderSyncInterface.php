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

namespace WiseRobot\Io\Api;

interface OrderSyncInterface
{
    /**
     * Get Order Sync by ID
     *
     * @param int $id
     * @return array
     */
    public function getById(int $id): array;

    /**
     * Get Order Sync by increment ID
     *
     * @param string $incrementId
     * @return array
     */
    public function getByIncrementId(string $incrementId): array;

    /**
     * Delete Order Sync by ID
     *
     * @param int $id
     * @return array
     */
    public function deleteById(int $id): array;

    /**
     * Delete Order Sync by increment ID
     *
     * @param string $incrementId
     * @return array
     */
    public function deleteByIncrementId(string $incrementId): array;
}
