<?php
/**
 * IAASSE Jobs Fetcher – PSU + Private + Non-IT
 */

date_default_timezone_set('Asia/Kolkata');
$OUT = __DIR__ . '/jobs-data.json';

/* =========================
   SOURCES
========================= */

$sources = [

/* ===== REMOTE / PRIVATE ===== */
['url'=>'https://remoteok.com/remote-jobs.rss','country'=>'Worldwide'],
['url'=>'https://weworkremotely.com/categories/remote-programming-jobs.rss','country'=>'Worldwide'],
['url'=>'https://wellfound.com/jobs.rss','country'=>'Worldwide'],

/* ===== INDIA – INTERNSHIPS ===== */
['url'=>'https://internshala.com/rss','country'=>'India'],

/* ===== INDIA – PRIVATE (NON-IT INCLUDED) ===== */
['url'=>'https://www.freshersworld.com/jobs/rss','country'=>'India'],
['url'=>'https://www.timesjobs.com/rss/jobFeed.xml','country'=>'India'],

/* ===== INDIA – GOVT / PSU ===== */
['url'=>'https://www.freejobalert.com/rss.xml','country'=>'India'],
['url'=>'https://www.sarkariresult.com/rss/latestjob.xml','country'=>'India'],
['url'=>'https://www.employmentnews.gov.in/NewEmp/RSS.xml','country'=>'India']
];

/* =========================
   HELPERS
========================= */

function loadRSS($url){
    $ctx = stream_context_create([
        'http'=>['timeout'=>20,'user_agent'=>'IAASSE Jobs Bot']
    ]);
    $raw = @file_get_contents($url,false,$ctx);
    return $raw ? @simplexml_load_string($raw) : null;
}

function detectEmployment($title){
    $t = strtolower($title);

    if(preg_match('/intern|apprentice|trainee/',$t)) return 'internship';
    if(preg_match('/contract|freelance/',$t)) return 'contract';
    if(preg_match('/govt|government|psu|railway|bank|ssc|upsc|drdo|isro|bhel|iocl/',$t))
        return 'govt';

    return 'full-time';
}

function detectSkills($title){
    $map = [
        'php','python','java','react','node','sql','data','cloud','ai',
        'marketing','sales','hr','accounts','finance','mechanical',
        'electrical','civil','technician','analyst'
    ];
    $skills=[];
    foreach($map as $s){
        if(stripos($title,$s)!==false) $skills[]=$s;
    }
    return $skills;
}

function freshnessScore($date){
    $age = time() - $date;
    if($age < 86400) return 'fresh';
    if($age < 259200) return 'new';
    if($age < 604800) return 'recent';
    return 'old';
}

/* =========================
   BUILD JOBS
========================= */

$jobs = [];
$seen = [];

foreach($sources as $src){
    $rss = loadRSS($src['url']);
    if(!$rss || empty($rss->channel->item)) continue;

    foreach($rss->channel->item as $item){
        $title = trim((string)$item->title);
        $link  = trim((string)$item->link);
        $date  = strtotime((string)$item->pubDate) ?: time();

        $hash = md5($title.$link);
        if(isset($seen[$hash])) continue;
        $seen[$hash]=1;

        $jobs[] = [
            'title'      => $title,
            'company'    => parse_url($src['url'],PHP_URL_HOST),
            'country'    => $src['country'],
            'employment' => detectEmployment($title),
            'skills'     => detectSkills($title),
            'freshness'  => freshnessScore($date),
            'link'       => $link,
            'date'       => $date
        ];
    }
}

usort($jobs, fn($a,$b)=>$b['date']<=>$a['date']);

file_put_contents($OUT, json_encode([
    'brand'=>'IAASSE',
    'generated_at'=>date('Y-m-d H:i'),
    'total_jobs'=>count($jobs),
    'jobs'=>array_slice($jobs,0,500)
], JSON_PRETTY_PRINT));
