<?php 

class Function_String {

	/**
	 * get the array of many strings surrounded by the same set of strings
	 * e.g. get all rows of a table: <tr>[this is the data to capature]</tr>
	 * 
	 * @uses public
	 * @see 
	 *  - public function getArrayString($string, $sRoot, $iFirst, $bIncludeFirst, $bIncludeLast)
	 *
	 * @param string $string
	 * @param array of tokens $sRoot
	 * @param int $iFirst
	 * @param bool $bIncludeFirst
	 * @param bool $bIncludeLast
	 * 
	 * @return array
	 */
	static public function getArrayString($string, $sRoot, $iFirst, $bIncludeFirst, $bIncludeLast)
	{
		$array = array();
		
		$iStart = 0;
		$finish = false;
		while ($finish == false)
		{
			$subContent = self::findNext($iStart, $sRoot, $iFirst, $bIncludeFirst, $bIncludeLast, $string, $iNewStart);
			if (empty($subContent))
			{
				$finish = true;
			}
			else 
			{
				$array[] = $subContent;
				$iStart = $iNewStart;
			}
		}
		
		return $array;
	}
	
	/**
	 * extract the desired data from the given content using the array of containing strings (tokens)
	 * 
	 * @uses public
	 *
	 * @param int $iStartFrom
	 * @param array of tokens $sRoot
	 * @param int $iFirst
	 * @param bool $bIncludeFirst
	 * @param bool $bIncludeLast
	 * @param string $sString
	 * @param int& $iNewStartFrom
	 * 
	 * @return string
	 */
	static public function findNext($iStartFrom, $sRoot, $iFirst, $bIncludeFirst, $bIncludeLast, $sString, &$iNewStartFrom)
	{
	    $sSubString = substr($sString,$iStartFrom,strlen($sString)-$iStartFrom);
	    $iTime   = count($sRoot);
	    $iStart  = 0;
	    $iEnd    = 0;
	    $pos     = 0;
	    $lastPos = 0;

	    for ($i=0; $i<$iTime; $i++) {
	        $pos = strpos($sSubString,  $sRoot[$i], $lastPos);
	        if ( $sRoot[$i] != substr($sSubString, 0, strlen($sRoot[$i])) )
	            if ($pos==false) return "";
	        $lastPos = $pos + strlen($sRoot[$i]);
	        if ($i==($iTime-1) ) $iEnd=$pos;
	    }
	    
	    // new start from 
	    $iNewStartFrom = $iStartFrom + $iEnd + strlen($sRoot[$iTime-1]);
	    
	    if ($bIncludeLast==true)
	        $iEnd = $iEnd+strlen($sRoot[$iTime-1]);
	
	    $pos     = 0;
	    $lastPos = 0;
	    for ($i=0;$i<=$iFirst;$i++)
	    {
	        $pos = strpos($sSubString, $sRoot[$i], $lastPos);
	        $iStart = $pos;
	        $lastPos = $pos + strlen($sRoot[$i]);
	    }
	    if ($bIncludeFirst==false)
	        $iStart = $iStart + strlen($sRoot[$iFirst]);
	        
	    /*
		echo $iStart . '<br />';
		echo $iEnd . '<br />';
		echo trim(substr($sSubString,$iStart,$iEnd-$iStart)); die;
		*/
	    return trim(substr($sSubString,$iStart,$iEnd-$iStart));
	}
	
