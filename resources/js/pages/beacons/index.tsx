import { Head, router, usePage } from '@inertiajs/react';
import { Bell, LogIn, LogOut, MapPin, Radio } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { demoOffer, index } from '@/routes/beacons';

interface BeaconStore {
    name: string | null;
    logo: string | null;
}

interface BeaconVisit {
    id: number;
    major: number;
    minor: number;
    store: BeaconStore;
    entered_at: string | null;
    exited_at: string | null;
    duration_seconds: number | null;
    ongoing: boolean;
}

interface BeaconEvent {
    id: number;
    major: number;
    minor: number;
    store: BeaconStore;
    type: string;
    occurred_at: string | null;
}

interface BeaconsPageProps {
    visits: BeaconVisit[];
    events: BeaconEvent[];
    eventCount: number;
}

function formatTimestamp(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-GB');
}

function formatDuration(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    if (seconds < 60) {
        return `${seconds}s`;
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }

    return `${minutes}m`;
}

function StoreCell({
    store,
    major,
    minor,
}: {
    store: BeaconStore;
    major: number;
    minor: number;
}) {
    return (
        <div className="flex items-center gap-3">
            {store.logo ? (
                <img
                    src={store.logo}
                    alt={store.name ?? ''}
                    className="size-12 shrink-0 rounded object-contain"
                />
            ) : (
                <div className="flex size-12 shrink-0 items-center justify-center rounded bg-muted">
                    <MapPin className="size-5 text-muted-foreground" />
                </div>
            )}
            <div className="leading-tight">
                <div className="font-medium">
                    {store.name ?? 'Unknown store'}
                </div>
                <div className="text-xs text-muted-foreground tabular-nums">
                    {major} / {minor}
                </div>
            </div>
        </div>
    );
}

export default function BeaconsIndex({
    visits,
    events,
    eventCount,
}: BeaconsPageProps) {
    const { flash } = usePage().props;
    const [sending, setSending] = useState(false);

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const sendExampleOffer = () => {
        router.post(
            demoOffer().url,
            {},
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <>
            <Head title="Beacons" />
            <div className="flex h-full flex-1 flex-col gap-8 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Beacons</h1>
                        <p className="text-sm text-muted-foreground">
                            Store visits and raw iBeacon enter/exit events.
                        </p>
                    </div>
                    <Button onClick={sendExampleOffer} disabled={sending}>
                        <Bell />
                        Send example offer
                    </Button>
                </div>

                {events.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                                <Radio className="size-6 text-muted-foreground" />
                            </div>
                            <div className="space-y-1">
                                <p className="font-medium">
                                    No beacon events yet
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Events appear here as devices enter and exit
                                    beacon range.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <section className="flex flex-col gap-3">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    Visits
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Paired enter/exit with time spent in store.
                                </p>
                            </div>
                            <Card>
                                <CardContent>
                                    {visits.length === 0 ? (
                                        <p className="py-4 text-sm text-muted-foreground">
                                            No visits yet.
                                        </p>
                                    ) : (
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Store</TableHead>
                                                    <TableHead>
                                                        Entered
                                                    </TableHead>
                                                    <TableHead>Left</TableHead>
                                                    <TableHead className="text-right">
                                                        Dwell time
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {visits.map((visit) => (
                                                    <TableRow key={visit.id}>
                                                        <TableCell>
                                                            <StoreCell
                                                                store={
                                                                    visit.store
                                                                }
                                                                major={
                                                                    visit.major
                                                                }
                                                                minor={
                                                                    visit.minor
                                                                }
                                                            />
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground tabular-nums">
                                                            {formatTimestamp(
                                                                visit.entered_at,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground tabular-nums">
                                                            {visit.ongoing ? (
                                                                <Badge variant="secondary">
                                                                    In store
                                                                </Badge>
                                                            ) : (
                                                                formatTimestamp(
                                                                    visit.exited_at,
                                                                )
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-right font-medium tabular-nums">
                                                            {visit.ongoing
                                                                ? '—'
                                                                : formatDuration(
                                                                      visit.duration_seconds,
                                                                  )}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    )}
                                </CardContent>
                            </Card>
                        </section>

                        <section className="flex flex-col gap-3">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    Raw events
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Every enter/exit reported by a beacon.
                                </p>
                            </div>
                            <Card>
                                <CardContent>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Event</TableHead>
                                                <TableHead>Store</TableHead>
                                                <TableHead className="text-right">
                                                    When
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {events.map((event) => (
                                                <TableRow key={event.id}>
                                                    <TableCell>
                                                        <Badge
                                                            variant={
                                                                event.type ===
                                                                'enter'
                                                                    ? 'default'
                                                                    : 'secondary'
                                                            }
                                                            className="gap-1"
                                                        >
                                                            {event.type ===
                                                            'enter' ? (
                                                                <LogIn className="size-3" />
                                                            ) : (
                                                                <LogOut className="size-3" />
                                                            )}
                                                            {event.type ===
                                                            'enter'
                                                                ? 'Enter'
                                                                : 'Exit'}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        <StoreCell
                                                            store={event.store}
                                                            major={event.major}
                                                            minor={event.minor}
                                                        />
                                                    </TableCell>
                                                    <TableCell className="text-right text-muted-foreground tabular-nums">
                                                        {formatTimestamp(
                                                            event.occurred_at,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                    {eventCount > 0 && (
                                        <p className="pt-4 text-xs text-muted-foreground">
                                            Showing {events.length} of{' '}
                                            {eventCount} events.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        </section>
                    </>
                )}
            </div>
        </>
    );
}

BeaconsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Beacons',
            href: index(),
        },
    ],
};
