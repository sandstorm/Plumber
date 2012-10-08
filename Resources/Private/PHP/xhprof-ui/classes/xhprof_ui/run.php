<?php 
namespace XHProf_UI;

class Run {

	public $namespace = '';
	public $run_id = '';

	private $_dir = '';
	private $_suffix = 'xhprof';

	private $_file_format = ':run_id.:namespace';

	private $_data = array();

	public function __construct($dir, $namespace, $run_id) {

		$this->_dir = $dir;
		$this->namespace = $namespace;
		$this->run_id = $run_id;
	}

	private function file_name() {
		return rtrim($this->_dir, '/').'/'.strtr($this->_file_format, array(':run_id' => $this->run_id, ':namespace' => $this->namespace));
	}
	
	public function get_data() {
		if (count($this->_data)) {
			return $this->_data;
		}

		if (!file_exists($file_name = $this->file_name())) {
			return null;
		}

		return $this->_data = unserialize(file_get_contents($file_name));
	}

}