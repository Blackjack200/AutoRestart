<?php


namespace prokits;


class TextContainer {
	
	protected $var = [];
	
	public function __construct(array $var) {
		$this->var = $var;
	}
	
	public function getText(string $text) : ?string {
		foreach($this->var as $name => $value) {
			echo sprintf('%%s%' , $name) , PHP_EOL;
			$text = str_replace("%$name%" , $value , $text);
		}
		return $text;
	}
}