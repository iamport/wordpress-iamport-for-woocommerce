<?php
require_once('INode.php');

class NaverCombination implements INode {

	private $manageCode;
	private $price;
	private $options;

	public function __construct($manageCode) {
		$this->manageCode = $manageCode;
		$this->options = array();
	}

	public function setPrice($price) {
		$this->price = $price;
	}

	public function addOption($label, $code) {
		$this->options[] = array(
			"name" => mb_substr($label, 0, 20, 'UTF-8'),
			"id" => $code,
		);
	}

	public function getAttribute() {
		$attribute = array(
			"manageCode" => $this->manageCode,
		);

		if ( is_numeric($this->price) )	$attribute['price'] = $this->price;

		foreach ($this->options as $idx=>$op) {
			$attribute["options" . $idx] = $op;
		}

		return $attribute;
	}

}