import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Landmark, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { usePage } from '@inertiajs/react';
import { connect, index } from '@/routes/accounts';
import { destroy, refresh } from '@/routes/accounts/connections';

interface BankAccount {
    id: number;
    display_name: string | null;
    account_type: string | null;
    currency: string | null;
    account_number: string | null;
    sort_code: string | null;
    current_balance: string | null;
    available_balance: string | null;
}

interface BankConnection {
    id: number;
    provider_name: string | null;
    logo_uri: string | null;
    status: string;
    consent_expires_at: string | null;
    last_synced_at: string | null;
    accounts: BankAccount[];
}

interface AccountsPageProps {
    connections: BankConnection[];
    trueLayerConfigured: boolean;
}

function formatBalance(amount: string | null, currency: string | null): string {
    if (amount === null) {
        return '—';
    }

    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: currency ?? 'GBP',
    }).format(Number(amount));
}

function consentSummary(expiresAt: string | null): {
    expired: boolean;
    label: string;
} {
    if (!expiresAt) {
        return { expired: false, label: 'Offline access enabled' };
    }

    const days = Math.ceil(
        (new Date(expiresAt).getTime() - Date.now()) / 86_400_000,
    );

    if (days <= 0) {
        return { expired: true, label: 'Consent expired — reconnect needed' };
    }

    return {
        expired: false,
        label: `Offline access for ${days} more ${days === 1 ? 'day' : 'days'}`,
    };
}

function maskAccountNumber(account: BankAccount): string {
    const parts: string[] = [];

    if (account.sort_code) {
        parts.push(account.sort_code);
    }

    if (account.account_number) {
        parts.push(`••••${account.account_number.slice(-4)}`);
    }

    return parts.join(' · ');
}

export default function AccountsIndex({
    connections,
    trueLayerConfigured,
}: AccountsPageProps) {
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const disconnect = (connectionId: number) => {
        if (
            confirm(
                'Disconnect this bank? The linked accounts will be removed.',
            )
        ) {
            router.delete(destroy(connectionId).url, { preserveScroll: true });
        }
    };

    const refreshConnection = (connectionId: number) => {
        router.post(
            refresh(connectionId).url,
            {},
            { preserveScroll: true },
        );
    };

    const hasAccounts = connections.some(
        (connection) => connection.accounts.length > 0,
    );

    return (
        <>
            <Head title="Accounts" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Accounts</h1>
                        <p className="text-sm text-muted-foreground">
                            Connected bank accounts via open banking.
                        </p>
                    </div>
                    <Button asChild>
                        <a href={connect().url}>
                            <Plus />
                            Connect account
                        </a>
                    </Button>
                </div>

                {!hasAccounts ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                                <Landmark className="size-6 text-muted-foreground" />
                            </div>
                            <div className="space-y-1">
                                <p className="font-medium">
                                    No accounts connected
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Connect your bank to securely import your
                                    accounts.
                                </p>
                            </div>
                            <Button asChild className="mt-2">
                                <a href={connect().url}>
                                    <Plus />
                                    Connect account
                                </a>
                            </Button>
                            {!trueLayerConfigured && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    TrueLayer credentials are not configured
                                    yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="flex flex-col gap-4">
                        {connections.map((connection) => (
                            <Card key={connection.id}>
                                <CardHeader className="flex flex-row items-center justify-between gap-4">
                                    <div className="flex items-center gap-3">
                                        {connection.logo_uri ? (
                                            <img
                                                src={connection.logo_uri}
                                                alt=""
                                                className="size-8 rounded"
                                            />
                                        ) : (
                                            <div className="flex size-8 items-center justify-center rounded bg-muted">
                                                <Landmark className="size-4 text-muted-foreground" />
                                            </div>
                                        )}
                                        <div>
                                            <CardTitle className="text-base">
                                                {connection.provider_name ??
                                                    'Connected bank'}
                                            </CardTitle>
                                            <CardDescription>
                                                {connection.accounts.length}{' '}
                                                {connection.accounts.length ===
                                                1
                                                    ? 'account'
                                                    : 'accounts'}
                                            </CardDescription>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {(() => {
                                            const consent = consentSummary(
                                                connection.consent_expires_at,
                                            );
                                            return consent.expired ? (
                                                <Button asChild size="sm">
                                                    <a href={connect().url}>
                                                        <AlertTriangle />
                                                        Reconnect
                                                    </a>
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        refreshConnection(
                                                            connection.id,
                                                        )
                                                    }
                                                >
                                                    <RefreshCw />
                                                    Refresh
                                                </Button>
                                            );
                                        })()}
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                disconnect(connection.id)
                                            }
                                        >
                                            <Trash2 />
                                            Disconnect
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="flex flex-col divide-y">
                                    {connection.accounts.map((account) => (
                                        <div
                                            key={account.id}
                                            className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                        >
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">
                                                        {account.display_name ??
                                                            'Account'}
                                                    </span>
                                                    {account.account_type && (
                                                        <Badge variant="secondary">
                                                            {account.account_type}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    {maskAccountNumber(
                                                        account,
                                                    ) || '—'}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="font-semibold tabular-nums">
                                                    {formatBalance(
                                                        account.current_balance,
                                                        account.currency,
                                                    )}
                                                </p>
                                                {account.available_balance !==
                                                    null && (
                                                    <p className="text-xs text-muted-foreground tabular-nums">
                                                        {formatBalance(
                                                            account.available_balance,
                                                            account.currency,
                                                        )}{' '}
                                                        available
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                    <div className="flex items-center justify-between gap-2 pt-3 text-xs text-muted-foreground">
                                        <span
                                            className={
                                                consentSummary(
                                                    connection.consent_expires_at,
                                                ).expired
                                                    ? 'text-destructive'
                                                    : undefined
                                            }
                                        >
                                            {
                                                consentSummary(
                                                    connection.consent_expires_at,
                                                ).label
                                            }
                                        </span>
                                        {connection.last_synced_at && (
                                            <span>
                                                Synced{' '}
                                                {new Date(
                                                    connection.last_synced_at,
                                                ).toLocaleString('en-GB')}
                                            </span>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

AccountsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Accounts',
            href: index(),
        },
    ],
};
