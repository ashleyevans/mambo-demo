import { Head, router } from '@inertiajs/react';
import { Download, Landmark, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import {
    demoRefresh as demoRefreshRoute,
    pushNotifications as pushNotificationsRoute,
    sync,
} from '@/routes/dashboard';
import { csv, json } from '@/routes/transactions/export';
import {
    csv as csvForTransaction,
    json as jsonForTransaction,
} from '@/routes/transactions/export/single';

const DEMO_REFRESH_MS = 60_000;

interface Transaction {
    id: number;
    status: string;
    booked_at: string | null;
    description: string | null;
    merchant_name: string | null;
    transaction_type: string | null;
    transaction_category: string | null;
    transaction_classification: string[] | null;
    amount: string | null;
    currency: string | null;
    running_balance: string | null;
    running_balance_currency: string | null;
    provider_transaction_id: string | null;
    normalised_provider_transaction_id: string | null;
    meta: Record<string, unknown> | null;
    raw: Record<string, unknown> | null;
    bank: {
        name: string | null;
        logo: string | null;
    };
    account: string | null;
}

interface DashboardProps {
    transactions: Transaction[];
    transactionCount: number;
    demoRefresh: boolean;
    pushNotifications: boolean;
    canManagePush: boolean;
}

function formatMoney(amount: string | null, currency: string | null): string {
    if (amount === null) {
        return '—';
    }

    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: currency ?? 'GBP',
        signDisplay: 'auto',
    }).format(Number(amount));
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function DetailRow({
    label,
    value,
}: {
    label: string;
    value: React.ReactNode;
}) {
    return (
        <div className="grid grid-cols-[160px_1fr] gap-2 border-b py-2 text-sm last:border-b-0">
            <span className="text-muted-foreground">{label}</span>
            <span className="break-words">{value ?? '—'}</span>
        </div>
    );
}

