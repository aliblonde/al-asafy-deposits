import 'package:flutter/material.dart';
import '../constants/theme.dart';
import '../services/api_service.dart';

class NotificationsSheet extends StatefulWidget {
  const NotificationsSheet({super.key});

  @override
  State<NotificationsSheet> createState() => _NotificationsSheetState();
}

class _NotificationsSheetState extends State<NotificationsSheet> {
  bool _isLoading = true;
  List _notifications = [];
  int _unreadCount = 0;

  @override
  void initState() {
    super.initState();
    _fetch();
  }

  Future<void> _fetch() async {
    setState(() => _isLoading = true);
    final res = await ApiService.getNotifications();
    if (!mounted) return;
    setState(() {
      _isLoading = false;
      if (res['success'] == true) {
        _notifications = (res['data']?['notifications'] as List?) ?? [];
        _unreadCount = (res['data']?['unread_count'] as int?) ?? 0;
      }
    });
  }

  Future<void> _markAllRead() async {
    await ApiService.markAllNotificationsRead();
    setState(() {
      _unreadCount = 0;
      for (var n in _notifications) {
        n['is_read'] = true;
      }
    });
  }

  Future<void> _markRead(Map<String, dynamic> item) async {
    if (item['is_read'] == true) return;
    final id = item['id'] as int;
    await ApiService.markNotificationRead(id);
    setState(() {
      item['is_read'] = true;
      if (_unreadCount > 0) _unreadCount--;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.75,
      padding: const EdgeInsets.only(top: 20, left: 16, right: 16, bottom: 20),
      decoration: const BoxDecoration(
        color: AppTheme.bgCard,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        border: Border(top: BorderSide(color: AppTheme.gold, width: 2)),
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  const Icon(Icons.notifications_active, color: AppTheme.gold, size: 22),
                  const SizedBox(width: 8),
                  const Text(
                    'مركز الإشعارات والتنبيهات',
                    style: TextStyle(color: AppTheme.gold, fontSize: 17, fontWeight: FontWeight.bold),
                  ),
                  if (_unreadCount > 0) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: AppTheme.danger,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        '$_unreadCount جديد',
                        style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ],
              ),
              IconButton(
                icon: const Icon(Icons.close, color: AppTheme.textMuted),
                onPressed: () => Navigator.of(context).pop(),
              ),
            ],
          ),
          if (_notifications.isNotEmpty && _unreadCount > 0)
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                icon: const Icon(Icons.done_all, size: 16, color: AppTheme.goldLight),
                label: const Text('تحديد الكل كمقروء', style: TextStyle(color: AppTheme.goldLight, fontSize: 12)),
                onPressed: _markAllRead,
              ),
            ),
          const Divider(color: AppTheme.borderGold),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppTheme.gold))
                : _notifications.isEmpty
                    ? const Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.notifications_none, size: 48, color: AppTheme.textMuted),
                            SizedBox(height: 12),
                            Text('لا توجد إشعارات حالياً', style: TextStyle(color: AppTheme.textMuted)),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: _fetch,
                        color: AppTheme.gold,
                        child: ListView.separated(
                          itemCount: _notifications.length,
                          separatorBuilder: (_, __) => const Divider(color: Color(0x1AD4AF37), height: 1),
                          itemBuilder: (ctx, i) {
                            final n = _notifications[i] as Map<String, dynamic>;
                            final isUnread = n['is_read'] != true;

                            return ListTile(
                              onTap: () => _markRead(n),
                              contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              leading: CircleAvatar(
                                backgroundColor: isUnread
                                    ? AppTheme.gold.withValues(alpha: 0.2)
                                    : AppTheme.textMuted.withValues(alpha: 0.1),
                                child: Icon(
                                  isUnread ? Icons.mark_email_unread : Icons.drafts_outlined,
                                  color: isUnread ? AppTheme.gold : AppTheme.textMuted,
                                  size: 20,
                                ),
                              ),
                              title: Text(
                                n['title'] ?? 'إشعار',
                                style: TextStyle(
                                  color: isUnread ? AppTheme.textPrimary : AppTheme.textMuted,
                                  fontWeight: isUnread ? FontWeight.bold : FontWeight.normal,
                                  fontSize: 14,
                                ),
                              ),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const SizedBox(height: 4),
                                  Text(
                                    n['message'] ?? '',
                                    style: TextStyle(
                                      color: isUnread ? AppTheme.textPrimary.withValues(alpha: 0.85) : AppTheme.textMuted,
                                      fontSize: 12,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    n['created_at'] ?? '',
                                    style: const TextStyle(color: AppTheme.textMuted, fontSize: 10),
                                  ),
                                ],
                              ),
                              trailing: isUnread
                                  ? Container(
                                      width: 8,
                                      height: 8,
                                      decoration: const BoxDecoration(
                                        color: AppTheme.gold,
                                        shape: BoxShape.circle,
                                      ),
                                    )
                                  : null,
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}
