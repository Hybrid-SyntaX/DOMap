<?php

function dv($thing, $msg = null) {util\debug::dv($thing, $msg);
}
//FIXME: Some problem with parameters
class DOMap {

	private $originalTemplateString;
	/** @var DOMDocument */
	private $_domDoc;
	/** @var DOMNode */
	private $_node;
	private $_selector;
	private $_childSelector;
	private $_childIndex;
	private $_params;
	private $_param;
	private $_classes;
	private $_loadMode;

	public function __construct($in, $args = array()) {
		if (isset($args['params'])) {
			$this -> _params = $args['params'];
		}
		if (isset($args['domDoc'])) {
			$this -> _domDoc = $args['domDoc'];
		}
		if (isset($args['loadMode'])) {
			$this -> _loadMode = $args['loadMode'];
		} else
			$this -> _loadMode = 'html';

		if ($in instanceof DOMElement || $in instanceof DOMNodeList) {
			$this -> _node = $in;

		} else if ($in instanceof DOMap) {
			$this -> _node = $in -> getNode();
		} else if ($in instanceof stdClass) {
			$this -> _param = $in;
		} else if (is_string($in)) {
			$this -> originalTemplateString = $in;
			$this -> _load($in);
			// template string
		}

		if (!isset($this -> _params))
			$this -> _params = new ArrayObject();
	}

	public function Bind($object, $bind_to = null, $exclude = array()) {

		switch($bind_to) {
			case 'id' :
				$bindSelector = '#';
				break;
			case 'class' :
				$bindSelector = '.';
				break;
			case 'tag' :
				$bindSelector = '';
				break;
			case 'param' :
			default :
				$bindSelector = '@';
		}

		$this -> BoundObject = $object;
		$vars = get_object_vars($this -> BoundObject);

		while ($value = current($vars)) {
			if (!in_array(key($vars), $exclude)) {
				if ($bindSelector == 'class' || $bindSelector == 'tag')
					$this -> _($bindSelector . key($vars) . ':first') -> Text($value);
				else
					$this -> _($bindSelector . key($vars)) -> Text($value);
			}
			next($vars);
		}

		return $this;

	}

	private function copyAttributes($new_tag) {
		$new_tag -> nodeValue = $this -> _node -> nodeValue;
		if ($this -> _node -> hasAttributes()) {
			foreach ($this->_node->attributes as $attribute) {
				$new_tag -> setAttribute($attribute -> nodeName, $attribute -> nodeValue);
			}
		}
	}

	/**
	 * Transforms current tag to another
	 * @param target_tag : target tag name
	 */
	function Transform($target_tag) {
		$new_tag = $this -> _domDoc -> createElement($target_tag);

		//Copying values
		$this -> copyAttributes($new_tag);

		//Replace it with the old tag

		$this -> _node -> parentNode -> replaceChild($new_tag, $this -> _node);

		$this -> _node = $new_tag;

		return $this;
	}

	function Update() {
		libxml_use_internal_errors(true);
		$this -> _domDoc -> loadHTML($this -> _domDoc -> saveHtml());
		libxml_use_internal_errors(false);
	}

	public function attr($name, $value = null) {
		if ($value == null)
			return $this -> _node -> getAttribute($name);
		else {
			if ($this -> _node instanceof DOMElement)
				$this -> _node -> setAttribute($name, $value);
			else if ($this -> _node instanceof DOMNodeList) {
				$this -> _doInDomNodeList($this -> _node, function($singleNode) {
					$singleNode -> setAttribute($name, $value);
				});
			}
		}
	}

	/**
	 * TODO: Needs mroe work
	 */
	public function addEvent($eventName, $eventDefinition) {
		$events = explode(' ', $this -> attr($eventName));

		array_push($events, trim($eventDefinition));

		$this -> attr($eventName, trim(join(' ', $events)));

		return $this;
	}

	private function _alterAttribute($attrName, $func) {
		$attributes = explode(' ', $this -> attr($attrName));

		$func();

		$this -> attr($attrName, trim(join(' ', $attributes)));
	}

