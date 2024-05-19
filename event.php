<?php
require_once 'monster.php';
require_once 'chest.php';

abstract class Event {
    public $room;
    public $type;
    public $message;

    function __construct($room) {
        $this->room = $room;
    }

    abstract public function trigger();
}

class EmptyEvent extends Event {
    public function __construct($room) {
        parent::__construct($room);
        $this->type = 'empty';
        $this->message = 'Ничего не случилось';
    }

    public function trigger() {
       return $this->message . "\n";
    }
}

class MonsterEvent extends Event {
    private $monster;

    public function __construct($room) {
        parent::__construct($room);
        $this->type = 'monster';
        $this->monster = $this->generateMonster();
    }

    private function generateMonster() {
        $monsterTypes = ['Goblin', 'Orc'];
        $type = $monsterTypes[array_rand($monsterTypes)];
        return new $type();
    }

    public function trigger() {
        global $player, $database;
        $a = "A {$this->monster->name} appears in room {$this->room->name}!\n";

        $hits = 0;
        while (!$this->monster->isDefeated()) {
            $hits++;
            $attackPower = rand(1, 20);
            if ($attackPower > $this->monster->strength) {
                break;
            } else {
                $this->monster->reduceStrength();
            }
        }

        $earnedPoints = $this->monster->strength;
        $player->score += $earnedPoints;
        $player->save_to_db($database);
        $b = "Вы победили {$this->monster->name}  за {$hits} ударов, и заработали {$earnedPoints}, ваш счёт {$player->score}!\n";

        return $a.$b;
    }
}

class TreasureEvent extends Event {
    private $chest;

    public function __construct($room) {
        parent::__construct($room);
        $this->type = 'treasure';
        $this->chest = $this->generateChest();
    }

    private function generateChest() {
        $chestTypes = ['CommonChest', 'RareChest', 'EpicChest'];
        $type = $chestTypes[array_rand($chestTypes)];
        return new $type();
    }

    public function trigger() {
        global $player, $database;
        $a =  "Вы нашли {$this->chest->rarity} сундук в комнате {$this->room->name}!\n";

        $earnedPoints = $this->chest->open();
        $player->score += $earnedPoints;
        $player->save_to_db($database);
        $b = "Вы открыли {$this->chest->rarity} сундук и получили {$earnedPoints} очков!\n";
        return $a . $b;
    }
}

class EventFactory {
    public static function createEvent($type, $room) {
        switch ($type) {
            case 'monster':
                return new MonsterEvent($room);
            case 'treasure':
                return new TreasureEvent($room);
            default:
                return new EmptyEvent($room);
        }
    }
}
?>
