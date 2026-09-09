<?php
/**
 * includes/marketing_analytics.php
 * Fire 10 — aggregation queries for the campaign analytics dashboard and
 * per-contact message history. Read-only; never touches messaging/queue
 * state. Depends on marketing_messages having at most one row per
 * (recipient) — true per Fire 8's worker, which inserts exactly once on
 * final success or final failure.
 *
 * ACCURACY NOTE: 'delivered' status for the email channel is inferred from
 * the recipient opening the message or clicking a link (Fire 9) — SMTP has
 * no delivery webhook. Every rate computed here that touches "delivery"
 * for email is really an engagement proxy. The UI surfaces this explicitly
 * rather than let a delivery-rate number imply more certainty than exists.
 */
require_once __DIR__ . '/marketing.php';
require_once __DIR__ . '/marketing_campaigns.php';

const MARKETING_MESSAGE_STATUS_SHAPE = ['sent'=>0,'delivered'=>0,'read'=>0,'failed'=>0,'bounced'=>0];

/** Batch-attaches per-campaign metrics to a set of campaign rows in 2 queries total, not N+1. */
function attachMetricsToCampaigns(array $campaigns): array {
    if (empty($campaigns)) return $campaigns;
    $db = getDB();
    $ids = array_column($campaigns, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));

    $st = $db->prepare("SELECT campaign_id, status, COUNT(*) c FROM marketing_messages WHERE campaign_id IN ($ph) GROUP BY campaign_id, status");
    $st->execute($ids);
    $byCampaign = [];
    foreach ($st->fetchAll() as $row) $byCampaign[$row['campaign_id']][$row['status']] = (int)$row['c'];

    $st2 = $db->prepare("SELECT campaign_id, COUNT(*) c FROM marketing_campaign_recipients WHERE campaign_id IN ($ph) GROUP BY campaign_id");
    $st2->execute($ids);
    $recipCounts = [];
    foreach ($st2->fetchAll() as $row) $recipCounts[$row['campaign_id']] = (int)$row['c'];

    foreach ($campaigns as &$c) {
        $s = array_merge(MARKETING_MESSAGE_STATUS_SHAPE, $byCampaign[$c['id']] ?? []);
        $dispatched = array_sum($s);
        $deliveredOrBetter = $s['delivered'] + $s['read'];
        $c['metrics'] = [
            'total_recipients'    => $recipCounts[$c['id']] ?? 0,
            'dispatched'          => $dispatched,
            'delivered_or_better' => $deliveredOrBetter,
            'read'                => $s['read'],
            'failed'              => $s['failed'],
            'delivery_rate'       => $dispatched ? round(($deliveredOrBetter / $dispatched) * 100, 1) : null,
        ];
    }
    unset($c);
    return $campaigns;
}

function getMarketingCampaignsWithMetrics(array $opts = []): array {
    $result = getMarketingCampaigns($opts);
    $result['rows'] = attachMetricsToCampaigns($result['rows']);
    return $result;
}

