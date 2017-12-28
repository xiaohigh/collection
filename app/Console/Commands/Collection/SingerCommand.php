<?php

namespace App\Console\Commands\Collection;

use App\Models\Collection\Singer;
use Illuminate\Console\Command;
use Predis\Client;

class SingerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'collection:singer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'collect singers';
    protected $client = null;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->client = new \GuzzleHttp\Client();

        $this->redis = new \Predis\Client;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //字母
        foreach(range('B', 'Z') as $letter) {
            if($this->redis->sismember('success_singers_letter', $letter)) {
                $this->info("字母 $letter 已经采集过");
            }

            //获取总页数
            $pages = $this->getTotalPage($letter);

            //遍历
            for ($i=1; $i <= $pages; $i++) { 
                //接口地址
                $url = 'http://c.y.qq.com/v8/fcg-bin/v8.fcg?channel=singer&page=list&key=all_all_'.$letter.'&pagesize=100&pagenum='.$i.'&g_tk=5381&jsonpCallback=GetSingerListCallback&loginUin=0&hostUin=0&format=jsonp&inCharset=utf8&outCharset=utf-8&notice=0&platform=yqq&needNewCode=0';
                //创建hash值
                $hash = md5($url);

                //检测当前页是否已经采集过
                if($this->redis->sismember('success_singers_page', $hash)) {
                    $this->info("字母 $letter 第 $i 页 已经采集过");
                    continue;
                }

                //获取数据
                $res = $this->client->request('GET', $url);

                //获取数据
                $body = $res->getBody();

                //正则匹配
                preg_match('/GetSingerListCallback\((\{".*\})\s\)/is', $body, $tmp);
                if(empty($tmp)){
                    $this->error("$letter 字母第 $i 页数据获取失败");
                    continue;
                }

                //提取数据
                $data = json_decode($tmp[1], true);

                //遍历
                foreach ($data['data']['list'] as $key => $value) {
                    //检测该人是否已经采集过
                    if($this->redis->sismember('success_singers', $value['Fsinger_id'])) {
                        $this->info($value['Fsinger_name'].'已经获取过');
                        continue;
                    }

                    $singer = new Singer;
                    //写入数据
                    $singer -> name = $value['Fsinger_name'];
                    //如果名字过长  忽略
                    if(strlen($singer->name) > 100) continue;
                    $singer -> fother_name = $value['Fother_name'];
                    $singer -> qq_id = $value['Fsinger_id'];
                    $singer -> qq_mid = $value['Fsinger_mid'];
                    $singer -> letter = $letter;
                    $singer -> area = $value['Farea'];

                    $singer->save();
                    sleep(1);

                    //记录写入状态 success_singer qq_id
                    $this->redis->sadd('success_singers', $value['Fsinger_id']);
                    
                    $this->info("{$singer->name} 已经获取成功");
                }

                //标记该页已经成功
                $this->redis->sadd('success_singers_page',  $hash);
                $this->info("字母 $letter 第 $i 页 完成");
            }

            $this->redis->sadd('success_singers_letter','A');
            $this->info("字母 $letter 完成");
        }

    }

    private function send($url)
    {

    }

    private function getTotalPage($letter)
    {
        //获取数据
        $url = 'http://c.y.qq.com/v8/fcg-bin/v8.fcg?channel=singer&page=list&key=all_all_'.$letter.'&pagesize=100&pagenum=1&g_tk=5381&jsonpCallback=GetSingerListCallback&loginUin=0&hostUin=0&format=jsonp&inCharset=utf8&outCharset=utf-8&notice=0&platform=yqq&needNewCode=0';

        //请求
        $res = $this->client->request('GET', $url);

        //获取数据
        $body = $res->getBody();

        //获取json
        preg_match('/GetSingerListCallback\((\{".*\})\s\)/is', $body, $tmp);

        if(empty($tmp)){
            $this->error("$letter 获取页码失败！！");
            return 0;
        }

        $data = json_decode($tmp[1], true);

        return ($data['data']['total_page']);
    }
}
