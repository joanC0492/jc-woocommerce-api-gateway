<?php
if (!defined('ABSPATH'))
  exit;

function jc_process_variations($product_id, $variations)
{
  foreach ($variations as $variation_data) {
    if (jc_validate_variation_data($variation_data)) {
      jc_create_or_update_variation($product_id, $variation_data);
    }
  }
}

function jc_validate_variation_data($variation_data)
{
  return !empty($variation_data['attributes']) && !empty($variation_data['price']);
}

function jc_create_or_update_variation($product_id, $variation_data)
{
  $variation = new WC_Product_Variation();
  $variation->set_parent_id($product_id);
  $variation->set_attributes($variation_data['attributes']);
  $variation->set_regular_price($variation_data['price']);
  $variation->save();
}