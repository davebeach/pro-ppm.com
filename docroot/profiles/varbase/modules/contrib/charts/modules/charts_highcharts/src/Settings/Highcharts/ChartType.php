<?php

namespace Drupal\charts_highcharts\Settings\Highcharts;

class ChartType implements \JsonSerializable {
  private $type;

  /**
   * @return mixed
   */
  public function getType() {
    return $this->type;
  }

  /**
   * @param mixed $type
   */
  public function setType($type) {
    $this->type = $type;
  }

  /**
   * @return array
   */
  public function jsonSerialize() {
    $vars = get_object_vars($this);

    if ($vars['type'] == 'pie' || $vars['type'] == 'donut') {
      unset($vars['x']);
    }

    return $vars;
  }

}
