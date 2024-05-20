<?php
require_once 'player.php';
require_once 'SQLiteWorker.php';

class Room {
    public $id;
    public $name;
    public $dungeon_id;
    public $is_end;
    public $neighbors;
    public $visited;

    function __construct($id, $name, $dungeon_id,$is_end) {
        $this->dungeon_id = $dungeon_id;
        $this->id = $id;
        $this->name = $name;
        $this->neighbors = [];
        $this->visited = false;
        $this->is_end  = $is_end;

    }

    function addNeighbor($room, $direction) {
        $this->neighbors[$direction] = $room;
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
    function save_to_db($db_worker) {
        // Создаем таблицу комнат, если она не существует
        $query_create_table_rooms = "CREATE TABLE IF NOT EXISTS rooms (
            id INTEGER PRIMARY KEY,
            dungeon_id INTEGER,
            name TEXT,
            visited BOOLEAN,
            is_end INTEGER,
            FOREIGN KEY(dungeon_id) REFERENCES dungeons(id)
        )";
        $db_worker->executeQuery($query_create_table_rooms);

        // Вставляем информацию о комнате в таблицу rooms
        $query_insert_room = "INSERT OR REPLACE INTO rooms (id, dungeon_id, name, visited,is_end) VALUES (?, ?, ?, ?,?)";
        $params_room = [$this->id, $this->dungeon_id, $this->name, $this->visited,$this->is_end];
        $db_worker->executeQuery($query_insert_room, $params_room);
    }
    public function getDistanceToNeighbor($neighbor) {
         return 1;
    }
}

class Dungeon {
    public $rooms;
    public $id;
    public $is_locked;
    public $name;
    public $visitedRooms;


    function __construct() {
        $this->rooms = [];
        $this->is_locked = 0;
        $this->visitedRooms = [];

    }

    function addRoom($room) {
        $this->rooms[$room->id] = $room;
    }
  // Функция для нахождения кратчайшего пути от начальной комнаты до центральной
    // Функция для нахождения кратчайшего пути от начальной комнаты до центральной
    function findShortestPath($startRoomId) {
        // Инициализация массива для хранения комнат и их приоритета (длины пути)
        $queue = [];
        // Инициализация массива путей: для каждой комнаты хранится путь от начальной комнаты
        $paths = [];

        // Инициализация начального пути: путь до начальной комнаты состоит только из самой комнаты
        $paths[$startRoomId] = [$startRoomId];

        // Добавляем начальную комнату в массив с приоритетом 0
        $queue[$startRoomId] = 0;

        // Пока очередь не пуста
        while (!empty($queue)) {
            // Ищем комнату с минимальным приоритетом (длиной пути)
            $currentRoomId = array_search(min($queue), $queue);
             $currentPath = $paths[$currentRoomId];

            // Удаляем текущую комнату из массива
            unset($queue[$currentRoomId]);

            // Помечаем комнату как посещенную
            $this->visitedRooms[$currentRoomId] = true;

            // Если мы достигли центральной комнаты, завершаем поиск
            if ($this->rooms[$currentRoomId]->is_end == 1) {
                return implode(' -> ', $currentPath); // Возвращаем путь в виде строки
            }

            // Для каждого соседа текущей комнаты
            foreach ($this->rooms[$currentRoomId]->neighbors as $neighborDirection => $neighborRoom) {
                // Если сосед еще не посещен
                if (!isset($this->visitedRooms[$neighborRoom->id])) {
                    // Добавляем соседа в текущий путь
                    $newPath = $currentPath;
                    $newPath[] = $neighborRoom->id;

                    // Обновляем путь и добавляем соседа в массив с новым приоритетом
                    $paths[$neighborRoom->id] = $newPath;
                    $queue[$neighborRoom->id] = count($newPath);
                }
            }
        }

        // Если не удалось найти путь до центральной комнаты, возвращаем null
        return null;
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
        if (isset($this->rooms[$room_id])) {
            $this->rooms[$room_id]->visited = true;
        }
    }

    public function save_to_db($db_worker) {
        // Создаем таблицу подземелья, если она не существует
        $query_create_table_dungeon = "CREATE TABLE IF NOT EXISTS dungeons (
            id INTEGER PRIMARY KEY,
            name TEXT,
            is_locked INTEGER
        )";
        $db_worker->executeQuery($query_create_table_dungeon);

        // Вставляем информацию о подземелье в таблицу dungeons
        $query_insert_dungeon = "INSERT OR REPLACE INTO dungeons (id, name,is_locked) VALUES (?, ?,?)";
        $params_dungeon = [$this->id, $this->name,$this->is_locked];
        $db_worker->executeQuery($query_insert_dungeon, $params_dungeon);

