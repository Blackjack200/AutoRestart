<?php


namespace prokits;


class TextContainer {
	
	protected $var = [];
	
	public function __construct(array $var) {
		$this->var = $var;
	}
	
	public function getText(string $text) : ?string {
		foreach($this->var as $name => $value) {
			$text = str_replace(sprintf('%%s%' , $name) , $value , $text);
		}
		return $text;
	}
	
}