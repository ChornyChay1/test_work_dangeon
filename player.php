<?php
class Player {
    public $id;
    public $name;
    public $score;
    public $current_room;

    function __construct($id, $name, $score, $current_room) {
        $this->id = $id;
        $this->name = $name;
        $this->score = $score;
        $this->current_room = $current_room;
    }

    public function save_to_db($db_worker) {
        // Подготовим запрос для сохранения данных игрока в базе данных
        $query = "INSERT OR REPLACE INTO players (id, name, score, current_room_id) 
VALUES (?, ?, ?, ?)";
        $params = [$this->id, $this->name, $this->score, $this->current_room];

        // Выполним запрос к базе данных
        $db_worker->executeQuery($query, $params);
    }

    // Функция для загрузки подземелья из базы данных по его ID
    public static function get_from_db($db_worker, $dungeon_id) {
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

            // Создаем экземпляры класса Room для каждой комнаты и добавляем их в подземелье
            foreach ($result_rooms as $room_data) {
                $room = new Room($room_data['id'], $room_data['name']);
                $room->visited = $room_data['visited'];
                $dungeon->addRoom($room);
            }

            return $dungeon;
        }

        return null; // Возвращаем null, если подземелье не найдено
    }


}
?>