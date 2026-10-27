<?php

return [
    'title' => 'Growth system',
    'disabled' => 'The Growth system is not enabled for this store. Ask the platform administrator to switch it on.',
    'menu' => ['group' => 'Growth (n8n)', 'approvals' => 'Approvals', 'experiments' => 'Experiments', 'events' => 'Event log', 'clients' => 'Integrations', 'settings' => 'Guardrails'],
    'fields' => ['name' => 'Name', 'scopes' => 'Scopes', 'webhook_url' => 'Webhook URL', 'reason' => 'Reason', 'hypothesis' => 'Hypothesis', 'objective' => 'Objective', 'metric' => 'Metric',
        'starts_on' => 'Starts on', 'ends_on' => 'Ends on', 'budget_cap' => 'Budget cap', 'outcome' => 'Outcome', 'learnings' => 'Learnings', 'value' => 'Value', 'spend' => 'Spend', 'recorded_on' => 'Date', 'note' => 'Note'],
    'api' => [
        'unauthenticated' => 'Invalid credentials, signature or timestamp.', 'growth_disabled' => 'Growth system is disabled for this tenant.', 'forbidden_scope' => 'This client lacks the required scope.',
        'too_many_requests' => 'Too many requests.', 'idempotency_required' => 'The Idempotency-Key header is required (max 120 chars).',
        'idempotency_mismatch' => 'This Idempotency-Key was already used with a different request.', 'in_flight' => 'A request with this Idempotency-Key is still being processed.',
    ],
    'clients' => [
        'title' => 'Integration clients (n8n)', 'hint' => 'Each client gets a bearer token and an HMAC secret. There is no scope that can change prices, refunds, stock or ad budgets. API base:',
        'create' => 'Create client', 'created' => 'Client created. Copy the credentials now.', 'show_once' => 'Copy these now: they are shown only once.', 'token' => 'Bearer token', 'hmac' => 'HMAC secret',
        'webhook_hint' => 'Public https endpoint that receives signed events (optional).', 'last_used' => 'Last used', 'active' => 'Active', 'revoked' => 'Revoked',
        'rotate' => 'Rotate', 'rotate_confirm' => 'Rotate the token and secret? The old ones stop working immediately.', 'rotated' => 'Credentials rotated. Copy them now.',
        'revoke' => 'Revoke', 'revoke_confirm' => 'Revoke this client? It will stop working immediately.', 'revoked_ok' => 'Client revoked.', 'already_revoked' => 'This client is revoked.',
        'bad_scope' => 'Scope ":scope" is not allowed.', 'need_scope' => 'Choose at least one scope.', 'bad_webhook' => 'Webhook must be a public https URL (no localhost / private IPs).',
    ],
    'events' => [
        'title' => 'Event log (outbox)', 'hint' => 'Business events sent to n8n, signed with HMAC. Failed deliveries retry with backoff, then become dead; replay re-queues them.',
        'type' => 'Type', 'event_id' => 'Event ID', 'attempts' => 'Attempts', 'error' => 'Last error', 'created' => 'Created', 'replay' => 'Replay', 'replayed' => 'Event re-queued.',
        'statuses' => ['pending' => 'Pending', 'delivered' => 'Delivered', 'dead' => 'Dead'],
    ],
    'approvals' => [
        'title' => 'Approval queue', 'hint' => 'Anything that spends money, changes prices, publishes or creates an offer waits here for a human decision.',
        'type' => 'Type', 'details' => 'Details', 'requested_by' => 'Requested by', 'approve' => 'Approve', 'reject' => 'Reject', 'approve_confirm' => 'Approve and run this action?',
        'reason_prompt' => 'Reason', 'approved_ok' => 'Approved.', 'rejected_ok' => 'Rejected.', 'unknown_type' => 'Unknown approval type ":type".',
        'no_handler' => 'No handler is registered for this approval type.', 'already' => 'Already decided (:status).', 'expired' => 'This approval has expired.', 'handler_failed' => 'The action failed and was rolled back; the approval is still pending.',
        'statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'expired' => 'Expired'],
    ],
    'experiments' => [
        'title' => 'Experiments & outcomes', 'new' => 'New experiment', 'open' => 'Open', 'results' => 'Results', 'add_result' => 'Add result', 'no_results' => 'No results recorded yet.', 'source' => 'Source',
        'dates' => 'End date must not be before the start date.', 'over_cap' => 'Budget cap exceeds the store guardrail (:cap).', 'outcome_required' => 'Choose an outcome to complete the experiment.',
        'cap_hint' => 'Ceiling, not a spending instruction. Guardrail max: :cap', 'roas_note' => 'ROAS is shown for reference only and is not profit.',
        'statuses' => ['draft' => 'Draft', 'running' => 'Running', 'paused' => 'Paused', 'completed' => 'Completed', 'cancelled' => 'Cancelled'],
        'outcomes' => ['win' => 'Win', 'loss' => 'Loss', 'inconclusive' => 'Inconclusive'],
    ],
    'settings' => [
        'title' => 'Growth guardrails', 'hint' => 'Store-level limits used by every growth module. Leave a field empty to use the platform default.', 'default' => 'Default: :v',
        'out_of_range' => 'Must be between :min and :max.', 'cover_order' => 'Minimum days of cover cannot exceed the maximum.', 'allowlist_hint' => 'Comma-separated FAQ keys that may be answered automatically. Empty = nothing is automatic.',
        'fields' => ['min_margin_percent' => 'Minimum margin %', 'max_discount_percent' => 'Maximum discount %', 'low_stock_threshold' => 'Low-stock threshold (units)', 'min_days_of_cover' => 'Minimum days of cover',
            'max_days_of_cover' => 'Maximum days of cover', 'reorder_cash_cap' => 'Reorder cash cap', 'ad_daily_budget_cap' => 'Ad daily budget cap', 'ad_total_budget_cap' => 'Ad total budget cap', 'auto_reply_allowlist' => 'Auto-reply allowlist'],
    ],
];
