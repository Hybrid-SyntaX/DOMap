<?php

/*
 *
 aside { border: 1px solid #ccc;box-shadow: 0px 0px 3px 3px #ccc; } .list { list-style: none; }
 */

class CSSParser {
	private $_css;
	private $_cssRules;

	public function __construct($css = null) {
		//$css = '/* LABLABLA */ aside { border: 1px solid #ccc; /* shit */box-shadow: 0px 0px 3px 3px #ccc; } .list { list-style: none; }';
		if (isset($css)) {

			$this -> _css = $css;

			if (is_null($this -> _cssRules))
				$this -> _cssRules = $this -> _parseCSS($css);
		}

	}

	private function _parseCSS($css) {
		$css = preg_replace("/\\/\\*.*\\*\\//", '', $css);

		preg_match_all('/([^{}]*)/', $css, $matches, PREG_PATTERN_ORDER);
		$matches = array_values(array_filter($matches[0]));
		$cssRules = array();

		if (count($matches) == 1) {
			$cssRule = new stdClass();
			$cssRule -> declaration = trim($matches[0]);
			array_push($cssRules, $cssRule);
		} else {

			for ($i = 0; $i < count($matches); $i += 2) {
				$cssRule = new stdClass();
				$cssRule -> selector = trim($matches[$i]);

				if ($i + 1 < count($matches))
					$cssRule -> declaration = trim($matches[$i + 1]);
				array_push($cssRules, $cssRule);
			}
		}
		return $cssRules;
	}

	private function _processRule($line) {
		$cssRuleArray = explode(':', trim($line));
		$cssRule = new stdClass();
		$cssRuleArray = array_filter($cssRuleArray);

		if (isset($cssRuleArray[0]) && isset($cssRuleArray[1])) {

			$cssRule -> name = $cssRuleArray[0];
			$cssRule -> value = $cssRuleArray[1];
		}

		return $cssRule;
	}

	public function getCSSRule($name, $css = null) {

		$cssRules = explode(';', $css);
		foreach ($cssRules as $line) {
			$cssRule = $this -> _processRule($line);
			if (isset($cssRule -> name) && $name == $cssRule -> name)
				return $cssRule;
		}
	}

}
?>