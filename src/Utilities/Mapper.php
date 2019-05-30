<?php

namespace Creationmedia\Utilities;

class Mapper extends \DB\SQL\Mapper {

  public function save($saveVirtual = FALSE) {
    parent::save();
    if ($saveVirtual) {
      $this->saveVirtual();
    }
  }

  public function saveVirtual() {
    foreach ($this->adhoc as $k => $v) {

      if ($v['changed']) {
        $info = $this->getTableFromExpr($v['expr'], $k);
        if ($info) {
          $vt = new Mapper($this->db, $info[0]);
          $vt->load([sprintf("%s=?", $info[1]), $this->id]);
          if ($vt->dry()) {
            $vt->$info[1] = $this->id;
          }
          $vt->$k = $v['value'];
          $vt->save();
        }
      }
    }
  }

  private function getTableFromExpr($sql, $key) {
    $sql = trim(trim($sql, "()"));
    $sql = explode(' ', $sql);
    if (count($sql) < 6) {
      return FALSE;
    }
    if (strtoupper($sql[0]) != 'SELECT') {
      //only works for selects
      return FALSE;
    }
    if (strtolower($sql[1]) != strtolower($key)) {
      //mismatch
      return FALSE;
    }
    if (strtoupper($sql[2]) != 'FROM') {
      //only works for single items
      return FALSE;
    }

    if (strtoupper($sql[4]) != 'WHERE') {
      //only works for single tables
      return FALSE;
    }
    $rest = array_slice($sql, 5);

    $rest = implode(' ', $rest);

    return $this->extractForeignKey($rest);

  }

  private function extractForeignKey($str) {
    //lose everytinhg that is nt in the key = key seq
    $strArray = explode('LIMIT', $str); //strip limit
    $strArray = explode('OFFSET', $strArray[0]); //strip offset
    $strArray = explode('ORDER', $strArray[0]); //strip ORDER
    $str = $strArray[0];
    $bits = explode('=', $str);
    $left = explode('.', trim($bits[0]));
    $right = explode('.', trim($bits[1]));
    $ct = trim($this->table, '` ');

    if ($left[0] = $ct) {
      //we want the right side
      return $right;
    }
    else {
      return $left;
    }
  }

  /**
   *   Assign value to field
   *
   * @param $key string
   * @param $val scalar
   **@return scalar
   */
  public function set($key, $val) {
    if (array_key_exists($key, $this->fields)) {
      $val = is_null($val) && $this->fields[$key]['nullable'] ?
        NULL : $this->db->value($this->fields[$key]['pdo_type'], $val);
      if ($this->fields[$key]['value'] !== $val ||
        $this->fields[$key]['default'] !== $val && is_null($val)
      ) {
        $this->fields[$key]['changed'] = TRUE;
      }
      return $this->fields[$key]['value'] = $val;
    }
    // adjust result on existing expressions
    if (isset($this->adhoc[$key])) {
      $this->adhoc[$key]['value'] = $val;
      $this->adhoc[$key]['changed'] = TRUE;
    }
    else {
      // Parenthesize expression in case it's a subquery
      $this->adhoc[$key] = [
        'expr' => '(' . $val . ')',
        'value' => NULL,
        'changed' => FALSE,
      ];
    }
    return $val;
  }
}