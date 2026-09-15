<?php
/**
 * LiteSpeed Cache for Prestashop.
 *
 * NOTICE OF LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author   LiteSpeed Technologies
 * @copyright  Copyright (c) 2017-2020 LiteSpeed Technologies, Inc. (https://www.litespeedtech.com)
 * @license     https://opensource.org/licenses/GPL-3.0
 */

class LiteSpeedCacheDynamicFragment
{
    const PRODUCT_ADD_TO_CART = 'product-add-to-cart';
    const PRODUCT_ADD_TO_CART_REFRESH = 'product_add_to_cart';
    const NOTIFICATIONS = 'notifications';

    public static function isSupported($name)
    {
        return in_array($name, self::getSupportedNames(), true);
    }

    public static function buildEsiParam($name)
    {
        if (!self::isSupported($name)) {
            return null;
        }

        // product-add-to-cart is handled directly by ProductController.
        if ($name === self::PRODUCT_ADD_TO_CART) {
            return null;
        }

        return [
            'pt' => LiteSpeedCacheEsiItem::ESI_DYNAMIC_FRAGMENT,
            'm' => LscDynamicFragment::NAME,
            'f' => $name,
        ];
    }

    /**
     * Build the parameters required by ProductController::displayAjaxRefresh()
     * for a supported product-page fragment, preserving the current product
     * and selected combination context.
     */
    public static function buildProductRefreshParam($product, $fragment)
    {
        if (!in_array($fragment, [
            self::PRODUCT_ADD_TO_CART,
            self::NOTIFICATIONS,
        ], true)) {
            return null;
        }

        $idProduct = self::getProductId($product);

        if ($idProduct <= 0) {
            return null;
        }

        return [
            'id_product' => $idProduct,
            'id_product_attribute' => (int) self::getProductValue(
                $product,
                'id_product_attribute'
            ),
            'ajax' => 1,
            'action' => 'refresh',
            'lscache_fragment' => $fragment === self::PRODUCT_ADD_TO_CART
                ? self::PRODUCT_ADD_TO_CART_REFRESH
                : self::NOTIFICATIONS,
        ];
    }

    private static function getSupportedNames()
    {
        return [
            self::PRODUCT_ADD_TO_CART,
            self::NOTIFICATIONS,
        ];
    }

    private static function getProductId($product)
    {
        $idProduct = self::getProductValue($product, 'id_product');

        return $idProduct ? (int) $idProduct : (int) self::getProductValue($product, 'id');
    }

    private static function getProductValue($product, $field)
    {
        if (empty($product)) {
            return null;
        }

        if (is_array($product) || $product instanceof ArrayAccess) {
            return isset($product[$field]) ? $product[$field] : null;
        }

        if (is_object($product) && isset($product->$field)) {
            return $product->$field;
        }

        return null;
    }
}
