<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\TemplateController;
use esc\Models\Player;

class UiSettings
{
    public function __construct()
    {
        Hook::add('PlayerConnect', [self::class, 'sendUiSettings']);

        ManiaLinkEvent::add('ui.settings', [self::class, 'mleShowSettingsWindow']);
        ManiaLinkEvent::add('ui.save', [self::class, 'mleSaveSettings']);

        QuickButtons::addButton('', 'UI Settings', 'ui.settings');
    }

    public static function mleSaveSettings(Player $player, ...$data)
    {
        $player->setSetting('ui', implode(',', $data));
        self::sendUiSettings($player);
    }

    public static function mleShowSettingsWindow(Player $player)
    {
        $settings = $player->setting('ui');
        Template::show($player, 'ui-settings.manialink', compact('settings'));
    }

    public static function sendUiSettings(Player $player)
    {
        $settings = $player->setting('ui');
        Template::show($player, 'ui-settings.update', compact('settings'));
    }
}