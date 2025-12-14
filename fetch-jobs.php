<?php
date_default_timezone_set('Asia/Kolkata');

$sources = [

    /* GLOBAL / REMOTE */
    ['url'=>'https://remoteok.com/remote-jobs.rss','country'=>'Worldwide','employment'=>'full-time'],
    ['url'=>'https://weworkremotely.com/categories/remote-programming-jobs.rss','country'=>'Worldwide','employment'=>'full-time'],
    ['url'=>'https://wellfound.com/jobs.rss','country'=>'Worldwide','employment'=>'full-time'],

    /* INDIA – PRIVATE */
    ['url'=>'https://www.indeed.com/rss?q=jobs&l=India','country'=>'India','employment'=>'full-time'],
    ['url'=>'https://www.indeed.com/rss?q=internship&l=India','country'=>'India','employment'=>'internship'],

    /* INDIA – GOVT / PSU */
    ['url'=>'https://www.indeed.com/rss?q=government+job&l=India','country'=>'India','employment'=>'govt'],
    ['url'=>'https://www.indeed.com/rss?q=psu+job&l=India','country'=>'India','employment'=>'govt'],
];

function detectSkills($title){
    $map=['php','python','java','ai','ml','react','node','sql','data','cloud'];
    $out=[];
    foreach($map as $s){
        if(stripos($title,$s)!==false) $out[]=$s;
    }
    return $out;
}

$jobs=[];
$seen=[];

foreach($sources as $s){
    $rss=@simplexml_load_file($s['url']);
    if(!$rss) continue;

    foreach($rss->channel->item as $i){
        $title=(string)$i->title;
        $link=(string)$i->link;
        $hash=md5($title.$link);
        if(isset($seen[$hash])) continue;
        $seen[$hash]=1;

        $jobs[]=[
            'title'=>$title,
            'company'=>parse_url($s['url'],PHP_URL_HOST),
            'country'=>$s['country'],
            'employment'=>$s['employment'],
            'skills'=>detectSkills($title),
            'link'=>$link,
            'date'=>strtotime((string)$i->pubDate)
        ];
    }
}

usort($jobs,fn($a,$b)=>$b['date']<=>$a['date']);

file_put_contents('jobs-data.json',json_encode([
    'brand'=>'IAASSE',
    'generated_at'=>date('Y-m-d H:i'),
    'jobs'=>$jobs
],JSON_PRETTY_PRINT));
