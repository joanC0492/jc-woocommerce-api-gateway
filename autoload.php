<?php
$includes_path = __DIR__ . '/includes';

$files_to_include = [
  'class-jc-helper.php',
  'class-jc-api.php',
  'class-jc-gateway.php',
  'class-jc-notifications.php',
];

foreach ($files_to_include as $file) {
  $file_path = $includes_path . '/' . $file;
  if (file_exists($file_path)) {
    require_once $file_path;
  }
}
?>