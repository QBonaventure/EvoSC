<?php


namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Server;
use esc\Classes\Template;
use esc\Interfaces\ModuleInterface;
use esc\Models\Player;

class ThreeTwoOneGo implements ModuleInterface
{

    /**
     * Called when the module is loaded
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
//        Hook::add('PlayerConnect', [self::class, 'sendWidget']);
    }

    public static function sendWidget(Player $player)
    {
        Template::show($player, '321.widget');
    }
}