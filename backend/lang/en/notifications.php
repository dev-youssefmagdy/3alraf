<?php

return [
    // staff bell / e-mail subjects (rendered at read time)
    'admin' => [
        'new_order' => 'New order :number from :customer (:total)',
        'status_changed' => 'Order :number is now :status',
        'proof_uploaded' => 'Payment proof uploaded for order :number',
        'payment_received' => 'Online payment received for order :number (:total)',
        'return_requested' => 'Return :return requested for order :number',
        'tenant_created' => 'New store created: :name',
        'tenant_status' => 'Store :name was :state',
        'tenant_active' => 'activated',
        'tenant_inactive' => 'deactivated',
    ],
    'admin_mail' => ['greeting' => 'Hello :name,', 'view' => 'Open in admin panel', 'customer' => 'Customer', 'total' => 'Total', 'payment' => 'Payment'],
    // customer e-mails
    'customer' => [
        'greeting' => 'Hello :name,',
        'guest' => 'there',
        'view' => 'View your order',
        'order' => 'Order',
        'total' => 'Total',
        'status' => 'Status',
        'thanks' => 'Thank you for shopping with :store.',
        'placed' => ['subject' => 'We received your order :number', 'line' => 'Thanks! We received your order and will start processing it shortly.'],
        'status_changed' => ['subject' => 'Order :number is now :status', 'line' => 'The status of your order changed to :status.'],
        'proof_approved' => ['subject' => 'Payment confirmed for order :number', 'line' => 'We verified your payment. Thank you!'],
        'proof_rejected' => ['subject' => 'Payment proof rejected for order :number', 'line' => 'We could not verify your payment proof. Please open your order and upload a clear proof again.'],
        'paid' => ['subject' => 'Payment received for order :number', 'line' => 'Your online payment was received. Thank you!'],
        'return_requested' => ['subject' => 'Return request :return received', 'line' => 'We received your return request for order :number and will review it soon.'],
        'return_approved' => ['subject' => 'Return :return approved', 'line' => 'Your return request for order :number was approved.'],
        'return_rejected' => ['subject' => 'Return :return rejected', 'line' => 'Unfortunately your return request for order :number was rejected.'],
        'return_refunded' => ['subject' => 'Refund issued for return :return', 'line' => 'We refunded :amount for your return on order :number.'],
    ],
    'settings' => [
        'title' => 'Notification settings',
        'hint' => 'Choose which e-mails your store sends to customers. Staff e-mails are controlled by the "E-mail notifications" permission on each role.',
        'placed' => 'Order received',
        'status' => 'Order status changes',
        'payment' => 'Payment confirmed / proof rejected',
        'return' => 'Return updates',
    ],
    'audit' => ['sent' => 'sent', 'failed' => 'failed'],
];
