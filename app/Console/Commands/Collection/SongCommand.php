<?php

namespace App\Console\Commands\Collection;

use App\Models\Collection\Album;
use App\Models\Collection\Song;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Predis\Client as Redis;

class SongCommand extends Command
{

    const SUCCESS_SONG = 'success_songs';

    const SUCCESS_ALBUM_SONG = 'success_album_songs';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'collection:song';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get songs';

    /**
     * HTTP client
     */
    protected $http = null;

    /**
     * Redis client
     */
    protected $redis = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Client $http, Redis $redis)
    {
        parent::__construct();
        $this->http = $http;
        $this->redis = $redis;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $albums = Album::all(); 

        foreach ($albums as $album) {
            //检测
            if($this->redis->sismember(self::SUCCESS_ALBUM_SONG, $album->albumID)) {
                $this->info("专辑 {$album->albumName} 已经完成 跳过");
                continue;
            }

            //请求接口
            $response = $this->http->request('GET', 'https://c.y.qq.com/v8/fcg-bin/fcg_v8_album_info_cp.fcg?albummid='.$album->albumMID.'&g_tk=5381&jsonpCallback=albuminfoCallback&loginUin=0&hostUin=0&format=jsonp&inCharset=utf8&outCharset=utf-8&notice=0&platform=yqq&needNewCode=0');

            // 获取响应体
            $body = $response -> getBody();

            // 提取内容
            preg_match('/albuminfoCallback\((\{.*\})\)/isU', $body, $tmp);

            // 判断结果
            if(empty($tmp[1])) {
                Log::error("专辑 {$album->id} -- {$album->albumName} 获取失败");
                continue;
            }

            // 解析数据
            $data = json_decode($tmp[1], true);

            if(empty($data['data']['list'])) {
                Log::error("专辑 {$album->id} -- {$album->albumName} 歌曲列表为空， 获取失败");
                return;
            }

            // 遍历写入数据库
            foreach ($data['data']['list'] as $key => $value) {
                // 检测该歌曲是否已经完成
                if($this->redis->sismember(self::SUCCESS_SONG, $value['songid'])) {
                    $this->info("歌曲 {$value['songname']} 已经完成  跳过");
                    continue;
                }

                $song = new Song;            

                $song -> albumid = $value['albumid'];
                $song -> songid = $value['songid'];
                $song -> songmid = $value['songmid'];
                $song -> songname = $value['songname'];
                $song -> songorig = $value['songorig'];
                $song -> strMediaMid = $value['strMediaMid'];

                $album-> songs() -> save($song);
                
                // 写入 redis 中
                $this -> redis -> sadd(self::SUCCESS_SONG, $value['songid']);

                // 提示
                $this -> info("歌曲 {$value['songname']} 获取成功");
            }

            // 写入专辑歌曲成功标记
            $this->redis->sadd(self::SUCCESS_ALBUM_SONG, $album->albumID);

            //提示
            $this->info("专辑 {$album->albumName} 歌曲完成");
        }
    }
}
