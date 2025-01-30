<?php
if (!defined('ABSPATH'))
  exit;

require_once __DIR__ . '/../vendor/autoload.php'; // Cargar el autoloader de Composer

use Automattic\WooCommerce\Client; // Usar la clase Client de WooCommerce

function get_woocommerce_client()
{
  return new Client(
    site_url(),
    'ck_53a81c639c0c5751361030f76f97e19a3e53b988',
    'cs_cb0c74d812ad3aded991c50a894a60e8458c875e',
    [
      'version' => 'wc/v3',
      'wp_api' => true, // Desactiva la verificación SSL (POR MIENTRAS)
    ]
  );
}
