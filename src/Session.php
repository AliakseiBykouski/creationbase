<?php

namespace CreationMedia;

use DB\SQL\Mapper;

//! SQL-managed session handler

/*********
 * Class Session
 *
 * @package CreationMedia
 */

/**********
 * CREATE TABLE `sessions` (
 * `session_id` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
 * `data` text COLLATE utf8_unicode_ci,
 * `csrf` text COLLATE utf8_unicode_ci,
 * `ip` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
 * `agent` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
 * `stamp` int(11) DEFAULT NULL,
 * PRIMARY KEY (`session_id`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
 */
class Session extends Mapper {

  protected $sid;

  /**
   *   Open session
   *
   * @param $path string
   * @param $name string
   **@return TRUE
   */
  public function open($path, $name) {
    return TRUE;
  }

  /**
   *   Close session
   *
   * @return TRUE
   **/
  public function close() {
    return TRUE;
  }

  /**
   *   Return session data in serialized format
   *
   * @param $id string
   **@return string|FALSE
   */
  public function read($id) {
    if ($id != $this->sid) {
      $this->load(['session_id=?', $this->sid = $id]);
    }
    return $this->dry() ? '' : $this->get('data');
  }

  /**
   *   Write session data
   *
   * @param $id string
   * @param $data string
   **@return TRUE
   */
  public function write($id, $data) {
    $fw = \Base::instance();
    $sent = headers_sent();
    $headers = $fw->get('HEADERS');
    if ($id != $this->sid) {
      $this->load(['session_id=?', $this->sid = $id]);
    }
    $csrf = $fw->hash($fw->get('ROOT') . $fw->get('BASE')) . '.' .
      $fw->hash(mt_rand());
    $this->set('session_id', $id);
    $this->set('data', $data);
    $this->set('csrf', $sent ? $this->csrf() : $csrf);
    $this->set('ip', $fw->get('IP'));
    $this->set('agent',
      isset($headers['User-Agent']) ? $headers['User-Agent'] : '');
    $this->set('stamp', time());
    $this->save();
    return TRUE;
  }

  /**
   *   Destroy session
   *
   * @param $id string
   **@return TRUE
   */
  public function destroy($id) {
    $this->erase(['session_id=?', $id]);
    setcookie(session_name(), '', strtotime('-1 year'));
    unset($_COOKIE[session_name()]);
    header_remove('Set-Cookie');
    return TRUE;
  }

  /**
   *   Garbage collector
   *
   * @param $max int
   **@return TRUE
   */
  public function cleanup($max) {
    $this->erase(['stamp+?<?', $max, time()]);
    return TRUE;
  }

  /**
   *   Return anti-CSRF token
   *
   * @return string|FALSE
   **/
  public function csrf() {
    return $this->dry() ? TRUE : $this->get('csrf');
  }

  /**
   *   Return IP address
   *
   * @return string|FALSE
   **/
  public function ip() {
    return $this->dry() ? FALSE : $this->get('ip');
  }

  /**
   *   Return Unix timestamp
   *
   * @return string|FALSE
   **/
  public function stamp() {
    return $this->dry() ? FALSE : $this->get('stamp');
  }

  /**
   *   Return HTTP user agent
   *
   * @return string|FALSE
   **/
  public function agent() {
    return $this->dry() ? FALSE : $this->get('agent');
  }

  /**
   * Session constructor.
   *
   * @param string $table
   */
  public function __construct($table = 'sessions') {
    $f3 = \Base::instance();
    parent::__construct($f3->get('DB'), $table);
    session_set_save_handler(
      [$this, 'open'],
      [$this, 'close'],
      [$this, 'read'],
      [$this, 'write'],
      [$this, 'destroy'],
      [$this, 'cleanup']
    );
    register_shutdown_function('session_commit');
    session_start();
  }
}