	/**
	 * get an array of satisfied HTML nodes
	 *
	 * @param string $html
	 * @param array $paths
	 * 	e.g.1.
	 * 		$paths = array(
	 *				array('div', 'class', 'ostk_auc_sublist', 1),
	 *				array('table'),
	 *				array('tr')
	 *			)
	 * Means:
	 * 	1. Find all <div class="ostk_auc_sublist"> (1)
	 * 	2. Find all table under (1) (2)
	 * 	3. Find all tr under (2)
	 * 	=> return an array of all satisfied tr as DOMElements
	 * 	* 1 stands for exact lookup
	 * 
	 *  e.g.2.
	 * 		$paths = array(
	 *				array('div', 'id', 'product_', 0, class, 'bold', 1),
	 *				array('table'),
	 *				array('tr')
	 *			)
	 * Means:
	 * 	1. Find all <div> which possess attribute id contains 'product_' and class="bold" (1)
	 * 	2. Find all table under (1) (2)
	 * 	3. Find all tr under (2)
	 * 	=> return an array of all satisfied tr as DOMElements
	 * 	* 1 stands for exact lookup, 0 stands for relative lookup
	 * @return array of DOMElements
	 */
	static public function getHtmlElements($html, $paths) {
		// load dom html and xpath
		$dom = new DOMDocument();
		@$dom->loadHTML($html);
		$xpath = new DOMXPath($dom);
		
		// INSEPCT THE FIRST PATH TO LOCATE THE APPROPRIATE LIST OF FATHER NODES
		// prepare an array of elements
		$elements = array();
		$elements = $xpath->evaluate('/html/body//' . $paths[0][0]);
		// prepare an array of found elements
		$foundElements = array();
		// for and check
		for ($i=0; $i<$elements->length; $i++) {
			// locate current element
			$element = $elements->item($i);
			$isCorrectElement = true;
			
			// check if this element satisfies its associated values
			for ($j=1; $j<count($paths[0]); $j++) {
				if ($j % 3 == 1) {
					//echo $paths[0][$j] . ' = ' . $element->getAttribute($paths[0][$j]) . $paths[0][$j+1] . '<br />';
					
					// if rough lookup
					if ($paths[0][$j+2] == 0) {
						if ( !substr_count($element->getAttribute($paths[0][$j]), $paths[0][$j+1]) ) {
							$isCorrectElement = false;
							break;
						}
					}
					// else if exact lookup
					else if ($paths[0][$j+2] == 1) {
						if ($element->getAttribute($paths[0][$j]) != $paths[0][$j+1]) {
							$isCorrectElement = false;
							break;
						}
					}
				}
			}
				
			// check if this is the corrent element
			if ($isCorrectElement) {
				$foundElements[] = $element;
			}
		}
		// FROM HERE WE HAVE THE LIST OF FATHER NODES
		//echo count($foundElements); die;
		
		// START DIGGING DOWNWARD
		// $nodes now is the array of father nodes
		$nodes = $foundElements;
		// loop though paths from index 1 to start digging out to find the child nodes
		for ($k=1; $k<count($paths); $k++) {
			// this path is applied for locating the child nodes under current father nodes
			$path = $paths[$k];
			// $childNodes now is supposed to store the found child nodes under current father nodes
			$childNodes = array();
			
			// for and check  the list of $nodes' child nodes
			for ($i=0; $i<count($nodes); $i++) {
				// locate the current father node
				$node = $nodes[$i];
				//echo $node->nodeName; die;
				//echo $node->nodeValue; die;
				//echo $node->childNodes->length; die;
				
				// check the list of its child nodes
				for ($j=0; $j<$node->childNodes->length; $j++) {
					// locate the current child node
					$childNode = $node->childNodes->item($j);
					//echo $childNode->nodeName . '<br />';
					//echo $childNode->nodeValue . '<br />';
					$isCorrectChildNode = true;
					
					// beforehand check if it is the corrent node name
					if ($childNode->nodeName != $path[0]) {
						$isCorrectChildNode = false;
					}
					
					// only continue if it is the correct node name
					if ($isCorrectChildNode) {
						// check if this child node satisfies its associated values
						for ($t=1; $t<count($path); $t++) {
							if ($t % 3 == 1) {
								// if rough lookup
								if ($path[$t+2] == 0) {
									if ( !substr_count($childNode->getAttribute($path[$t]), $path[$t+1]) ) {
										$isCorrectChildNode = false;
										break;
									}
								}
								// else if exact lookup
								else if ($path[$t+2] == 1) {
									if ($childNode->getAttribute($path[$t]) != $path[$t+1]) {
										$isCorrectChildNode = false;
										break;
									}
								}
							}
						}
					}
					
					// check if this is the corrent child node
					if ($isCorrectChildNode) {
						// add to the array $childNodes
						$childNodes[] = $childNode;
					}
				}
			}
			
			// now child nodes become father nodes for the next loop
			$nodes = $childNodes;
			//echo $nodes[0]; die;
		}
		
		// return the array that contains all satisfied html nodes
		//echo count($nodes); die;
		return $nodes;
	}
	
