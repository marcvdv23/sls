<?php

use App\Models\Country;
use App\Models\IntelligenceSource;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sources = [
    ['DZ', 'Caisse Nationale des Assurances Sociales', 'https://www.cnas.dz/'],
    ['DZ', 'Caisse Nationale des Retraites', 'https://cnr.dz/'],
    ['AO', 'Instituto Nacional de Seguranca Social', 'https://www.inss.gov.ao/'],
    ['BJ', 'Caisse Nationale de Securite Sociale du Benin', 'https://www.cnss.bj/'],
    ['BW', 'Botswana Public Officers Pension Fund', 'https://www.bpopf.co.bw/'],
    ['BF', 'Caisse Nationale de Securite Sociale du Burkina Faso', 'https://www.cnss.bf/'],
    ['BI', 'Institut National de Securite Sociale du Burundi', 'https://www.inss.gov.bi/'],
    ['CV', 'Instituto Nacional de Previdencia Social Cabo Verde', 'https://www.inps.cv/'],
    ['CM', 'Caisse Nationale de Prevoyance Sociale du Cameroun', 'https://www.cnps.cm/'],
    ['CI', 'Caisse Nationale de Prevoyance Sociale Cote d Ivoire', 'https://www.cnps.ci/'],
    ['CI', 'Institution de Prevoyance Sociale - CGRAE', 'https://www.cgrae.ci/'],
    ['CD', 'Caisse Nationale de Securite Sociale RDC', 'https://www.cnss.cd/'],
    ['DJ', 'Caisse Nationale de Securite Sociale Djibouti', 'https://www.cnss.dj/'],
    ['EG', 'National Organization for Social Insurance', 'https://www.nosi.gov.eg/'],
    ['SZ', 'Eswatini National Provident Fund', 'https://www.enpf.co.sz/'],
    ['ET', 'Public Servants Social Security Service', 'https://www.psssa.gov.et/'],
    ['GA', 'Caisse Nationale de Securite Sociale du Gabon', 'https://www.cnss.ga/'],
    ['GM', 'Social Security and Housing Finance Corporation', 'https://www.sshfc.gm/'],
    ['GH', 'Social Security and National Insurance Trust', 'https://www.ssnit.org.gh/'],
    ['GH', 'National Pensions Regulatory Authority', 'https://www.npra.gov.gh/'],
    ['GN', 'Caisse Nationale de Securite Sociale de Guinee', 'https://www.cnssguinee.org/'],
    ['KE', 'National Social Security Fund Kenya', 'https://www.nssf.or.ke/'],
    ['KE', 'Retirement Benefits Authority Kenya', 'https://www.rba.go.ke/'],
    ['LR', 'National Social Security and Welfare Corporation', 'https://www.nasscorp.org.lr/'],
    ['MG', 'Caisse Nationale de Prevoyance Sociale Madagascar', 'https://www.cnaps.mg/'],
    ['MW', 'Public Service Pension Trust Fund Malawi', 'https://www.psptf.mw/'],
    ['ML', 'Institut National de Prevoyance Sociale Mali', 'https://www.inps.ml/'],
    ['MR', 'Caisse Nationale de Securite Sociale Mauritanie', 'https://www.cnss.mr/'],
    ['MU', 'Mauritius Revenue Authority - Social Contributions', 'https://www.mra.mu/'],
    ['MA', 'Caisse Nationale de Securite Sociale Maroc', 'https://www.cnss.ma/'],
    ['MA', 'Caisse Marocaine des Retraites', 'https://www.cmr.gov.ma/'],
    ['MZ', 'Instituto Nacional de Seguranca Social Mocambique', 'https://www.inss.gov.mz/'],
    ['NA', 'Social Security Commission Namibia', 'https://www.ssc.org.na/'],
    ['NE', 'Caisse Nationale de Securite Sociale Niger', 'https://www.cnss.ne/'],
    ['NG', 'National Pension Commission Nigeria', 'https://www.pencom.gov.ng/'],
    ['NG', 'Nigeria Social Insurance Trust Fund', 'https://www.nsitf.gov.ng/'],
    ['RW', 'Rwanda Social Security Board', 'https://www.rssb.rw/'],
    ['SL', 'National Social Security and Insurance Trust', 'https://www.nassit.org.sl/'],
    ['ZA', 'South African Social Security Agency', 'https://www.sassa.gov.za/'],
    ['ZA', 'Government Pensions Administration Agency', 'https://www.gpaa.gov.za/'],
    ['TZ', 'National Social Security Fund Tanzania', 'https://www.nssf.go.tz/'],
    ['TZ', 'Public Service Social Security Fund Tanzania', 'https://www.psssf.go.tz/'],
    ['TG', 'Caisse Nationale de Securite Sociale Togo', 'https://www.cnss.tg/'],
    ['TN', 'Caisse Nationale de Securite Sociale Tunisie', 'https://www.cnss.tn/'],
    ['TN', 'Caisse Nationale de Retraite et de Prevoyance Sociale Tunisie', 'https://www.cnrps.nat.tn/'],
    ['UG', 'National Social Security Fund Uganda', 'https://www.nssfug.org/'],
    ['ZM', 'National Pension Scheme Authority Zambia', 'https://www.napsa.co.zm/'],
    ['ZW', 'National Social Security Authority Zimbabwe', 'https://www.nssa.org.zw/'],

    ['AI', 'Anguilla Social Security Board', 'https://www.ssbai.com/'],
    ['AW', 'Sociale Verzekeringsbank Aruba', 'https://www.svbaruba.org/'],
    ['BZ', 'Belize Social Security Board', 'https://www.socialsecurity.org.bz/'],
    ['BM', 'Bermuda Department of Social Insurance', 'https://www.gov.bm/department/social-insurance'],
    ['VG', 'BVI Social Security Board', 'https://www.bvissb.vg/'],
    ['KY', 'Cayman Islands Department of Labour and Pensions', 'https://www.dlp.gov.ky/'],
    ['CW', 'Sociale Verzekeringsbank Curacao', 'https://www.svbcur.org/'],
    ['DO', 'Tesoreria de la Seguridad Social', 'https://www.tss.gob.do/'],
    ['DO', 'Superintendencia de Pensiones Republica Dominicana', 'https://www.sipen.gob.do/'],
    ['GP', 'Caisse Generale de Securite Sociale de la Guadeloupe', 'https://www.cgss-guadeloupe.fr/'],
    ['GY', 'National Insurance Scheme Guyana', 'https://www.nis.org.gy/'],
    ['HT', 'Office National d Assurance Vieillesse', 'https://ona.gouv.ht/'],
    ['MQ', 'Caisse Generale de Securite Sociale de la Martinique', 'https://www.cgss-martinique.fr/'],
    ['MS', 'Montserrat Social Security Fund', 'https://www.socialsecurity.ms/'],
    ['PR', 'US Social Security Administration Puerto Rico', 'https://www.ssa.gov/'],
    ['KN', 'St. Christopher and Nevis Social Security Board', 'https://www.socialsecurity.kn/'],
    ['SX', 'Social and Health Insurances SZV', 'https://www.szv.sx/'],
    ['TC', 'Turks and Caicos National Insurance Board', 'https://www.tcinib.tc/'],
    ['VI', 'US Social Security Administration Virgin Islands', 'https://www.ssa.gov/'],

    ['AM', 'Unified Social Service Armenia', 'https://socservice.am/'],
    ['AZ', 'State Social Protection Fund Azerbaijan', 'https://www.sosial.gov.az/'],
    ['BH', 'Social Insurance Organization Bahrain', 'https://www.sio.gov.bh/'],
    ['BN', 'Tabung Amanah Pekerja Brunei', 'https://www.tap.com.bn/'],
    ['KH', 'National Social Security Fund Cambodia', 'https://www.nssf.gov.kh/'],
    ['GE', 'Social Service Agency Georgia', 'https://www.ssa.gov.ge/'],
    ['IN', 'Employees Provident Fund Organisation India', 'https://www.epfindia.gov.in/'],
    ['IN', 'Employees State Insurance Corporation India', 'https://www.esic.gov.in/'],
    ['ID', 'BPJS Ketenagakerjaan', 'https://www.bpjsketenagakerjaan.go.id/'],
    ['ID', 'BPJS Kesehatan', 'https://bpjs-kesehatan.go.id/'],
    ['IL', 'National Insurance Institute Israel', 'https://www.btl.gov.il/'],
    ['JP', 'Japan Pension Service', 'https://www.nenkin.go.jp/'],
    ['JO', 'Social Security Corporation Jordan', 'https://www.ssc.gov.jo/'],
    ['KZ', 'Unified Accumulative Pension Fund Kazakhstan', 'https://www.enpf.kz/'],
    ['KW', 'Public Institution for Social Security Kuwait', 'https://www.pifss.gov.kw/'],
    ['KG', 'Social Fund of the Kyrgyz Republic', 'https://www.sf.kg/'],
    ['LA', 'Lao Social Security Organization', 'https://www.lss.gov.la/'],
    ['MY', 'Social Security Organisation Malaysia', 'https://www.perkeso.gov.my/'],
    ['MY', 'Employees Provident Fund Malaysia', 'https://www.kwsp.gov.my/'],
    ['MV', 'Maldives Pension Administration Office', 'https://pension.gov.mv/'],
    ['MN', 'General Authority for Social Insurance Mongolia', 'https://ndaatgal.mn/'],
    ['NP', 'Social Security Fund Nepal', 'https://ssf.gov.np/'],
    ['OM', 'Social Protection Fund Oman', 'https://www.spf.gov.om/'],
    ['PK', 'Employees Old-Age Benefits Institution Pakistan', 'https://www.eobi.gov.pk/'],
    ['PH', 'Social Security System Philippines', 'https://www.sss.gov.ph/'],
    ['PH', 'Government Service Insurance System Philippines', 'https://www.gsis.gov.ph/'],
    ['QA', 'General Retirement and Social Insurance Authority Qatar', 'https://www.grsia.gov.qa/'],
    ['SA', 'General Organization for Social Insurance Saudi Arabia', 'https://www.gosi.gov.sa/'],
    ['SG', 'Central Provident Fund Board Singapore', 'https://www.cpf.gov.sg/'],
    ['KR', 'National Pension Service Korea', 'https://www.nps.or.kr/'],
    ['LK', 'Employees Provident Fund Sri Lanka', 'https://epf.lk/'],
    ['LK', 'Employees Trust Fund Board Sri Lanka', 'https://www.etfb.lk/'],
    ['TW', 'Bureau of Labor Insurance Taiwan', 'https://www.bli.gov.tw/'],
    ['TH', 'Social Security Office Thailand', 'https://www.sso.go.th/'],
    ['TR', 'Social Security Institution Turkey', 'https://www.sgk.gov.tr/'],
    ['AE', 'General Pension and Social Security Authority UAE', 'https://www.gpssa.gov.ae/'],
    ['UZ', 'Off-Budget Pension Fund Uzbekistan', 'https://pfru.uz/'],
    ['VN', 'Vietnam Social Security', 'https://vss.gov.vn/'],
];

