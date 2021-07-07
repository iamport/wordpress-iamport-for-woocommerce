<?php
require_once('INode.php');

class NaverShippingPolicy implements INode {

	/*
	{
		groupId : "묶음배송구분 카테고리 ID",
		method : "DELIVERY", //DELIVERY(택배·소포·등기), QUICK_SVC(퀵 서비스), DIRECT_DELIVERY(직접 전달), VISIT_RECEIPT(방문 수령), NOTHING(배송 없음)
		baseFee : 400, //기본 배송비
		feePayType : "PREPAYED", //PREPAYED(선불), CASH_ON_DELIVERY(착불)
		feeRule : {
			freeByThreshold : 20000,
			repeatByQty : 4,
			rangesByQty : [
				{from: 10, surcharge:4000},
				{from: 20, surcharge:6000}
			],
			surchargesByArea : [ //array or string(API)
				{area:"island", surcharge:2000},
				{area:"jeju", surcharge:3000}
			]
		}
	}
	*/

	private $groupId;
	private $method;
	private $feePayType;
	private $baseFee;
	private $feeRule;
	private $surcharge;

	// public function __construct($shipping) {
	// 	$this->groupId = $shipping->groupId;
	// 	$this->method = $shipping->method;
	// 	$this->feeType = $shipping->feeType;
	// 	$this->feePayType = $shipping->feePayType;
	// 	$this->baseFee = $shipping->baseFee;

	// 	if ( isset($shipping->feeRule) ) {
	// 		$this->feeRule = $this->createFeeRule($shipping->feeRule);

	// 		if ( isset($shipping->feeRule->surchargesByArea) ) {
	// 			$this->surcharge = new NaverSurchargesByArea($shipping->feeRule->surchargesByArea);
	// 		}
	// 	}
	// }

	public function getGroupId() {
		return $this->groupId;
	}

	public function getMethod() {
		return $this->method;
	}

	public function getFeePayType() {
		return $this->feePayType;
	}

	public function getFeeType() {
		if ( $this->feeRule instanceof NaverFreeByThreshold ) {
			return "CONDITIONAL_FREE";
		} else if ( $this->feeRule instanceof NaverRangesByQuantity ) {
			return "CHARGE_BY_QUANTITY";
		} else {
			if ( $this->baseFee == 0 ) {
				return "FREE";
			} else {
				return "CHARGE";
			}
		}
	}

	public function getBaseFee() {
		return $this->baseFee;
	}

	public function getFeeRule() {
		return $this->feeRule;
	}

	public function getSurcharge() {
		return $this->surcharge;
	}

	public function setGroupId($groupId) {
		$this->groupId = $groupId;
	}

	public function setMethod($method) {
		$this->method = $method;
	}

	public function setFeePayType($feePayType) {
		$this->feePayType = $feePayType;
	}

	public function setBaseFee($baseFee) {
		$this->baseFee = $baseFee;
	}

	public function setFeeRule($feeRule) {
		$this->feeRule = $feeRule;
	}

	public function setSurcharge($surcharge) {
		$this->surcharge = $surcharge;
	}

	private function createFeeRule($rule) {
		if ( isset($rule->freeByThreshold) ) {
			return new NaverFreeByThreshold($rule->freeByThreshold);
		} else if ( isset($rule->repeatByQty) ) {
			return new NaverRangesByQuantity($rule->repeatByQty);
		} else if ( isset($rule->rangesByQty) ) {
			return new NaverRangesByQuantity($rule->rangesByQty);
		}

		return null;
	}

	public function getAttribute() {
		$attribute = array(
			'groupId' => $this->groupId,
			'method' => $this->method,
			'feePayType' => $this->feePayType,
			'feePrice' => intval($this->baseFee),
			'feeType' => $this->getFeeType(),
		);

		if ( $this->feeRule instanceof NaverFreeByThreshold ) {
			$attribute['conditionalFree'] = $this->feeRule->getAttribute();
		} else if ( $this->feeRule instanceof NaverRangesByQuantity ) {
			$attribute['chargeByQuantity'] = $this->feeRule->getAttribute();
		} else {
			if ( $this->baseFee == 0 ) {
				$attribute['feePayType'] = "FREE";
			}
		}

		if ( $this->surcharge ) {
			$attribute['surchargeByArea'] = $this->surcharge->getAttribute();
		}

		return $attribute;
	}

}

class NaverFreeByThreshold implements INode {

	private $threshold;

	public function __construct($threshold) {
		$this->threshold = $threshold;
	}

	public function getAttribute() {
		return array('basePrice' => $this->threshold);
	}

	public function getThreshold() {
		return $this->threshold;
	}

}

class NaverRangesByQuantity implements INode {

	private $ranges;

	public function __construct($ranges) {
		$this->ranges = $ranges; //sorting
	}

	public function sorting($a, $b) {
		return $a->from - $b->from;
	}

	public function getAttribute() {
		if ( is_numeric($this->ranges) ) {
			return array(
				"type" => "REPEAT",
				"repeatQuantity" => intval($this->ranges)
			);
		} else if ( is_array($this->ranges) ) {
			usort($this->ranges, array($this, 'sorting'));

			$attribute = array(
				"type" => "RANGE",
				"range" => array(
					"type" => count($this->ranges)+1
				)
			);

			foreach ($this->ranges as $idx=>$iter) {
				$r = $idx+2;
				$attribute["range"]["range{$r}From"] = $iter->from;
				$attribute["range"]["range{$r}FeePrice"] = $iter->surcharge;

				if ( $idx >= 1 )	break; //range3From, range3FeePrice까지만 등록가능
			}

			return $attribute;
		}

		return null;
	}

}

class NaverSurchargesByArea implements INode {

	private $areas;

	public function __construct($areas) {
		$this->areas = $areas;
	}

	public function getAttribute() {
		if ( is_string($this->areas) ) {
			return array("apiSupport" => "true");
		} else if ( is_array($this->areas) ) {
			$attribute = array(
				"apiSupport" => "false",
			);

			$count = 0;
			$surcharge = array();
			foreach ($this->areas as $idx=>$iter) {
				if ( !in_array($iter->area, array("jeju", "island")) )	continue;

				$count++;
				$surcharge[$iter->area] = $iter->surcharge;
			}

			if ( $count == 1 ) {
				$attribute["splitUnit"] = 2;
				$attribute["area2Price"] = intval( $surcharge["island"] );
			} else if ( $count == 2 ) {
				$attribute["splitUnit"] = 3;
				$attribute["area2Price"] = intval( $surcharge["jeju"] );
				$attribute["area3Price"] = intval( $surcharge["island"] );
			}

			return $attribute;
		}

		return null;
	}

}