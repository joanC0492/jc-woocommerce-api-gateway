<?php
if (!defined('ABSPATH'))
  exit;

function jc_handle_import_products()
{
  // Obtener datos del JSON (simulado)
  $json_data = file_get_contents('https://joancochachi.dev/aster.json');
  $products = json_decode($json_data, true);

  if (is_array($products)) {
    foreach ($products as $product_data) {
      if (jc_validate_product_data($product_data)) {
        $product_id = jc_create_or_update_product($product_data);
        if ($product_id) {
          jc_handle_product_images($product_id, $product_data['images']);
          jc_process_variations($product_id, $product_data['variations']);
        }
      }
    }
    echo '<div class="notice notice-success"><p>Productos importados correctamente.</p></div>';
  } else {
    echo '<div class="notice notice-error"><p>Error al importar productos.</p></div>';
  }
}