$countries = collect(config('country_intelligence.monitored_countries', []))
    ->mapWithKeys(fn (array $country, string $iso) => [strtoupper((string) ($country['iso_code'] ?? $iso)) => $country]);

$created = 0;
$updated = 0;
$countryNamesUpdated = 0;

foreach ($sources as [$iso, $name, $url]) {
    $iso = strtoupper($iso);
    $domain = parse_url($url, PHP_URL_HOST);
    $domain = $domain ? preg_replace('/^www\./', '', strtolower($domain)) : null;
    $countryConfig = $countries->get($iso, []);

    $source = IntelligenceSource::query()->updateOrCreate([
        'country_iso' => $iso,
        'domain' => $domain,
        'url' => $url,
    ], [
        'region' => $countryConfig['region'] ?? null,
        'name' => $name,
        'source_class' => 'social_security_admin',
        'focus' => 'news',
        'access_method' => 'official_portal',
        'connector' => 'portal_html',
        'registration_status' => 'configured',
        'registration_notes' => 'First-pass official social security / pension source populated for monitoring. Verify exact scope during source audit.',
        'is_enabled' => true,
    ]);

    $source->wasRecentlyCreated ? $created++ : $updated++;

    $country = Country::query()->where('iso_code', $iso)->first();
    if ($country && blank($country->social_security_administration_name)) {
        $country->forceFill([
            'social_security_administration_name' => $name,
        ])->save();
        $countryNamesUpdated++;
    }
}

echo "Official social security sources created: {$created}" . PHP_EOL;
echo "Official social security sources updated: {$updated}" . PHP_EOL;
echo "Country administration names backfilled: {$countryNamesUpdated}" . PHP_EOL;
