<?php

/**
 * @file
 * Contains \Drupal\migrate\Tests\RowTest.
 */

namespace Drupal\migrate\Tests;

use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Row;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for Row class.
 *
 * @group migrate
 */
class RowTest extends UnitTestCase {

  protected $test_source_ids = array(
    'nid' => 'Node ID',
  );
  protected $test_values = array(
    'nid' => 1,
    'title' => 'node 1',
  );
  protected $test_hash = '85795d4cde4a2425868b812cc88052ecd14fc912e7b9b4de45780f66750e8b1e';
  // after changing title value to 'new title'
  protected $test_hash_mod = '9476aab0b62b3f47342cc6530441432e5612dcba7ca84115bbab5cceaca1ecb3';

  public static function getInfo() {
    return array(
      'name' => 'Row class functionality',
      'description' => 'Tests Row class functionality.',
      'group' => 'Migrate',
    );
  }

  /**
   * Tests object creation: empty.
   */
  public function testRowWithoutData() {
    $row = new Row(array(), array());
    $this->assertSame(array(), $row->getSource(), 'Empty row');
  }

  /**
   * Tests object creation: basic.
   */
  public function testRowWithBasicData() {
    $row = new Row($this->test_source_ids, $this->test_values);
    $this->assertSame($this->test_values, $row->getSource(), 'Row with data, simple id.');
  }

  /**
   * Tests object creation: multiple source ids.
   */
  public function testRowWithMultipleSourceIds() {
    $multi_source_ids = $this->test_source_ids + array('vid' => 'Node revision');
    $multi_source_ids_values = $this->test_values + array('vid' => 1);
    $row = new Row($multi_source_ids, $multi_source_ids_values);
    $this->assertSame($multi_source_ids_values, $row->getSource(), 'Row with data, multifield id.');
  }

  /**
   * Tests object creation: invalid values.
   *
   * @expectedException Exception
   */
  public function testRowWithInvalidData() {
    $invalid_values = array(
      'title' => 'node X',
    );
    $row = new Row($this->test_source_ids, $invalid_values);
  }

  /**
   * Tests source inmutability after freeze.
   *
   * @expectedException Exception
   */
  public function testSourceFreeze() {
    $row = new Row($this->test_source_ids, $this->test_values);
    $row->rehash();
    $this->assertSame($this->test_hash, $row->getHash(), 'Correct hash.');
    $row->setSourceProperty('title', 'new title');
    $row->rehash();
    $this->assertSame($this->test_hash_mod, $row->getHash(), 'Hash changed correctly.');
    $row->freezeSource();
    $row->setSourceProperty('title', 'new title');
  }

  /**
   * Tests hashing.
   */
  public function testHashing() {
    $row = new Row($this->test_source_ids, $this->test_values);
    $this->assertSame('', $row->getHash(), 'No hash at creation');
    $row->rehash();
    $this->assertSame($this->test_hash, $row->getHash(), 'Correct hash.');
    $row->rehash();
    $this->assertSame($this->test_hash, $row->getHash(), 'Correct hash even doing it twice.');
    $test_id_map = array(
      'original_hash' => '',
      'hash' => '',
      'needs_update' => MigrateIdMapInterface::STATUS_NEEDS_UPDATE,
    );
    $row->setIdMap($test_id_map);
    $row->rehash();
    $this->assertSame($this->test_hash, $row->getHash(), 'Correct hash even if id_mpa have changed.');
    $row->setSourceProperty('title', 'new title');
    $row->rehash();
    $this->assertSame($this->test_hash_mod, $row->getHash(), 'Hash changed correctly.');
  }

}
