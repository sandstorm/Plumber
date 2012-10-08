<?php 
namespace XHProf_UI;

class Utils {
	
	/**
	 * Type definitions for URL params
	 */
	const STRING_PARAM = 1;
	const UINT_PARAM   = 2;
	const FLOAT_PARAM  = 3;
	const BOOL_PARAM   = 4;

	/**
	 * Initialize params from URL query string. The function
	 * creates globals variables for each of the params
	 * and if the URL query string doesn't specify a particular
	 * param initializes them with the corresponding default
	 * value specified in the input.
	 *
	 * @params array $params An array whose keys are the names
	 *                       of URL params who value needs to
	 *                       be retrieved from the URL query
	 *                       string. PHP globals are created
	 *                       with these names. The value is
	 *                       itself an array with 2-elems (the
	 *                       param type, and its default value).
	 *                       If a param is not specified in the
	 *                       query string the default value is
	 *                       used.
	 */
	public static function parse_params($params) {

		/* Create variables specified in $params keys, init defaults */
		foreach ($params as $k => &$v) {
			$p = Utils::_get_param($k, $v[1]);
			
			switch ($v[0]) {
				case Utils::STRING_PARAM:
					$v = $p;
				break;
				case Utils::UINT_PARAM:
					$v = filter_var($p, FILTER_VALIDATE_INT);
				break;
				case Utils::FLOAT_PARAM:
					$v = filter_var($p, FILTER_VALIDATE_FLOAT);
				break;
				case Utils::BOOL_PARAM:
					$v = filter_var($p, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
				break;
				default:
					throw new Exception('Invalid param type passed to xhprof_param_init: '.$v[0]);
				break;
			}

		}
		
		return $params;
	}
	
	/**
	 * Extracts value for string param $param from query
	 * string. If param is not specified, return the
	 * $default value.
	 *
	 * @param string   name of the URL query string param
	 */
	private static function _get_param($name, $default = '') {
		if (isset($_GET[$name])) {
			return $_GET[$name];
		
		} elseif (isset($_POST[$name])) {
			return $_POST[$name];

		} elseif (isset($_REQUEST[$name])) {
			return $_REQUEST[$name];
		}

		return $default;
	}
	
	

	public static function list_runs($dir) {
		echo "<hr/>Existing runs:\n<ul>\n";

		foreach (glob("{$dir}/*") as $file) {
			list($run, $namespace) = explode('.', basename($file));

			echo '<li><a href="?run='.htmlentities($run).'&namespace='.htmlentities($namespace).'">'.htmlentities(basename($file)).'</a><small> '.date("Y-m-d H:i:s", filemtime($file))."</small></li>\n";
		}

		echo "</ul>\n";
	}
	
	public static function sort(&$data, $ui) {
		uasort($data, function($a, $b) use ($ui) {
			return Utils::sort_cbk($a, $b, $ui);
		});
	}
	
	/**
	 * Callback comparison operator (passed to usort() for sorting array of
	 * tuples) that compares array elements based on the sort column
	 * specified in $sort_col (global parameter).
	 */
	public static function sort_cbk($a, $b, \XHProf_UI $ui) {
		if ($ui->sort == 'fn') {
			// case insensitive ascending sort for function names
			$left = strtoupper($a['fn']);
			$right = strtoupper($b['fn']);

		} else {
			// descending sort for all others
			$left = $a[$ui->sort];
			$right = $b[$ui->sort];

			// if diff mode, sort by absolute value of regression/improvement
			if ($ui->diff_mode) {
				$left = abs($left);
				$right = abs($right);
			}
		}

		return ($left == $right) ? 0 : (($left > $right) ? -1 : 1);
	}
	
	/**
	 * Computes percentage for a pair of values, and returns it
	 * in string format.
	 */
	function pct($a, $b) {
		if ($b == 0) {
			return 'N/A';
		} else {
			$res = (round(($a * 1000 / $b)) / 10);
			return $res;
		}
	}

	/**
	 * Given a number, returns the td class to use for display.
	 *
	 * For instance, negative numbers in diff reports comparing two runs (run1 & run2)
	 * represent improvement from run1 to run2. We use green to display those deltas,
	 * and red for regression deltas.
	 */
	static function td_class($num, $bold, $diff_mode = false) {
		if ($bold) {
			if ($diff_mode) {
				if ($num <= 0) {
					$class = 'green'; // green (improvement)
				} else {
					$class = 'red'; // red (regression)
				}
			} else {
				$class = 'blue'; // blue
			}
		} else {
			$class = 'black';  // default (black)
		}

		return $class;
	}

	/**
	 * Prints a <td> element with a numeric value.
	 */
	public static function td_num($num, $fmt_func, $bold = false, $diff_mode = false) {
		$class = Utils::td_class($num, $bold, $diff_mode);

		if (!empty($fmt_func)) {
			$num = call_user_func($fmt_func, $num);
		}

		return "<td class=\"right $class\">$num</td>";
	}

	/**
	 * Prints a <td> element with a pecentage.
	 */
	public static function td_pct($numer, $denom, $bold = false, $diff_mode = false) {
		$class = Utils::td_class($numer, $bold, $diff_mode);

		if ($denom == 0) {
			$pct = "N/A%";
		} else {
			$pct = Utils::percent_format($numer / abs($denom));
		}

		return "<td class=\"right $class\">$pct</td>";
	}
	

	public static function print_symbol_summary($symbol_info, $stat, $base) {

		$val = $symbol_info[$stat];
		$desc = str_replace("<br>", " ", stat_description($stat));

		print("$desc: </td>");
		print(number_format($val));
		print(" (" . pct($val, $base) . "% of overall)");
		if (substr($stat, 0, 4) == "excl") {
			$func_base = $symbol_info[str_replace("excl_", "", $stat)];
			print(" (" . pct($val, $func_base) . "% of this function)");
		}
		print("<br>");
	}



	
	
	/*
	 * Formats call counts for XHProf reports.
	 *
	 * Description:
	 * Call counts in single-run reports are integer values.
	 * However, call counts for aggregated reports can be
	 * fractional. This function will print integer values
	 * without decimal point, but with commas etc.
	 *
	 *   4000 ==> 4,000
	 *
	 * It'll round fractional values to decimal precision of 3
	 *   4000.1212 ==> 4,000.121
	 *   4000.0001 ==> 4,000
	 *
	 */
	public static function count_format($num) {
		$num = round($num, 3);
		if (round($num) == $num) {
			return number_format($num);
		} else {
			return number_format($num, 3);
		}
	}

	public static function percent_format($s, $precision = 1) {
		return sprintf('%.'.$precision.'f%%', 100 * $s);
	}


	public static function safe_fn($symbol) {
		return str_replace('\\\\', '\\', $symbol);
	}
	
	
}