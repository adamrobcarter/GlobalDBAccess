<?php

$wgExtensionCredits['other'][] = array(
		'path' => __FILE__,
		'name' => 'GlobalDBAccess',
		'author' => 'Adam Carter/UltrasonicNXT',
		'url' => '',
		'description' => 'Allows easy access to DBs on other wikis of a farm',
		'version'  => 1.0,
);

class GlobalDB {
	
	public $wiki;
	public $slave;
	public $master;
	
	function __construct( $wiki ){
		$this -> wiki = $wiki;
		$this -> slave = $this -> getSlave();
		$this -> master = $this -> getMaster();
	}
	
	function getSlave(){
		return $this -> getDB( DB_SLAVE, $this -> wiki );
	}
	
	function getMaster( ){
		return $this -> getDB( DB_MASTER, $this -> wiki );
	}
	
	function getDB( $dbtype, $wiki ){
		return wfGetDB( $dbtype, array(), $this -> wiki );
	}
	
	static function selectGlobal( array $wikis,
									$table,
									$vars,
									$conds = '',
									$fname = __METHOD__,
									$options = array(),
									$join_conds = array() 
								  ){
		$dbs = array();
		foreach( $wikis as $wiki ){
			$db = new GlobalDB( $wiki );
			$dbs[$wiki] = $db -> getSlave();
		}
		$wikiresults = array();
		foreach( $dbs as $wiki => $db ){
			$result = $db -> select( $table, $vars, $conds, $fname, $options, $join_conds );
			$wikiresults[$wiki] = $result;
		}
		return new GlobalResultWrapper( $wikiresults );
	}
}
/*
 * Not really like resultwrapper at all, resultwrapper relies heavily
 * on using the db object, which we cannot with multiple dbs, but you
 * can just about use it in the same way as resultwrapper
 */
class GlobalResultWrapper implements Iterator {
	var $db, $result, $pos = 0, $currentRow = null;

	function __construct( $wikiResults ) {

		foreach( $wikiResults as $wiki => $aResult ){
			foreach($aResult as $row){
				$row -> wiki = $wiki;
				$this -> result[] = $row;
			}
		}
	}
	
	function numRows(){
		return count( $this -> result );
	}

	/*********************
	 * Iterator functions
	* Note that using these in combination with the non-iterator functions
	* above may cause rows to be skipped or repeated.
	*/

	function rewind() {
		$this->pos = 0;
		$this->currentRow = null;
	}

	function current() {
		if ( is_null( $this->currentRow ) ) {
			$this->next();
		}
		return $this->currentRow;
	}

	function key() {
		return $this->pos;
	}

	function next() {
		$this->pos++;
		$this->currentRow = $this->result[$this->pos];
		return $this->currentRow;
	}

	function valid() {
		return $this->current() !== false;
	}
}