<?php

use esc\classes\Config;
use esc\classes\Database;
use esc\classes\File;
use esc\classes\Hook;
use esc\classes\Log;
use esc\classes\RestClient;
use esc\classes\Template;
use esc\controllers\ChatController;
use esc\controllers\MapController;
use esc\controllers\ServerController;
use esc\models\Map;
use esc\models\Player;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;

class Dedimania
{
    private static $dedis;

    public function __construct()
    {
        $this->createTables();
        self::$dedis = new Collection();

        include_once __DIR__ . '/Models/Dedi.php';
        include_once __DIR__ . '/Models/DedimaniaSession.php';

        Hook::add('BeginMap', 'Dedimania::beginMap');
        Template::add('dedis', File::get(__DIR__ . '/Templates/dedis.latte.xml'));
    }

    private function createTables()
    {
        Database::create('dedi-records', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('Map');
            $table->integer('Player');
            $table->integer('Score');
            $table->integer('Rank');
            $table->unique(['Map', 'Rank']);
        });

        Database::create('dedi-sessions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Session');
            $table->boolean('Expired')->default(false);
            $table->timestamps();
        });
    }

    private static function getSession(): ?DedimaniaSession
    {
        $sessions = DedimaniaSession::whereExpired(false)->orderByDesc('updated_at');

        $session = $sessions->first();

        if ($session == null) {
//            $sessionId = self::authenticateAndValidateAccount();
            $sessionId = null;

            if ($sessionId == null) {
                Log::warning("Connection to Dedimania failed. Using cached values.");
                return null;
            }

            return DedimaniaSession::create(['Session' => $sessionId]);
        }

        return $session;
    }

    public static function beginMap(Map $map)
    {
        $session = self::getSession();

        if($session == null){
            Log::warning("Dedimania offline. Using cached values.");
            ChatController::messageAll('Dedimania is offline. Using cached values.');
        }

        $data = self::call('dedimania.GetChallengeInfo', [
            'UId' => $map->UId
        ]);

        $records = $data->params->param->value->struct->member[5]->value->array->data->value;

        foreach ($records as $record) {
            $login = (string)$record->struct->member[0]->value->string;
            $nickname = (string)$record->struct->member[1]->value->string;
            $score = (int)$record->struct->member[2]->value->int;
            $rank = (int)$record->struct->member[3]->value->int;

            $player = Player::firstOrCreate(['Login' => $login, 'NickName' => $nickname]);

            if(isset($player->id)){
                Dedi::firstOrCreate([
                    'Map' => $map->id,
                    'Player' => $player->id,
                    'Score' => $score,
                    'Rank' => $rank
                ]);
            }
        }

        self::displayDedis();
    }

    public static function displayDedis()
    {
        $map = MapController::getCurrentMap();
        $dedis = $map->dedis->sortBy('Rank')->take(15);
        Template::showAll('dedis', ['dedis' => $dedis]);
    }

    /**
     * Connect to dedimania and authenticate
     */
    private static function authenticateAndValidateAccount(): ?string
    {
        $response = Dedimania::callStruct('dedimania.OpenSession', [
            'Game' => 'TM2',
            'Login' => Config::get('dedimania.login'),
            'Code' => Config::get('dedimania.key'),
            'Tool' => 'EvoSC',
            'Version' => '0.7.8',
            'Packmask' => 'Stadium',
            'ServerVersion' => ServerController::getRpc()->getVersion()->version,
            'ServerBuild' => ServerController::getRpc()->getVersion()->build,
            'Path' => ServerController::getRpc()->getDetailedPlayerInfo(Config::get('dedimania.login'))->path
        ]);

        Log::logAddLine($response, false);

        try {
            if (trim($response) == '') {
                throw new Exception('Connection to Dedimania failed.');
            }

            return (string)$response->params->param->value->array->data->value->array->data->value->struct->member->value->string;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Call a method on dedimania server
     * See documentation at http://dedimania.net:8082/Dedimania
     * @param string $method
     * @param array|null $parameters
     * @return null|SimpleXMLElement
     */
    public static function call(string $method, array $parameters = null): ?SimpleXMLElement
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', $method);

        $params = $xml->addChild('params');

        if ($parameters) {
            foreach ($parameters as $key => $param) {
                $member = $params->addChild('member');
                $member->addChild('name', $key);
                $member->addChild('value')->addChild('string', $param);
            }
        }

        $response = RestClient::post('http://dedimania.net:8082/Dedimania', [
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF8'
            ],
            'body' => $xml->asXML()
        ]);

        if ($response->getStatusCode() != 200) {
            Log::error($response->getReasonPhrase());
            return null;
        }

        return new SimpleXMLElement($response->getBody());
    }

    /**
     * Call a method on dedimania server
     * See documentation at http://dedimania.net:8082/Dedimania
     * @param string $method
     * @param array|null $parameters
     * @return null|SimpleXMLElement
     */
    public static function callStruct(string $method, array $parameters = null): ?SimpleXMLElement
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><methodCall></methodCall>');
        $xml->addChild('methodName', 'system.multicall');

        $struct = $xml
            ->addChild('params')
            ->addChild('param')
            ->addChild('value')
            ->addChild('array')
            ->addChild('data')
            ->addChild('value')
            ->addChild('struct');

        $member = $struct->addChild('member');
        $member->addChild('name', 'methodName');
        $member->addChild('value')->addChild('string', $method);

        if ($parameters) {
            $structArrayMember = $struct->addChild('member');
            $structArrayMember->addChild('name', 'params');
            $structArray = $structArrayMember->addChild('value')->addChild('array')->addChild('data')->addChild('value')->addChild('struct');

            foreach ($parameters as $key => $param) {
                $subMember = $structArray->addChild('member');
                $subMember->addChild('name', $key);
                $subMember->addChild('value')->addChild('string', $param);
            }
        }

        $response = RestClient::post('http://dedimania.net:8082/Dedimania', [
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF8'
            ],
            'body' => $xml->asXML()
        ]);

        if ($response->getStatusCode() != 200) {
            Log::error($response->getReasonPhrase());
            return null;
        }

        return new SimpleXMLElement($response->getBody());
    }
}