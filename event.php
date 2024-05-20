<?php
require_once 'monster.php';
require_once 'chest.php';

abstract class Event {
    public $room;
    public $type;
    public $message;
    public $player;


    function __construct($room,$player) {
        $this->room = $room;
        $this->player=$player;
    }

    abstract public function trigger();
}

class EmptyEvent extends Event {
    public function __construct($room,$player) {
        parent::__construct($room,$player);
        $this->type = 'empty';
        $this->player = $player;
        $this->message = 'Ничего не случилось';
    }

    public function trigger() {
       return $this->message . "\n";
    }
}

class MonsterEvent extends Event {
    private $monster;


    public function __construct($room,$player) {
        parent::__construct($room,$player);
        $this->type = 'monster';
        $this->monster = $this->generateMonster();
        $this->player = $player;
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



        $database = SQLiteWorker::getInstance('mydatabase.db');
        $earnedPoints = $this->monster->strength;
        $this->player->score += $earnedPoints;
        $this->player->save_to_db($database);
        $b = "Вы победили {$this->monster->name}  за {$hits} ударов, и заработали {$earnedPoints}, ваш счёт {$this->player->score}!\n";

        return $a.$b;
    }
}

class TreasureEvent extends Event {
    private $chest;

    public function __construct($room,$player) {
        parent::__construct($room,$player);
        $this->type = 'treasure';
        $this->chest = $this->generateChest();
        $this->player = $player;
    }

    private function generateChest() {
        $chestTypes = ['CommonChest', 'RareChest', 'EpicChest'];
        $type = $chestTypes[array_rand($chestTypes)];
        return new $type();
    }

    public function trigger() {
        global $player, $database;
        // Получаем текущего игрока из базы данных

        $database = SQLiteWorker::getInstance('mydatabase.db');



        $a =  "Вы нашли {$this->chest->rarity} сундук в комнате {$this->room->name}!\n";

        $earnedPoints = $this->chest->open();
        $this->player->score += $earnedPoints;
        $this->player->save_to_db($database);
        $b = "Вы открыли {$this->chest->rarity} сундук и получили {$earnedPoints} очков,ваш счёт - {$this->player->score}!\n";
        return $a . $b;
    }
}

class EventFactory {
    public static function createEvent($type, $room,$player) {
        switch ($type) {
            case 'monster':
                return new MonsterEvent($room,$player);
            case 'treasure':
                return new TreasureEvent($room,$player);
            default:
                return new EmptyEvent($room,$player);
        }
    }
}
?>