	private function _addAttribute($attrName, $attrValue, $singleValue = false) {
		if ($this -> _node instanceof DOMElement) {
			if ($singleValue)
				$this -> attr($attrName, $attrValue);
			else {

				$attributes = explode(' ', $this -> attr($attrName));

				array_push($attributes, trim($attrValue));

				$this -> attr($attrName, trim(join(' ', $attributes)));
			}
		} else if ($this -> _node instanceof DOMNodeList) {
			foreach ($this->_node as $node) {
				if ($singleValue) {
					$element -> attr($attrName, $attrValue);
				} else {

					$element = new DOMap($node);

					$attributes = explode(' ', $this -> attr($attrName));

					array_push($attributes, trim($attrValue));

					$element -> attr($attrName, trim(join(' ', $attributes)));
				}
			}
		}

		return $this;
	}

	private function _removeAttribute($attrName, $attrValue) {
		$attributes = explode(' ', $this -> attr($attrName));
		if (count($attributes) > 0) {

			$attributes_to_remove = explode(' ', $attrValue);

			//Delete
			for ($i = 0; $i < count($attributes); $i++) {
				if (in_array($attributes[$i], $attributes_to_remove)) {
					unset($attributes[$i]);

				}
			}

			$this -> attr($attrName, trim(join(' ', $attributes)));
		}
		return $this;
	}

	public function hasClass($className) {
		dv($this -> _classes);
		$classes = explode(' ', $this -> attr('class'));
		if (in_array(trim($className), $classes))
			return true;
	}

	/**
	 * @param className string (may be space delimited)
	 */
	public function removeClass($className, $singleValue = false) {
		return $this -> _removeAttribute('class', $className, $singleValue);
	}

	public function addClass($className, $singleValue = false) {
		return $this -> _addAttribute('class', $className, $singleValue);
	}

	public function getNode() {
		return $this -> _node;
	}

	public function getParentNode() {
		return $this -> _node -> parentNode;
	}

	private function createDOMElement($html) {
		$frag = $this -> _domDoc -> createDocumentFragment();
		$frag -> appendXML($html);
		return $frag;
	}

	private function addNode($element, $as, $on) {
		if (is_string($element)) {
			$element = $this -> createDOMElement($element);
		} else if ($element instanceof DOMap) {
			if ($element -> getNode() -> ownerDocument === $this -> _domDoc)
				$element = $element -> getNode();
			else
				$element = $this -> _domDoc -> importNode($element -> getNode(), true);
		}

		if ($as == 'sibling') {
			if ($on == 'front')
				$this -> _node -> parentNode -> insertBefore($element, $this -> _node -> nextSibling);
			else if ($on == 'behind')
				$this -> _node -> parentNode -> insertBefore($element, $this -> _node);
		} else if ($as == 'child') {
			if ($on == 'front')
				$this -> _node -> appendChild($element);
			else if ($on == 'behind')
				$this -> _node -> insertBefore($element, $this -> _node -> firstChild);
		}
		return $this;
	}

	function appendSibling($element) {
		return $this -> addNode($element, 'sibling', 'front');
	}

	function prependSibling($element) {
		return $this -> addNode($element, 'sibling', 'behind');
	}

	function prependChild($element) {
		return $this -> addNode($element, 'child', 'behind');
	}

	function appendChild($element) {
		return $this -> addNode($element, 'child', 'front');
	}

	function WrapIn($target_tag_name) {
		//FIXME: Can't parent goes into the child

		//See if the Element already exists
		$tag = $this -> _($target_tag_name);
		if (isset($tag)) {
			$tag -> append($this -> _node);
		} else {

			//Creating tag
			$target_tag_array = explode('#', $target_tag_name);

			$tagName = $target_tag_array[0];
			$target_tag = $this -> _domDoc -> createElement($tagName);

			if (isset($target_tag_array[1])) {
				$tagId = $target_tag_array[1];
				$target_tag -> setAttribute('id', $tagId);
			}

			$dom = new DOMDocument();

			//Adding the tag
			$this -> _node -> parentNode -> insertBefore($target_tag, $this -> _node);
			$target_tag -> appendChild($this -> _node);

		}
		return $this;
	}

	function Validify() {
		//TODO: this is supposed to like...turn ">" into "/>";

	}

	public function Render($render = true) {

		$output = $this -> _domDoc -> saveHTML();

		//Inject parameters
		$output = $this -> _injectParams($output);

		if ($render)
			echo $output;
		else
			return $output;
	}

	function Remove() {
		if ($this -> _node instanceof DOMElement)
			$this -> _node -> parentNode -> removeChild($this -> _node);
		else if ($this -> _node instanceof DOMNodeList) {
			while ($this -> _node -> length > 0) {
				$node = $this -> _node -> item(0);
				$node -> parentNode -> removeChild($node);

			}

		}
	}

	function Disappear() {
		$this -> attr('style', 'display:none;');
	}

