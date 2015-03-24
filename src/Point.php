<?php
/**
 * Created by PhpStorm.
 * User: Kyriakos
 * Date: 18/08/2014
 * Time: 08:51
 */

namespace Kyriakos\TableRow;

class Point {


	public $values = [ 0, 0 ];

	function __construct($lat=0,$lng =0) {
		$this->values[0] = $lat;
		$this->values[1] = $lng;
	}

	public function __toString() {
		return 'POINT(' . $this->values[0] . ' ' . $this->values[1] . ')';
	}

	static public function fromArray( $a ) {
		$p = new Point();
		$p->values = $a;
		return $p;
	}

	static public function fromString( $s ) {
		$p = new Point();
		$s = strtoupper( $s );
		$s = str_replace( [ 'POINT(', ')' ], [ '', '' ], $s );
		$pair = explode( ' ', $s );
		if (count($pair)<2) $pair = [0,0];

		$p->values = $pair;
		return $p;

	}
} 