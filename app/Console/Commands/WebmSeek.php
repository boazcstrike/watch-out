<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

use App\Support\Facades\Webm;

class WebmSeek extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webm:seek {file} {hextag} {{--split}}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seek Tag in webm file';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $stream = Storage::readStream($this->argument('file'));
        $offset = 0;
        $splitId = 1;
        $this->info('Read Webm');
        while ($pos = Webm::seekNextId($stream, $this->argument('hextag'))) {
            if ($this->option('split')) {
                $tmp = fopen("php://temp", "wb");
                fseek($stream, 0);
                stream_copy_to_stream($stream, $tmp, $pos);
                Storage::put($this->argument('file') . "-" . $splitId, $tmp);
                fclose($tmp);
                $offset = $pos;
                $splitId++;
                // Avoid inf loop
                fseek($stream, $offset + 1);
            }
        }
        if ($offset) {
            $tmp = fopen("php://temp", "wb");
            stream_copy_to_stream($stream, $tmp, $pos - $offset, $offset);
            Storage::put($this->argument('file') . "-" . $splitId, $tmp);
            fclose($tmp);
        }
        fclose($stream);        
    }
}