	/**
	 * get all html elements containing a given set of phrases
	 *
	 * @param string $html refer to 1st argument of self::getHtmlElements(...)
	 * @param array $paths refer to 2nd argument of self::getHtmlElements(...)
	 * @param array $phrases array of string
	 * @param bool $caseSensitive set to false to use case insensitive
	 * @return mix of DOMElement
	 */
	static public function getHtmlElementsContainingPhrases($html, $paths, $phrases, $caseSensitive=true) {
		// first of all, get all elements specified by paths
		$elements = self::getHtmlElements($html, $paths);
		
		// if case insensitive
		if (!$caseSensitive) {
			for ($i=0; $i<count($phrases); $i++)
				$phrases[$i] = strtolower($phrases[$i]);
		}
		
		// then only return those elements containing the given phrases
		$result = array();
		// check
		for ($i=0; $i<count($elements); $i++) {
			// html code of current element
			$html = self::getHtmlFromDomElement($elements[$i]);
			// if case insensitive
			if (!$caseSensitive)
				$html = strtolower($html);
			
			// check if it contains all given phrases
			$contain = true;
			for ($j=0; $j<count($phrases); $j++)
				if ( !substr_count($html, $phrases[$j]) ) {
					$contain = false;
					break;
				}
			// get or not 
			if ($contain)
				$result[] = $elements[$i];
		}
		
		// return
		return $result;
	}
	
	/**
	 * extract a single child domElement of a given domElement
	 *
	 * @param DOMElement $domElement
	 * @param array of int $childsOrder
	 * NOTE: skip all "#text" nodes
	 * 	e.g.
	 * 		$domElement is a DIV container
	 * 		$childsOrder = array(2, 3, 1)
	 * Means:
	 * 	1. Locate the second element under the given DIV container, let say it is a <span>
	 * 	2. Locate the third element under the just found SPAN container, let suppose it is an <a>
	 * 	3. Extract the first element under the just found A container, for instance, an <img>
	 * 	=> return the DOMElement of that IMG
	 * 
	 * @return DOMElement (the found child)
	 * 
	 */
	static public function locateHtmlElementByChildsOrder($domElement, $childsOrder) {
		// current father node
		$currentNode = $domElement;
		
		// locate through childs order
		for ($k=0; $k<count($childsOrder); $k++) {
			// current path to locate downward
			$childOrder = $childsOrder[$k];
			
			// if the current node doesn't have any child node
			/*
			if (!$currentNode->hasChildNodes()) {
				$currentNode = null;
				break;
			}
			*/
			
			// if this child node doesn't exist in the context
			if (($currentNode->childNodes->length - 1) < $childOrder-1) {
				$currentNode = null;
				break;
			}
			
			/**
			 * get this child node under the current father node
			 * optimization on Dec 16 ,2010: skip all "#text" nodes
			 */
			$correctNodeIndex 	= -1;
			$correctOrder 		= 0;
			for ($i=0; $i<$currentNode->childNodes->length; $i++) {
				if ( $currentNode->childNodes->item($i)->nodeName != '#text' ) {
					$correctNodeIndex = $i;
					$correctOrder = $correctOrder + 1;
				}
				if ($correctOrder == $childOrder)
					break;
			}
			
			// get this current node
			$currentNode = $currentNode->childNodes->item($correctNodeIndex);
		}
		//echo $currentNode->nodeName; die;
		
		// found it!
		return $currentNode;
	}
	
