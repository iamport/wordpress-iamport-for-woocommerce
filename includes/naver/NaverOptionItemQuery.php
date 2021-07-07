<?php
require_once('INode.php');

class NaverOptionItemQuery implements INode {

  private $code = null;
  private $value = null;

  public function __construct($code, $value) {
    $this->code = $code;
    $this->value = $value;
  }

  public function getAttribute() {
    return array(
      "id" => $this->code,
      "text" => $this->value,
    );
  }

}