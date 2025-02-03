<?php
if (!defined('ABSPATH')) {
  exit;
}

function jc_validate_product_data($product_data)
{
  return !empty($product_data['name']) && !empty($product_data['price']);
}

function jc_create_or_update_product($product_data)
{
  $product_id = wc_get_product_id_by_sku($product_data['sku']);

  $product = new WC_Product($product_id ? $product_id : 0);
  $product->set_name($product_data['name']);
  $product->set_regular_price($product_data['price']);
  $product->set_sku($product_data['sku']);
  $product->set_description($product_data['description']);

  return $product->save();
}