<?php

namespace esc\Models;


use esc\Classes\Cache;
use esc\Classes\File;
use esc\Classes\Log;
use esc\Controllers\MapController;
use esc\Modules\MxMapDetails;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use stdClass;

/**
 * Class Map
 *
 * @package esc\Models
 *
 * @property string $id
 * @property string $uid
 * @property string $filename
 * @property Player $author
 * @property boolean $enabled
 * @property string $last_played
 * @property string $mx_id
 * @property int $cooldown
 * @property int $plays
 * @property string $name
 * @property string $environment
 * @property string $title_id
 *
 */
class Map extends Model
{
    /**
     * @var string
     */
    protected $table = 'maps';

    /**
     * @var array
     */
    protected $fillable = [
        'uid',
        'filename',
        'plays',
        'author',
        'last_played',
        'enabled',
        'mx_id',
        'mx_details',
        'mx_world_record',
        'cooldown',
        'name',
        'environment',
        'title_id',
    ];

    /**
     * @var array
     */
    protected $dates = ['last_played'];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return HasMany
     */
    public function locals()
    {
        return $this->hasMany(LocalRecord::class, 'Map');
    }

    /**
     * @return HasMany
     */
    public function dedis()
    {
        return $this->hasMany(Dedi::class, 'Map');
    }

    /**
     * @return HasOne
     */
    public function author()
    {
        return $this->hasOne(Player::class, 'id', 'author');
    }

    /**
     * @param $playerId
     *
     * @return mixed
     */
    public function getAuthorAttribute($playerId)
    {
        return Player::whereId($playerId)->first();
    }

    /**
     * @return HasMany
     */
    public function ratings()
    {
        return $this->hasMany(Karma::class, 'Map', 'id');
    }

    /**
     * @return mixed
     */
    public function getAverageRatingAttribute()
    {
        $mxDetails = $this->mx_details;

        if ($mxDetails && $mxDetails->RatingVoteCount > 0) {
            return $mxDetails->RatingVoteAverage;
        }

        return $this->ratings()->pluck('Rating')->average();
    }

    /**
     * @return mixed|stdClass|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getMxDetailsAttribute()
    {
        if (!$this->mx_id) {
            return null;
        }

        if (Cache::has('mx-details/'.$this->mx_id)) {
            return Cache::get('mx-details/'.$this->mx_id);
        }

        return null;
    }

    /**
     * @return mixed|stdClass|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getMxWorldRecordAttribute()
    {
        if (!$this->mx_id) {
            return null;
        }

        if (Cache::has('mx-wr/'.$this->mx_id)) {
            return Cache::get('mx-wr/'.$this->mx_id);
        }

        return null;
    }

    /**
     * @return stdClass
     */
    public function getGbxAttribute()
    {
        $cacheId = 'gbx/'.$this->uid;

        if (Cache::has($cacheId)) {
            return Cache::get($cacheId);
        }

        $gbx = MapController::getGbxInformation($this->filename, false);
        Cache::put($cacheId, $gbx);

        return $gbx;
    }

    /**
     * @return bool
     */
    public function canBeJuked(): bool
    {
        $lastPlayedDate = $this->last_played;

        if ($lastPlayedDate) {
            return $this->last_played->diffInSeconds() > 1800;
        }

        return true;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $gbx = $this->gbx;

        if (!$gbx) {
            Log::write('Loading missing GBX for '.$this->filename);
            $gbx = MapController::getGbxInformation($this->filename);
            $this->gbx = $gbx;
            $this->save();

            $gbx = json_decode($gbx);
        }

        return $gbx->Name;
    }

    /**
     * @param  string  $mapUid
     *
     * @return Map|null
     */
    public static function getByUid(string $mapUid): ?Map
    {
        foreach (Map::all() as $map) {
            if ($map->gbx->MapUid == $mapUid) {
                return $map;
            }
        }

        return null;
    }

    /**
     * @param  string  $mxId
     *
     * @return Map|null
     */
    public static function getByMxId(string $mxId): ?Map
    {
        if (config('database.type') == 'mysql') {
            return Map::where('mx_details->TrackID', $mxId)
                ->get()
                ->first();
        } else {
            return Map::all()->filter(function (Map $map) use ($mxId) {
                $details = $map->mx_details;

                if (!$details) {
                    return false;
                }

                return $details->TrackID == $mxId;
            })->first();
        }
    }
}