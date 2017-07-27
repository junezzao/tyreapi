<?php

namespace App\Modules\ThirdParty\Repositories\Contracts;

/**
 * All Marketplaces are required to implement this Interface
 * in order to fully comply with Hubwire coding standards.
 *
 * @version   1.0
 * @author    Raheel Masood <raheel@hubwire.com>
 */

interface MarketplaceInterface
{
    /**
     * Create Product
     * Bulk creation is subjective to marketplaces. If bulk creation is supported in any marketplace,
     * the first paramter $product_id should be provided as array of arrays.
     * 
     * @param  array  $product
     * @param  boolean $bulk Should be passed as true for bulk creation
     */
    public function createProduct(array $product, $bulk = false);

    /**
     * Update Product
     * If bulk creation is supported in any marketplace, the first paramter $product_id
     * should be provided as array of arrays.
     * 
     * @param  array  $product
     * @param  boolean $bulk Should be passed as true for bulk creation
     */
    public function updateProduct(array $product, $bulk = false);

    /**
     * Update Visibility of product in marketplaces such as 
     * making products active or inactive.
     * If bulk creation is supported in any marketplace, the first paramter $product
     * should be provided as array of arrays.
     * 
     * @param  array  $product
     * @param  boolean $bulk Should be passed as true for bulk creation
     */
    public function updateVisibility(array $product, $bulk = false);

    /**
     * Delete Product
     * If bulk creation is supported in any marketplace, the first paramter $product
     * should be provided as array of arrays.
     * 
     * @param  array  $product
     * @param  boolean $bulk Should be passed as true for bulk creation
     */
    public function deleteProduct(array $product, $bulk = false);

    /**
     * Create SKU
     * Bulk creation is subjective to marketplaces. If bulk creation is supported in any marketplace,
     * the first paramter $sku should be provided as array of arrays.
     * 
     * @param  array  $sku 
     * @param  boolean $bulk Should be passed as true for bulk creation
     */
    public function createSku(array $sku, $bulk = false);

    /**
     * Update SKU
     * If bulk creation is supported in any marketplace, the first paramter $sku
     * should be provided as array of arrays.
     * 
     * @param  array  $sku 
     * @param  boolean $bulk Should be passed as true for bulk creation
     */
    public function updateSku(array $sku, $bulk = false);

    /**
     * Update quantity of skus in marketplaces. If bulk creation is supported in any marketplace,
     * the first paramter $sku should be provided as array of arrays.
     * 
     * @param  array $sku
     * @param  boolean $bulk Should be passed as true for bulk creation
     */
    public function updateQuantity(array $sku, $bulk = false);

    /**
     * Delete SKU
     * If bulk creation is supported in any marketplace, the first paramter $sku
     * should be provided as array of arrays.
     * 
     * @param  array  $sku
     * @param  boolean $bulk Should be passed as true for bulk creation
     */
    public function deleteSku(array $sku, $bulk = false);

    /**
     * Updates Image
     * Images must always be provided in array format even if there is a single image
     * 
     * @param  array $data
     */
    public function updateImages(array $data);

    /*/**
     * Returns the list of available categories
     */
    // public function getCategories();*/

    /**
     * Get Orders
     * 
     * @param  array  $filters 
     */
    public function getOrders(array $filters);

    /**
     * This method is used to send every type of response to the API
     * Consumer. Implementing class should use this method where ever they are using return keyword
     * This function should return an array of response in both cases (Failed or Successfull)
     *
     * @param  mixed $response Response object or array from Markeplaces or any other internal functionality
     * @return array This array should contain following keys
     *
     * <code>
     * Array(
     *  'success' => bool,
     *  'status_code' => integer,
     *  'errors' => mixed|null,
     *  'body' => mixed|null
     * )
     * </code>
     */
    public function sendResponse($response);
}

