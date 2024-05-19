<?php
class SQLiteWorker {

    protected static $instance;  // object instance
    public $dbFile;
    public $connectLink = null;

    // Чтобы нельзя было создать через вызов new SQLiteWorker
    public function __construct() { /* ... */
    }

    // Чтобы нельзя было создать через клонирование
    public function __clone() { /* ... */
    }

    // Чтобы нельзя было создать через unserialize
    public function __wakeup() { /* ... */
    }

    // Получаем объект синглтона
    public static function getInstance($dbFile) {
        if (is_null(self::$instance)) {
            self::$instance = new SQLiteWorker();
            self::$instance->dbFile = $dbFile;
            self::$instance->checkDatabase();
            self::$instance->openConnection();
        }
        return self::$instance;
    }

    public function checkDatabase() {
        $this->createPlayersTable();
    }


    public function createPlayersTable() {
        $query = "
        CREATE TABLE IF NOT EXISTS players (
            id INTEGER PRIMARY KEY,
            name TEXT,
            score INTEGER,
            current_room_id INTEGER
        );
    ";
        $this->executeQuery($query);
    }


    // Соединяемся с базой
    public function openConnection() {
        if (is_null($this->connectLink)) {
            try {
                $this->connectLink = new PDO('sqlite:' . $this->dbFile);
                $this->connectLink->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                printf("Подключение невозможно: %s\n", $e->getMessage());
                $this->connectLink = null;
            }
        }
        return $this->connectLink;
    }

    // Закрываем соединение с базой
    public function closeConnection() {
        if (!is_null($this->connectLink)) {
            $this->connectLink = null;
        }
    }

    // Определяем типы параметров запроса к базе и возвращаем строку для привязки через ->bind
    function prepareParams($params) {
        // В SQLite привязка параметров не требует указания типов
        return array_map(function($param) {
            return is_int($param) || is_double($param) ? PDO::PARAM_INT : PDO::PARAM_STR;
        }, $params);
    }

    // Выполнение запроса
    public function executeQuery($query, $params = []) {
        if (is_null($this->connectLink)) {
            $this->openConnection();
        }
        $stmt = $this->connectLink->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    // Преобразуем ответ в ассоциативный массив
    public function fetchAssoc($stmt) {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_player($player_id) {
        $query = "SELECT * FROM players WHERE id = ?";
        $stmt = $this->executeQuery($query, [$player_id]);
        $player_data = $this->fetchAssoc($stmt);

        if (!empty($player_data)) {
            // Возвращаем данные игрока в виде объекта Player
            return new Player($player_data[0]['id'], $player_data[0]['name'], $player_data[0]['score'], $player_data[0]['current_room_id']);
        } else {
            return null; // Если игрок не найден в базе данных
        }
    }
}
?>