export default function Dashboard({
    transactions,
    transactionCount,
    demoRefresh,
    pushNotifications,
    canManagePush,
}: DashboardProps) {
    const [selected, setSelected] = useState<Transaction | null>(null);

    useEffect(() => {
        if (!demoRefresh) {
            return;
        }

        const interval = setInterval(() => {
            router.post(
                sync().url,
                {},
                { preserveScroll: true, preserveState: true },
            );
        }, DEMO_REFRESH_MS);

        return () => clearInterval(interval);
    }, [demoRefresh]);

    const toggleDemoRefresh = (value: boolean) => {
        router.patch(
            demoRefreshRoute().url,
            { demo_refresh: value },
            { preserveScroll: true, preserveState: true },
        );
    };

    const togglePushNotifications = (value: boolean) => {
        router.patch(
            pushNotificationsRoute().url,
            { push_notifications: value },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Transactions</h1>
                        <p className="text-sm text-muted-foreground">
                            {transactionCount > 0
                                ? `${transactionCount.toLocaleString('en-GB')} transactions across all connected accounts`
                                : 'Transactions across all connected accounts'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-4">
                        <div className="flex items-center gap-2">
                            <Switch
                                id="demo-refresh"
                                checked={demoRefresh}
                                onCheckedChange={toggleDemoRefresh}
                            />
                            <Label
                                htmlFor="demo-refresh"
                                className="text-sm font-normal text-muted-foreground"
                            >
                                Per-minute refresh
                            </Label>
                        </div>
                        {canManagePush && (
                            <div className="flex items-center gap-2">
                                <Switch
                                    id="push-notifications"
                                    checked={pushNotifications}
                                    onCheckedChange={togglePushNotifications}
                                />
                                <Label
                                    htmlFor="push-notifications"
                                    className="text-sm font-normal text-muted-foreground"
                                >
                                    Push notifications
                                </Label>
                            </div>
                        )}
                        <div className="flex items-center gap-2">
                            <Button variant="outline" asChild>
                                <a href={csv().url}>
                                    <Download />
                                    Download CSV
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a href={json().url}>
                                    <Download />
                                    Download JSON
                                </a>
                            </Button>
                        </div>
                    </div>
                </div>

                {transactions.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                                <Landmark className="size-6 text-muted-foreground" />
                            </div>
                            <div className="space-y-1">
                                <p className="font-medium">
                                    No transactions yet
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Connect a bank account to import your
                                    transactions.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Bank</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Description</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead className="text-right">
                                            Amount
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Balance
                                        </TableHead>
                                        <TableHead className="w-10" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {transactions.map((transaction) => {
                                        const isCredit =
                                            Number(transaction.amount) > 0;

                                        return (
                                            <TableRow key={transaction.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Avatar className="size-7 rounded-md">
                                                            {transaction.bank
                                                                .logo && (
                                                                <AvatarImage
                                                                    src={
                                                                        transaction
                                                                            .bank
                                                                            .logo
                                                                    }
                                                                    alt={
                                                                        transaction
                                                                            .bank
                                                                            .name ??
                                                                        ''
                                                                    }
                                                                />
                                                            )}
                                                            <AvatarFallback className="rounded-md">
                                                                <Landmark className="size-3.5" />
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <span className="hidden text-sm text-muted-foreground sm:inline">
                                                            {transaction.bank
                                                                .name ?? '—'}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap text-sm text-muted-foreground">
                                                    {formatDate(
                                                        transaction.booked_at,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2 font-medium">
                                                        {transaction.merchant_name ??
                                                            transaction.description ??
                                                            '—'}
                                                        {transaction.status ===
                                                            'pending' && (
                                                            <Badge
                                                                variant="outline"
                                                                className="text-amber-600 dark:text-amber-400"
                                                            >
                                                                Pending
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    {transaction.account && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {transaction.account}
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-sm text-muted-foreground">
                                                    {transaction.transaction_category ??
                                                        '—'}
                                                </TableCell>
                                                <TableCell>
                                                    {transaction.transaction_type && (
                                                        <Badge variant="secondary">
                                                            {
                                                                transaction.transaction_type
                                                            }
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell
                                                    className={`text-right font-medium tabular-nums ${isCredit ? 'text-emerald-600 dark:text-emerald-400' : ''}`}
                                                >
                                                    {formatMoney(
                                                        transaction.amount,
                                                        transaction.currency,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right text-sm text-muted-foreground tabular-nums">
                                                    {formatMoney(
                                                        transaction.running_balance,
                                                        transaction.running_balance_currency,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="View all fields"
                                                        onClick={() =>
                                                            setSelected(
                                                                transaction,
                                                            )
                                                        }
                                                    >
                                                        <Search />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>

            <Dialog
                open={selected !== null}
                onOpenChange={(open) => !open && setSelected(null)}
            >
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {selected?.merchant_name ??
                                selected?.description ??
                                'Transaction'}
                        </DialogTitle>
                        <DialogDescription>
                            Every field TrueLayer returned for this transaction.
                        </DialogDescription>
                    </DialogHeader>

                    {selected && (
                        <div className="flex flex-col gap-6">
                            <div className="flex gap-2">
                                <Button variant="outline" size="sm" asChild>
                                    <a
                                        href={csvForTransaction(selected.id).url}
                                    >
                                        <Download />
                                        CSV
                                    </a>
                                </Button>
                                <Button variant="outline" size="sm" asChild>
                                    <a
                                        href={
                                            jsonForTransaction(selected.id).url
                                        }
                                    >
                                        <Download />
                                        JSON
                                    </a>
                                </Button>
                            </div>
                            <div>
                                <DetailRow
                                    label="Date"
                                    value={
                                        selected.booked_at
                                            ? new Date(
                                                  selected.booked_at,
                                              ).toLocaleString('en-GB')
                                            : null
                                    }
                                />
                                <DetailRow
                                    label="Description"
                                    value={selected.description}
                                />
                                <DetailRow
                                    label="Merchant"
                                    value={selected.merchant_name}
                                />
                                <DetailRow
                                    label="Amount"
                                    value={formatMoney(
                                        selected.amount,
                                        selected.currency,
                                    )}
                                />
                                <DetailRow
                                    label="Status"
                                    value={selected.status}
                                />
                                <DetailRow
                                    label="Type"
                                    value={selected.transaction_type}
                                />
                                <DetailRow
                                    label="Category"
                                    value={selected.transaction_category}
                                />
                                <DetailRow
                                    label="Classification"
                                    value={
                                        selected.transaction_classification
                                            ?.length
                                            ? selected.transaction_classification.join(
                                                  ' › ',
                                              )
                                            : 'None (not provided)'
                                    }
                                />
                                <DetailRow
                                    label="Running balance"
                                    value={formatMoney(
                                        selected.running_balance,
                                        selected.running_balance_currency,
                                    )}
                                />
                                <DetailRow
                                    label="Bank"
                                    value={selected.bank.name}
                                />
                                <DetailRow
                                    label="Account"
                                    value={selected.account}
                                />
                                <DetailRow
                                    label="Provider txn id"
                                    value={selected.provider_transaction_id}
                                />
                                <DetailRow
                                    label="Normalised id"
                                    value={
                                        selected.normalised_provider_transaction_id
                                    }
                                />
                            </div>

                            {selected.meta &&
                                Object.keys(selected.meta).length > 0 && (
                                    <div>
                                        <p className="mb-1 text-sm font-medium">
                                            Provider metadata
                                        </p>
                                        {Object.entries(selected.meta).map(
                                            ([key, value]) => (
                                                <DetailRow
                                                    key={key}
                                                    label={key}
                                                    value={String(value)}
                                                />
                                            ),
                                        )}
                                    </div>
                                )}

                            <div>
                                <p className="mb-1 text-sm font-medium">
                                    Raw payload
                                </p>
                                <pre className="overflow-x-auto rounded-md bg-muted p-3 text-xs">
                                    {JSON.stringify(selected.raw, null, 2)}
                                </pre>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