	/**
	 * retrieve the child nodes of a given father node based on the childs' attributes
	 *
	 * @param DOMElement $fatherNode
	 * @param string $childNodeName
	 * @param array $attributes
	 * 	e.g.1.
	 * 		$childNodeName = 'div'
	 * 		$attributes = array(
	 * 							'class', 'newPrice', 1,
	 * 							'id', 'item_price_', 0
	 * 						)
	 * Means:
	 * 	1. Find all <div> under the given $fatherNode (1)
	 * 	2. Filter from (1): nodes with class = 'newPrice' (1 stands for exact attribute value lookup) (2)
	 * 	3. Filter from (2): nodes with id contains 'item_price_' (0 stands for rough attribute value lookup) (3)
	 * 	=> return an array of all satisfied div as DOMElements
	 * 
	 * 	e.g.2.
	 * 		$childNodeName = 'div'
	 * 		$attributes = array()
	 * Means:
	 * 	1. Find all <div> under the given $fatherNode (1)
	 * 	=> return an array of all satisfied div as DOMElements
	 * 
	 * @return array of all satisfied node as DOMElements
	 */
	static public function getHtmlChildElementsByAttributes($fatherNode, $childNodeName, $attributes = array()) {
		// foundNode
		$foundElements = null;
		
		// check each child node
		for ($i=0; $i<$fatherNode->childNodes->length; $i++)
			if ($fatherNode->childNodes->item($i)->nodeName == $childNodeName) {
				// locate current child element
				$element = $fatherNode->childNodes->item($i);
				$isCorrectElement = true;
				
				// check if this element satisfies its associated attributes
				for ($j=0; $j<count($attributes); $j++) {
					if ($j % 3 == 0) {
						// if this child doesn't have the indicated attribute
						if (!$element->hasAttribute($attributes[$j])) {
							$isCorrectElement = false;
							break;
						}
						
						// if rough lookup
						if ($attributes[$j+2] == 0) {
							if ( !substr_count($element->getAttribute($attributes[$j]), $attributes[$j+1]) ) {
								$isCorrectElement = false;
								break;
							}
						}
						// else if exact lookup
						else if ($attributes[$j+2] == 1) {
							if ($element->getAttribute($attributes[$j]) != $attributes[$j+1]) {
								$isCorrectElement = false;
								break;
							}
						}
					}
				}
					
				// check if this is the corrent element
				if ($isCorrectElement) {
					$foundElements[] = $element;
				}
			}
		
		// found it or not!
		return $foundElements;
	}
	
	/**
	 * retrieve the descendant nodes of a given ancestor node based on the descendants' attributes
	 *
	 * @param DOMElement $anceNode
	 * @param string $descNodeName
	 * @param array $attributes
	 * 	e.g.1.
	 * 		$anceNodeName = 'div'
	 * 		$attributes = array(
	 * 							'class', 'newPrice', 1,
	 * 							'id', 'item_price_', 0
	 * 						)
	 * Means:
	 * 	1. Find all descendant <div> under the given $anceNode (1)
	 * 	2. Filter from (1): nodes with class = 'newPrice' (1 stands for exact attribute value lookup) (2)
	 * 	3. Filter from (2): nodes with id contains 'item_price_' (0 stands for rough attribute value lookup) (3)
	 * 	=> return an array of all satisfied div as DOMElements
	 * 
	 * 	e.g.2.
	 * 		$descNodeName = 'div'
	 * 		$attributes = array()
	 * Means:
	 * 	1. Find all descendant <div> under the given $anceNode (1)
	 * 	=> return an array of all satisfied div as DOMElements
	 * 
	 * @return array of all satisfied node as DOMElements
	 */
	static public function getHtmlDescendantElementsByAttributes($anceNode, $descNodeName, $attributes = array(), &$foundElements = array()) {
		// check each child node
		for ($i=0; $i<$anceNode->childNodes->length; $i++) {
			// locate current child element
			$element = $anceNode->childNodes->item($i);
				
			if ($anceNode->childNodes->item($i)->nodeName == $descNodeName) {
				$isCorrectElement = true;
				
				// check if this element satisfies its associated attributes
				for ($j=0; $j<count($attributes); $j++) {
					if ($j % 3 == 0) {
						// if this child doesn't have the indicated attribute
						if (!$element->hasAttribute($attributes[$j])) {
							$isCorrectElement = false;
							break;
						}
						
						// if rough lookup
						if ($attributes[$j+2] == 0) {
							if ( !substr_count($element->getAttribute($attributes[$j]), $attributes[$j+1]) ) {
								$isCorrectElement = false;
								break;
							}
						}
						// else if exact lookup
						else if ($attributes[$j+2] == 1) {
							if ($element->getAttribute($attributes[$j]) != $attributes[$j+1]) {
								$isCorrectElement = false;
								break;
							}
						}
					}
				}
					
				// check if this is the corrent element
				if ($isCorrectElement) {
					$foundElements[] = $element;
				}
			}
			
			// continue to dig under this current childNode
			if ($element->hasChildNodes())
				self::getHtmlDescendantElementsByAttributes($element, $descNodeName, $attributes, $foundElements);
		}
		
		// found it or not!
		return $foundElements;
	}
	
