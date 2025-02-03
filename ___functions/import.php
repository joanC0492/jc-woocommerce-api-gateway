<?php
if (!defined('ABSPATH'))
  exit;
function aster_import_products()
{
  try {
    // Configura la API REST de WooCommerce
    $woocommerce = get_woocommerce_client();

    // API URL
    $json_url = 'https://joancochachi.dev/aster.json';

    // Obtener datos del JSON
    $response = wp_remote_get($json_url);

    if (is_wp_error($response)) {
      echo '<div class="notice notice-error"><p>' . __('Error al obtener los productos: ', 'jc-woocommerce-api') . $response->get_error_message() . '</p></div>';
      return;
    }

    $productos = json_decode(wp_remote_retrieve_body($response), true);

    if (!is_array($productos)) {
      echo '<div class="notice notice-error"><p>' . __('Respuesta del JSON no válida.', 'jc-woocommerce-api') . '</p></div>';
      return;
    }

    // Array para rastrear los SKU procesados
    $processed = [];
    foreach ($productos as $producto)
      process_product_batch($woocommerce, $producto, $processed);

    echo '<div class="notice notice-success"><p>' . __('Importación completada.', 'jc-woocommerce-api') . '</p></div>';
  } catch (Exception $e) {
    echo '<div class="notice notice-error"><p>' . __('Error fatal en la importación: ', 'jc-woocommerce-api') . $e->getMessage() . '</p></div>';
  }
}
