<?php
if (!defined('ABSPATH'))
  exit;

function jc_handle_product_images($product_id, $images)
{
  foreach ($images as $image_url) {
    $image_id = jc_upload_image_from_url($image_url);
    if ($image_id) {
      add_post_meta($product_id, '_thumbnail_id', $image_id);
    }
  }
}

function jc_upload_image_from_url($image_url)
{
  require_once(ABSPATH . 'wp-admin/includes/image.php');
  require_once(ABSPATH . 'wp-admin/includes/file.php');
  require_once(ABSPATH . 'wp-admin/includes/media.php');

  $tmp = download_url($image_url);
  if (is_wp_error($tmp)) {
    return false;
  }

  $file_array = [
    'name' => basename($image_url),
    'tmp_name' => $tmp
  ];

  $image_id = media_handle_sideload($file_array, 0);
  if (is_wp_error($image_id)) {
    @unlink($file_array['tmp_name']);
    return false;
  }

  return $image_id;
}