	function Appear() {
		$this -> attr('style', 'display:box;');
	}

	function Reset() {
		//FIXME: it breaks addClass
		$this -> _params = new ArrayObject();

		libxml_use_internal_errors(true);
		$this -> _domDoc -> loadHTML($this -> originalTemplateString);
		$this -> _childIndex = null;
		$this -> _childSelector = null;
		$this -> _selector = null;
		$this -> _param = array();
		libxml_use_internal_errors(false);
	}

	/**
	 * @param attrName string
	 * @param attrValue string
	 * @return DOMNodeList
	 */
	public function getByAttribute($attrName, $attrValue) {
		return $this -> getByXPath("//*[@$attrName=\"$attrValue\"]");
	}

	function getByXPath($query) {
		$domXpath = new DOMXPath($this -> _domDoc);
		return $domXpath -> query($query);
	}

	private function getById($id) {

		//$id = util\string::Remove($id, '#');

		$element = $this -> _domDoc -> getElementById($id);

		if ($element == null)//fallback i.e. on HTML5
			$element = $this -> getByAttribute('id', $id);

		return $element;
	}

	function getByClass($class_name) {
		return $this -> getByAttribute('class', $class_name);
	}

	function getByTag($tag_name) {
		return $this -> _domDoc -> getElementsByTagName($tag_name);
	}

	public function _($in) {

		$in = $this -> _detectSelector($in);
		if ($this -> _selector != 'param') {
			$element = $this -> _getElement($in);

			if (($element instanceof DOMNodeList && $element -> length != 0) || ($element != null && $element instanceof DOMElement)) {
				return new DOMap($element, array('domDoc' => $this -> _domDoc));
			}
		} else {
			$param = $this -> _getParam($in);

			if (isset($param))
				return new DOMap($param, array('params' => $this -> _params));
		}

	}

	public function Get($index) {
		if ($this -> _node instanceof DOMNodeList) {
			return new DOMap($this -> _node -> item($index));
		}
	}

	public function Text($value = null) {
		//FIXME: Raw html is set sometimes?
		if (isset($value)) {
			if ($this -> _node instanceof DOMElement)
				$this -> _node -> nodeValue = $value;
			else if ($this -> _node instanceof DOMNodeList) {
				//FIXME: Some bug here
				$this -> _doInDomNodeList($this -> _node, function(DOMElement $singleNode) use ($value) {
					$singleNode -> nodeValue = $value;
				});
			} else if ($this -> _param instanceof stdClass) {
				$this -> _param -> value = $value;
			}
		} else {
			if ($this -> _node instanceof DOMElement)
				return $this -> _node -> nodeValue;
			else if ($this -> _node instanceof DOMNodeList) {
				//FIXME: Some bug here

				$values = new ArrayObject();
				$this -> _doInDomNodeList($this -> _node, function(DOMElement $singleNode) use ($values) {
					$values -> append($singleNode -> nodeValue);
				});

				return $values;

			} else if ($this -> _param instanceof stdClass) {
				return $this -> _param -> value;
			}
		}
	}

	private function _detectSelector($in) {
		if (is_string($in)) {
			if (util\string::StartsWith($in, '#')) {
				$this -> _selector = 'id';
				$in = util\string::Remove($in, '#');
			} else if (util\string::StartsWith($in, '.')) {
				$this -> _selector = 'class';
				$in = util\string::Remove($in, '.');
			} else if (util\string::StartsWith($in, '@')) {
				$this -> _selector = 'param';
				$in = util\string::Remove($in, '@');

				$param = new stdClass();
				$param -> name = $in;
				$param -> value = '';

				$this -> _params -> append($param);
			} else if (util\string::StartsWith($in, '[')) {
				$this -> _selector = 'attr';
				$in = util\string::Remove($in, '[');
				$in = util\string::Remove($in, ']');

			} else if (util\string::StartsWith($in, '/')) {
				$this -> _selector = 'xpath';
			} else if (util\string::StartsWith($in, '*')) {
				$this -> _selector = 'all';
			} else {
				$this -> _selector = 'tag';
			}

			//Childselectors
			if (util\string::Contains($in, ':')) {
				if (util\string::Contains($in, ':first')) {
					$this -> _childSelector = 'first';
					$in = util\string::Remove($in, ':first');
				} else if (util\string::Contains($in, ':last')) {
					$this -> _childSelector = 'last';
					$in = util\string::Remove($in, ':last');
				} else If (util\string::Contains($in, ':even')) {
					$this -> _childSelector = 'even';
					$in = util\string::Remove($in, ':even');
				} else If (util\string::Contains($in, ':odd')) {
					$this -> _childSelector = 'odd';
					$in = util\string::Remove($in, ':odd');
				} else if (util\string::Contains($in, ':')) {
					$ele_array = explode(':', $in);
					$this -> _childSelector = ':';

					$in = $ele_array[0];
					$this -> _childIndex = $ele_array[1];
				}
			} else {
				$this -> _childSelector = null;
				$this -> _childIndex = null;
			}
		}

		return trim($in);
	}

