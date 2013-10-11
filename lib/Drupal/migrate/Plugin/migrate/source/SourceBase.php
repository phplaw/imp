<?php

/**
 * @file
 * Contains \Drupal\migrate\Plugin\migrate\source\SourceBase.
 */

namespace Drupal\migrate\Plugin\migrate\source;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\migrate\Entity\Migration;
use Drupal\migrate\Plugin\MigratePluginManager;
use Drupal\migrate\Plugin\MigrateSourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class SourceBase extends PluginBase implements ContainerFactoryPluginInterface, MigrateSourceInterface {

  /**
   * The current row from the quey
   *
   * @var \stdClass
   */
  protected $currentRow;

  /**
   * The primary key of the current row
   *
   * @var array
   */
  protected $currentKey;

  /**
   * Number of rows intentionally ignored (prepareRow() returned FALSE)
   *
   * @var int
   */
  protected $numIgnored = 0;

  /**
   * Number of rows we've at least looked at.
   *
   * @var int
   */
  protected $numProcessed = 0;

  /**
   * Information on the highwater mark for the current migration, if any.
   *
   * @var array
   */
  protected $highwaterField;

  /**
   * The highwater mark at the beginning of the import operation.
   *
   * @var
   */
  protected $originalHighwater = '';

  /**
   * List of source IDs to process.
   *
   * @var array
   */
  protected $idList = array();

  /**
   * Whether this instance should cache the source count.
   *
   * @var boolean
   */
  protected $cacheCounts = FALSE;

  /**
   * Key to use for caching counts.
   *
   * @var string
   */
  protected $cacheKey;

  /**
   * Whether this instance should not attempt to count the source.
   *
   * @var boolean
   */
  protected $skipCount = FALSE;

  /**
   * If TRUE, we will maintain hashed source rows to determine whether incoming
   * data has changed.
   *
   * @var bool
   */
  protected $trackChanges = FALSE;

  /**
   * By default, next() will directly read the map row and add it to the data
   * row. A source plugin implementation may do this itself (in particular, the
   * SQL source can incorporate the map table into the query) - if so, it should
   * set this TRUE so we don't duplicate the effort.
   *
   * @var bool
   */
  protected $mapRowAdded = FALSE;

  /**
   * @var array
   */
  protected $sourceKey;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * @var \MigrateMap
   */
  protected $activeMap;

  public function getCurrentKey() {
    return $this->currentKey;
  }

  public function getIgnored() {
    return $this->numIgnored;
  }

  public function getProcessed() {
    return $this->numProcessed;
  }

  /**
   * Reset numIgnored back to 0.
   */
  public function resetStats() {
    $this->numIgnored = 0;
  }

  /**
   * Return a count of available source records, from the cache if appropriate.
   * Returns -1 if the source is not countable.
   *
   * @param boolean $refresh
   * @return int
   */
  public function count($refresh = FALSE) {
    if ($this->skipCount) {
      return -1;
    }

    if (!isset($this->cacheKey)) {
      $this->cacheKey = md5((string)$this);
    }

    // If a refresh is requested, or we're not caching counts, ask the derived
    // class to get the count from the source.
    if ($refresh || !$this->cacheCounts) {
      $count = $this->computeCount();
      $this->cache->set($this->cacheKey, $count, 'cache');
    }
    else {
      // Caching is in play, first try to retrieve a cached count.
      $cache_object = $this->cache->get($this->cacheKey, 'cache');
      if (is_object($cache_object)) {
        // Success
        $count = $cache_object->data;
      }
      else {
        // No cached count, ask the derived class to count 'em up, and cache
        // the result
        $count = $this->computeCount();
        $this->cache->set($this->cacheKey, $count, 'cache');
      }
    }
    return $count;
  }

  /**
   * Class constructor.
   *
   * @param array $configuration
   *  Optional array of options.
   * @param string $plugin_id
   * @param array $plugin_definition
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   * @param \Drupal\migrate\Entity\Migration $migration
   */
  function __construct(array $configuration, $plugin_id, array $plugin_definition, Migration $migration, CacheBackendInterface $cache,
                       MigratePluginManager $row_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cache = $cache;
    $this->migration = $migration;
    $this->rowManager = $row_manager;
    if (!empty($configuration['cache_counts'])) {
      $this->cacheCounts = TRUE;
    }
    if (!empty($configuration['skip_count'])) {
      $this->skipCount = TRUE;
    }
    if (!empty($configuration['cache_key'])) {
      $this->cacheKey = $configuration['cache_key'];
    }
    if (!empty($configuration['track_changes'])) {
      $this->trackChanges = $configuration['track_changes'];
    }
    if (!empty($configuration['highwater_field'])) {
      $this->highwaterField = $configuration['highwater_field'];
    }
    if (!empty($configuration['original_highwater'])) {
      $this->originalHighwater = $configuration['original_highwater'];
    }
    if (!empty($configuration['idList'])) {
      $this->idList = $configuration['idList'];
    }
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $configuration['migration'],
      $container->get('cache.migrate'),
      $container->get('plugin.manager.migrate.row')
    );
  }

  /**
   * Default implementations of Iterator methods - many derivations will find
   * these adequate and will only need to implement rewind() and next()
   */

  /**
   * Implementation of Iterator::current() - called when entering a loop
   * iteration, returning the current row
   */
  public function current() {
    return $this->currentRow;
  }

  /**
   * Implementation of Iterator::key - called when entering a loop iteration, returning
   * the key of the current row. It must be a scalar - we will serialize
   * to fulfill the requirement, but using getCurrentKey() is preferable.
   */
  public function key() {
    return serialize($this->currentKey);
  }

  /**
   * Implementation of Iterator::valid() - called at the top of the loop, returning
   * TRUE to process the loop and FALSE to terminate it
   */
  public function valid() {
    return !is_null($this->currentRow);
  }

  /**
   * Implementation of Iterator::rewind() - subclasses of MigrateSource should
   * implement performRewind() to do any class-specific setup for iterating
   * source records.
   */
  public function rewind() {
    $this->activeMap = $this->migration->getMap();
    $this->numProcessed = 0;
    $this->numIgnored = 0;
    $this->highwaterField = $this->migration->getHighwaterField();
    if (!empty($this->highwaterField)) {
      $this->originalHighwater = $this->migration->getHighwater();
    }
    if ($this->migration->getOption('idlist')) {
      $this->idList = explode(',', $this->migration->getOption('idlist'));
    }
    else {
      $this->idList = array();
    }
    migrate_instrument_start(get_class($this) . ' performRewind');
    $this->performRewind();
    migrate_instrument_stop(get_class($this) . ' performRewind');
    $this->next();
  }

  /**
   * Implementation of Iterator::next() - subclasses of MigrateSource should
   * implement getNextRow() to retrieve the next valid source rocord to process.
   */
  public function next() {
    $this->currentKey = NULL;
    $this->currentRow = NULL;

    migrate_instrument_start(get_class($this) . ' getNextRow');
    while ($row = $this->getNextRow()) {
      $row_plugin = $this->rowManager->createInstance($this->migration->row, $row);
      migrate_instrument_stop(get_class($this) . ' getNextRow');

      // Populate the source key for this row
      $this->currentKey = $this->migration->prepareKey(
        $this->activeMap->getSourceKey(), $row);

      // Pick up the existing map row, if any, unless getNextRow() did it.
      if (!$this->mapRowAdded) {
        $map_row = $this->activeMap->getRowBySource($this->currentKey);
        // Add map info to the row, if present
        if ($map_row) {
          foreach ($map_row as $field => $value) {
            $field = 'migrate_map_' . $field;
            $row->$field = $value;
          }
        }
      }

      // First, determine if this row should be passed to prepareRow(), or
      // skipped entirely. The rules are:
      // 1. If there's an explicit idlist, that's all we care about (ignore
      //    highwaters and map rows).
      $prepared = FALSE;
      if (!empty($this->idList)) {
        if (in_array(reset($this->currentKey), $this->idList)) {
          // In the list, fall through.
        }
        else {
          // Not in the list, skip it
          continue;
        }
      }
      // 2. If the row is not in the map (we have never tried to import it
      //    before), we always want to try it.
      elseif (!isset($row->migrate_map_sourceid1)) {
        // Fall through
      }
      // 3. If the row is marked as needing update, pass it.
      elseif ($row->migrate_map_needs_update == \MigrateMap::STATUS_NEEDS_UPDATE) {
        // Fall through
      }
      // 4. At this point, we have a row which has previously been imported and
      //    not marked for update. If we're not using highwater marks, then we
      //    will not take this row. Except, if we're looking for changes in the
      //    data, we need to go through prepareRow() before we can decide to
      //    skip it.
      elseif (empty($this->highwaterField)) {
        if ($this->trackChanges) {
          if ($this->prepareRow($row) !== FALSE) {
            if ($this->dataChanged($row)) {
              // This is a keeper
              $this->currentRow = $row;
              break;
            }
            else {
              // No change, skip it.
              continue;
            }
          }
          else {
            // prepareRow() told us to skip it.
            continue;
          }
        }
        else {
          // No highwater and not tracking changes, skip.
          continue;
        }
      }
      // 5. The initial highwater mark, before anything is migrated, is ''. We
      //    want to make sure we don't mistakenly skip rows with a highwater
      //    field value of 0, so explicitly handle '' here.
      elseif ($this->originalHighwater === '') {
        // Fall through
      }
      // 6. So, we are using highwater marks. Take the row if its highwater
      //    field value is greater than the saved mark, otherwise skip it.
      else {
        // Call prepareRow() here, in case the highwaterField needs preparation
        if ($this->prepareRow($row) !== FALSE) {
          if ($row->{$this->highwaterField['name']} > $this->originalHighwater) {
            $this->currentRow = $row;
            break;
          }
          else {
            // Skip
            continue;
          }
        }
        $prepared = TRUE;
      }

      // Allow the Migration to prepare this row. prepareRow() can return boolean
      // FALSE to ignore this row.
      if (!$prepared) {
        if ($this->prepareRow($row) !== FALSE) {
          // Finally, we've got a keeper.
          $this->currentRow = $row;
          break;
        }
        else {
          $this->currentRow = NULL;
        }
      }
    }
    migrate_instrument_stop(get_class($this) . ' getNextRow');
    if (!$this->currentRow) {
      $this->currentKey = NULL;
    }
  }

  /**
   * Give the calling migration a shot at manipulating, and possibly rejecting,
   * the source row.
   *
   * @param $row
   * @return bool
   *  FALSE if the row is to be skipped.
   */
  protected function prepareRow($row) {
    migrate_instrument_start(get_class($this->migration) . ' prepareRow');
    $return = $this->migration->prepareRow($row);
    migrate_instrument_stop(get_class($this->migration) . ' prepareRow');
    // We're explicitly skipping this row - keep track in the map table
    if ($return === FALSE) {
      // Make sure we replace any previous messages for this item with any
      // new ones.
      $this->migration->getMap()->delete($this->currentKey, TRUE);
      $this->migration->saveQueuedMessages();
      $this->migration->getMap()->saveIDMapping($row, array(),
        \MigrateMap::STATUS_IGNORED, $this->migration->rollbackAction);
      $this->numIgnored++;
      $this->currentRow = NULL;
      $this->currentKey = NULL;
    }
    else {
      $return = TRUE;
      // When tracking changed data, We want to quietly skip (rather than
      // "ignore") rows with changes. The caller needs to make that decision,
      // so we need to provide them with the necessary information (before and
      // after hashes).
      if ($this->trackChanges) {
        $unhashed_row = clone ($row);
        // Remove all map data, otherwise we'll have a false positive on the
        // second import (attempt) on a row.
        foreach ($unhashed_row as $field => $data) {
          if (strpos($field, 'migrate_map_') === 0) {
            unset($unhashed_row->$field);
          }
        }
        $row->migrate_map_original_hash = $row->migrate_map_hash;
        $row->migrate_map_hash = $this->hash($unhashed_row);
      }
      else {
        $row->migrate_map_hash = '';
      }
    }

    $this->numProcessed++;
    return $return;
  }

  /**
   * Determine whether this row has changed, and therefore whether it should
   * be processed.
   *
   * @param $row
   *
   * @return bool
   */
  protected function dataChanged($row) {
    if ($row->migrate_map_original_hash != $row->migrate_map_hash) {
      $return = TRUE;
    }
    else {
      $return = FALSE;
    }

    return $return;
  }

  /**
   * Generate a hash of the source row.
   *
   * @param $row
   *
   * @return string
   */
  protected function hash($row) {
    migrate_instrument_start('MigrateSource::hash');
    $hash = md5(serialize($row));
    migrate_instrument_stop('MigrateSource::hash');
    return $hash;
  }

  /**
   * Derived classes must implement fields(), returning a list of available
   * source fields.
   *
   * @return array
   *  Keys: machine names of the fields (to be passed to addFieldMapping)
   *  Values: Human-friendly descriptions of the fields.
   */
  abstract public function fields();

  /**
   * Derived classes must implement computeCount(), to retrieve a fresh count of
   * source records.
   *
   * @return int
   */
  abstract function computeCount();

  /**
   * @return \stdClass
   */
  abstract function getNextRow();

  abstract function performRewind();

}