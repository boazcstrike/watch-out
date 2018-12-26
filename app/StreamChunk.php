<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StreamChunk extends Model
{
    public function stream()
    {
        return $this->belongsTo('App\Stream');
    }

    public static function getFilename($streamId, $chunkId, $clusterOffset) {
        // TODO Autogenerate from attribute
        return "stream-" . $streamId
            . "-" .  str_pad($chunkId, 8, "0", STR_PAD_LEFT) 
            . ($chunkId == 0 ? "-init" : 
                ( is_null($clusterOffset) ? "-chunk" : "-cluster-" . intval($clusterOffset) )
                )
            . ".webm";
    }
}