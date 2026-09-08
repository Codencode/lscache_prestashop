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

    const NOTIFICATIONS = 'notifications';

    public static function isSupported($name)
    {
        return in_array($name, self::getSupportedNames(), true);
    }

    public static function buildEsiParam($name, $product)
    {
        if (!self::isSupported($name)) {
            return null;
        }

        /*
         * notifications is a global fragment and may exist on pages without a
         * product context.
         *
         * product-add-to-cart cannot be rebuilt without a product and must not
         * be converted to ESI when no valid product is available.
         */
        $idProduct = self::getProductId($product);
        if ($name === self::PRODUCT_ADD_TO_CART && $idProduct <= 0) {
            return null;
        }

        $params = [
            'pt' => LiteSpeedCacheEsiItem::ESI_DYNAMIC_FRAGMENT,
            'm' => LscDynamicFragment::NAME,
            'f' => $name,
        ];

        if ($idProduct > 0) {
            $params['id_product'] = $idProduct;
            $params['id_product_attribute'] = (int) self::getProductValue($product, 'id_product_attribute');
        }

        /*
         * Do not put id_customization in the ESI URL stored in the public FPC.
         * Customization data is user/cart-specific and must be derived from the
         * current ESI request context instead.
         */
        return $params;
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