	/**
	 * convert DOMElement to its original html code
	 *
	 * @param DOMElement $element
	 * @return string
	 */
	static public function getHtmlFromDomElement($element) {
	    $doc = new DOMDocument();
	    foreach($element->childNodes as $child) {
	        $doc->appendChild($doc->importNode($child, true));
	    }
	    
	    return $doc->saveHTML();
	}
	
	/**
	 * get submission form
	 *
	 * @param string $containerHtml
	 * @param mix $attributes
	 * e.g.1.
	 * 		$attributes = array(
	 * 							'name', 'signin', 1,
	 * 							'id', 'style_', 0
	 * 						)
	 * means:
	 * 	search form with name = 'signin' exact lookup, id contains 'style'
	 * 
	 * @return mix
	 */
	static public function getHtmlForm($containerHtml, $attributes) {
		// get form
		$attributes = array_merge(array('form'), $attributes);
		$temp = Function_String::getHtmlElements(
			$containerHtml, 
			array(
				$attributes
			)
		);
		$formNode = $temp[0];
		// get url where the form submit information to
		$submitUrl = $formNode->getAttribute('action');
		// build data to submit
		$data = array();
		$formInputs = array();
		$formInputs = self::getHtmlDescendantElementsByAttributes($formNode, 'input', array(), $temp);
		foreach ($formInputs as $input) {
			$data[ $input->getAttribute('name') ] = $input->getAttribute('value');
		}
		
		// return
		$form = array();
		$form['action'] = $submitUrl;
		$form['inputs'] = $data;
		//print_r($form); die;
		return $form;
	}
	
	static public function getHtmlFormInputs($formElement) {
		// build data to submit for this form
		$data = array();
		$formInputs = array();
		$formInputs = self::getHtmlDescendantElementsByAttributes($formElement, 'input', array(), $temp);
		foreach ($formInputs as $input) {
			$data[ $input->getAttribute('name') ] = $input->getAttribute('value');
		}
		//print_r($data); die;
		
		// return
		return $data;
	}
	
	/**
	 * strip all html entities from a given string
	 *
	 * @param string $record
	 * @return string
	 */
	static public function stripHtmlout($record)
	{
	    $record = trim($record);
	    //$record = str_replace("'", "\'", $record);
	    
	    // $document should contain an HTML document.
	    // This will remove HTML tags, javascript sections
	    // and white space. It will also convert some
	    // common HTML entities to their text equivalent.
	    $search = array ('@<script[^>]*?>.*?</script>@si', // Strip out javascript
	                     '@<[\/\!]*?[^<>]*?>@si',          // Strip out HTML tags
	                     '@([\r\n])[\s]+@',                // Strip out white space
	                     '@&(quot|#34);@i',                // Replace HTML entities
	                     '@&(amp|#38);@i',
	                     '@&(lt|#60);@i',
	                     '@&(gt|#62);@i',
	                     '@&(nbsp|#160);@i',
	                     '@&(iexcl|#161);@i',
	                     '@&(cent|#162);@i',
	                     '@&(pound|#163);@i',
	                     '@&(copy|#169);@i',
	                     '@&#(\d+);@e');                    // evaluate as php
	
	    $replace = array ('',
	                      '',
	                      '\1',
	                      '"',
	                      '&', 
	                      '<',
	                      '>',
	                      ' ',
	                      chr(161),
	                      chr(162),
	                      chr(163),
	                      chr(169),
	                      'chr(\1)');
		
	    $record = preg_replace($search, $replace, $record);
	    return trim($record);
	}
	
