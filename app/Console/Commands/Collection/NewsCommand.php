<?php

namespace App\Console\Commands\Collection;

use App\Models\Collection\News;
use Goutte\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;


class NewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'collection:news';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $client = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Client $client)
    {
        parent::__construct();
        //初始化客户端
        $this->client = $client;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $client = $this->client;
        $redis = new \Predis\Client();

        //获取目标页面的总页数
        if(!$this->getTotalPage()) {
            $this->error('获取总页数失败！！');die;
        }

        for ($i=1; $i <= $this->totalPage; $i++) { 
            //列表页 url
            $url = 'http://www.kejixun.com/news/'.$i.'.html';
            //请求
            $crawler = $this->client->request('GET', $url);

            //如果节点数为0 则进入下一页
            if($crawler->filter('.list-box .list-items .title a')->count() <= 0){
                $this->error($url);
                continue;
            }
            //获取当前列表页的 URL
            $crawler->filter('.list-box .list-items .title a')->each(function($v)use($client,$redis){
                //获取url
                $url = $v->attr('href');
                $hash = md5($url);
                //检测是否已经采集过
                if($redis->sismember('news_success', $hash)){
                    $this->info("$url 已经采集过，无需再次采集");
                    return;
                }
                //请求
                $crawler = $client -> request('GET', $v->attr('href'));

                //针对标题的判断
                if($crawler->filter('h1')->count() > 0){
                    $title = $crawler->filter('h1')->text();
                }else{
                    return;
                }

                //针对发布时间
                if($crawler->filter('.data')->count() > 0){
                    $addtime = $crawler->filter('.data')->text();
                }else{
                    $addtime = '';
                }

                //针对内容
                if($crawler->filter('.detail_content')->count() > 0){
                    $content = $crawler->filter('.detail_content')->html();
                }else{
                    $content = '';
                }

                //针对图片
                $image = $crawler->filter('.detail_content img');
                if($image->count() > 0) {
                    $img = $image->attr('src');
                }else{
                    $img = '';
                }

                //拼接数据
                $news = new News;
                $news->title = $title;
                $news->addtime = $addtime;
                $news->content = trim($content);
                $news->img = $img;

                //保存
                $news->save();
                Log::info(\memory_get_usage());


                //将hash值存入到redis集合中  news_success
                $redis->sadd('news_success', $hash);

                //控制台显示
                $this->info("$url 已经完成采集");

                //删除对象
                unset($news);
            });
        }
    }

    /**
     * 获取总页数
     */
    protected function getTotalPage()
    {
        //列表页地址
        $url = 'http://www.kejixun.com/news/';
        //发送请求
        $crawler = $this->client->request('GET', $url);
        //获取结果
        $pages = convert($crawler->filter('.container .paging')->html());

        preg_match('/\.\.<a.*>(\d+)<\/a>/isU', $pages, $res);
        //如果页码没有获取到则停止
        if(empty($res[1])) {
            return false;
        }else{
            $this->totalPage = $res[1];
            return true;
        }
    }
}
