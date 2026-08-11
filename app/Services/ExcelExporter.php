<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Transaction;
use App\Support\DateRange;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes the active book to a workbook: transactions first, then the accounts,
 * categories and contacts behind them.
 *
 * Amounts go out as real numbers in major units with two decimals, whatever the
 * book shows on screen. Rounding a 0 decimal book into the file would lose
 * money the moment anyone added the column up.
 */
class ExcelExporter
{
    private Style $header;

    private Style $money;

    public function __construct()
    {
        $this->header = new Style(fontBold: true, cellVerticalAlignment: CellVerticalAlignment::CENTER);
        $this->money = new Style(format: '#,##0.00');
    }

    /**
     * Streams the workbook straight to the browser.
     */
    public function download(Book $book, DateRange $range, string $filename): void
    {
        $writer = new Writer($this->options($book));
        $writer->openToBrowser($filename);

        $this->transactions($writer, $range);
        $this->accounts($writer);
        $this->categories($writer);
        $this->contacts($writer);

        $writer->close();
    }

    /**
     * Same workbook, written to disk. Used by the tests.
     */
    public function write(Book $book, DateRange $range, string $path): void
    {
        $writer = new Writer($this->options($book));
        $writer->openToFile($path);

        $this->transactions($writer, $range);
        $this->accounts($writer);
        $this->categories($writer);
        $this->contacts($writer);

        $writer->close();
    }

    /**
     * Document properties; the XLSX writer takes these up front, not by setter.
     */
    private function options(?Book $book): Options
    {
        return new Options(properties: new Properties(
            title: $book?->name ?? config('app.name'),
            application: config('app.name'),
            creator: config('app.name'),
            lastModifiedBy: config('app.name'),
        ));
    }

    private function transactions(Writer $writer, DateRange $range): void
    {
        [$start, $end] = $range->bounds();

        $sheet = $writer->getCurrentSheet();
        $sheet->setName(__('Transactions'));
        $sheet->setColumnWidth(12, 1);
        $sheet->setColumnWidth(9, 2);
        $sheet->setColumnWidth(11, 3);
        $sheet->setColumnWidthForRange(20, 4, 6);
        $sheet->setColumnWidthForRange(14, 7, 10);
        $sheet->setColumnWidth(30, 11);

        $writer->addRow(Row::fromValuesWithStyle([
            __('Date'), __('Time'), __('Type'), __('Category'),
            __('From account'), __('To account'),
            __('Amount'), __('Charge'), __('From balance'), __('To balance'),
            __('Note'),
        ], $this->header));

        Transaction::query()
            ->with(['category', 'fromAccount', 'toAccount'])
            ->when($start, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end, fn ($query) => $query->where('created_at', '<=', $end))
            ->orderBy('created_at')
            ->orderBy('id')
            // Chunked so a book with years of history does not have to fit in memory.
            ->chunk(500, function ($transactions) use ($writer) {
                foreach ($transactions as $transaction) {
                    $writer->addRow($this->transactionRow($transaction));
                }
            });
    }

    private function transactionRow(Transaction $transaction): Row
    {
        $isTransfer = $transaction->type === TransactionType::Transfer;

        $values = [
            $transaction->created_at->format('Y-m-d'),
            $transaction->created_at->format('H:i'),
            $transaction->type->label(),
            $isTransfer ? '' : ($transaction->category?->name ?? ''),
            $transaction->fromAccount?->name ?? '',
            $transaction->toAccount?->name ?? '',
            $this->major($transaction->amount),
            $this->major($transaction->charge),
            $transaction->from_account_id ? $this->major($transaction->from_account_balance) : '',
            $transaction->to_account_id ? $this->major($transaction->to_account_balance) : '',
            (string) $transaction->note,
        ];

        return Row::fromValuesWithStyles($values, [
            6 => $this->money,
            7 => $this->money,
            8 => $this->money,
            9 => $this->money,
        ]);
    }

    private function accounts(Writer $writer): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName(__('Accounts'));
        $sheet->setColumnWidth(26, 1);
        $sheet->setColumnWidthForRange(14, 2, 5);

        $writer->addRow(Row::fromValuesWithStyle([
            __('Account'), __('Kind'), __('Status'), __('Opening balance'), __('Current balance'),
        ], $this->header));

        foreach (Account::orderBy('type')->orderBy('name')->get() as $account) {
            $writer->addRow(Row::fromValuesWithStyles([
                $account->name,
                $account->type === AccountType::Contact ? __('Contact') : __('Account'),
                $account->status === AccountStatus::Active ? __('Active') : __('Inactive'),
                $this->major($account->initial_amount),
                $this->major($account->amount),
            ], [3 => $this->money, 4 => $this->money]));
        }
    }

    private function categories(Writer $writer): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName(__('Categories'));
        $sheet->setColumnWidth(26, 1);
        $sheet->setColumnWidthForRange(14, 2, 3);

        $writer->addRow(Row::fromValuesWithStyle([
            __('Category'), __('Type'), __('Status'),
        ], $this->header));

        foreach (Category::orderBy('type')->orderBy('name')->get() as $category) {
            $writer->addRow(Row::fromValues([
                $category->name,
                $category->type->label(),
                $category->status->label(),
            ]));
        }
    }

    private function contacts(Writer $writer): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName(__('Contacts'));
        $sheet->setColumnWidthForRange(24, 1, 3);
        $sheet->setColumnWidthForRange(16, 4, 5);

        $writer->addRow(Row::fromValuesWithStyle([
            __('Contact'), __('Phone'), __('Email'), __('Balance'), __('Standing'),
        ], $this->header));

        foreach (Contact::with('account')->orderBy('name')->get() as $contact) {
            $balance = (int) ($contact->account?->amount ?? 0);

            $writer->addRow(Row::fromValuesWithStyles([
                $contact->name,
                (string) $contact->phone,
                (string) $contact->email,
                $this->major($balance),
                // A contact in credit still owes you; in debit, you owe them.
                match (true) {
                    $balance > 0 => __('Owes you'),
                    $balance < 0 => __('You owe'),
                    default => __('Settled'),
                },
            ], [3 => $this->money]));
        }
    }

    /**
     * Cents to a spreadsheet number, so columns can be summed.
     */
    private function major(int $minor): float
    {
        return round($minor / 100, 2);
    }
}
