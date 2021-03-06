<?php

/**
 * @file
 * Contains \Drupal\migrate\Plugin\migrate\source\d6\Variable.
 */

namespace Drupal\migrate\Plugin\migrate\source;

use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\Plugin\migrate\source\d6\Drupal6SqlBase;

/**
 * Drupal 6 variable source from database.
 *
 * @PluginID("drupal6_variable")
 */
class D6Variable extends Drupal6SqlBase {

  /**
   * The variable names to fetch.
   *
   * @var array
   */
  protected $variables;

  function __construct(array $configuration, $plugin_id, array $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->variables = $this->configuration['variables'];
  }

  protected function runQuery() {
    return new \ArrayIterator(array(array_map('unserialize', $this->query()->execute()->fetchAllKeyed())));
  }

  public function count() {
    return intval($this->query()->countQuery()->execute()->fetchField() > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return drupal_map_assoc($this->variables);
  }

  /**
   * {@inheritdoc}
   */
  function query() {
    return $this->getDatabase()
      ->select('variable', 'v')
      ->fields('v', array('name', 'value'))
      ->condition('name', $this->variables, 'IN');
  }
}