/** Full drill-down for one campaign: recipient/message status breakdown, engagement, top errors, daily timeline. */
function getMarketingCampaignAnalytics(int $campaignId): ?array {
    $campaign = getMarketingCampaign($campaignId);
    if (!$campaign) return null;
    $db = getDB();

    $recipShape = ['pending'=>0,'queued'=>0,'processing'=>0,'sent'=>0,'delivered'=>0,'read'=>0,
                   'failed'=>0,'cancelled'=>0,'rate_limited'=>0,'opted_out'=>0];
    $st = $db->prepare("SELECT status, COUNT(*) c FROM marketing_campaign_recipients WHERE campaign_id=? GROUP BY status");
    $st->execute([$campaignId]);
    foreach ($st->fetchAll() as $row) $recipShape[$row['status']] = (int)$row['c'];

    $msgStatus = MARKETING_MESSAGE_STATUS_SHAPE;
    $st2 = $db->prepare("SELECT status, COUNT(*) c FROM marketing_messages WHERE campaign_id=? GROUP BY status");
    $st2->execute([$campaignId]);
    foreach ($st2->fetchAll() as $row) $msgStatus[$row['status']] = (int)$row['c'];

    // COUNT(DISTINCT message_id): a message opened 3 times is one "opened" message, not three.
    $events = ['opened' => 0, 'clicked' => 0];
    $st3 = $db->prepare("SELECT me.event_type, COUNT(DISTINCT me.message_id) c
                          FROM marketing_message_events me JOIN marketing_messages mm ON mm.id = me.message_id
                          WHERE mm.campaign_id=? AND me.event_type IN ('opened','clicked') GROUP BY me.event_type");
    $st3->execute([$campaignId]);
    foreach ($st3->fetchAll() as $row) $events[$row['event_type']] = (int)$row['c'];

    $st4 = $db->prepare("SELECT error_message, COUNT(*) c FROM marketing_messages
                          WHERE campaign_id=? AND status='failed' AND error_message IS NOT NULL
                          GROUP BY error_message ORDER BY c DESC LIMIT 5");
    $st4->execute([$campaignId]);
    $topErrors = $st4->fetchAll();

    $st5 = $db->prepare("SELECT DATE(FROM_UNIXTIME(sent_at)) d, COUNT(*) c FROM marketing_messages
                          WHERE campaign_id=? AND sent_at IS NOT NULL GROUP BY d ORDER BY d ASC");
    $st5->execute([$campaignId]);
    $timeline = $st5->fetchAll();

    $dispatched = array_sum($msgStatus);
    $deliveredOrBetter = $msgStatus['delivered'] + $msgStatus['read'];

    return [
        'campaign'   => $campaign,
        'recipients' => $recipShape,
        'messages'   => $msgStatus,
        'events'     => $events,
        'top_errors' => $topErrors,
        'timeline'   => $timeline,
        'rates' => [
            'dispatched'    => $dispatched,
            'delivery_rate' => $dispatched ? round(($deliveredOrBetter / $dispatched) * 100, 1) : null,
            'read_rate'     => ($campaign['channel'] !== 'email' && $dispatched) ? round(($msgStatus['read'] / $dispatched) * 100, 1) : null,
            'open_rate'     => ($campaign['channel'] !== 'whatsapp' && $dispatched) ? round(($events['opened'] / $dispatched) * 100, 1) : null,
            'click_rate'    => ($campaign['channel'] !== 'whatsapp' && $dispatched) ? round(($events['clicked'] / $dispatched) * 100, 1) : null,
            'failure_rate'  => $dispatched ? round(($msgStatus['failed'] / $dispatched) * 100, 1) : null,
        ],
    ];
}

/** Dashboard-wide summary for the last $days (0 = all-time), split by channel. */
function getMarketingDashboardSummary(int $days = 30): array {
    $db = getDB();
    $since = $days > 0 ? (time() - $days * 86400) : 0;

    $byChannelStatus = ['whatsapp' => MARKETING_MESSAGE_STATUS_SHAPE, 'email' => MARKETING_MESSAGE_STATUS_SHAPE];
    $st = $db->prepare("SELECT channel, status, COUNT(*) c FROM marketing_messages WHERE created_at>=? GROUP BY channel, status");
    $st->execute([$since]);
    foreach ($st->fetchAll() as $row) $byChannelStatus[$row['channel']][$row['status']] = (int)$row['c'];

    $events = ['whatsapp' => ['opened'=>0,'clicked'=>0], 'email' => ['opened'=>0,'clicked'=>0]];
    $st2 = $db->prepare("SELECT mm.channel, me.event_type, COUNT(DISTINCT me.message_id) c
                          FROM marketing_message_events me JOIN marketing_messages mm ON mm.id = me.message_id
                          WHERE mm.created_at>=? AND me.event_type IN ('opened','clicked') GROUP BY mm.channel, me.event_type");
    $st2->execute([$since]);
    foreach ($st2->fetchAll() as $row) $events[$row['channel']][$row['event_type']] = (int)$row['c'];

    $summary = [];
    foreach (['whatsapp', 'email'] as $channel) {
        $s = $byChannelStatus[$channel];
        $dispatched = array_sum($s);
        $deliveredOrBetter = $s['delivered'] + $s['read'];
        $summary[$channel] = [
            'dispatched'          => $dispatched,
            'delivered_or_better' => $deliveredOrBetter,
            'read'                => $s['read'],
            'failed'              => $s['failed'],
            'opened'              => $events[$channel]['opened'],
            'clicked'             => $events[$channel]['clicked'],
            'delivery_rate'       => $dispatched ? round(($deliveredOrBetter / $dispatched) * 100, 1) : null,
            'read_rate'           => ($channel === 'whatsapp' && $dispatched) ? round(($s['read'] / $dispatched) * 100, 1) : null,
            'open_rate'           => ($channel === 'email' && $dispatched) ? round(($events['email']['opened'] / $dispatched) * 100, 1) : null,
            'click_rate'          => ($channel === 'email' && $dispatched) ? round(($events['email']['clicked'] / $dispatched) * 100, 1) : null,
            'failure_rate'        => $dispatched ? round(($s['failed'] / $dispatched) * 100, 1) : null,
        ];
    }

    return [
        'days'             => $days,
        'channels'         => $summary,
        'active_campaigns' => (int)$db->query("SELECT COUNT(*) FROM marketing_campaigns WHERE status IN ('running','scheduled')")->fetchColumn(),
        'total_contacts'   => (int)$db->query("SELECT COUNT(*) FROM marketing_contacts WHERE status='active'")->fetchColumn(),
        'suppressed'       => [
            'whatsapp' => (int)$db->query("SELECT COUNT(*) FROM marketing_suppression WHERE channel='whatsapp'")->fetchColumn(),
            'email'    => (int)$db->query("SELECT COUNT(*) FROM marketing_suppression WHERE channel='email'")->fetchColumn(),
        ],
    ];
}

/** Daily send-volume series (zero-filled for gap days) for the simple bar chart. */
function getMarketingSendVolumeTimeSeries(int $days = 14): array {
    $db = getDB();
    $since = time() - $days * 86400;
    $st = $db->prepare("SELECT DATE(FROM_UNIXTIME(created_at)) d, channel, COUNT(*) c FROM marketing_messages WHERE created_at>=? GROUP BY d, channel ORDER BY d ASC");
    $st->execute([$since]);

    $byDate = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $byDate[$d] = ['date' => $d, 'whatsapp' => 0, 'email' => 0];
    }
    foreach ($st->fetchAll() as $row) {
        if (isset($byDate[$row['d']])) $byDate[$row['d']][$row['channel']] = (int)$row['c'];
    }
    return array_values($byDate);
}

/** Paginated message history for one contact, across all campaigns/channels. */
function getMarketingContactMessageHistory(int $contactId, array $opts = []): array {
    $db = getDB();
    $limit = (int)($opts['limit'] ?? 20); $offset = (int)($opts['offset'] ?? 0);

    $cnt = $db->prepare("SELECT COUNT(*) FROM marketing_messages mm
                          JOIN marketing_campaign_recipients mcr ON mcr.id = mm.recipient_id WHERE mcr.contact_id=?");
    $cnt->execute([$contactId]);
    $total = (int)$cnt->fetchColumn();

    $st = $db->prepare("SELECT mm.*, mc.name AS campaign_name
                         FROM marketing_messages mm
                         JOIN marketing_campaign_recipients mcr ON mcr.id = mm.recipient_id
                         LEFT JOIN marketing_campaigns mc ON mc.id = mm.campaign_id
                         WHERE mcr.contact_id=?
                         ORDER BY mm.created_at DESC LIMIT ? OFFSET ?");
    $st->bindValue(1, $contactId, PDO::PARAM_INT);
    $st->bindValue(2, $limit, PDO::PARAM_INT);
    $st->bindValue(3, $offset, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();

    if ($rows) {
        $ids = array_column($rows, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $evSt = $db->prepare("SELECT message_id, event_type, COUNT(*) c FROM marketing_message_events WHERE message_id IN ($ph) GROUP BY message_id, event_type");
        $evSt->execute($ids);
        $byMsg = [];
        foreach ($evSt->fetchAll() as $row) $byMsg[$row['message_id']][$row['event_type']] = (int)$row['c'];
        foreach ($rows as &$r) $r['events'] = $byMsg[$r['id']] ?? [];
        unset($r);
    }

    $pendingSt = $db->prepare("SELECT COUNT(*) FROM marketing_queue mq JOIN marketing_campaign_recipients mcr ON mcr.id = mq.recipient_id
                                WHERE mcr.contact_id=? AND mq.status IN ('pending','rate_limited','processing')");
    $pendingSt->execute([$contactId]);

    return ['rows' => $rows, 'total' => $total, 'pending_in_queue' => (int)$pendingSt->fetchColumn()];
}

function getMarketingContactGroupMemberships(int $contactId): array {
    $st = getDB()->prepare("SELECT g.* FROM marketing_groups g JOIN marketing_group_contacts gc ON gc.group_id = g.id
                             WHERE gc.contact_id=? AND g.type='static'");
    $st->execute([$contactId]);
    return $st->fetchAll();
}

/**
 * For the drop-in client/user profile widget: looks up the linked
 * marketing_contacts row (if the record has ever been synced — see Fire 3's
 * syncContactsFromUsers()/syncContactsFromClients()) and returns a compact
 * summary. Returns null if this user/client was never synced — the widget
 * shows a "sync first" prompt in that case rather than an empty table.
 */
function getMarketingHistoryForSourceRecord(string $sourceType, int $sourceId): ?array {
    if ($sourceId <= 0 || !in_array($sourceType, ['user', 'client'], true)) return null;
    $st = getDB()->prepare("SELECT * FROM marketing_contacts WHERE source_type=? AND source_id=? LIMIT 1");
    $st->execute([$sourceType, $sourceId]);
    $contact = $st->fetch();
    if (!$contact) return null;

    $history = getMarketingContactMessageHistory((int)$contact['id'], ['limit' => 5, 'offset' => 0]);
    return [
        'contact'          => $contact,
        'recent'           => $history['rows'],
        'total'            => $history['total'],
        'pending_in_queue' => $history['pending_in_queue'],
    ];
}