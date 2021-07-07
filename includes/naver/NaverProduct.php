<?php
require_once('INode.php');

class NaverProduct implements INode {
	public $id;
	public $merchantProductId;
	public $ecMallProductId;
	public $name;
	public $basePrice;
	public $taxType;
	public $infoUrl;
	public $imageUrl;
	public $giftName;
	public $stockQuantity;
	public $status;

	//nested
	public $optionItems = array();
	public $combinations = array();
	public $shippingPolicy;
	public $supplement;

	public function addOptionItem($optionItem) {
		$this->optionItems[] = $optionItem;
	}

	public function addCombination($combination) {
		$this->combinations[] = $combination;
	}

	public function getAttribute() {
		$attribute = array(
			'id' => $this->id,
			'merchantProductId' => $this->merchantProductId,
			'ecMallProductId' => $this->ecMallProductId,
			'name' => $this->name,
			'basePrice' => $this->basePrice,
			'taxType' => $this->taxType,
			'infoUrl' => $this->infoUrl,
			'imageUrl' => $this->imageUrl,
			'status' => $this->status,
			'shippingPolicy' => $this->shippingPolicy->getAttribute()
		);

		if ( count($this->optionItems) > 0 ) {
			$attribute['optionSupport'] = "true";
			$attribute['option'] = array();

			foreach ($this->optionItems as $idx=>$optionItem) {
				$attribute['option']["optionItem" . $idx] = $optionItem->getAttribute();
			}

			foreach ($this->combinations as $idx=>$comb) {
				$attribute['option']["combination" . $idx] = $comb->getAttribute();
			}
		}

		return $attribute;
	}

}
