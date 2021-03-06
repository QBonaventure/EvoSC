<?php

namespace esc\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Class Dedi
 * @package esc\Models
 * @property Player $player
 * @property Map $map
 */
class Dedi extends Model
{
    protected $table = 'dedi-records';

    protected $fillable = ['Map', 'Player', 'Score', 'Rank', 'Checkpoints', 'ghost_replay', 'New'];

    public $timestamps = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function player()
    {
        return $this->belongsTo(Player::class, 'Player', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function map()
    {
        return $this->belongsTo(Map::class, 'Map', 'id');
    }

    /**
     * @return string
     */
    public function score(): string
    {
        return formatScore($this->Score);
    }

    public function getGhostReplayAttribute(): ?string
    {
        if (isset($this->ghost_replay) && $this->ghost_replay != null) {
            return ghost($this->ghost_replay);
        }

        return null;
    }

    public function getCpsAttribute(): Collection
    {
        $cps = collect(explode(',', $this->Checkpoints))->transform(function($cp){
            return intval($cp);
        });

        return $cps;
    }

    public function __toString()
    {
        return secondary($this->Rank.'.$').config('colors.dedi').' dedimania record '.secondary(formatScore($this->Score));
    }
}