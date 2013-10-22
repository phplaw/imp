<?php

/**
 * @file
 * Contains \Drupal\migrate\Tests\FakeCondition.
 */

namespace Drupal\migrate\Tests;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Database\Query\PlaceholderInterface;

class FakeCondition extends Condition {

  protected $conjuction;

  public function __construct($conjuction = 'AND') {
    $this->conjuction = $conjuction;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function compile(Connection $connection, PlaceholderInterface $queryPlaceholder) {
    throw new \Exception('Fake conditions can not be compiled.');
  }

  /**
   * {@inheritdoc}
   */
  public function conditionGroupFactory($conjunction = 'AND') {
    return new FakeCondition($conjunction);
  }

  /**
   * Resolves conditions by removing non-matching rows.
   *
   * @param array $rows
   */
  public function resolve(array &$rows) {
    foreach ($rows as $k => $row) {
      if (!$this->matchRow($row, $this)) {
        unset($rows[$k]);
      }
    }
  }

  protected function matchRow($row, FakeCondition $condition) {
    $and = $condition->conjuction == 'AND';
    $match = TRUE;
    foreach ($condition->conditions as $condition) {
      $match = $this->match($row, $condition);
      if ($and != $match) {
        break;
      }
    }
    // 1. AND, MATCH: the loop finished.
    // 2. AND, !MATCH: the loop terminates at first such, there is no match.
    // 3. OR, MATCH: the loop terminated, we have a match.
    // 4. OR, !$MATCH, the loop finished, there is no match.
    return $match;
  }

  protected function match($row, $condition) {
    if ($condition instanceof FakeCondition) {
      return $this->matchRow($row, $condition);
    }
    switch ($condition['operator']) {
      case '=': return $row[$condition['field']] == $condition['value'];
      case '<=': return $row[$condition['field']] <= $condition['value'];
      case '>=': return $row[$condition['field']] >= $condition['value'];
      case '!=': return $row[$condition['field']] != $condition['value'];
      case '<>': return $row[$condition['field']] != $condition['value'];
      case '<': return $row[$condition['field']] < $condition['value'];
      case '>': return $row[$condition['field']] > $condition['value'];
      case 'IN': return in_array($row[$condition['field']], $condition['value']);
      default: throw new \Exception(sprintf('operator %s is not supported', $condition['operator']));
    }
  }
}