        // Создаем таблицу комнат, если она не существует
        $query_create_table_rooms = "CREATE TABLE IF NOT EXISTS rooms (
            id INTEGER PRIMARY KEY,
            dungeon_id INTEGER,
            name TEXT,
            visited BOOLEAN,
            is_end INTEGER,
            FOREIGN KEY(dungeon_id) REFERENCES dungeons(id)
        )";
        $db_worker->executeQuery($query_create_table_rooms);

        // Вставляем информацию о каждой комнате подземелья в таблицу rooms
        foreach ($this->rooms as $room) {
            $query_insert_room = "INSERT OR REPLACE INTO rooms (id, dungeon_id, name, visited,is_end) VALUES (?, ?, ?, ?,?)";
            $params_room = [$room->id, $room->dungeon_id, $room->name, $room->visited,$room->is_end];
            $db_worker->executeQuery($query_insert_room, $params_room);
        }

        // Создаем таблицу связей между комнатами, если она не существует
        $query_create_table_connections = "CREATE TABLE IF NOT EXISTS room_connections (
            room1_id INTEGER,
            room2_id INTEGER,
            direction TEXT,
            PRIMARY KEY (room1_id, room2_id, direction),
            FOREIGN KEY (room1_id) REFERENCES rooms(id),
            FOREIGN KEY (room2_id) REFERENCES rooms(id)
        )";
        $db_worker->executeQuery($query_create_table_connections);

        // Вставляем информацию о связях между комнатами в таблицу room_connections
        foreach ($this->rooms as $room) {
            foreach ($room->neighbors as $direction => $neighbor) {
                $query_insert_connection = "INSERT OR REPLACE INTO room_connections (room1_id, room2_id, direction) VALUES (?, ?, ?)";
                $params_connection = [$room->id, $neighbor->id, $direction];
                $db_worker->executeQuery($query_insert_connection, $params_connection);
            }
        }
    }
}

function initDungeon($filePath, $person_id) {
    $dungeon = new Dungeon();
    $database = SQLiteWorker::getInstance('mydatabase.db');

    $playerData = $database->get_player($person_id);
    if ($playerData) {
        $player = new Player($playerData->id, $playerData->name, $playerData->score, $dungeon->getRoom($playerData->current_room));
    } else {
        $player = new Player(1, "New Player", 200, $dungeon->getRoom(1));
    }

    $dungeon_data = json_decode(file_get_contents($filePath), true);
    if ($dungeon_data === null) {
        throw new Exception("Не удалось прочитать или декодировать JSON файл.");
    }

    $dungeon = new Dungeon();
    $dungeon->id = $person_id;
    foreach ($dungeon_data['rooms'] as $room_data) {
        $room = new Room($room_data['id'], $room_data['name'], $person_id,$room_data['is_end']);
        $dungeon->addRoom($room);
    }

    foreach ($dungeon_data['connections'] as $connection) {
        $dungeon->connectRooms($connection['room1_id'], $connection['room2_id'], $connection['direction']);
    }

    return $dungeon;
}

function get_from_db($db_worker, $dungeon_id) {
    $query = "SELECT * FROM dungeons WHERE id = ?";
    $params = [$dungeon_id];
    $stmt = $db_worker->executeQuery($query, $params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($result && count($result) > 0) {
        $dungeon = new Dungeon();
        $dungeon->id = $result[0]['id'];
        $dungeon->name = $result[0]['name'];
        $dungeon->is_locked = $result[0]['is_locked'];


        $query_rooms = "SELECT * FROM rooms WHERE dungeon_id = ?";
        $params_rooms = [$dungeon_id];
        $stmt_rooms = $db_worker->executeQuery($query_rooms, $params_rooms);
        $result_rooms = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);

        if ($result_rooms && count($result_rooms) > 0) {
            foreach ($result_rooms as $room_data) {
                if (!isset($room_data['id'], $room_data['name'], $room_data['dungeon_id'])) {
                    continue;
                }
                $room = new Room($room_data['id'], $room_data['name'], $room_data['dungeon_id'],$room_data['is_end']);
                $room->visited = isset($room_data['visited']) ? $room_data['visited'] : false;
                $dungeon->addRoom($room);
            }
        } else {
            error_log("No rooms found for dungeon_id: $dungeon_id");
        }

        $query_connections = "SELECT * FROM room_connections WHERE room1_id IN (SELECT id FROM rooms WHERE dungeon_id = ?)";
        $params_connections = [$dungeon_id];
        $stmt_connections = $db_worker->executeQuery($query_connections, $params_connections);
        $result_connections = $stmt_connections->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result_connections as $connection_data) {
            if (!isset($connection_data['room1_id'], $connection_data['room2_id'], $connection_data['direction'])) {
                continue;
            }
            $room1 = $dungeon->getRoom($connection_data['room1_id']);
            $room2 = $dungeon->getRoom($connection_data['room2_id']);
            if ($room1 && $room2) {
                $room1->addNeighbor($room2, $connection_data['direction']);
            }
        }

        return $dungeon;
    }

    error_log("Dungeon not found for id: $dungeon_id");
    return null;
}
?>
