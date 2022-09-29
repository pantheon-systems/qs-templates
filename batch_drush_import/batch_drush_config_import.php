<?php
// Import all config changes.
echo "Starting config import tasks...\n";

// Assign threads
$threads = '5';

$cmd = 'timeout --preserve-status -k 15 75 /code/web/private/scripts/batch-import.sh ' . $threads;

passthru($cmd . ' 2>&1', $exit);

if (0 !== $exit) {
  trigger_error(sprintf('Command "drush config-import -y" exit status: %s', $exit), E_USER_ERROR);
  $message = "Error running config import tasks.\n";
  pantheon_raise_dashboard_error($message, true);
}

echo "Config import tasks complete.\n";

/**
 * Function to report an error on the Pantheon dashboard
 */
function pantheon_raise_dashboard_error($reason = 'Unknown failure', $extended = FALSE) {
  // Make creative use of the error reporting API
  $data = array(
    'file' => 'Config Import',
    'line' => 'Error',
    'type' => 'error',
    'message' => $reason
  );
  $params = http_build_query($data);
  $result = pantheon_curl('https://api.live.getpantheon.com/sites/self/environments/self/events?' . $params, NULL, 8443, 'POST');
  error_log("Config import workflow failed to complete - $reason");
  // Dump additional debug info into the error log
  if ($extended) {
    error_log(print_r($extended, 1));
  }
  die("Config import failed - $reason");
}






