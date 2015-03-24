<?php
/**
 * Created by PhpStorm.
 * User: Kyriakos
 * Date: 18/08/2014
 * Time: 07:57
 */

namespace Kyriakos\TableRow;

class Polygon {

	public $points = [ [ 0, 0 ] ];


	public function  __construct( $arr = null ) {
		if ( $arr != null ) {
			$this->points = $arr;
		}
	}

	public function __toString() {
		$str = 'POLYGON((';
		foreach ( $this->points as $p ) {
			$str .= $p[0] . ' ' . $p[1] . ',';
		}
		$str = substr( $str, 0, strlen( $str ) - 1 ) . '))';

		return $str;
	}

	static public function reverseLatLng($a) {
		$o = [];

		foreach($a as $p) {
			$o[] = [$p[1],$p[0]];
		}

		return $o;
	}

	static public function fromArray( $a ) {
		$p = new Polygon();
		$p->points = $a;
		return $p;
	}

	static public function fromString( $s ) {
		$p = new Polygon();
		$s = strtoupper( $s );
		$s = str_replace( [ 'POLYGON((', '))' ], [ '', '' ], $s );
		$pairs = explode( ',', $s );

		$points = [ ];
		foreach ( $pairs as $pair ) {
			$pair = explode( ' ', $pair );
			$points[] = $pair;
		}

		$p->points = $points;
		return $p;
	}

}