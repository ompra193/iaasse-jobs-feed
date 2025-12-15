<?php
/**
 * IAASSE Jobs Fetcher
 * India IT + Internships + Govt/PSU + Non-IT
 * GitHub Actions SAFE – FINAL FIX
 */

date_default_timezone_set('Asia/Kolkata');
$OUT = __DIR__ . '/jobs-data.json';

/* =========================
   VERIFIED WORKING RSS SOURCES
========================= */

$sources = [

/* ===== INDIA – IT JOBS ===== */
['url'=>'https://www.naukri.com/rss/jobs/search?qp=software','country'=>'India','company'=>'Naukri'],
['url'=>'https://www.naukri.com/rss/jobs/search?qp=developer','country'=>'India','company'=>'Naukri'],

/* ===== INDIA – INTERNSHIPS ===== */
['url'=>'https://www.naukri.com/rss/jobs/search?qp=internship','country'=>'India','company'=>'Naukri'],
['url'=>'https://www.freshersworld.com/rss/internship-jobs','country'=>'India','company'=>'FreshersWorld'],

/* ===== INDIA – GOVT / PSU ===== */
['url'=>'https://www.freejobalert.com/rss/government-jobs.xml','country'=>'India','company'=>'Govt / PSU'],
['url'=>'https://www.employmentnews.gov.in/NewEmp/RSS.xml','country'=>'India','company'=>'Govt of India'],

/* ===== REMOTE / GLOBAL ===== */
['url'=>'https://remoteok.com/remote-jobs.rss','country'=>'Worldwide','company'=>'RemoteOK'],
];

/* =========================
   HELPERS
========================= */

function loadRSS($url){
    $ctx = stream_context_create([
        'http'=>[
            'timeout'=>30,
            'user_agent'=>'IAASSE Jobs Bot'
        ]
    ]);
    $raw = @file_get_contents($url,false,$ctx);
    if(!$raw) return null;

    libxml_use_internal_errors(true);
    return @simplexml_load_string($raw);
}

/* -------- EMPLOYMENT DETECTION (CRITICAL FIX) -------- */
function detectEmployment($title){
    $t = strtolower($title);

    if (preg_match('/intern|internship|apprentice|trainee/', $t))
        return 'internship';

    if (preg_match('/contract|freelance/', $t))
        return 'contract';

    if (preg_match('/govt|government|psu|railway|bank|ssc|upsc|technician|clerk|assistant|officer|drdo|isro|iocl|bhel|ongc|ntpc|lic/', $t))
        return 'govt';

    return 'full-time';
}

/* -------- SKILLS (IT + NON-IT) -------- */
function detectSkills($title){
    $t = strtolower($title);

    $map = [
        // IT
        'php','python','java','react','node','sql','cloud','ai','data',
        // Non-IT / Govt
        'mechanical','electrical','civil','electronics',
        'technician','operator','draughtsman',
        'clerk','assistant','officer','admin',
        'teacher','professor','lecturer','faculty',
        'nurse','medical','lab',
        'accounts','finance','audit','hr','marketing'
    ];

    $skills=[];
    foreach($map as $s){
        if(strpos($t,$s)!==false) $skills[]=$s;
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

    $items = [];

    if(!empty($rss->channel->item)){
        $items = $rss->channel->item;
    } elseif(!empty($rss->entry)){
        $items = $rss->entry;
    }

    foreach($items as $item){

        $title = trim((string)($item->title ?? ''));
        $link  = trim((string)($item->link['href'] ?? $item->link ?? ''));
        $date  = strtotime((string)($item->pubDate ?? $item->updated ?? '')) ?: time();

        if(!$title || !$link) continue;

        $hash = md5($title.$link);
        if(isset($seen[$hash])) continue;
        $seen[$hash] = true;

        $employment = detectEmployment($title);

        $jobs[] = [
            'title'      => $title,
            'company'    => $src['company'],
            'country'    => $src['country'],   // 🔒 NEVER overwritten
            'employment' => $employment,
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
