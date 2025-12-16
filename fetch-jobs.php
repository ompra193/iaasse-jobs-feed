<?php
/**
 * IAASSE Jobs Fetcher
 * India IT + Internships + Govt/PSU + Non-IT
 * Stable | GitHub Actions SAFE
 */

date_default_timezone_set('Asia/Kolkata');
$OUT = __DIR__ . '/jobs-data.json';

/* =========================
   RSS SOURCES (WORKING)
========================= */

$sources = [

/* ===== INDIA – IT COMPANIES ===== */
['url'=>'https://www.indeed.com/rss?q=company:Infosys&l=India','country'=>'India','company'=>'Infosys'],
['url'=>'https://www.indeed.com/rss?q=company:TCS&l=India','country'=>'India','company'=>'TCS'],
['url'=>'https://www.indeed.com/rss?q=company:Wipro&l=India','country'=>'India','company'=>'Wipro'],
['url'=>'https://www.indeed.com/rss?q=company:HCL&l=India','country'=>'India','company'=>'HCL'],
['url'=>'https://www.indeed.com/rss?q=company:Tech+Mahindra&l=India','country'=>'India','company'=>'Tech Mahindra'],

/* ===== INDIA – INTERNSHIPS ===== */
['url'=>'https://www.naukri.com/rss/jobs/search?qp=internship+software','country'=>'India','company'=>'Naukri'],
['url'=>'https://www.indeed.com/rss?q=internship+software&l=India','country'=>'India','company'=>'India Internships'],
['url'=>'https://www.freshersworld.com/rss/internship-jobs','country'=>'India','company'=>'Freshersworld'],

/* ===== INDIA – IT JOBS ===== */
['url'=>'https://www.naukri.com/rss/jobs/search?qp=software+developer+india','country'=>'India','company'=>'Naukri'],
['url'=>'https://www.freshersworld.com/jobs/rss','country'=>'India','company'=>'Freshersworld'],

/* ===== INDIA – GOVT / PSU ===== */
['url'=>'https://www.freejobalert.com/rss/government-jobs.xml','country'=>'India','company'=>'Govt / PSU'],
['url'=>'https://www.employmentnews.gov.in/NewEmp/RSS.xml','country'=>'India','company'=>'Govt of India'],

/* ===== REMOTE ===== */
['url'=>'https://remoteok.com/remote-jobs.rss','country'=>'Worldwide','company'=>'RemoteOK'],
['url'=>'https://weworkremotely.com/categories/remote-programming-jobs.rss','country'=>'Worldwide','company'=>'WeWorkRemotely'],
];

/* =========================
   HELPERS
========================= */

function loadRSS($url){
    $ctx = stream_context_create([
        'http'=>['timeout'=>30,'user_agent'=>'IAASSE Jobs Bot']
    ]);
    $raw = @file_get_contents($url,false,$ctx);
    if(!$raw) return null;

    libxml_use_internal_errors(true);
    return @simplexml_load_string($raw);
}

function detectEmployment($title){
    $t = strtolower($title);

    if(preg_match('/intern|internship|apprentice|trainee/',$t)) return 'internship';
    if(preg_match('/contract|freelance/',$t)) return 'contract';
    if(preg_match('/govt|government|psu|railway|bank|ssc|upsc|drdo|isro|bhel|iocl|ongc|ntpc|lic/',$t))
        return 'govt';

    return 'full-time';
}

function detectSkills($title){
    $map = [
        'php','python','java','react','node','sql','cloud','ai','data',
        'mechanical','electrical','civil','technician','clerk','assistant',
        'teacher','professor','nurse','medical','accounts','finance','hr'
    ];
    $skills=[];
    foreach($map as $s){
        if(stripos($title,$s)!==false) $skills[]=$s;
    }
    return array_values(array_unique($skills));
}

/* ---------- SALARY PARSER ---------- */
function parseSalary($title){
    if(preg_match('/₹\s?([\d\.]+)\s?-\s?([\d\.]+)\s?lpa/i',$title,$m))
        return "₹{$m[1]}–{$m[2]} LPA";

    if(preg_match('/₹\s?([\d\.]+)\s?lpa/i',$title,$m))
        return "₹{$m[1]} LPA";

    if(preg_match('/([\d\.]+)\s?lpa/i',$title,$m))
        return "{$m[1]} LPA";

    if(preg_match('/₹\s?([\d,]+)\s?(per month|pm)/i',$title,$m))
        return "₹{$m[1]} / month";

    return null;
}

/* ---------- COMPANY LOGO ---------- */
function companyLogo($company){
    $map = [
        'Infosys'=>'https://upload.wikimedia.org/wikipedia/commons/9/95/Infosys_logo.svg',
        'TCS'=>'https://upload.wikimedia.org/wikipedia/commons/5/5e/Tata_Consultancy_Services_Logo.svg',
        'Wipro'=>'https://upload.wikimedia.org/wikipedia/commons/a/a0/Wipro_Primary_Logo_Color_RGB.svg',
        'HCL'=>'https://upload.wikimedia.org/wikipedia/commons/1/1c/HCL_Technologies_logo.svg',
        'Tech Mahindra'=>'https://upload.wikimedia.org/wikipedia/commons/6/6a/Tech_Mahindra_New_Logo.svg'
    ];
    return $map[$company] ?? null;
}

/* =========================
   BUILD JOBS
========================= */

$jobs = [];
$seen = [];

foreach($sources as $src){

    $rss = loadRSS($src['url']);
    if(!$rss) continue;

    $items = $rss->channel->item ?? $rss->entry ?? [];

    foreach($items as $item){

        $title = trim((string)($item->title ?? ''));
        $link  = trim((string)($item->link['href'] ?? $item->link ?? ''));
        $date  = strtotime((string)($item->pubDate ?? $item->updated ?? '')) ?: time();

        if(!$title || !$link) continue;

        /* 🔒 STRONG DEDUP */
        $hash = md5(strtolower($title.$src['company'].$src['country']));
        if(isset($seen[$hash])) continue;
        $seen[$hash]=1;

        $employment = detectEmployment($title);

        $jobs[] = [
            'title'      => $title,
            'company'    => $src['company'],
            'company_logo'=> companyLogo($src['company']),
            'country'    => $src['country'],
            'employment' => $employment,
            'skills'     => detectSkills($title),
            'salary'     => parseSalary($title),
            'link'       => $link,
            'date'       => $date
        ];
    }
}

/* =========================
   SORT & WRITE
========================= */

usort($jobs, fn($a,$b)=>$b['date']<=>$a['date']);

file_put_contents($OUT, json_encode([
    'brand'=>'IAASSE',
    'generated_at'=>date('Y-m-d H:i:s'),
    'total_jobs'=>count($jobs),
    'jobs'=>array_slice($jobs,0,500)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
