<?php
/**
 * IAASSE Jobs Fetcher – India + PSU + Private + Non-IT
 * FINAL STABLE VERSION
 */

date_default_timezone_set('Asia/Kolkata');
$OUT = __DIR__ . '/jobs-data.json';

/* =========================
   SOURCES
========================= */

$sources = [

/* ===== REMOTE / PRIVATE ===== */
['url'=>'https://remoteok.com/remote-jobs.rss','country'=>'Worldwide','company'=>'Various'],
['url'=>'https://weworkremotely.com/categories/remote-programming-jobs.rss','country'=>'Worldwide','company'=>'Various'],

/* ===== INDIA – INTERNSHIPS ===== */
['url'=>'https://internshala.com/rss','country'=>'India','company'=>'Internshala'],

/* ===== INDIA – PRIVATE (IT + NON-IT) ===== */
['url'=>'https://www.freshersworld.com/jobs/rss','country'=>'India','company'=>'Freshersworld'],
['url'=>'https://www.timesjobs.com/rss/jobFeed.xml','country'=>'India','company'=>'TimesJobs'],

/* ===== INDIA – GOVT / PSU ===== */
['url'=>'https://www.freejobalert.com/rss.xml','country'=>'India','company'=>'Govt / PSU'],
['url'=>'https://www.sarkariresult.com/rss/latestjob.xml','country'=>'India','company'=>'Govt / PSU'],
['url'=>'https://www.employmentnews.gov.in/NewEmp/RSS.xml','country'=>'India','company'=>'Govt of India'],
];

/* =========================
   HELPERS
========================= */

function loadRSS($url){
    $ctx = stream_context_create([
        'http'=>[
            'timeout'=>25,
            'user_agent'=>'IAASSE Jobs Bot'
        ]
    ]);
    $raw = @file_get_contents($url,false,$ctx);
    if(!$raw) return null;

    libxml_use_internal_errors(true);
    return @simplexml_load_string($raw);
}

function detectEmployment($title){
    $t = strtolower($title);

    if(preg_match('/intern|apprentice|trainee/',$t)) return 'internship';
    if(preg_match('/contract|freelance/',$t)) return 'contract';

    if(preg_match(
        '/govt|government|psu|railway|bank|ssc|upsc|drdo|isro|bhel|iocl|ongc|ntpc|lic/',
        $t
    )) return 'govt';

    return 'full-time';
}

/* ===== NON-IT + IT SKILL DETECTION ===== */
function detectSkills($title){
    $t = strtolower($title);

    $map = [
        // IT
        'php','python','java','react','node','sql','cloud','ai','data',
        // Non-IT Govt / PSU
        'mechanical','electrical','civil','electronics',
        'technician','operator','draughtsman',
        'clerk','assistant','officer','admin',
        'teacher','professor','lecturer','faculty',
        'nurse','pharmacist','lab','medical',
        'accounts','finance','audit','hr','marketing'
    ];

    $skills=[];
    foreach($map as $s){
        if(stripos($t,$s)!==false) $skills[]=$s;
    }
    return array_values(array_unique($skills));
}

function freshnessLabel($date){
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
    if(!$rss) continue;

    /* Support both <channel><item> and Atom feeds */
    $items = [];

    if(!empty($rss->channel->item)) {
        $items = $rss->channel->item;
    } elseif(!empty($rss->entry)) {
        $items = $rss->entry;
    }

    foreach($items as $item){

        $title = trim((string)($item->title ?? ''));
        $link  = trim((string)($item->link['href'] ?? $item->link ?? ''));
        $date  = strtotime((string)($item->pubDate ?? $item->updated ?? '')) ?: time();

        if(!$title || !$link) continue;

        $hash = md5($title.$link);
        if(isset($seen[$hash])) continue;
        $seen[$hash]=1;

        $jobs[] = [
            'title'      => $title,
            'company'    => $src['company'],
            'country'    => $src['country'],
            'employment' => detectEmployment($title),
            'skills'     => detectSkills($title),
            'freshness'  => freshnessLabel($date),
            'link'       => $link,
            'date'       => $date
        ];
    }
}

/* =========================
   SORT & WRITE
========================= */

usort($jobs, fn($a,$b)=>$b['date']<=>$a['date']);

file_put_contents(
    $OUT,
    json_encode([
        'brand'        => 'IAASSE',
        'generated_at' => date('Y-m-d H:i:s'),
        'total_jobs'   => count($jobs),
        'jobs'         => array_slice($jobs,0,500)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);