	static public function removeQuotedMsgFromEmail($record) {
		// trim first
		$record = trim($record);
	    
	    // this function will remove all blockquote tags
	    $search = array ('@<blockquote[^>]*?>.*</blockquote>@si',		// remove out blockquote
	    				 '/>[\r\t\n]*</',								// remove garbage characters between html tags
	    				 '/>[\r\t\n]*/',								// remove garbage characters between html tags
	    				 '/[\r\t\n]*</');								// remove garbage characters between html tags
	    $replace = array ('', '><', '>', '<');
		
	    $record = preg_replace($search, $replace, $record);
	    $record = str_replace("\r\t", '', $record);
	    $record = str_replace("\r", '', $record);
	    $record = str_replace("\t", '', $record);
	    $record = str_ireplace('original message', 'original message', $record);
	    $record = explode('-----original message-----', $record);
	    $record = $record[0];
	    $record = str_ireplace('--- en date de', '--- en date de', $record);
	    $record = explode('--- en date de', $record);
	    $record = $record[0];
	    $record = str_ireplace('<hr>', '<hr>', $record);
	    $record = explode('<hr>', $record);
	    $record = $record[0];
	    $record = str_ireplace('<hr', '<hr', $record);
	    $record = explode('<hr', $record);
	    $record = $record[0];
	    $record = explode('_________', $record);
	    $record = $record[0];
	    
	    $check = preg_match('/On[ ]{1,}.+wrote:/', $record, $matches);
	    if (count($matches)) {
	    	$delimiter = $matches[0];
	    	$record = explode($delimiter, $record);
	    	$record = trim($record[0]);
	    }
	    
	    $record = preg_replace('/>[ ]*</', '><', $record);
	    $record = str_replace("\n", '<br />', $record);
	    $record = preg_replace('/>(<br[ ]*[\/]?>)*</', '><', $record);
	    
	    //echo trim($record); die;
	    return trim($record);
	}
	
	static public function removeQuotedMsgFromEbayMessage($record) {
		// trim first
		$record = trim($record);
	    
	    // this function will remove all blockquote tags
	    $search = array ('@<div[ ]{1,}class="[A-Za-z0-9]*_quote">.*</div>@si');		// remove email quote
	    $replace = array ('');
	    $record = preg_replace($search, $replace, $record);
	    
	    $record = preg_replace('/^(<br[ ]*[\/]?>)*/', '', $record);
	    $record = preg_replace('/(<br[ ]*[\/]?>)*$/', '', $record);
	    $record = preg_replace('/>(<br[ ]*[\/]?>)*</', '><', $record);
	    
	    //echo trim($record); die;
	    return trim($record);
	}
	
	/**
	 * strip all javascript entities from a given string
	 *
	 * @param string $record
	 * @return string
	 */
	static public function stripJavascriptOut($record)
	{
	    $record = trim($record);
	    //$record = str_replace("'", "\'", $record);
	    
	    // $document should contain an HTML document.
	    // This will remove HTML tags, javascript sections
	    // and white space. It will also convert some
	    // common HTML entities to their text equivalent.
	    $search = array ('@<script[^>]*?>.*?</script>@si', // Strip out javascript
	                     '@([\r\n])[\s]+@',                // Strip out white space
	                     '@&(quot|#34);@i',                // Replace HTML entities
	                     '@&(amp|#38);@i',
	                     '@&(lt|#60);@i',
	                     '@&(gt|#62);@i',
	                     '@&(nbsp|#160);@i',
	                     '@&(iexcl|#161);@i',
	                     '@&(cent|#162);@i',
	                     '@&(pound|#163);@i',
	                     '@&(copy|#169);@i',
	                     '@&#(\d+);@e');                    // evaluate as php
	
	    $replace = array ('',
	                      '\1',
	                      '"',
	                      '&', 
	                      '<',
	                      '>',
	                      ' ',
	                      chr(161),
	                      chr(162),
	                      chr(163),
	                      chr(169),
	                      'chr(\1)');
		
	    $record = preg_replace($search, $replace, $record);
	    return trim($record);
	}
	
	static public function whiteSpacesToSingleSpace($string) {
		$string = preg_replace('/\s/', ' ', $string);
		$string = trim( preg_replace('/ {1,}/', ' ', $string) );
		
		// added Dec 16, 2010
	    $string = str_replace('&nbsp;', '', $string);
	    $string = str_replace('�', '', $string);
	    $string = trim($string);
	    
		return $string;
	}
	
	static public function getNumFromNumberFormat($numFormat) {
		$numFormat = strtolower($numFormat);
		$numFormat = str_replace('us $', '', $numFormat);
		$numFormat = str_replace('$', '', $numFormat);
		$numFormat = str_replace(',', '', $numFormat);
		$num = trim($numFormat);
		
		return $num;
	}
	
	static function encodeUrl($url) {
		$url = str_replace('/', '**', $url);
		$url = str_replace('?', '*q*', $url);
		
		return trim($url);
	}
	static function decodeUrl($url) {
		$url = str_replace('**', '/', $url);
		$url = str_replace('*q*', '?', $url);
		
		return trim($url);
	}

}