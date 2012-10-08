<?php 
namespace XHProf_UI\Report;

abstract class Driver {

	protected $_ui = array();
	protected $_data = array();

	/**
	 * Analyze raw data & generate the profiler report
	 * abstract class
	 */
	abstract public function __construct();
	
	abstract public function render();

	protected function _bind($data) {
		foreach ($data as $key => $value) {
			$this->_data[$key] =& $value;
		}
	}

}