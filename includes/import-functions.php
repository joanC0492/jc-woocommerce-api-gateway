<?php
if (!defined('ABSPATH'))
  exit;

require_once __DIR__ . '/../vendor/autoload.php';
use Automattic\WooCommerce\Client;

function aster_import_products()
{
  try {
    $woocommerce = new Client(
      site_url(),
      'ck_53a81c639c0c5751361030f76f97e19a3e53b988',
      'cs_cb0c74d812ad3aded991c50a894a60e8458c875e',
      [
        'version' => 'wc/v3',
        'verify_ssl' => false,
      ]
    );

    $json_url = 'https://joancochachi.dev/aster.json';
    $response = wp_remote_get($json_url);

    if (is_wp_error($response)) {
      wp_send_json_error('Error al obtener los productos: ' . $response->get_error_message());
    }

    $productos = json_decode(wp_remote_retrieve_body($response), true);

    if (!is_array($productos)) {
      wp_send_json_error('Respuesta del JSON no válida.');
    }

    $processed = [];
    foreach ($productos as $producto) {
      process_product_batch($woocommerce, $producto, $processed);
    }

    wp_send_json_success('Importación completada.');
  } catch (Exception $e) {
    wp_send_json_error('Error fatal en la importación: ' . $e->getMessage());
  }
}

// Registrar la acción AJAX
add_action('wp_ajax_aster_import_products', 'aster_import_products');
