<?php
if (!defined('ABSPATH'))
  exit; ?>

<div class="wrap">
  <h1>Importar Productos a WooCommerce</h1>
  <button id="aster-import-btn" class="button button-primary">Importar Productos</button>
  <div id="aster-loader" style="display: none;">
    <img src="<?php echo plugin_dir_url(__FILE__) . '../assets/img/loader.gif'; ?>" alt="Cargando...">
  </div>
  <div id="aster-import-result"></div>
</div>