<?php

namespace App\Console\Commands\Collection;

use App\Models\Collection\Album;
use App\Models\Collection\Singer;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AlbumCommand extends Command
{

    const SUCCESS_SINGER_ALBUM = 'success_singer_album';
    const FAIL_SINGER_ALBUM = 'fail_singer_album';

    const SUCCESS_ALBUM = 'success_album';
    const FAIL_ALBUM = 'success_album';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'collection:album';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'collection album from qq music';

    /**
     * http client
     */
    private $client = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->client = new Client;

        $this->redis = new \Predis\Client;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $singers = Singer::where('id','<',10)->get();

        //检测歌手信息
        if($singers->count() <= 0){
            $this->error('没有歌手信息，请先运行 collection:singers');
            return;
        }

        foreach($singers as $singer) {
            //检测该歌手是否已经采集完成
            if($this->redis->sismember(SELF::SUCCESS_SINGER_ALBUM, $singer->qq_id)) {
                $this->info("专辑采集--歌手 {$singer->qq_id} 已经完成 跳过");
                continue;
            }

            //获取总的页数
            $total = $this->getTotal($singer);

            if(!$total) {
                Log::error("{$singer->name} 专辑数量获取失败");
            }

            //发送请求
            $data = $this->send('http://c.y.qq.com/v8/fcg-bin/fcg_v8_singer_album.fcg?g_tk=5381&jsonpCallback=MusicJsonCallbacksinger_album&loginUin=0&hostUin=0&format=jsonp&inCharset=utf8&outCharset=utf-8&notice=0&platform=yqq&needNewCode=0&singermid='.$singer->qq_mid.'&order=time&begin=0&num='.$total.'&exstatus=1', []);

            //如果数据为空 记录
            if(empty($data['data']['list'])) {
                Log::error("{$singer->name} 专辑数量获取失败");
            }

            //遍历数组
            foreach ($data['data']['list'] as $key => $value) {
                //检测专辑状态
                if($this->redis->sismember(SELF::SUCCESS_ALBUM, $value['albumID'])) {
                    $this->info("专辑 {$value['albumID']} 已经采集完成 跳过");
                    continue;
                }

                $albums = $singer->albums();

                $album = new Album;
                $album -> albumID = $value['albumID'];
                $album -> albumMID = $value['albumMID'];
                $album -> albumtype = isset($value['albumtype']) ? $value['albumtype'] : '';
                $album -> albumName = isset($value['albumName']) ? $value['albumName'] : '';
                $album -> company = isset($value['company']) ? $value['company'] : '';
                $album -> lan = isset($value['lan']) ? $value['lan'] : '';
                $album -> desc = isset($value['desc']) ? $value['desc'] : '';
                $album -> pubTime = isset($value['pubTime']) ? $value['pubTime'] : '';
                $album -> listen_count = isset($value['listen_count']) ? $value['listen_count'] : '';

                $albums -> save($album);
                $this->info("专辑 {$album -> albumName} 专辑获取完成");

                //记录专辑状态
                $this->redis->sadd(SELF::SUCCESS_ALBUM, $value['albumID']);
            }

            //记录 redis 状态 success_
            $this->redis->sadd(SELF::SUCCESS_SINGER_ALBUM, $singer->qq_id);

            //日志记录该歌手采集完成
            Log::info("歌手 {$singer->name} -- {$singer->qq_id} 专辑获取完成");
            $this->info("歌手 {$singer->name} -- {$singer->qq_id} 专辑获取完成");
        }
    }

    /**
     * 获取专辑的总数
     */
    private function getTotal(Singer $singer)
    {
        //目标url
        $url = 'http://c.y.qq.com/v8/fcg-bin/fcg_v8_singer_album.fcg?g_tk=5381&jsonpCallback=MusicJsonCallbacksinger_album&loginUin=0&hostUin=0&format=jsonp&inCharset=utf8&outCharset=utf-8&notice=0&platform=yqq&needNewCode=0&singermid='.$singer->qq_mid.'&order=time&begin=0&num=1&exstatus=1';

        $data = $this->send($url);

        return !$data ?: $data['data']['total'];
    }

    /**
     * send http request and return json
     *
     * @param $url 
     * @return Array  json
     */
    private function send($url)
    {
        //发送请求
        $response = $this->client->request('GET', $url);

        //解析数据
        $data = preg_match('/MusicJsonCallbacksinger_album\((\{.*\})\s\)/isU', $response->getBody(), $tmp);

        //检测
        if(empty($tmp[0])) {
            return false;
        }

        return json_decode($tmp[1], true);
    }


}
