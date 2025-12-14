<?php
/**
 * IAASSE Jobs Fetcher (GitHub Actions Compatible)
 * Output: jobs-data.json
 * Purpose: Information & redirection only
 */

date_default_timezone_set('Asia/Kolkata');

/* =========================
   JOB SOURCES (WORKING RSS)
========================= */

$sources = [

    /* ===== GLOBAL / REMOTE ===== */
    [
        'url' => 'https://remoteok.com/remote-jobs.rss',
        'country' => 'Worldwide',
        'employment' => 'full-time'
    ],
    [
        'url' => 'https://weworkremotely.com/categories/remote-programming-jobs.rss',
        'country' => 'Worldwide',
        'employment' => 'full-time'
    ],
    [
        'url' => 'https://wellfound.com/jobs.rss',
        'country' => 'Worldwide',
        'employment' => 'full-time'
    ],

    /* ===== INDIA – PRIVATE ===== */
    [
        'url' => 'https://www.freshersworld.com/jobs/rss',
        'country' => 'India',
        'employment' => 'full-time'
    ],
    [
        'url' => 'https://internshala.com/rss',
        'country' => 'India',
        'employment' => 'internship'
    ],

    /* ===== INDIA – GOVT / PSU ===== */
    [
        'url' => 'https://www.freejobalert.com/rss.xml',
        'country' => 'India',
        'employment' => 'govt'
    ],
    [
        'url' => 'https://www.sarkariresult.com/rss/latestjob.xml',
        'country' => 'India',
        'employment' => 'govt'
    ],
    [
        'url' => 'https://www.employmentnews.gov.in/NewEmp/RSS.xml',
        'country' => 'India',
        'employment' => 'govt'
    ],
];

/* =========================
   HELPERS
========================= */

function fetchRSS($url){
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'IAASSE Jobs Bot'
    ]);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data ? @simplexml_load_string($data) : null;
}

function detectSkills($title){
    $map = [
        'php','python','java','ai','ml','react',
        'node','sql','data','cloud','devops'
    ];
    $skills = [];
    foreach($map as $s){
        if(stripos($title, $s) !== false){
            $skills[] = $s;
        }
    }
    return array_values(array_unique($skills));
}

/* =========================
   BUILD JOB LIST
========================= */

$jobs = [];
$seen = [];

foreach($sources as $src){
    $rss = fetchRSS($src['url']);
    if(!$rss || empty($rss->channel->item)) continue;

    foreach($rss->channel->item as $item){
        $title = trim(html_entity_decode((string)$item->title));
        $link  = trim((string)$item->link);

        if(!$title || !$link) continue;

        /* Deduplicate */
        $hash = md5($title.$link);
        if(isset($seen[$hash])) continue;
        $seen[$hash] = true;

        $jobs[] = [
            'title'      => $title,
            'company'    => parse_url($src['url'], PHP_URL_HOST),
            'country'    => $src['country'],        // FORCED BY SOURCE
            'employment' => $src['employment'],     // govt / full-time / internship
            'skills'     => detectSkills($title),
            'link'       => $link,
            'date'       => strtotime((string)$item->pubDate ?: 'now')
        ];
    }
}

/* Sort latest first */
usort($jobs, fn($a,$b) => $b['date'] <=> $a['date']);

/* =========================
   SAVE JSON
========================= */

file_put_contents('jobs-data.json', json_encode([
    'brand'        => 'IAASSE',
    'generated_at' => date('Y-m-d H:i'),
    'total_jobs'   => count($jobs),
    'jobs'         => array_slice($jobs, 0, 500)
], JSON_PRETTY_PRINT));
