<?php
/**
 * @file
 * Contains
 */

namespace Drupal\migrate;


interface MigrateMessageInterface {

  function display($message, $type = 'status');
}
