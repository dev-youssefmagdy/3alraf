<?php

namespace App\Services\Erp\Cash;

use App\Exceptions\AccountingException;
use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\JournalLine;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Support\Money;

/**
 * Cash & Bank settlements of the clearing accounts the business flows fill but never empty (ERP-012):
 *   VAT payment      : Dr VAT payable / Cr bank|cash
 *   Pay carrier      : Dr Shipping payable / Cr bank|cash   (prepaid-order shipping, return shipping, COD fees above the collected amount)
 *   Payout / deposit : Dr bank|cash (net) + Dr Gateway fees (fee) / Cr gateway|wallet|pos_cash (gross)  - gateway payout, wallet sweep,
 *                      POS register close (ERP-014)
 * Every action is one journal through JournalService (the only ledger writer), idempotent by a form token, serialised per tenant.
 */
class SettlementService
{
    public const PAY_FROM = ['bank', 'cash'];

    public const PAYOUT_SOURCES = ['gateway', 'wallet', 'pos_cash'];

    public function __construct(private JournalService $journals, private CoaTemplate $coa) {}

    private function ensureChart(): void
    {
        if (! Account::where('role_key', 'receivable')->exists()) {
            $this->coa->seed(current_tenant());
            app(\App\Services\Erp\Accounting\AccountResolver::class)->flush();
        }
    }

    /** Debit-positive balance of a role over posted lines (all branches). */
    public function balance(string $role): string
    {
        $id = Account::where('role_key', $role)->value('id');

        return (string) (JournalLine::query()->join('erp_journal_entries as e', 'e.id', '=', 'erp_journal_lines.entry_id')->whereIn('e.status', ['posted', 'reversed'])
            ->where('erp_journal_lines.account_id', $id ?? 0)->selectRaw('COALESCE(SUM(erp_journal_lines.debit - erp_journal_lines.credit),0) as b')->value('b') ?? '0');
    }

    private function amount(string $a): string
    {
        $a = Money::round($a);
        if (Money::cmp($a, '0') <= 0) {
            throw new AccountingException(__('erp_ext.errors.amount_positive'));
        }

        return $a;
    }

    private function role(string $role, array $allowed): string
    {
        return in_array($role, $allowed, true) ? $role : throw new AccountingException(__('erp_ext.errors.bad_account'));
    }

    private function post(string $key, string $date, string $desc, array $lines, ?int $branchId): JournalEntry
    {
        return $this->journals->post(['entry_date' => $date, 'type' => 'auto', 'description' => $desc, 'source_type' => 'settlement', 'source_id' => 0,
            'idempotency_key' => 'settle:'.$key, 'branch_id' => $branchId, 'created_by' => auth('tenant')->id()], $lines);
    }

    /** Pay a liability (vat_payable | shipping_payable) out of bank/cash, never more than is owed. */
    private function payLiability(string $liability, string $kind, string $amount, string $from, string $ref, ?string $date, ?int $branchId, ?string $note): JournalEntry
    {
        $this->ensureChart();
        $amount = $this->amount($amount);
        $from = $this->role($from, self::PAY_FROM);

        return $this->journals->exclusive(function () use ($liability, $kind, $amount, $from, $ref, $date, $branchId, $note) {
            if ($done = JournalEntry::where('idempotency_key', 'settle:'.$kind.':'.$ref)->first()) {
                $this->journals->assertSameReplay($done, $amount); // QA-012

                return $done;
            }
            $owed = Money::max('0', Money::round(bcmul($this->balance($liability), '-1', 4))); // a liability carries a credit balance
            if (Money::cmp($amount, $owed) > 0) {
                throw new AccountingException(__('erp_acc.errors.over_settle', ['owed' => number_format((float) $owed, 2)]));
            }
            $m = $note ?: __('erp_acc.settle.'.$kind);

            return $this->post($kind.':'.$ref, $date ?? now()->toDateString(), $m, [
                ['account' => $liability, 'debit' => $amount, 'branch_id' => $branchId, 'memo' => $m],
                ['account' => $from, 'credit' => $amount, 'branch_id' => $branchId, 'memo' => $m],
            ], $branchId);
        });
    }

    public function payVat(string $amount, string $from, string $ref, ?string $date = null, ?int $branchId = null, ?string $note = null): JournalEntry
    {
        return $this->payLiability('vat_payable', 'vat', $amount, $from, $ref, $date, $branchId, $note);
    }

    public function payCarrier(string $amount, string $from, string $ref, ?string $date = null, ?int $branchId = null, ?string $note = null): JournalEntry
    {
        return $this->payLiability('shipping_payable', 'carrier', $amount, $from, $ref, $date, $branchId, $note);
    }

    /** Gateway payout / wallet sweep / POS register close: gross leaves the source, the fee is the provider's charge, the rest lands in bank/cash. */
    public function payout(string $source, string $gross, string $fee, string $into, string $ref, ?string $date = null, ?int $branchId = null, ?string $note = null): JournalEntry
    {
        $this->ensureChart();
        $source = $this->role($source, self::PAYOUT_SOURCES);
        $into = $this->role($into, self::PAY_FROM);
        $gross = $this->amount($gross);
        $fee = Money::round($fee ?: '0');
        if (Money::cmp($fee, '0') < 0 || Money::cmp($fee, $gross) >= 0) {
            throw new AccountingException(__('erp_acc.errors.bad_fee'));
        }

        return $this->journals->exclusive(function () use ($source, $gross, $fee, $into, $ref, $date, $branchId, $note) {
            if ($done = JournalEntry::where('idempotency_key', 'settle:payout:'.$source.':'.$ref)->first()) {
                $this->journals->assertSameReplay($done, $gross); // QA-012

                return $done;
            }
            $held = Money::round($this->balance($source));
            if (Money::cmp($gross, $held) > 0) {
                throw new AccountingException(__('erp_acc.errors.over_settle', ['owed' => number_format((float) $held, 2)]));
            }
            $m = $note ?: __('erp_acc.settle.payout_'.$source);

            return $this->post('payout:'.$source.':'.$ref, $date ?? now()->toDateString(), $m, [
                ['account' => $into, 'debit' => Money::sub($gross, $fee), 'branch_id' => $branchId, 'memo' => $m],
                ['account' => 'gateway_fees', 'debit' => $fee, 'branch_id' => $branchId, 'memo' => $m],
                ['account' => $source, 'credit' => $gross, 'branch_id' => $branchId, 'memo' => $m],
            ], $branchId);
        });
    }
}
