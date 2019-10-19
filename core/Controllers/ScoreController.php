<?php


namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\ScoreTracker;
use esc\Interfaces\ControllerInterface;
use esc\Models\Player;
use Illuminate\Support\Collection;

class ScoreController implements ControllerInterface
{
    /**
     * @var Collection
     */
    private static $tracker;

    /**
     * Method called on controller boot.
     */
    public static function init()
    {
    }

    /**
     * Method called on controller start and mode change
     *
     * @param  string  $mode
     * @param  bool  $isBoot
     */
    public static function start(string $mode, bool $isBoot)
    {
        self::$tracker = collect();
        Hook::add('BeginMatch', [self::class, 'beginMatch']);
        Hook::add('PlayerFinish', [self::class, 'playerFinish']);
    }

    public static function beginMatch()
    {
        self::$tracker = collect();
    }

    /**
     * @param  int  $playerId
     * @return ScoreTracker
     */
    private static function getScoreTracker(int $playerId)
    {
        return self::$tracker->get($playerId);
    }

    public static function playerFinish(Player $player, int $score, string $checkpoints)
    {
        if ($score == 0) {
            return;
        }

        if (!self::$tracker->has($player->id)) {
            $tracker = new ScoreTracker($player, $score, $checkpoints);
            self::$tracker->put($player->id, $tracker);
        } else {
            $tracker = self::getScoreTracker($player->id);

            $tracker->last_score = $score;
            $tracker->last_checkpoints = $checkpoints;

            if ($score < $tracker->best_score) {
                $tracker->best_score = $score;
                $tracker->best_checkpoints = $checkpoints;
            }

            self::$tracker->put($player->id, $tracker);
        }

//        Hook::fire('ScoreTrackerUpdated', self::$tracker);
    }
}