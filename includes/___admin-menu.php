<?php
if (!defined('ABSPATH'))
  exit;

function jc_api_gateway_add_admin_menu()
{
  add_menu_page(
    'JC WooCommerce API Gateway', // Título de la página
    'JC API Gateway',             // Título del menú
    'manage_options',             // Capacidad requerida
    'jc-api-gateway',             // Slug del menú
    'jc_api_gateway_admin_page'   // Función que renderiza la página
  );
}

function jc_api_gateway_admin_page()
{
  ?>
  <div class="wrap">
    <h1>JC WooCommerce API Gateway</h1>
    <form method="post" action="">
      <?php
      if (isset($_POST['jc_import_products'])) {
        jc_handle_import_products();
      }
      ?>
      <input type="submit" name="jc_import_products" value="Importar Productos" class="button button-primary">
    </form>
  </div>
  <?php
}