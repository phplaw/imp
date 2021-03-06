<?php

/**
 * @file
 * Contains \Drupal\migrate\Tests\FakeSelect.
 */


namespace Drupal\migrate\Tests;

use Drupal\Core\Database\Schema;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Database\Query\PlaceholderInterface;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\Query\SelectInterface;

class FakeDatabaseSchema extends Schema {

  /**
   * As set on MigrateSqlSourceTestCase::databaseContents.
   */
  protected $databaseContents;

  public function __construct($connection, $database_contents) {
    parent::__construct($connection);
    // @todo Maybe we can generate an internal representation.
    $this->databaseContents = $database_contents;
  }

  public function tableExists($table) {
    return in_array($table, array_keys($this->databaseContents));
  }

  public function prefixNonTable($table) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  protected function buildTableNameCondition($table_name, $operator = '=', $add_prefix = TRUE) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  protected function getPrefixInfo($table = 'default', $add_prefix = TRUE) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function addField($table, $field, $spec, $keys_new = array()) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function addIndex($table, $name, $fields) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function addPrimaryKey($table, $fields) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function addUniqueKey($table, $name, $fields) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function changeField($table, $field, $field_new, $spec, $keys_new = array()) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function __clone() {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function copyTable($source, $destination) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function createTable($name, $table) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function dropField($table, $field) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function dropIndex($table, $name) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function dropPrimaryKey($table) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function dropTable($table) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function dropUniqueKey($table, $name) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function fieldExists($table, $column) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function fieldNames($fields) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function fieldSetDefault($table, $field, $default) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function fieldSetNoDefault($table, $field) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function findTables($table_expression) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function getFieldTypeMap() {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function indexExists($table, $name) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function nextPlaceholder() {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function prepareComment($comment, $length = NULL) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function renameTable($table, $new_name) {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  public function uniqueIdentifier() {
    throw new \Exception(sprintf('Unsupported method "%s"', __METHOD__));
  }

  /**
   * Provide meta information about this battery of tests.
   */
  public static function getInfo() {
    return array(
      'name' => 'Fake database schema',
      'description' => 'Tests for fake database schema plugin.',
      'group' => 'Migrate',
    );
  }

}
