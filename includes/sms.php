<?php
declare(strict_types=1);

/**
 * Tumaini Connect — SMS Service
 * Provider: Swahili Software (swahilisms.co.tz)
 */

// ── Configuration ────────────────────────────────────────────────────────────
define('SMS_API_URL',   'https://swahilisms.co.tz/api/v1/sms/send');
define('SMS_API_TOKEN', '44f28716d8274506d0983f50f85c8ec4c852ae5ea3aa37ebba0ee79abb42229e');
define('SMS_SENDER_ID', 'SDA NTUC');
define('SMS_ENABLED',   true);

// ── Core send function ────────────────────────────────────────────────────────

/**
 * Send one SMS message to one or more recipients.
 *
 * @param  string|array  $to      E164 / local TZ numbers (string or array of strings)
 * @param  string        $message Plain text body (max ~160 chars per segment)
 * @return bool          true on HTTP 2xx, false otherwise
 */
function sms_send($to, string $message): bool
{
    if (!SMS_ENABLED) {
        return false;
    }

    $numbers = is_array($to) ? $to : [$to];
    // Normalise to 255XXXXXXXXX format
    $normalised = [];
    foreach ($numbers as $num) {
        $clean = preg_replace('/[^0-9]/', '', (string) $num);
        if ($clean === '') {
            continue;
        }
        if (str_starts_with($clean, '0')) {
            $clean = '255' . substr($clean, 1);
        } elseif (!str_starts_with($clean, '255')) {
            $clean = '255' . $clean;
        }
        if (strlen($clean) === 12) {
            $normalised[] = $clean;
        }
    }

    if (empty($normalised)) {
        return false;
    }

    $payload = json_encode([
        'api_token' => SMS_API_TOKEN,
        'recipient' => implode(',', $normalised),
        'message'   => $message,
        'sender_id' => SMS_SENDER_ID,
    ]);

    $ch = curl_init(SMS_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

// ── High-level notification helpers ──────────────────────────────────────────

/**
 * Notify the station coordinator when a new interest is submitted at their station.
 *
 * @param array  $interest  Row from interests table (with request_type, full_name, phone)
 * @param array  $center    Row from centers table (with name, coordinator_phone)
 */
function sms_notify_coordinator_new_interest(array $interest, array $center): void
{
    $phone = (string) ($center['coordinator_phone'] ?? '');
    if ($phone === '') {
        return;
    }

    $requestLabels = [
        'baptism'          => 'Ubatizo',
        'bible_study'      => 'Masomo ya Biblia',
        'prayer'           => 'Maombi',
        'visit'            => 'Ziara',
        'church_connection'=> 'Ushirikiano wa Kanisa',
    ];
    $requestLabel = $requestLabels[$interest['request_type'] ?? ''] ?? ucwords(str_replace('_', ' ', (string) ($interest['request_type'] ?? '')));

    $message = "TUMAINI CONNECT - OMBI JIPYA\n"
        . "Jina: " . (string) $interest['full_name'] . "\n"
        . "Simu: " . (string) $interest['phone'] . "\n"
        . "Ombi: " . $requestLabel . "\n"
        . "Kanisa: " . (string) $center['name'] . "\n"
        . "Tarehe: " . date('d/m/Y') . "\n"
        . "Tafadhali mfuatilie haraka iwezekanavyo.";

    sms_send($phone, $message);
}

/**
 * Notify the interest person when their follow-up has been actioned.
 *
 * @param array  $interest  Row with full_name, phone, request_type
 * @param string $workerName  Name of the staff member handling the follow-up
 */
function sms_notify_interest_followup(array $interest, string $workerName = ''): void
{
    $phone = (string) ($interest['phone'] ?? '');
    if ($phone === '') {
        return;
    }

    $firstName = explode(' ', trim((string) $interest['full_name']))[0];

    $requestLabels = [
        'baptism'          => 'Ubatizo',
        'bible_study'      => 'Masomo ya Biblia',
        'prayer'           => 'Maombi',
        'visit'            => 'Ziara',
        'church_connection'=> 'Ushirikiano wa Kanisa',
    ];
    $requestLabel = $requestLabels[$interest['request_type'] ?? ''] ?? ucwords(str_replace('_', ' ', (string) ($interest['request_type'] ?? '')));

    $workerLine = $workerName !== '' ? "Atakayekufikia: $workerName.\n" : '';

    $message = "Habari $firstName,\n"
        . "Asante kwa kuwasiliana nasi. Mchungaji/Mtumishi wetu atakufikia hivi karibuni kuhusu ombi lako la $requestLabel.\n"
        . $workerLine
        . "Mungu akubariki.\n"
        . "- SDA Tanzania | Tumaini Connect";

    sms_send($phone, $message);
}

/**
 * Send daily attendance + interest summary to a single admin user.
 *
 * @param array  $admin        User row (name, phone, role, union_id, conference_id)
 * @param array  $attendance   ['elderly_men', 'elderly_women', 'children_boys', 'children_girls', 'members_attended', 'total_attendance']
 * @param array  $interests    ['new_today', 'total', 'worked', 'completed']
 * @param string $scopeName    Human-readable scope label (e.g. "Central Tanzania Field")
 */
function sms_send_daily_summary(array $admin, array $attendance, array $interests, string $scopeName): void
{
    $phone = (string) ($admin['phone'] ?? '');
    if ($phone === '') {
        return;
    }

    $date = date('d/m/Y');

    $adultsMen      = (int) ($attendance['elderly_men'] ?? 0);
    $adultsWomen    = (int) ($attendance['elderly_women'] ?? 0);
    $childrenBoys   = (int) ($attendance['children_boys'] ?? 0);
    $childrenGirls  = (int) ($attendance['children_girls'] ?? 0);
    $churchMembers  = (int) ($attendance['members_attended'] ?? 0);
    $adultsTotal    = $adultsMen + $adultsWomen;
    $childrenTotal  = $childrenBoys + $childrenGirls;
    $grandTotal     = (int) ($attendance['total_attendance'] ?? ($adultsTotal + $childrenTotal + $churchMembers));

    $attendanceMsg = "TUMAINI CONNECT - RIPOTI YA MAHUDHURIO ($date)\n"
        . "Kituo: $scopeName\n"
        . "Jumla ya Wageni Watu Wazima: $adultsTotal\n"
        . "- Wanaume: $adultsMen\n"
        . "- Wanawake: $adultsWomen\n"
        . "Jumla ya Watoto: $childrenTotal\n"
        . "- Wavulana: $childrenBoys\n"
        . "- Wasichana: $childrenGirls\n"
        . "Washiriki wa Kanisa: $churchMembers\n"
        . "Jumla Kuu Mahudhurio: $grandTotal";

    $interestMsg = "TUMAINI CONNECT - MAOMBI ($date)\n"
        . "Kituo: $scopeName\n"
        . "Maombi Mapya Leo: " . (int) ($interests['new_today'] ?? 0) . "\n"
        . "Jumla Yote: " . (int) ($interests['total'] ?? 0) . "\n"
        . "Yanayofanyiwa Kazi: " . (int) ($interests['worked'] ?? 0) . "\n"
        . "Yaliyokamilika: " . (int) ($interests['completed'] ?? 0);

    sms_send($phone, $attendanceMsg);
    sms_send($phone, $interestMsg);
}
