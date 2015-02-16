<?php
/**
 * Created by PhpStorm.
 * User: Kyriakos
 * Date: 05/07/2014
 * Time: 15:40
 */

namespace Brainvial\TableRow;

use mysqli_result;
use mysqli;
use Iterator, Countable;

class TableRowIterator implements Iterator, Countable, \ArrayAccess {
	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Count elements of an object
	 * @link http://php.net/manual/en/countable.count.php
	 * @return int The custom count as an integer.
	 * </p>
	 * <p>
	 * The return value is cast to an integer.
	 */
	public function count() {
		return $this->resultSet->num_rows;
	}


	private $total = 0;
	/**
	 * @var \mysqli_result|null
	 */
	private $resultSet = null;
	/**
	 * @var string
	 */
	private $class = '';
	/**
	 * @var int
	 */
	private $position = - 1;

	private $currentObj = null;

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Return the current element
	 * @link http://php.net/manual/en/iterator.current.php
	 * @return mixed Can return any type.
	 */
	public function current() {
		if ( $this->currentObj == null ) {
			$this->next();
		}

		return $this->currentObj;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Move forward to next element
	 * @link http://php.net/manual/en/iterator.next.php
	 * @return void Any returned value is ignored.
	 */
	public function next() {
		$this->position ++;
		$class = $this->class;
		$d = $this->resultSet->fetch_row();
		$this->currentObj = new $class( $d[0] );
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Return the key of the current element
	 * @link http://php.net/manual/en/iterator.key.php
	 * @return mixed scalar on success, or null on failure.
	 */
	public function key() {
		return ( $this->position == - 1 ) ? 0 : $this->position;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Checks if current position is valid
	 * @link http://php.net/manual/en/iterator.valid.php
	 * @return boolean The return value will be casted to boolean and then evaluated.
	 * Returns true on success or false on failure.
	 */
	public function valid() {
		return ( ( $this->position < ( $this->resultSet->num_rows ) ) && ( $this->resultSet->num_rows != 0 ) );
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Rewind the Iterator to the first element
	 * @link http://php.net/manual/en/iterator.rewind.php
	 * @return void Any returned value is ignored.
	 */
	public function rewind() {
		$this->position = - 1;
		$this->currentObj = null;
		$this->resultSet->data_seek( 0 );
	}


	/**
	 * @param \mysqli_result $resultSet
	 * @param $class
	 */
	function __construct( $resultSet, $class ) {
		$this->position = 0;
		$this->resultSet = $resultSet;
		$this->class = $class;
		$this->total = $resultSet->num_rows;
	}


	public function offsetExists( $offset ) {
		return ( $offset >= 0 ) && ( $offset < $this->total );
	}

	public function offsetGet( $offset ) {
		$this->resultSet->data_seek( $offset );
		$this->position = $offset;
		$this->next();

		return $this->currentObj;
	}

	public function offsetSet( $offset, $value ) {
		// do nothing
	}

	public function offsetUnset( $offset ) {
		// do nothing
	}

	public function end() {
		return $this->offsetGet( $this->total - 1 );
	}
}