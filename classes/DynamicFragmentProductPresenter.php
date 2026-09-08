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
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see https://opensource.org/licenses/GPL-3.0 .
 *
 * @author   LiteSpeed Technologies
 * @copyright Copyright (c) 2017-2020 LiteSpeed Technologies, Inc. (https://www.litespeedtech.com)
 * @license  https://opensource.org/licenses/GPL-3.0
 */

class LiteSpeedCacheDynamicFragmentProductPresenter
{
    private $context;

    public function __construct($context)
    {
        $this->context = $context;
    }

    /**
     * Build the product representation required by dynamic product-page fragments.
     *
     * The ESI request cannot safely reuse ProductController::getTemplateVarProduct(),
     * so this method rebuilds only the product-page data required by the fragments.
     *
     * Keep this logic aligned with the ProductController/product presentation flow,
     * especially combinations, quantities, cart state, customizations,
     * add_to_cart_url, filterProductContent and displayProductActions.
     */
    public function present($idProduct, $idProductAttribute, $quantityWanted)
    {
        $idProduct = (int) $idProduct;
        $idProductAttribute = (int) $idProductAttribute;
        $quantityWanted = (int) $quantityWanted;

        if ($idProduct <= 0) {
            return null;
        }

        $productObject = new Product(
            $idProduct,
            true,
            $this->context->language->id,
            $this->context->shop->id
        );

        if (!Validate::isLoadedObject($productObject)) {
            return null;
        }

        if (!$this->isValidProductAttribute($idProduct, $idProductAttribute)) {
            return null;
        }

        $product = (new PrestaShop\PrestaShop\Adapter\Presenter\Object\ObjectPresenter())
            ->present($productObject);

        $product['id_product'] = $idProduct;
        $product['id_product_attribute'] = $idProductAttribute;
        $product['id_customization'] = $this->getCurrentCustomizationId(
            $idProduct,
            $idProductAttribute
        );
        $product['out_of_stock'] = (int) $productObject->out_of_stock;
        $product['minimal_quantity'] = $this->getProductMinimalQuantity(
            $idProduct,
            $idProductAttribute,
            $product
        );
        $product['cart_quantity'] = $this->context->cart
            ->getProductQuantity($idProduct, $idProductAttribute)['quantity'];
        $product['quantity_required'] = max(
            1,
            (int) $product['minimal_quantity'] - (int) $product['cart_quantity']
        );
        $product['quantity_wanted'] = $quantityWanted;

        if ($product['quantity_wanted'] < $product['quantity_required']) {
            $product['quantity_wanted'] = $product['quantity_required'];
        }

        $product = Product::getProductProperties(
            $this->context->language->id,
            $product,
            $this->context
        );

        if (!is_array($product)) {
            return null;
        }

        $factory = new ProductPresenterFactory(
            $this->context,
            new TaxConfiguration()
        );

        $presentedProduct = $factory->getPresenter()->present(
            $factory->getPresentationSettings(),
            $product,
            $this->context->language
        );

        return $this->filterProductContent($presentedProduct);
    }

    private function isValidProductAttribute($idProduct, $idProductAttribute)
    {
        if ($idProductAttribute <= 0) {
            return true;
        }

        $combination = new Combination($idProductAttribute);

        return Validate::isLoadedObject($combination)
            && (int) $combination->id_product === (int) $idProduct;
    }

    private function getCurrentCustomizationId($idProduct, $idProductAttribute)
    {
        $customizationIds = [];

        foreach ($this->context->cart->getProducts() as $cartProduct) {
            if ((int) $cartProduct['id_product'] !== (int) $idProduct
                || (int) $cartProduct['id_product_attribute'] !== (int) $idProductAttribute
                || empty($cartProduct['id_customization'])) {
                continue;
            }

            $customizationIds[(int) $cartProduct['id_customization']] = true;
        }

        if (count($customizationIds) === 1) {
            return (int) key($customizationIds);
        }

        return 0;
    }

    private function getProductMinimalQuantity($idProduct, $idProductAttribute, $product)
    {
        if ($idProductAttribute > 0) {
            $combination = new Combination($idProductAttribute);
            if (Validate::isLoadedObject($combination)) {
                return max(1, (int) $combination->minimal_quantity);
            }
        }

        if (isset($product['minimal_quantity'])) {
            return max(1, (int) $product['minimal_quantity']);
        }

        $productObject = new Product(
            $idProduct,
            false,
            $this->context->language->id,
            $this->context->shop->id
        );

        if (Validate::isLoadedObject($productObject)) {
            return max(1, (int) $productObject->minimal_quantity);
        }

        return 1;
    }

    private function filterProductContent($presentedProduct)
    {
        if (version_compare(_PS_VERSION_, '1.7.2.0', '<')) {
            return $presentedProduct;
        }

        $filteredProduct = Hook::exec(
            'filterProductContent',
            ['object' => $presentedProduct],
            null,
            false,
            true,
            false,
            null,
            true
        );

        if (!empty($filteredProduct['object'])) {
            return $filteredProduct['object'];
        }

        return $presentedProduct;
    }
}
