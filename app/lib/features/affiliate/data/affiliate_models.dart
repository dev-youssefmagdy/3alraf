import '../../../core/network/json.dart';

class AffiliateProfile {
  const AffiliateProfile({
    required this.id,
    required this.name,
    required this.email,
    required this.status,
    required this.ref,
  });

  factory AffiliateProfile.fromJson(Map<String, Object?> j) => AffiliateProfile(
    id: intOf(j['id']),
    name: str(j['name']),
    email: str(j['email']),
    status: str(j['status']),
    ref: str(j['ref']),
  );

  final int id;
  final String name;
  final String email;
  final String status;

  /// uuid used in share links.
  final String ref;
}

class AffiliateTier {
  const AffiliateTier({
    required this.name,
    required this.percent,
    required this.minSales,
  });

  factory AffiliateTier.fromJson(Map<String, Object?> j) => AffiliateTier(
    name: str(j['name']),
    percent: str(j['percent']),
    minSales: str(j['min_sales']),
  );

  final String name;
  final String percent;
  final String minSales;
}

class AffiliateStats {
  const AffiliateStats({
    this.clicks = 0,
    this.orders = 0,
    this.conversionPercent = '0',
    this.lifetimeSales = '0.00',
    this.tier,
    this.nextTier,
    this.commissions = const {},
  });

  factory AffiliateStats.fromJson(Map<String, Object?> j) {
    final tier = asMap(j['tier']);
    final next = asMap(j['next_tier']);
    return AffiliateStats(
      clicks: intOf(j['clicks']),
      orders: intOf(j['orders']),
      conversionPercent: str(j['conversion_percent'], '0'),
      lifetimeSales: str(j['lifetime_sales'], '0.00'),
      tier: tier.isEmpty ? null : AffiliateTier.fromJson(tier),
      nextTier: next.isEmpty ? null : AffiliateTier.fromJson(next),
      commissions: asMap(j['commissions']).map((k, v) => MapEntry(k, str(v))),
    );
  }

  final int clicks;
  final int orders;
  final String conversionPercent;
  final String lifetimeSales;
  final AffiliateTier? tier;
  final AffiliateTier? nextTier;

  /// pending / approved / paid / available (server money strings).
  final Map<String, String> commissions;
}

class Payout {
  const Payout({
    required this.id,
    required this.amount,
    required this.status,
    this.note,
    this.requestedAt,
    this.paidAt,
  });

  factory Payout.fromJson(Map<String, Object?> j) => Payout(
    id: intOf(j['id']),
    amount: str(j['amount'], '0.00'),
    status: str(j['status']),
    note: strOrNull(j['note']),
    requestedAt: dateOrNull(j['requested_at']),
    paidAt: dateOrNull(j['paid_at']),
  );

  final int id;
  final String amount;
  final String status;
  final String? note;
  final DateTime? requestedAt;
  final DateTime? paidAt;
}

class RecentCommission {
  const RecentCommission({
    required this.amount,
    required this.percent,
    required this.status,
    this.order,
  });

  factory RecentCommission.fromJson(Map<String, Object?> j) => RecentCommission(
    amount: str(j['amount'], '0.00'),
    percent: str(j['percent']),
    status: str(j['status']),
    order: strOrNull(j['order']),
  );

  final String amount;
  final String percent;
  final String status;
  final String? order;
}

class AffiliateDashboard {
  const AffiliateDashboard({
    required this.affiliate,
    required this.stats,
    this.payouts = const [],
    this.recentCommissions = const [],
  });

  factory AffiliateDashboard.fromJson(Map<String, Object?> j) =>
      AffiliateDashboard(
        affiliate: AffiliateProfile.fromJson(asMap(j['affiliate'])),
        stats: AffiliateStats.fromJson(asMap(j['stats'])),
        payouts: asMapList(j['payouts']).map(Payout.fromJson).toList(),
        recentCommissions: asMapList(
          j['recent_commissions'],
        ).map(RecentCommission.fromJson).toList(),
      );

  final AffiliateProfile affiliate;
  final AffiliateStats stats;
  final List<Payout> payouts;
  final List<RecentCommission> recentCommissions;
}

class AffiliateProduct {
  const AffiliateProduct({
    required this.id,
    required this.slug,
    required this.name,
    required this.price,
    required this.link,
    this.image,
  });

  factory AffiliateProduct.fromJson(Map<String, Object?> j) => AffiliateProduct(
    id: intOf(j['id']),
    slug: str(j['slug']),
    name: str(j['name']),
    price: str(j['price'], '0.00'),
    link: str(j['link']),
    image: strOrNull(j['image']),
  );

  final int id;
  final String slug;
  final String name;
  final String price;

  /// Share link carrying the affiliate ref (the QR encodes it too).
  final String link;
  final String? image;
}

class AmbassadorLink {
  const AmbassadorLink({
    required this.id,
    required this.channel,
    required this.productId,
    required this.clicks,
    required this.url,
  });

  factory AmbassadorLink.fromJson(Map<String, Object?> j) => AmbassadorLink(
    id: intOf(j['id']),
    channel: str(j['channel']),
    productId: intOf(j['product_id']),
    clicks: intOf(j['clicks']),
    url: str(j['url']),
  );

  final int id;
  final String channel;
  final int productId;
  final int clicks;
  final String url;
}

class AmbassadorIdea {
  const AmbassadorIdea({required this.title, required this.body, this.channel});

  factory AmbassadorIdea.fromJson(Map<String, Object?> j) => AmbassadorIdea(
    title: str(j['title']),
    body: str(j['body']),
    channel: strOrNull(j['channel']),
  );

  final String title;
  final String body;
  final String? channel;
}

/// `GET /affiliate/ambassador`.
class AmbassadorPortal {
  const AmbassadorPortal({
    required this.enrolled,
    this.disclosure,
    this.channels = const [],
    this.ideas = const [],
    this.links = const [],
    this.report = const {},
  });

  factory AmbassadorPortal.fromJson(Map<String, Object?> j) {
    final amb = asMap(j['ambassador']);
    final report = asMap(j['report']);
    return AmbassadorPortal(
      enrolled: boolOf(j['enrolled']),
      disclosure: strOrNull(amb['disclosure']),
      channels: asList(j['channels']).whereType<String>().toList(),
      ideas: asMapList(j['ideas']).map(AmbassadorIdea.fromJson).toList(),
      links: asMapList(j['links']).map(AmbassadorLink.fromJson).toList(),
      report: {
        for (final k in const [
          'valid_orders',
          'pending_orders',
          'excluded_orders',
          'window_days',
        ])
          k: intOf(report[k]),
      },
    );
  }

  final bool enrolled;
  final String? disclosure;
  final List<String> channels;
  final List<AmbassadorIdea> ideas;
  final List<AmbassadorLink> links;

  /// valid_orders / pending_orders / excluded_orders / window_days.
  final Map<String, int> report;
}
