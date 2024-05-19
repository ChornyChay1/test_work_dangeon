<?php
abstract class Monster {
    public $name;
    public $strength;

    public function __construct($name, $strength) {
        $this->name = $name;
        $this->strength = $strength;
    }

    abstract public function reduceStrength();

    public function isDefeated() {
        return $this->strength <= 0;
    }
}

class Goblin extends Monster {
    public function __construct() {
        $name = 'Goblin';
        $strength = rand(5, 10); // диапазон стартовой силы
        parent::__construct($name, $strength);
    }

    public function reduceStrength() {
        $this->strength -= 1; // уменьшение силы на 1
    }
}

class Orc extends Monster {
    public function __construct() {
        $name = 'Orc';
        $strength = rand(10, 20); // диапазон стартовой силы
        parent::__construct($name, $strength);
    }

    public function reduceStrength() {
        $this->strength -= 2; // уменьшение силы на 2
    }
}
?>
