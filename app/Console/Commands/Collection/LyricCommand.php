<?php

namespace App\Console\Commands\Collection;

use App\Models\Collection\Lyric;
use App\Models\Collection\Song;
use Illuminate\Console\Command;

class LyricCommand extends Command
{

    const SUCCESS_LYRIC = 'success_lyric';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'collection:lyric';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get lyric of song';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(\GuzzleHttp\Client $client, \Predis\Client $redis)
    {
        parent::__construct();

        $this->client = $client;
        $this->redis  = $redis;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $songs = Song::all();

        foreach ($songs as $song) {
            // 检测歌曲是否已经采集过
            if($this->redis->sismember(self::SUCCESS_LYRIC, $song->songid)) {
                $this->info("歌曲 {$song->songname} 已经完成 跳过");
                continue;
            }

            // 接口地址
            $url = 'https://c.y.qq.com/lyric/fcgi-bin/fcg_query_lyric_new.fcg?callback=MusicJsonCallback_lrc&pcachetime=1514518204316&songmid='.$song->songmid.'&g_tk=5381&jsonpCallback=MusicJsonCallback_lrc&loginUin=0&hostUin=0&format=jsonp&inCharset=utf8&outCharset=utf-8&notice=0&platform=yqq&needNewCode=0';

            $response = $this->client->request('GET', $url, [
                'headers' => [
                    ':authority' => 'c.y.qq.com',
                    ':method' => 'GET',
                    ':path' => '/lyric/fcgi-bin/fcg_query_lyric_new.fcg?callback=MusicJsonCallback_lrc&pcachetime=1514518204316&songmid='.$song->songmid.'&g_tk=5381&jsonpCallback=MusicJsonCallback_lrc&loginUin=0&hostUin=0&format=jsonp&inCharset=utf8&outCharset=utf-8&notice=0&platform=yqq&needNewCode=0',
                    ':scheme' => 'https',
                    'accept' => '*/*',
                    'accept-encoding' => 'gzip, deflate, br',
                    'accept-language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                    'cache-control' => 'no-cache',
                    'pragma' => 'no-cache',
                    'referer' => 'https://y.qq.com/portal/player.html',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.94 Safari/537.36',
                ]
            ]);

            // 获取响应体
            $body = $response->getBody();

            //提取数据
            preg_match('/MusicJsonCallback_lrc\((\{.*\})\)/isU', $body, $tmp);

            //解析数据
            $data = json_decode($tmp[1], true);

            // 检测数据
            if(!$data || empty($data['lyric'])) {
                continue;
            }

            // 获取歌词
            $ly = base64_decode($data['lyric']);

            // 写入数据库
            $lyric = new Lyric;
            $lyric -> song_id = $song->id;
            $lyric -> songname = $song->songname;
            $lyric -> lyric = $ly;

            $lyric -> save();

            // 写入 redis
            $this->redis->sadd(self::SUCCESS_LYRIC, $song->songid);

            $this->info("歌曲 {$song->songname} 完成");
        }


    }
}
