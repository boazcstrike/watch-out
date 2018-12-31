<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Stream;
use App\StreamChunk;
use App\Lib\Webm;
use App\Http\Requests\EditStream;

class StreamController extends Controller
{

    public function __construct() {
        $this->authorizeResource(Stream::class);
    }

    /**
     * Display a listing of the last stream.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $streams = Stream::orderBy('updated_at', 'desc')
            ->with('user:id,name')
            ->streamingSince(60)
            ->limit(1) // FIX
            ->paginate();
        return view('streams', ['streams' => $streams]);
    }

    /**
     * Display a user's stream history.
     *
     * @return \Illuminate\Http\Response
     */
    public function history()
    {
        $streams = Auth::user()->streams()
        // Select 1st chunk created_at
        // Select last chunk created_at
            ->with(['firstChunk', 'lastChunk'])
            ->orderBy('id', 'desc')
            ->paginate();

        return view('streams_history', ['streams' => $streams]);
    }

    /**
     * Display a stream in single screen.
     *
     * @param  \App\Stream  $Stream
     * @return \Illuminate\Http\Response
     */
    public function show(Stream $stream)
    {
        return view('stream', ['stream' => $stream]);
    }

    /**
     * Update the specified stream.
     *
     * @param  \App\Http\Requests\EditStream  $request
     * @param  \App\Stream  $Stream
     * @return \Illuminate\Http\Response
     */
    public function update(EditStream $request, Stream $stream)
    {
        $validated = $request->validated();
        $stream->title = $validated['title'];
        $stream->save();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Stream  $stream
     * @return \Illuminate\Http\Response
     */
    public function destroy(Stream $stream)
    {
        // TODO Auto-remove with TTL
    }

    /**
     * Create a new Stream and return a view to allow streaming chunks
     * 
     * @return \Illuminate\Http\Response
     */
    public function record()
    {
        $this->authorize('create', Stream::class);

        $stream = new Stream();
        $stream->title = "Do it Live!";
        // Most supported codec
        // TODO allow user to choose codecs, but warns that some browser may not support it
        $stream->mime_type = 'video/webm;codecs="opus,vp8"';
        $stream->user_id = Auth::id();
        $stream->save();

        return view('record', ['stream' => $stream]);
    }

    /**
     * Retreive chunk pushed by the streaming app
     * Store it or repair it
     *
     * @param  \App\Stream  $Stream
     * @return \Illuminate\Http\Response
     */
    public function push(Request $request, Stream $stream)
    {
        $this->authorize('update', $stream);

        // Same RFC, different behaviors, and dont want to rework chunk on js
        // https://w3c.github.io/media-source/webm-byte-stream-format.html
        // Firefox:
        // - Split stream by cluster with fixed size on trigger
        // - SimpleBlocks timecode counter differs on each tracks (sound and video)
        // Chrome:
        // - Clusters with infinite size that hold ~11sec that are split on trigger
        // - SimpleBlock of all tracks share the same timecode counter
        // - On push first Chunk holds the EBML header and the first Cluster
        // - On pull if you send the same first chunk and use it on appendBuffer it crashes,
        //   the first chunk must always be the EBMLHeader only

        // TODO Stop stream if it reaches size limit
        // TODO Check if stream is in progress

        // Start with EBML header => look for next cluter and split
        // Not start with Cluster => look for next cluster and split by timecode, begin will be appended to last file
        // Create an handle to manage the payload
        $fStream = fopen('php://temp', 'wb');
        $chunkSize = fwrite($fStream, $request->getContent());
        rewind($fStream);
        $webm = new Webm();
        // Get pos of first Cluster
        // Needed for Chrome, allow me flags and seeks the closest one.
        $clusterOffset = $webm->seekNextId($fStream, '1f43b675');
        rewind($fStream);
        $chunkId = $request->header('X-Chunk-Order');
        // TODO Consistency check if possible ?
        // Chunk are ordered by client but http request may not arrive at the same time
        // Check if first bytes is the EMBL Header
        if ($chunkId == 1) {
            // Fist chunk must contains the EBMLHeader
            // TODO Parse the whole if it doesnt affects spec
            $tag = bin2hex(fread($fStream, 5));
            rewind($fStream);
            if ($tag == '1a45dfa3') {
                Log::error('stream[' . $stream->id .'] first chunk has no EBMLHeader tag');
                abort('400', 'Invalid Chunk, first chunk must hold the EBMLHeader');
            }
            $streamChunk = new StreamChunk();
            $streamChunk->stream_id = $stream->id;
            $streamChunk->chunk_id = 0;
            $streamChunk->filename = StreamChunk::getFilename($stream->id, 0, false);
            $fHeader = fopen('php://temp', 'wb');
            $streamChunk->filesize = stream_copy_to_stream($fStream, $fHeader,
                is_null($clusterOffset) ? -1 : intval($clusterOffset),
                0);
            Storage::put($streamChunk->filename, $fHeader);
            fclose($fHeader);
            $streamChunk->save();
            Log::debug('stream[' . $stream->id .'] push header');
            // Still has cluster, set offset to 0 for next code section
            if (!is_null($clusterOffset)) {
               $clusterOffset = 0;
            }
        }
        // If not eof write chunk and flag if cluster
        if (!feof($fStream)) {
            $streamChunk = new StreamChunk();
            $streamChunk->stream_id = $stream->id;
            $streamChunk->chunk_id = $chunkId;
            $streamChunk->filename = StreamChunk::getFilename($stream->id, $chunkId, $clusterOffset);
            $streamChunk->cluster_offset = $clusterOffset;
            $fChunk = fopen('php://temp', 'wb');
            $streamChunk->filesize = stream_copy_to_stream($fStream, $fChunk);
            // Repair Chunk if wrong timecode
            rewind($fChunk);
            if ($webm->needRepairCluster($fChunk, true)) {
                rewind($fChunk);
                $fRepaired = $webm->repairCluster($fChunk);
                Log::debug('stream[' . $stream->id .'] repair chunk ' . $chunkId);
                Storage::put($streamChunk->filename, $fRepaired);
                fclose($fRepaired);
            } else {
                Storage::put($streamChunk->filename, $fChunk);
            }
            fclose($fChunk);
            $streamChunk->save();
            Log::debug('stream[' . $stream->id .'] push chunk ' . $chunkId);
        }
        fclose($fStream);
        // Increments total_size
        $stream->increment('total_size', $chunkSize);
    }

    /**
     * Return asked chunk base on X-Chunk-Order header
     * if asked chunk is -1 return latest cluster
     * 
     * @param  \App\Stream  $Stream
     * @return \Illuminate\Http\Response
     */
    public function pull(Request $request, Stream $stream)
    {
        $this->authorize('view', $stream);

        $filesToStream = [];
        $nextChunkId = -1;
        $seekCluster = false;

        $chunkId = intval($request->header('X-Chunk-Order'));

        $streamChunk = StreamChunk::where('stream_id', $stream->id);
        if ($chunkId == -1) {
            // Seek latest cluster
            $streamChunk = $streamChunk
                ->whereNotNull('cluster_offset')
                ->orderBy('chunk_id', 'desc');
        } else {
            // TODO Manage rupture in stream with timestamp ?
            $streamChunk = $streamChunk
                ->where('chunk_id', '=', $chunkId);
        }
        $streamChunk = $streamChunk->first();
        if ($streamChunk) {
            if ($chunkId == -1) {
                // Set latest Chunk as the retrieved one
                $chunkId = $streamChunk->chunk_id;
                $seekCluster = true;
            }
            // Inc
            if ($chunkId > 0) {
               $nextChunkId = $chunkId + 1;
            }
        }
        
        return response()->stream(function() use ($streamChunk, $seekCluster) {
            // https://www.w3.org/TR/media-source/#init-segment
            if ($streamChunk && Storage::exists($streamChunk->filename)) {
                $stream = Storage::readStream($streamChunk->filename);
                if ($seekCluster && $streamChunk->cluster_offset) {
                    fseek($stream, $streamChunk->cluster_offset);
                    Log::debug('stream [' . $streamChunk->stream_id 
                        . '] chunk [' . $streamChunk->chunk_id 
                        . '] seek cluster at ' . $streamChunk->cluster_offset);
                }
                fpassthru($stream);
                fclose($stream);
                Log::debug('stream [' . $streamChunk->stream_id 
                    . '] chunk [' . $streamChunk->chunk_id 
                    . '] file sent: ' . $streamChunk->filename);
            } else {
                // No Content
                abort(204);
            }
        }, 200, [
            'Cache-Control'         => 'no-cache, no-store',
            'Pragma'                => 'public',
            'Content-Type'          => $stream->mime_type,
            'X-Chunk-Order'         => $chunkId,
            'X-Next-Chunk-Order'    => $nextChunkId,
            'Retry-After'           => 2
        ]);
    }

    /**
     * Send all the Chunks !
     * 
     * @param  \App\Stream  $Stream
     * @return \Illuminate\Http\Response
     */
    public function full(Stream $stream)
    {
        $this->authorize('update', $stream);

        // Get all chunk filename
        $filesToStream = $stream->chunks()->orderBy('chunk_id')->pluck('filename');
        return response()->stream(function() use ($stream, $filesToStream) {
            foreach ($filesToStream as $file) {
                if (Storage::exists($file)) {
                    $fStream = Storage::readStream($file);
                    fpassthru($fStream);
                    fclose($fStream);
                    Log::debug('stream [' . $stream->id 
                        . '] file sent: ' . $file);
                }
            }
        }, 200, [
            'Cache-Control'         => 'no-cache, no-store',
            'Pragma'                => 'public',
            'Content-Type'          => $stream->mime_type,
        ]);
    }

}