	private function _load($templateString) {
		if (is_string($templateString)) {
			$originalTemplateString = $templateString;
			if ($this -> _domDoc == null)
				$this -> _domDoc = new DOMDocument('1.0', 'utf-8');
			libxml_use_internal_errors(true);
			if ($this -> _loadMode == 'html')
				$this -> _domDoc -> loadHTML($templateString);
			else if ($this -> _loadMode == 'xml')
				$this -> _domDoc -> loadXML($templateString);

			libxml_use_internal_errors(false);
			//$this->_node=$this -> _domDoc->childNodes->item(1);
			$this -> _node = $this -> _domDoc -> documentElement;
			// sets teh node to the root of document
		}
	}

	private function _injectParams($output) {
		//dv($this->_params);
		foreach ($this->_params as $param) {
			$output = util\string::Replace($output, $param -> name, $param -> value);
		}

		return $output;
	}

	private function _getElement($in) {
		switch ($this -> _selector) {
			case 'id' :
				$element = $this -> getById($in);
				break;
			case 'class' :
				$element = $this -> getByClass($in);
				break;
			case 'tag' :
				$element = $this -> getByTag($in);
				break;
			case 'attr' :
				$attr_array = explode('=', $in);
				$attr_name = $attr_array[0];
				$attr_value = $attr_array[1];

				$element = $this -> getByAttribute($attr_name, $attr_value);
				break;
			case 'xpath' :
				$element = $this -> getByXPath($in);
				break;
			case 'all' :
				$element = $this -> getByXPath('//*');
				break;
		}

		switch($this -> _childSelector) {
			case 'first' :
				if ($element instanceof DOMNodeList && $element -> length > 0)
					$element = $element -> item(0);
				break;
			case 'last' :
				if ($element instanceof DOMNodeList && $element -> length > 0)
					$element = $element -> item($element -> length - 1);
				break;
			//FIXME: :odd and :even needs more work
			case 'even' :
				$path = $this -> _node -> getNodePath();

				if ($element instanceof DOMNodeList && $element -> length > 0)
					$element = $this -> getByXPath("//$path/*[position() mod 2 = 0 and position() > 0]");

				break;
			case 'odd' :
				$path = $this -> _node -> getNodePath();

				if ($element instanceof DOMNodeList && $element -> length > 0)
					$element = $this -> getByXPath("//$path/*[position() mod 2 = 1 and position() > 0]");

				break;
			case 'last' :
				if ($element instanceof DOMNodeList && $element -> length > 0)
					$element = $element -> item($element -> length - 1);
				break;
			case ':' :
				if ($element instanceof DOMNodeList && $element -> length > 0)
					$element = $element -> item($this -> _childIndex);
				break;
		}

		if ($element instanceof DOMNodeList && $element -> length == 1)
			$element = $element -> item(0);

		return $element;
	}

	private function _doInDomNodeList($domNodeList, $func) {
		if ($this -> _node instanceof DOMNodeList && $domNodeList -> length > 0) {
			foreach ($domNodeList as $singleNode) {
				for ($i = 0; $i < $domNodeList -> length; $i++)
					if ($singleNode instanceof DOMElement)
						$func($singleNode);
			}
		}
	}

	public function css($name, $value = null) {
		if (isset($value)) {
			return $this -> _addAttribute('style', "$name:$value;");
		} else {
			$css = new CSSParser();
			return $css -> getCSSRule($name, $this -> attr('style')) -> value;
		}
	}

	private function _getParam($in) {
		foreach ($this->_params as $param) {
			if ($param -> name == $in)
				return $param;
		}
	}

	private function _debug() {
		$debugStr = $this -> _selector . '<br/>';
		$debugStr .= $this -> _childIndex . '<br/>';
		$debugStr .= $this -> _childSelector . '<br/>';

		return $debugStr;
	}

}
?>