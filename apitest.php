<?php
require_once 'room.php';
require_once 'event.php';
require_once 'monster.php';

class apitest extends apiBaseClass
{
    private $dangeon;
    private $player;
    private $database;


    //http://www.example.com/api/?apitest.helloAPI={}
    function helloAPI()
    {
        $retJSON = $this->createDefaultJson();
        $retJSON->withoutParams = 'It\'s method called without parameters';
        return $retJSON;
    }

    //http://www.example.com/api/?apitest.helloAPIWithParams={"TestParamOne":"Text of first parameter"}
    function helloAPIWithParams($apiMethodParams)
    {
        $retJSON = $this->createDefaultJson();
        if (isset($apiMethodParams->TestParamOne)) {
            //Все ок параметры верные, их и вернем
            $retJSON->retParameter = $apiMethodParams->TestParamOne;
        } else {
            $retJSON->errorno = APIConstants::$ERROR_PARAMS;
        }
        return $retJSON;
    }

    /**
     * Пример URL: http://localhost:8000/api/?apitest.startGame={"player_id":1}
     */
    function startGame($apiMethodParams)
    {

        $player_id = $apiMethodParams->player_id;
        $this->database = SQLiteWorker::getInstance('mydatabase.db');
        $this->dangeon = initDungeon("dangeon.json", $player_id);
        $this->dangeon->save_to_db($this->database);
        $playerData = $this->database->get_player($player_id);
        $retJSON = $this->createDefaultJson();
        if (!$playerData) {
            $this->player = new Player($playerData->id, $playerData->name, $playerData->score, $this->dangeon->getRoom($playerData->current_room));
            $this->player->save_to_db($this->database);
        } else {
            // Если игрок не найден, возвращаем ошибку
            $retJSON->responce = ["player_id" => $player_id, "dangeon_id" => $this->dangeon->id];
            return $retJSON;
        }
    }


    /**
     * Пример URL: http://localhost:8000/api/?apitest.move={"player_id":1,"direction":"north","dangeon_id":1}
     */
    function move($apiMethodParams)
    {
        global $dungeon, $player, $database;

        $player_id = $apiMethodParams->player_id;
        $direction = $apiMethodParams->direction;
        $dangeon_id = $apiMethodParams->dangeon_id;
        $this->database = SQLiteWorker::getInstance('mydatabase.db');
        $this->dangeon = get_from_db($this->database, $dangeon_id);

        // Получаем текущего игрока из базы данных
        $playerData = $this->database->get_player($player_id);
        if ($playerData) {
            if ($playerData) {
                $this->player = new Player($playerData->id, $playerData->name, $playerData->score, $dungeon->getRoom($playerData->current_room));
            } else {
                // Если игрок не найден, возвращаем ошибку
                $retJSON = $this->createDefaultJson();
                $retJSON->errorno = APIConstants::$ERROR_PLAYER_NOT_FOUND;
                return $retJSON;
            }

            $current_room_id = $this->player->current_room->id;
            $current_room = $this->dangeon->getRoom($current_room_id);
            $dungeon->changeVisitedRoom($current_room->id);
            $next_room = $current_room->getNeighbor($direction);
            if ($next_room) {
                $player->current_room = $next_room->id;
                $player->save_to_db($database);
                $eventMessage = '';
                if (isset($next_room->visited) && $next_room->visited === true) {
                    $event = EventFactory::createEvent('empty', $dungeon, $player);
                    $event->trigger();
                    $eventMessage = $event->trigger();
                } else {

                    // Тригерим случайное событие в новой комнате
                    $events = ['monster', 'treasure', 'empty'];
                    $eventType = $events[rand(0, 2)];
                    $event = EventFactory::createEvent($eventType, $dungeon, $player);
                    $event->trigger();
                    $eventMessage = $event->trigger();
                }

                $retJSON = $this->createDefaultJson();
                $retJSON->success = true;
                $retJSON->room = [
                    "id" => $next_room->id,
                    "name" => $next_room->name,
                    "visited" => $next_room->visited,
                    "available_directions" => array_keys($next_room->neighbors)
                ];

                $retJSON->event_message = $eventMessage;


                return $retJSON;
            } else {
                $retJSON = $this->createDefaultJson();
                $retJSON->success = false;
                $retJSON->available_directions = array_keys($current_room->neighbors);
                return $retJSON;
            }
        }
    }


        //http://www.example.com/api/?apitest.helloAPIResponseBinary={"responseBinary":1}
        function helloAPIResponseBinary($apiMethodParams)
        {
            header('Content-type: image/png');
            echo file_get_contents("http://habrahabr.ru/i/error-404-monster.jpg");
        }


}


?>