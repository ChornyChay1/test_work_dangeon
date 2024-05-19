<?php
abstract class TreasureChest {
    public $rarity;
    public $rewardRange;

    public function __construct($rarity, $rewardRange) {
        $this->rarity = $rarity;
        $this->rewardRange = $rewardRange;
    }

    public function open() {
        return rand($this->rewardRange[0], $this->rewardRange[1]);
    }
}

class CommonChest extends TreasureChest {
    public function __construct() {
        parent::__construct('Common', [5, 10]);
    }
}

class RareChest extends TreasureChest {
    public function __construct() {
        parent::__construct('Rare', [10, 20]);
    }
}

class EpicChest extends TreasureChest {
    public function __construct() {
        parent::__construct('Epic', [20, 40]);
    }
}
?>
