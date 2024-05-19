<?php
require_once 'player.php';
require_once 'SQLiteWorker.php';

class Room {
    public $id;
    public $name;
    public $dungeon_id;

    public $neighbors;
    public $visited;


    function __construct($id, $name,$dangeon_id) {
        $this->dungeon_id = $dangeon_id;
        $this->id = $id;
        $this->name = $name;
        $this->neighbors = [];
        $this->visited= false;
    }

    function addNeighbor($room, $direction) {
        $this->neighbors[$direction] = $room;
        // Также добавляем обратную связь от соседней комнаты к текущей
        $room->neighbors[$this->getOppositeDirection($direction)] = $this;
    }

    function getNeighbor($direction) {
        return isset($this->neighbors[$direction]) ? $this->neighbors[$direction] : null;

    }

    private function getOppositeDirection($direction) {
        switch ($direction) {
            case 'north': return 'south';
            case 'south': return 'north';
            case 'east': return 'west';
            case 'west': return 'east';
            default: return null;
        }
    }
}

class Dungeon {
    public $rooms;
    public $id;
    public $name;
    function __construct() {
        $this->rooms = [];
    }

    function addRoom($room) {
        $this->rooms[$room->id] = $room;
    }

    function connectRooms($room1_id, $room2_id, $direction_from_room1_to_room2) {
        $room1 = isset($this->rooms[$room1_id]) ? $this->rooms[$room1_id] : null;
        $room2 = isset($this->rooms[$room2_id]) ? $this->rooms[$room2_id] : null;
        if ($room1 && $room2) {
            $room1->addNeighbor($room2, $direction_from_room1_to_room2);
        }
    }

    function getRoom($room_id) {


        return isset($this->rooms[$room_id]) ? $this->rooms[$room_id] : null;
    }
    function changeVisitedRoom($room_id) {


        $this->rooms[$room_id]->visited = true;

    }
    public function save_to_db($db_worker) {
        // Создаем таблицу подземелья, если она не существует
        $query_create_table_dungeon = "CREATE TABLE IF NOT EXISTS dungeons (
            id INTEGER PRIMARY KEY,
            name TEXT
        )";
        $db_worker->executeQuery($query_create_table_dungeon);

        // Вставляем информацию о подземелье в таблицу dungeons
        $query_insert_dungeon = "INSERT OR REPLACE INTO dungeons (name,id) VALUES (?,?)";
        $params_dungeon = [$this->name,$this->id];
        $db_worker->executeQuery($query_insert_dungeon, $params_dungeon);

        // Создаем таблицу комнат, если она не существует
        $query_create_table_rooms = "CREATE TABLE IF NOT EXISTS rooms (
            id INTEGER PRIMARY KEY,
            dungeon_id INTEGER,
            name TEXT,
            visited BOOLEAN,
            FOREIGN KEY(dungeon_id) REFERENCES dungeons(id)
        )";
        $db_worker->executeQuery($query_create_table_rooms);

        // Вставляем информацию о каждой комнате подземелья в таблицу rooms
        foreach ($this->rooms as $room) {
            $query_insert_room = "INSERT OR REPLACE INTO rooms (id, dungeon_id, name, visited) VALUES (?, ?, ?, ?)";
            $params_room = [$room->id, $room->dungeon_id, $room->name, $room->visited];
            $db_worker->executeQuery($query_insert_room, $params_room);
        }
    }
}



function initDungeon($filePath,$person_id) {

    $dungeon = new Dungeon();
// Создаем экземпляр SQLiteWorker
    $database = SQLiteWorker::getInstance('mydatabase.db');

    $playerData = $database->get_player($person_id); // Предполагается, что у игрока id = 1
    if ($playerData) {
        // Если игрок найден, создаем игрока с загруженными данными
        $player = new Player($playerData->id, $playerData->name, $playerData->score, $dungeon->getRoom($playerData->current_room));
    } else {
        // Если игрок не найден, создаем нового игрока и помещаем его в комнату 1
        $player = new Player(1, "New Player",200, $dungeon->getRoom(1)); // Обратите внимание на изменение в этой строке
    }

    // Читаем структуру подземелья из JSON файла
    $dungeon_data = json_decode(file_get_contents($filePath), true);

    if ($dungeon_data === null) {
        throw new Exception("Не удалось прочитать или декодировать JSON файл.");
    }

    // Создаем подземелье
    $dungeon = new Dungeon();
    $dungeon->id = $person_id;
    // Создаем комнаты и добавляем их в подземелье
    foreach ($dungeon_data['rooms'] as $room_data) {
        $room = new Room($room_data['id'], $room_data['name'],$person_id);
        $dungeon->addRoom($room);
    }

    // Связываем комнаты
    foreach ($dungeon_data['connections'] as $connection) {
        $dungeon->connectRooms($connection['room1_id'], $connection['room2_id'], $connection['direction']);
    }

    return $dungeon;
}
function get_from_db($db_worker, $dungeon_id) {
    // Запрос для получения информации о подземелье по его ID
    $query = "SELECT * FROM dungeons WHERE id = ?";
    $params = [$dungeon_id];
    $result = $db_worker->executeQuery($query, $params);

    // Если подземелье существует, создаем экземпляр класса Dungeon и заполняем его данными из базы данных
    if ($result && count($result) > 0) {
        $dungeon = new Dungeon();
        $dungeon->name = $result[0]['name'];

        // Запрос для получения информации о комнатах подземелья
        $query_rooms = "SELECT * FROM rooms WHERE dungeon_id = ?";
        $params_rooms = [$dungeon_id];
        $result_rooms = $db_worker->executeQuery($query_rooms, $params_rooms);

        // Проверка результатов запроса на комнаты
        if ($result_rooms && count($result_rooms) > 0) {
            // Создаем экземпляры класса Room для каждой комнаты и добавляем их в подземелье
            foreach ($result_rooms as $room_data) {
                if (!isset($room_data['id'], $room_data['name'])) {
                    // Если данные комнаты не содержат ожидаемых полей
                    continue;
                }
                $room = new Room($room_data['id'], $room_data['name']);
                $room->visited = $room_data['visited'] ?? false; // Используем значение по умолчанию, если поле отсутствует
                $dungeon->addRoom($room);
            }
        } else {
            // Если не удалось получить комнаты, выводим отладочную информацию
            error_log("No rooms found for dungeon_id: $dungeon_id");
        }

        return $dungeon;
    }

    // Если подземелье не найдено, выводим отладочную информацию
    error_log("Dungeon not found for id: $dungeon_id");
    return null;
}




?>
